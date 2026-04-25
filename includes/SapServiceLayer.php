<?php

class SapServiceLayer
{
    private string $baseUrl;
    private string $companyDb;
    private string $username;
    private string $password;
    private bool   $verifySsl;
    private string $sessionKey = 'sap_b1_session';

    public function __construct(array $config)
    {
        $this->baseUrl   = rtrim($config['base_url'], '/');
        $this->companyDb = $config['company_db'];
        $this->username  = $config['username'];
        $this->password  = $config['password'];
        $this->verifySsl = $config['verify_ssl'] ?? false;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function login(): void
    {
        $response = $this->request('POST', '/Login', [
            'CompanyDB' => $this->companyDb,
            'UserName'  => $this->username,
            'Password'  => $this->password,
        ], false);

        if (empty($response['SessionId'])) {
            throw new RuntimeException('SAP login failed: ' . json_encode($response));
        }

        $_SESSION[$this->sessionKey] = [
            'cookie'    => 'B1SESSION=' . $response['SessionId'],
            'company'   => $this->companyDb,
            'logged_at' => time(),
        ];
    }

    public function logout(): void
    {
        try {
            $this->request('POST', '/Logout');
        } catch (Throwable $e) {
            // best-effort
        }
        unset($_SESSION[$this->sessionKey]);
    }

    /**
     * Search chart of accounts.
     * Returns array of { Code, Name, AccountType }
     */
    public function getAccounts(string $search = '', int $limit = 60): array
    {
        $this->ensureSession();

        $filters = ["ActiveAccount eq 'tYES'"];
        if ($search !== '') {
            $s = str_replace("'", "''", $search);
            $filters[] = "(contains(tolower(Code),tolower('{$s}')) or contains(tolower(Name),tolower('{$s}')))";
        }

        $qs = http_build_query([
            '$select'  => 'Code,Name,AccountType,Balance',
            '$filter'  => implode(' and ', $filters),
            '$orderby' => 'Code asc',
            '$top'     => $limit,
        ]);

        return $this->callWithRetry('GET', '/ChartOfAccounts?' . $qs)['value'] ?? [];
    }

    /**
     * Create a balanced journal entry in SAP B1.
     *
     * $lines = [
     *   ['account' => 'CODE', 'debit' => 100.00, 'credit' => 0.00, 'memo' => '...'],
     *   ...
     * ]
     */
    public function createJournalEntry(
        string $memo,
        string $date,
        array  $lines,
        string $ref1 = '',
        string $ref2 = ''
    ): array {
        $this->ensureSession();

        $jeLines = array_map(fn($l) => array_filter([
            'AccountCode' => $l['account'],
            'Debit'       => $l['debit']  > 0 ? round((float)$l['debit'],  2) : null,
            'Credit'      => $l['credit'] > 0 ? round((float)$l['credit'], 2) : null,
            'LineMemo'    => $l['memo'] ?? $memo,
        ], fn($v) => $v !== null && $v !== ''), $lines);

        $payload = array_filter([
            'Memo'          => $memo,
            'ReferenceDate' => $date,
            'DueDate'       => $date,
            'TaxDate'       => $date,
            'Reference'     => $ref1 ?: null,
            'Reference2'    => $ref2 ?: null,
            'JournalEntryLines' => $jeLines,
        ], fn($v) => $v !== null);

        return $this->callWithRetry('POST', '/JournalEntries', $payload);
    }

    public function callRaw(string $method, string $endpoint, array $data = []): array
    {
        $this->ensureSession();
        return $this->callWithRetry($method, $endpoint, $data);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function ensureSession(): void
    {
        $s = $_SESSION[$this->sessionKey] ?? null;
        // Re-login if no session or session older than 25 minutes (B1 default is 30 min idle)
        if (!$s || (time() - ($s['logged_at'] ?? 0)) > 1500) {
            $this->login();
        }
    }

    private function callWithRetry(string $method, string $endpoint, array $data = []): array
    {
        try {
            return $this->request($method, $endpoint, $data);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'session')) {
                $this->login();
                return $this->request($method, $endpoint, $data);
            }
            throw $e;
        }
    }

    private function request(string $method, string $endpoint, array $data = [], bool $withSession = true): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Prefer: return=representation'];

        if ($withSession && !empty($_SESSION[$this->sessionKey]['cookie'])) {
            $headers[] = 'Cookie: ' . $_SESSION[$this->sessionKey]['cookie'];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 30,
        ];

        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('cURL error connecting to SAP B1: ' . $curlErr);
        }

        $decoded = json_decode($body, true) ?? [];

        if ($httpCode >= 400) {
            $msg = $decoded['error']['message']['value']
                ?? $decoded['error']['message']
                ?? $body;
            throw new RuntimeException("SAP B1 ({$httpCode}): {$msg}");
        }

        return $decoded;
    }
}
