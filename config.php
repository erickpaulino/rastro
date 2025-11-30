<?php
define('RASTRO_ENV_PATH', __DIR__ . '/.env');

configure_session();
load_env(RASTRO_ENV_PATH);
require __DIR__ . '/translations.php';

$DB_HOST = env('DB_HOST', 'localhost');
$DB_NAME = env('DB_NAME', '');
$DB_USER = env('DB_USER', '');
$DB_PASS = env('DB_PASS', '');
$APP_INSTALLED = env('APP_INSTALLED', '0') === '1';

if (!defined('RASTRO_BYPASS_INSTALL_CHECK')) {
    if (!$APP_INSTALLED && php_sapi_name() !== 'cli') {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($scriptName, '/install/') === false) {
            header('Location: install/');
            exit;
        }
    }
}

$RASTRO_USERS = load_rastro_users();
$RASTRO_USER_EMAILS = load_rastro_user_emails();
$MAIL_FROM = env('MAIL_FROM', 'no-reply@localhost');
$APP_URL = rtrim(env('APP_URL', ''), '/');

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
            if ($quote === '"') {
                $value = stripcslashes($value);
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

function load_rastro_user_emails() {
    $json = env('RASTRO_USER_EMAILS_JSON', '');
    if (!$json) {
        return [];
    }
    $emails = json_decode($json, true);
    if (!is_array($emails)) {
        return [];
    }
    return $emails;
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

function rastro_user_email(string $username): ?string {
    global $RASTRO_USER_EMAILS;
    return $RASTRO_USER_EMAILS[$username] ?? null;
}

function rastro_username_by_email(string $email): ?string {
    global $RASTRO_USER_EMAILS;
    if (!$email) return null;
    foreach ($RASTRO_USER_EMAILS as $user => $addr) {
        if (strcasecmp($addr, $email) === 0) {
            return $user;
        }
    }
    return null;
}

function rastro_set_env_value(string $key, string $value): void {
    $path = RASTRO_ENV_PATH;
    $lines = [];
    if (is_file($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
    }
    $updated = false;
    foreach ($lines as $idx => $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') {
            continue;
        }
        if (strpos($line, $key . '=') === 0) {
            $lines[$idx] = $key . '=' . $value;
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        $lines[] = $key . '=' . $value;
    }
    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
}

function rastro_update_user_password(string $username, string $hash): void {
    global $RASTRO_USERS;
    $RASTRO_USERS[$username] = $hash;
    $payload = json_encode($RASTRO_USERS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    rastro_set_env_value('RASTRO_USERS_JSON', $payload);
}

function rastro_app_url(): string {
    global $APP_URL;
    if ($APP_URL) {
        return $APP_URL;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
