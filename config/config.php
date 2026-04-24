<?php
return [
    'sap' => [
        'base_url'   => getenv('SAP_BASE_URL')   ?: 'https://localhost:50000/b1s/v1',
        'company_db' => getenv('SAP_COMPANY_DB') ?: 'SBODEMOUS',
        'username'   => getenv('SAP_USERNAME')   ?: 'manager',
        'password'   => getenv('SAP_PASSWORD')   ?: 'manager',
        'verify_ssl' => filter_var(getenv('SAP_VERIFY_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],
    'db' => [
        'host'     => getenv('DB_HOST')     ?: 'localhost',
        'name'     => getenv('DB_NAME')     ?: 'expense_app',
        'user'     => getenv('DB_USER')     ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],
    'upload' => [
        'dir'     => __DIR__ . '/../uploads/',
        'max_size' => 5 * 1024 * 1024,
        'allowed' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],
    ],
    'app' => [
        'currency'         => getenv('APP_CURRENCY') ?: 'EUR',
        'default_vat_rate' => (float)(getenv('APP_VAT_RATE') ?: 19),
        'company_name'     => getenv('APP_COMPANY') ?: 'My Company',
    ],
];
