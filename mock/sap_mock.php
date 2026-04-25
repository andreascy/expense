<?php
/**
 * SAP Business One Service Layer — local mock server
 * Run with:  php -S localhost:50001 mock/sap_mock.php
 *
 * Endpoints implemented:
 *   POST /b1s/v1/Login
 *   GET  /b1s/v1/ChartOfAccounts
 *   POST /b1s/v1/JournalEntries
 *   POST /b1s/v1/Logout
 */

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ─── Remove base prefix ───────────────────────────────────────────────────────
$path = preg_replace('#^/b1s/v1#', '', $path);

// ─── Route ────────────────────────────────────────────────────────────────────
match (true) {
    $method === 'POST' && $path === '/Login'                    => handleLogin(),
    $method === 'POST' && $path === '/Logout'                   => handleLogout(),
    $method === 'GET'  && str_starts_with($path, '/ChartOfAccounts')    => handleAccounts(),
    $method === 'POST' && $path === '/JournalEntries'           => handleJournalEntry(),
    $method === 'GET'  && str_starts_with($path, '/BusinessPartners')   => handleBusinessPartners(),
    $method === 'GET'  && str_starts_with($path, '/JournalEntryLines')  => handleJournalEntryLines(),
    default => notFound($method, $path),
};

// ─── Handlers ─────────────────────────────────────────────────────────────────

function handleLogin(): void
{
    $body = json_decode(file_get_contents('php://input'), true);
    $db   = $body['CompanyDB'] ?? '';
    $user = $body['UserName']  ?? '';
    $pass = $body['Password']  ?? '';

    // Accept any non-empty credentials for mock
    if (!$db || !$user || !$pass) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => ['value' => 'Missing credentials']]]);
        return;
    }

    http_response_code(200);
    echo json_encode([
        'SessionId' => 'MOCK_SESSION_' . bin2hex(random_bytes(8)),
        'Version'   => '10.0',
        'SessionTimeout' => 30,
    ]);
}

function handleLogout(): void
{
    http_response_code(204);
}

function handleAccounts(): void
{
    $search = strtolower($_GET['$filter'] ?? '');

    // Extract search term from OData filter
    preg_match("/tolower\('([^']+)'\)/", $search, $m);
    $term = strtolower($m[1] ?? '');

    $accounts = mockAccounts();

    if ($term !== '') {
        $accounts = array_filter($accounts, fn($a) =>
            str_contains(strtolower($a['Code']), $term) ||
            str_contains(strtolower($a['Name']), $term)
        );
    }

    $top = (int)($_GET['$top'] ?? 60);

    echo json_encode([
        'value' => array_values(array_slice($accounts, 0, $top)),
    ]);
}

function handleJournalEntry(): void
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (empty($body['JournalEntryLines'])) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => ['value' => 'JournalEntryLines is required']]]);
        return;
    }

    // Verify the entry is balanced
    $totalDebit  = array_sum(array_column($body['JournalEntryLines'], 'Debit'));
    $totalCredit = array_sum(array_column($body['JournalEntryLines'], 'Credit'));

    if (abs($totalDebit - $totalCredit) > 0.005) {
        http_response_code(422);
        echo json_encode(['error' => ['message' => ['value' =>
            sprintf('Unbalanced journal entry: DR %.2f ≠ CR %.2f', $totalDebit, $totalCredit)
        ]]]);
        return;
    }

    static $counter = 1000;
    $counter++;

    http_response_code(201);
    echo json_encode([
        'JdtNum'    => $counter,
        'DocNumber' => $counter,
        'Memo'      => $body['Memo'] ?? '',
        'ReferenceDate' => $body['ReferenceDate'] ?? date('Y-m-d'),
        'JournalEntryLines' => $body['JournalEntryLines'],
    ]);
}

