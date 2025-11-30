<?php
define('RASTRO_BYPASS_INSTALL_CHECK', true);
require __DIR__ . '/../config.php';

if (empty($_SESSION['install_lang_initialized'])) {
    rastro_set_lang('en');
    $_SESSION['install_lang_initialized'] = true;
}

if ($APP_INSTALLED) {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = false;

$defaults = [
    'db_host'        => env('DB_HOST', 'localhost'),
    'db_name'        => env('DB_NAME', 'rastro'),
    'db_user'        => env('DB_USER', ''),
    'db_pass'        => env('DB_PASS', ''),
    'app_url'        => env('APP_URL', rastro_app_url()),
    'mail_from'      => env('MAIL_FROM', 'Rastro Timeline <no-reply@example.com>'),
    'admin_user'     => '',
    'admin_email'    => '',
    'admin_password' => '',
];

$data = $defaults;
$installDescription = rastro_t('install.description', ['env' => '<code>.env</code>']);
$languageOptions = rastro_available_languages();
$i18nJson = json_encode(
    rastro_client_i18n_data(),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        if ($key === 'db_pass' || $key === 'admin_password') {
            $data[$key] = $_POST[$key] ?? '';
        } else {
            $data[$key] = trim($_POST[$key] ?? '');
        }
    }

    $errors = validate_install_data($data);
    if (!$errors) {
        if (perform_install($data, $errorMessage)) {
            $success = true;
        } else {
            $errors[] = $errorMessage ?: rastro_t('install.error.generic');
        }
    }
}

function validate_install_data(array $data): array {
    $errors = [];
    if ($data['db_host'] === '') $errors[] = rastro_t('install.validation.db_host');
    if ($data['db_name'] === '') $errors[] = rastro_t('install.validation.db_name');
    if ($data['db_user'] === '') $errors[] = rastro_t('install.validation.db_user');
    if (!filter_var($data['app_url'], FILTER_VALIDATE_URL)) {
        $errors[] = rastro_t('install.validation.app_url');
    }
    if ($data['mail_from'] === '') {
        $errors[] = rastro_t('install.validation.mail_from');
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,}$/', $data['admin_user'])) {
        $errors[] = rastro_t('install.validation.admin_user');
    }
    if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = rastro_t('install.validation.admin_email');
    }
    if (strlen($data['admin_password']) < 8) {
        $errors[] = rastro_t('install.validation.admin_password');
    }
    return $errors;
}

function perform_install(array $data, ?string &$errorMessage): bool {
    try {
        $pdo = connect_or_create_database($data['db_host'], $data['db_name'], $data['db_user'], $data['db_pass']);
    } catch (Throwable $e) {
        $errorMessage = rastro_t('install.error.db_connection', ['message' => $e->getMessage()]);
        return false;
    }

    try {
        run_schema($pdo, __DIR__ . '/../.sql-install');
    } catch (Throwable $e) {
        $errorMessage = rastro_t('install.error.schema', ['message' => $e->getMessage()]);
        return false;
    }

    $envValues = [
        'DB_HOST' => $data['db_host'],
        'DB_NAME' => $data['db_name'],
        'DB_USER' => $data['db_user'],
        'DB_PASS' => $data['db_pass'],
        'RASTRO_USERS_JSON' => json_encode([$data['admin_user'] => password_hash($data['admin_password'], PASSWORD_DEFAULT)], JSON_UNESCAPED_SLASHES),
        'RASTRO_USER_EMAILS_JSON' => json_encode([$data['admin_user'] => $data['admin_email']], JSON_UNESCAPED_SLASHES),
        'APP_URL' => rtrim($data['app_url'], '/'),
        'MAIL_FROM' => $data['mail_from'],
        'APP_INSTALLED' => '1'
    ];

    try {
        write_env_file($envValues);
    } catch (Throwable $e) {
        $errorMessage = rastro_t('install.error.env_write', ['message' => $e->getMessage()]);
        return false;
    }

    return true;
}

function connect_or_create_database(string $host, string $dbName, string $user, string $pass): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown database') === false) {
            throw $e;
        }
        $pdo = new PDO(sprintf('mysql:host=%s;charset=utf8mb4', $host), $user, $pass, $options);
        $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $dbName)));
        return new PDO($dsn, $user, $pass, $options);
    }
}

