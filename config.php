<?php
configure_session();
load_env(__DIR__ . '/.env');

$DB_HOST = env('DB_HOST', 'localhost');
$DB_NAME = env('DB_NAME', '');
$DB_USER = env('DB_USER', '');
$DB_PASS = env('DB_PASS', '');
$RASTRO_USERS = load_rastro_users();

function configure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookieSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'secure' => $cookieSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'],
            '',
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();
}

function load_env($path) {
    static $loaded = [];
    if (isset($loaded[$path]) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            if (substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            }
        }

        $_ENV[$key] = $value;
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }

    $loaded[$path] = true;
}

function env($key, $default = null) {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

function load_rastro_users() {
    $json = env('RASTRO_USERS_JSON', '');
    if (!$json) {
        return [];
    }

    $users = json_decode($json, true);
    if (!is_array($users)) {
        return [];
    }

    return $users;
}

function db() {
    static $pdo;
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    if (!$pdo) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $DB_HOST, $DB_NAME);
        $pdo = new PDO(
            $dsn,
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function rastro_is_logged_in() {
    return isset($_SESSION['rastro_user']);
}

function require_login_html() {
    if (!rastro_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_login_api() {
    if (!rastro_is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'nao_autenticado']);
        exit;
    }
}
