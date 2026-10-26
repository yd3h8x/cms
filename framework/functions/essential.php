<?php
declare(strict_types=1);

session_start();

$config = require dirname(__DIR__) . '/config.php';
$GLOBALS['config'] = $config;

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/database.php';

if (!is_local_request()) {
    http_response_code(403);
    exit('This application is available only from localhost.');
}

function app_url(string $path, array $params = []): string
{
    $url = '/' . ltrim($path, '/');
    return $params ? $url . '?' . http_build_query($params) : $url;
}

function load_partial(string $name, array $vars = []): void
{
    $file = dirname(__DIR__) . '/partials/' . basename($name) . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Missing partial.');
    }
    extract($vars, EXTR_SKIP);
    require $file;
}

function current_records_per_page(): int
{
    $allowed = $GLOBALS['config']['allowed_records_per_page'];
    if (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowed, true)) {
        $_SESSION['per_page'] = (int)$_GET['per_page'];
    }
    return in_array($_SESSION['per_page'] ?? 0, $allowed, true)
        ? (int)$_SESSION['per_page']
        : (int)$GLOBALS['config']['default_records_per_page'];
}

function current_breadcrumbs(): array
{
    $crumbs = [['Home', '/index.php']];
    $dbName = (string)($_GET['db'] ?? '');
    $table = (string)($_GET['table'] ?? '');
    if ($dbName !== '') {
        $crumbs[] = [$dbName, app_url('database/index.php', ['db' => $dbName])];
    }
    if ($table !== '') {
        $crumbs[] = [$table, app_url('table/index.php', ['db' => $dbName, 'table' => $table])];
    }
    return $crumbs;
}
