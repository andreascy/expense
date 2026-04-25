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
// Parameterized PATCH: /Items('CODE')
$isPatchItem = $method === 'PATCH' && preg_match("#^/Items\('([^']+)'\)#", $path);

match (true) {
    $method === 'POST' && $path === '/Login'                              => handleLogin(),
    $method === 'POST' && $path === '/Logout'                            => handleLogout(),
    $method === 'GET'  && str_starts_with($path, '/ChartOfAccounts')     => handleAccounts(),
    $method === 'POST' && $path === '/JournalEntries'                    => handleJournalEntry(),
    $method === 'GET'  && str_starts_with($path, '/BusinessPartners')    => handleBusinessPartners(),
    $method === 'GET'  && str_starts_with($path, '/JournalEntryLines')   => handleJournalEntryLines(),
    // Sales documents
    $method === 'GET'  && str_starts_with($path, '/Quotations')          => handleSalesDoc('Quotation'),
    $method === 'POST' && $path === '/Quotations'                        => handleSalesDoc('Quotation'),
    $method === 'GET'  && str_starts_with($path, '/Orders')              => handleSalesDoc('Order'),
    $method === 'POST' && $path === '/Orders'                            => handleSalesDoc('Order'),
    $method === 'GET'  && str_starts_with($path, '/Invoices')            => handleSalesDoc('Invoice'),
    $method === 'POST' && $path === '/Invoices'                          => handleSalesDoc('Invoice'),
    // Payments
    $method === 'GET'  && str_starts_with($path, '/IncomingPayments')    => handleIncomingPayments(),
    $method === 'POST' && $path === '/IncomingPayments'                  => handleIncomingPayments(),
    // Items
    $method === 'GET'  && str_starts_with($path, '/Items')               => handleItems(),
    $method === 'POST' && $path === '/Items'                             => handleItems(),
    (bool)$isPatchItem                                                   => handleItemPatch(),
    // Inventory
    $method === 'GET'  && str_starts_with($path, '/ItemWarehouseInfoCollection') => handleStockLevels(),
    $method === 'POST' && $path === '/InventoryGenEntries'               => handleInventoryMovement('receipt'),
    $method === 'POST' && $path === '/InventoryGenExits'                 => handleInventoryMovement('issue'),
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

// ── Sales Documents (Quotations / Orders / Invoices) ─────────────────────────
function handleSalesDoc(string $type): void
{
    static $counters = ['Quotation' => 500, 'Order' => 600, 'Invoice' => 700];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        echo json_encode(['value' => []]);
        return;
    }

    // POST — create
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['CardCode'])) {
        http_response_code(400);
        echo json_encode(['error'=>['message'=>['value'=>'CardCode is required']]]);
        return;
    }
    $counters[$type]++;
    $num = $counters[$type];
    $de  = $num + 10000;
    http_response_code(201);
    echo json_encode([
        'DocEntry'      => $de,
        'DocNum'        => $num,
        'CardCode'      => $body['CardCode'],
        'DocDate'       => $body['DocDate']  ?? date('Y-m-d'),
        'DocDueDate'    => $body['DocDueDate'] ?? date('Y-m-d', strtotime('+30 days')),
        'Comments'      => $body['Comments'] ?? '',
        'DocumentLines' => $body['DocumentLines'] ?? [],
    ]);
}

// ── Incoming Payments ─────────────────────────────────────────────────────────
function handleIncomingPayments(): void
{
    static $counter = 800;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo json_encode(['value' => []]); return; }
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['CardCode'])) {
        http_response_code(400); echo json_encode(['error'=>['message'=>['value'=>'CardCode is required']]]); return;
    }
    $counter++;
    http_response_code(201);
    echo json_encode([
        'DocEntry' => $counter + 20000,
        'DocNum'   => $counter,
        'CardCode' => $body['CardCode'],
        'DocDate'  => $body['DocDate'] ?? date('Y-m-d'),
    ]);
}