function handleBusinessPartners(): void
{
    $filter = strtolower($_GET['$filter'] ?? '');
    $top    = (int)($_GET['$top'] ?? 80);

    preg_match("/tolower\('([^']+)'\)/", $filter, $m);
    $term = strtolower($m[1] ?? '');

    // type filter: CardType eq 'C' or CardType eq 'S'
    $typeMatch = null;
    if (preg_match("/cardtype eq '([CS])'/i", $filter, $tm)) {
        $typeMatch = $tm[1];
    }

    $partners = mockBusinessPartners();

    if ($term !== '') {
        $partners = array_filter($partners, fn($p) =>
            str_contains(strtolower($p['CardCode']), $term) ||
            str_contains(strtolower($p['CardName']), $term)
        );
    }
    if ($typeMatch !== null) {
        $partners = array_filter($partners, fn($p) => $p['CardType'] === $typeMatch);
    }

    echo json_encode(['value' => array_values(array_slice($partners, 0, $top))]);
}

function handleJournalEntryLines(): void
{
    $filter = $_GET['$filter'] ?? '';
    $top    = (int)($_GET['$top'] ?? 100);

    // Extract ShortName from filter: ShortName eq 'CODE'
    preg_match("/ShortName eq '([^']+)'/i", $filter, $m);
    $code = $m[1] ?? '';

    $lines = mockJournalLines($code);
    echo json_encode(['value' => array_slice($lines, 0, $top)]);
}

function notFound(string $method, string $path): void
{
    http_response_code(404);
    echo json_encode(['error' => ['message' => ['value' => "Mock: no handler for {$method} {$path}"]]]);
}

// ─── Mock data ────────────────────────────────────────────────────────────────

function mockBusinessPartners(): array
{
    return [
        ['CardCode'=>'C001','CardName'=>'Acme Corporation',       'CardType'=>'C','Phone1'=>'+1 212 555 0100','EmailAddress'=>'billing@acme.com',   'CurrentAccountBalance'=>12500.00, 'CreditLimit'=>50000],
        ['CardCode'=>'C002','CardName'=>'Global Tech GmbH',       'CardType'=>'C','Phone1'=>'+49 30 9876543', 'EmailAddress'=>'info@globaltech.de',  'CurrentAccountBalance'=>-3200.00, 'CreditLimit'=>30000],
        ['CardCode'=>'C003','CardName'=>'Sunrise Retail Ltd',     'CardType'=>'C','Phone1'=>'+44 20 7000 123','EmailAddress'=>'ap@sunrise.co.uk',    'CurrentAccountBalance'=>0,        'CreditLimit'=>20000],
        ['CardCode'=>'C004','CardName'=>'Nordic Solutions AS',    'CardType'=>'C','Phone1'=>'+47 23 456789',  'EmailAddress'=>'finance@nordic.no',   'CurrentAccountBalance'=>8750.50,  'CreditLimit'=>25000],
        ['CardCode'=>'C005','CardName'=>'Pacific Imports Inc',    'CardType'=>'C','Phone1'=>'+1 310 555 0199','EmailAddress'=>'orders@pacific.com',  'CurrentAccountBalance'=>1100.00,  'CreditLimit'=>15000],
        ['CardCode'=>'S001','CardName'=>'Office Depot SA',        'CardType'=>'S','Phone1'=>'+33 1 4000 5678','EmailAddress'=>'invoices@odepot.fr',  'CurrentAccountBalance'=>-4800.00, 'CreditLimit'=>0],
        ['CardCode'=>'S002','CardName'=>'TechParts GmbH',         'CardType'=>'S','Phone1'=>'+49 89 1234567', 'EmailAddress'=>'ar@techparts.de',     'CurrentAccountBalance'=>-1250.00, 'CreditLimit'=>0],
        ['CardCode'=>'S003','CardName'=>'Utilities AG',           'CardType'=>'S','Phone1'=>'+41 44 000 1234','EmailAddress'=>'billing@utilities.ch','CurrentAccountBalance'=>-580.00,  'CreditLimit'=>0],
        ['CardCode'=>'S004','CardName'=>'Courier Express SRL',    'CardType'=>'S','Phone1'=>'+39 02 3456789', 'EmailAddress'=>'billing@courier.it',  'CurrentAccountBalance'=>-220.00,  'CreditLimit'=>0],
        ['CardCode'=>'S005','CardName'=>'Cloud Hosting Ltd',      'CardType'=>'S','Phone1'=>'+353 1 234 5678','EmailAddress'=>'ap@cloudhost.ie',     'CurrentAccountBalance'=>-3400.00, 'CreditLimit'=>0],
    ];
}

