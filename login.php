<?php
require __DIR__ . '/config.php';

$error = null;
$info = null;
$setupWarning = null;
$languageOptions = rastro_available_languages();
$i18nJson = json_encode(
    rastro_client_i18n_data(),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if (empty($RASTRO_USERS)) {
    $setupWarning = rastro_t('auth.login.setup_warning');
}

if (isset($_GET['reset'])) {
    if ($_GET['reset'] === 'ok') {
        $info = rastro_t('auth.reset.notice.success');
    } elseif ($_GET['reset'] === 'sent') {
        $info = rastro_t('auth.reset.notice.sent');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    global $RASTRO_USERS;

    if (!isset($RASTRO_USERS[$u])) {
        $error = rastro_t('auth.login.error.invalid');
    } elseif (!password_verify($p, $RASTRO_USERS[$u])) {
        $error = rastro_t('auth.login.error.invalid');
    } else {
        session_regenerate_id(true);
        $_SESSION['rastro_user'] = $u;
        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(rastro_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(rastro_t('auth.login.title'), ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      padding:24px 20px;
      border-radius:16px;
      box-shadow:0 20px 40px rgba(15,23,42,.8);
      width:100%;
      max-width:340px;
    }
    h1{margin:0 0 16px;font-size:18px}
    label{display:block;font-size:12px;margin-bottom:4px}
    input[type=text],input[type=password]{
      width:100%;padding:6px 8px;border-radius:8px;border:1px solid #334155;
      background:#020617;color:#e5e7eb;font-size:13px;margin-bottom:10px;
    }
    button{
      width:100%;padding:8px 10px;border-radius:999px;border:none;
      background:#22c55e;color:#022c22;font-weight:600;font-size:13px;cursor:pointer;
    }
    .error{color:#f97373;font-size:12px;margin-bottom:8px}
    .info{color:#34d399;font-size:12px;margin-bottom:8px}
    .hint{font-size:11px;color:#64748b;margin-top:8px}
    .hint a{color:#93c5fd;text-decoration:none}
    .hint a:hover{text-decoration:underline}
    .language-switcher{
      display:flex;
      justify-content:flex-end;
      align-items:center;
      gap:6px;
      font-size:11px;
      color:#94a3b8;
      margin-bottom:8px;
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
  </script>
  <script src="assets/i18n.js?v=1"></script>
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
    <h1><?= htmlspecialchars(rastro_t('auth.login.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
      <div class="info"><?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($setupWarning): ?>
      <div class="error" style="color:#facc15"><?= htmlspecialchars($setupWarning, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="username"><?= htmlspecialchars(rastro_t('auth.login.username'), ENT_QUOTES, 'UTF-8') ?></label>
      <input type="text" name="username" id="username" required>

      <label for="password"><?= htmlspecialchars(rastro_t('auth.login.password'), ENT_QUOTES, 'UTF-8') ?></label>
      <input type="password" name="password" id="password" required>

      <button type="submit"><?= htmlspecialchars(rastro_t('auth.login.submit'), ENT_QUOTES, 'UTF-8') ?></button>
      <div class="hint">
        <div><?= htmlspecialchars(rastro_t('auth.login.hint.users'), ENT_QUOTES, 'UTF-8') ?></div>
        <div><a href="forgot_password.php"><?= htmlspecialchars(rastro_t('auth.login.hint.forgot'), ENT_QUOTES, 'UTF-8') ?></a></div>
      </div>
    </form>
  </div>
</body>
</html>