// ── Items ─────────────────────────────────────────────────────────────────────
function handleItems(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $search = strtolower($_GET['$filter'] ?? '');
        preg_match("/tolower\('([^']+)'\)/", $search, $m);
        $term  = strtolower($m[1] ?? '');
        $items = mockItems();
        if ($term !== '') {
            $items = array_filter($items, fn($i) =>
                str_contains(strtolower($i['ItemCode']), $term) ||
                str_contains(strtolower($i['ItemName']), $term));
        }
        $top = (int)($_GET['$top'] ?? 60);
        echo json_encode(['value' => array_values(array_slice($items, 0, $top))]);
        return;
    }
    // POST — create
    static $counter = 0;
    $counter++;
    $body = json_decode(file_get_contents('php://input'), true);
    http_response_code(201);
    echo json_encode(['ItemCode' => $body['ItemCode'] ?? 'ITM' . str_pad($counter, 3, '0', STR_PAD_LEFT), 'ItemName' => $body['ItemName'] ?? '']);
}

function handleItemPatch(): void
{
    http_response_code(204);
}

// ── Inventory stock levels ────────────────────────────────────────────────────
function handleStockLevels(): void
{
    $search = strtolower($_GET['$filter'] ?? '');
    preg_match("/tolower\('([^']+)'\)/", $search, $m);
    $term   = strtolower($m[1] ?? '');
    $top    = (int)($_GET['$top'] ?? 50);

    $stock = mockStockLevels();
    if ($term !== '') {
        $stock = array_filter($stock, fn($r) =>
            str_contains(strtolower($r['ItemCode']), $term) ||
            str_contains(strtolower($r['ItemName']), $term));
    }
    echo json_encode(['value' => array_values(array_slice($stock, 0, $top))]);
}

// ── Inventory movements ───────────────────────────────────────────────────────
function handleInventoryMovement(string $type): void
{
    static $counters = ['receipt' => 900, 'issue' => 950];
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['DocumentLines'])) {
        http_response_code(400); echo json_encode(['error'=>['message'=>['value'=>'DocumentLines required']]]); return;
    }
    $counters[$type]++;
    http_response_code(201);
    echo json_encode(['DocEntry' => $counters[$type] + 30000, 'DocNum' => $counters[$type]]);
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

function mockItems(): array
{
    return [
        ['ItemCode'=>'LPT001','ItemName'=>'Laptop Pro 15"',       'ItemType'=>'itItems', 'OnHand'=>45, 'ItemPrices'=>[['Price'=>1299.00]]],
        ['ItemCode'=>'LPT002','ItemName'=>'Laptop Business 13"',  'ItemType'=>'itItems', 'OnHand'=>12, 'ItemPrices'=>[['Price'=>899.00]]],
        ['ItemCode'=>'MON001','ItemName'=>'Monitor 27" 4K',       'ItemType'=>'itItems', 'OnHand'=>30, 'ItemPrices'=>[['Price'=>349.00]]],
        ['ItemCode'=>'KEY001','ItemName'=>'Wireless Keyboard',    'ItemType'=>'itItems', 'OnHand'=>80, 'ItemPrices'=>[['Price'=>79.99]]],
        ['ItemCode'=>'MSE001','ItemName'=>'Wireless Mouse',       'ItemType'=>'itItems', 'OnHand'=>65, 'ItemPrices'=>[['Price'=>49.99]]],
        ['ItemCode'=>'PRN001','ItemName'=>'Laser Printer A4',     'ItemType'=>'itItems', 'OnHand'=>8,  'ItemPrices'=>[['Price'=>249.00]]],
        ['ItemCode'=>'SWT001','ItemName'=>'Network Switch 24-port','ItemType'=>'itItems', 'OnHand'=>5, 'ItemPrices'=>[['Price'=>189.00]]],
        ['ItemCode'=>'CAB001','ItemName'=>'USB-C Cable 2m',       'ItemType'=>'itItems', 'OnHand'=>200,'ItemPrices'=>[['Price'=>14.99]]],
        ['ItemCode'=>'HDK001','ItemName'=>'External HDD 2TB',     'ItemType'=>'itItems', 'OnHand'=>22, 'ItemPrices'=>[['Price'=>89.00]]],
        ['ItemCode'=>'WEB001','ItemName'=>'Webcam 4K',            'ItemType'=>'itItems', 'OnHand'=>0,  'ItemPrices'=>[['Price'=>129.00]]],
        ['ItemCode'=>'SVC001','ItemName'=>'IT Support (hourly)',  'ItemType'=>'itLabor', 'OnHand'=>0,  'ItemPrices'=>[['Price'=>95.00]]],
        ['ItemCode'=>'SVC002','ItemName'=>'Network Setup',        'ItemType'=>'itLabor', 'OnHand'=>0,  'ItemPrices'=>[['Price'=>350.00]]],
        ['ItemCode'=>'SVC003','ItemName'=>'Software Installation','ItemType'=>'itLabor', 'OnHand'=>0,  'ItemPrices'=>[['Price'=>150.00]]],
        ['ItemCode'=>'SVC004','ItemName'=>'Annual Maintenance',   'ItemType'=>'itLabor', 'OnHand'=>0,  'ItemPrices'=>[['Price'=>1200.00]]],
        ['ItemCode'=>'OFF001','ItemName'=>'Office Chair Ergo',    'ItemType'=>'itItems', 'OnHand'=>15, 'ItemPrices'=>[['Price'=>299.00]]],
        ['ItemCode'=>'OFF002','ItemName'=>'Standing Desk',        'ItemType'=>'itItems', 'OnHand'=>7,  'ItemPrices'=>[['Price'=>599.00]]],
        ['ItemCode'=>'OFF003','ItemName'=>'Whiteboard 120x90',    'ItemType'=>'itItems', 'OnHand'=>3,  'ItemPrices'=>[['Price'=>199.00]]],
    ];
}