function mockJournalLines(string $code): array
{
    // Generate plausible statement rows for any given code
    $seed  = crc32($code);
    $rows  = [];
    $bal   = 0;
    $base  = mktime(0,0,0,1,1,2025);
    $docs  = [1001,1004,1008,1012,1015,1019,1022,1025];

    $memos = [
        'Invoice payment','Balance carry-forward','Service fee','Advance payment',
        'Credit note','Monthly retainer','Expense reimbursement','Correction entry',
    ];

    foreach ($docs as $i => $doc) {
        $ts   = $base + ($i * 14 * 86400) + ($seed % 86400);
        $dr   = ($i % 2 === 0) ? round(abs((($seed >> $i) % 5000) + 500), 2) : 0;
        $cr   = ($i % 2 !== 0) ? round(abs((($seed >> ($i+1)) % 5000) + 500), 2) : 0;
        $bal += $cr - $dr;
        $rows[] = [
            'JdtNum'             => $doc,
            'RefDate'            => date('Y-m-d\TH:i:s', $ts),
            'Memo'               => $memos[$i % count($memos)],
            'Ref1'               => 'REF-' . str_pad($doc, 5, '0', STR_PAD_LEFT),
            'Debit'              => $dr,
            'Credit'             => $cr,
            'CumulativeBalance'  => round($bal, 2),
            'TransactionCode'    => 'JE',
            'ShortName'          => $code,
        ];
    }

    // Most-recent first
    return array_reverse($rows);
}

// ─── Mock chart of accounts ───────────────────────────────────────────────────

function mockAccounts(): array
{
    return [
        // Expense accounts
        ['Code' => '6100', 'Name' => 'Office Supplies',       'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6110', 'Name' => 'Telephone & Internet',  'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6120', 'Name' => 'Travel & Transport',    'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6130', 'Name' => 'Meals & Entertainment', 'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6140', 'Name' => 'Rent & Utilities',      'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6150', 'Name' => 'Repairs & Maintenance', 'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6160', 'Name' => 'Advertising & Marketing','AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6200', 'Name' => 'Salaries & Wages',      'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6300', 'Name' => 'Depreciation',          'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6400', 'Name' => 'Insurance',             'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6500', 'Name' => 'Professional Fees',     'AccountType' => 'at_Expenses', 'Balance' => 0],
        ['Code' => '6600', 'Name' => 'Bank Charges',          'AccountType' => 'at_Expenses', 'Balance' => 0],
        // VAT accounts
        ['Code' => '2310', 'Name' => 'Input VAT 19%',         'AccountType' => 'at_Liabilities', 'Balance' => 0],
        ['Code' => '2311', 'Name' => 'Input VAT 7%',          'AccountType' => 'at_Liabilities', 'Balance' => 0],
        ['Code' => '2312', 'Name' => 'Input VAT 0%',          'AccountType' => 'at_Liabilities', 'Balance' => 0],
        // Bank / Cash accounts
        ['Code' => '1200', 'Name' => 'Main Bank Account',     'AccountType' => 'at_Assets', 'Balance' => 50000],
        ['Code' => '1210', 'Name' => 'Secondary Bank Account','AccountType' => 'at_Assets', 'Balance' => 15000],
        ['Code' => '1300', 'Name' => 'Petty Cash',            'AccountType' => 'at_Assets', 'Balance' => 500],
        ['Code' => '1310', 'Name' => 'Petty Cash — Branch',   'AccountType' => 'at_Assets', 'Balance' => 200],
        // Revenue / other
        ['Code' => '4000', 'Name' => 'Sales Revenue',         'AccountType' => 'at_Revenues', 'Balance' => 0],
        ['Code' => '1000', 'Name' => 'Accounts Receivable',   'AccountType' => 'at_Assets',   'Balance' => 0],
        ['Code' => '2000', 'Name' => 'Accounts Payable',      'AccountType' => 'at_Liabilities', 'Balance' => 0],
    ];
}
