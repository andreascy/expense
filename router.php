<?php
/**
 * PHP built-in server router — loads env vars from config/.env.local
 * and dispatches requests to the correct file.
 */

// Load local env if present (dev only)
$envFile = __DIR__ . '/config/.env.local';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($val));
        $_ENV[trim($key)] = trim($val);
    }
}

// Serve static assets directly
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && is_file(__DIR__ . $uri)) {
    return false;
}

// Route to index.php for root
if ($uri === '/') {
    require __DIR__ . '/index.php';
    return true;
}

// Let PHP handle everything else normally
return false;