function run_schema(PDO $pdo, string $path): void {
    if (!is_file($path)) {
        throw new RuntimeException(rastro_t('install.error.schema_missing'));
    }
    $raw = file_get_contents($path);
    $raw = preg_replace('/--.*$/m', '', $raw);
    $raw = preg_replace('/\/\*.*?\*\//s', '', $raw);
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $raw)));
    foreach ($statements as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

function write_env_file(array $values): void {
    $lines = [];
    foreach ($values as $key => $value) {
        $lines[] = $key . '=' . env_quote($value);
    }
    $content = implode(PHP_EOL, $lines) . PHP_EOL;
    $envPath = RASTRO_ENV_PATH;
    if (is_file($envPath)) {
        @copy($envPath, $envPath . '.backup-' . date('YmdHis'));
    }
    if (@file_put_contents($envPath, $content) === false) {
        throw new RuntimeException(rastro_t('install.error.env_permission'));
    }
    @chmod($envPath, 0640);
}

function env_quote(string $value): string {
    if ($value === '' || preg_match('/^[A-Za-z0-9_@.,:\/+-]*$/', $value)) {
        return $value;
    }
    if (strpos($value, '"') === false) {
        return '"' . $value . '"';
    }
    if (strpos($value, "'") === false) {
        return "'" . $value . "'";
    }
    return '"' . addcslashes($value, "\"\\") . '"';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(rastro_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(rastro_t('install.title'), ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="alternate icon" href="../assets/favicon.svg">
  <style>
    *{box-sizing:border-box}body{
      margin:0;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:#0f172a;
      color:#e5e7eb;
      display:flex;
      align-items:center;
      justify-content:center;
      min-height:100vh;
    }
    .card{
      background:#020617;
      padding:24px 24px 28px;
      border-radius:18px;
      box-shadow:0 30px 60px rgba(15,23,42,.7);
      width:100%;
      max-width:620px;
    }
    h1{margin:0 0 18px;font-size:22px}
    form{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px 16px}
    label{display:block;font-size:12px;margin-bottom:4px;color:#94a3b8}
    input[type=text],input[type=password],input[type=email]{
      width:100%;padding:8px 10px;border-radius:10px;border:1px solid #334155;
      background:#0b1220;color:#e5e7eb;font-size:13px;
    }
    input[type=text]:focus,input[type=password]:focus,input[type=email]:focus{
      outline:none;border-color:#22d3ee;box-shadow:0 0 0 1px rgba(34,211,238,.6);
    }
    .full-row{grid-column:1/-1}
    button{
      grid-column:1/-1;margin-top:8px;
      padding:10px 12px;border-radius:999px;border:none;
      background:#22c55e;color:#022c22;font-weight:600;font-size:14px;cursor:pointer;
    }
    ul.errors{background:rgba(248,113,113,.15);border:1px solid rgba(248,113,113,.5);
      color:#fecaca;padding:10px 14px;border-radius:12px;font-size:13px;list-style:disc;margin-bottom:16px}
    ul.errors li{margin-left:18px}
    .success{background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.5);
      color:#bbf7d0;padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:16px}
    .success a{color:#bae6fd}
    p.desc{font-size:13px;color:#cbd5f5;margin-top:0;margin-bottom:18px}
    .language-switcher{
      display:flex;
      justify-content:flex-end;
      align-items:center;
      gap:6px;
      font-size:11px;
      color:#94a3b8;
      margin-bottom:12px;
    }
    .language-switcher select{
      border-radius:999px;
      padding:2px 8px;
      background:#020617;
      color:#e5e7eb;
      border:1px solid #334155;
      cursor:pointer;
      font-size:12px;
    }
  </style>
  <script>
    window.RASTRO_I18N = <?= $i18nJson ?>;
    window.RASTRO_SET_LANGUAGE_URL = '../set_language.php';
  </script>
  <script src="../assets/i18n.js?v=1"></script>
</head>
<body>
  <div class="card">
    <div class="language-switcher">
      <label for="language-select"><?= htmlspecialchars(rastro_t('panel.language'), ENT_QUOTES, 'UTF-8') ?></label>
      <select id="language-select" data-language-select>
        <?php foreach ($languageOptions as $code => $meta): ?>
          <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" <?php if ($code === rastro_lang()) echo 'selected'; ?>>
            <?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <h1><?= htmlspecialchars(rastro_t('install.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="desc"><?= $installDescription ?></p>
    <?php if ($success): ?>
      <div class="success">
        <?= htmlspecialchars(rastro_t('install.success'), ENT_QUOTES, 'UTF-8') ?>
        <a href="../login.php"><?= htmlspecialchars(rastro_t('install.success.link'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    <?php else: ?>
      <?php if ($errors): ?>
        <ul class="errors">
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div>
          <label for="db_host"><?= htmlspecialchars(rastro_t('install.field.db_host'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="db_host" id="db_host" required value="<?= htmlspecialchars($data['db_host'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label for="db_name"><?= htmlspecialchars(rastro_t('install.field.db_name'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="db_name" id="db_name" required value="<?= htmlspecialchars($data['db_name'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label for="db_user"><?= htmlspecialchars(rastro_t('install.field.db_user'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="db_user" id="db_user" required value="<?= htmlspecialchars($data['db_user'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label for="db_pass"><?= htmlspecialchars(rastro_t('install.field.db_pass'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="password" name="db_pass" id="db_pass" value="<?= htmlspecialchars($data['db_pass'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="full-row">
          <label for="app_url"><?= htmlspecialchars(rastro_t('install.field.app_url'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="app_url" id="app_url" required value="<?= htmlspecialchars($data['app_url'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="full-row">
          <label for="mail_from"><?= htmlspecialchars(rastro_t('install.field.mail_from'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="mail_from" id="mail_from" required value="<?= htmlspecialchars($data['mail_from'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label for="admin_user"><?= htmlspecialchars(rastro_t('install.field.admin_user'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" name="admin_user" id="admin_user" required value="<?= htmlspecialchars($data['admin_user'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label for="admin_email"><?= htmlspecialchars(rastro_t('install.field.admin_email'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="email" name="admin_email" id="admin_email" required value="<?= htmlspecialchars($data['admin_email'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="full-row">
          <label for="admin_password"><?= htmlspecialchars(rastro_t('install.field.admin_password'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="password" name="admin_password" id="admin_password" required value="<?= htmlspecialchars($data['admin_password'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit"><?= htmlspecialchars(rastro_t('install.button.submit'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