function mockStockLevels(): array
{
    $warehouses = ['01' => 'Main Warehouse', '02' => 'Branch Warehouse'];
    $items = [
        ['ItemCode'=>'LPT001','ItemName'=>'Laptop Pro 15"',        'InStock'=>45,'Committed'=>10,'OnOrder'=>20],
        ['ItemCode'=>'LPT002','ItemName'=>'Laptop Business 13"',   'InStock'=>12,'Committed'=>3, 'OnOrder'=>10],
        ['ItemCode'=>'MON001','ItemName'=>'Monitor 27" 4K',        'InStock'=>30,'Committed'=>5, 'OnOrder'=>15],
        ['ItemCode'=>'KEY001','ItemName'=>'Wireless Keyboard',     'InStock'=>80,'Committed'=>12,'OnOrder'=>0],
        ['ItemCode'=>'MSE001','ItemName'=>'Wireless Mouse',        'InStock'=>65,'Committed'=>8, 'OnOrder'=>0],
        ['ItemCode'=>'PRN001','ItemName'=>'Laser Printer A4',      'InStock'=>8, 'Committed'=>2, 'OnOrder'=>5],
        ['ItemCode'=>'SWT001','ItemName'=>'Network Switch 24-port','InStock'=>5, 'Committed'=>1, 'OnOrder'=>10],
        ['ItemCode'=>'CAB001','ItemName'=>'USB-C Cable 2m',        'InStock'=>200,'Committed'=>30,'OnOrder'=>0],
        ['ItemCode'=>'HDK001','ItemName'=>'External HDD 2TB',      'InStock'=>22,'Committed'=>4, 'OnOrder'=>0],
        ['ItemCode'=>'WEB001','ItemName'=>'Webcam 4K',             'InStock'=>0, 'Committed'=>0, 'OnOrder'=>15],
        ['ItemCode'=>'OFF001','ItemName'=>'Office Chair Ergo',     'InStock'=>15,'Committed'=>2, 'OnOrder'=>0],
        ['ItemCode'=>'OFF002','ItemName'=>'Standing Desk',         'InStock'=>7, 'Committed'=>3, 'OnOrder'=>5],
        ['ItemCode'=>'OFF003','ItemName'=>'Whiteboard 120x90',     'InStock'=>3, 'Committed'=>1, 'OnOrder'=>0],
    ];

    $result = [];
    foreach ($items as $i) {
        foreach ($warehouses as $whCode => $whName) {
            $factor = $whCode === '01' ? 1 : 0.3;
            $result[] = [
                'ItemCode'      => $i['ItemCode'],
                'ItemName'      => $i['ItemName'],
                'WarehouseCode' => $whCode,
                'InStock'       => round($i['InStock'] * $factor),
                'Committed'     => round($i['Committed'] * $factor),
                'OnOrder'       => round($i['OnOrder'] * $factor),
            ];
        }
    }
    return $result;
}
