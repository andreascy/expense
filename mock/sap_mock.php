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
    $method === 'POST' && $path === '/Login'           => handleLogin(),
    $method === 'POST' && $path === '/Logout'          => handleLogout(),
    $method === 'GET'  && str_starts_with($path, '/ChartOfAccounts') => handleAccounts(),
    $method === 'POST' && $path === '/JournalEntries'  => handleJournalEntry(),
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

function notFound(string $method, string $path): void
{
    http_response_code(404);
    echo json_encode(['error' => ['message' => ['value' => "Mock: no handler for {$method} {$path}"]]]);
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
