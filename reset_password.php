<?php
require __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$success = null;
$showForm = true;
$password = '';
$passwordConfirm = '';
$usernameForToken = null;
if ($token === '') {
    $error = rastro_t('auth.reset.error.no_token');
    $showForm = false;
} else {
    $usernameForToken = rastro_validate_token($token);
    if (!$usernameForToken) {
        $error = rastro_t('auth.reset.error.expired');
        $showForm = false;
    }
}

if ($showForm && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameForToken = rastro_validate_token($token);
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = rastro_t('auth.reset.error.password_length');
    } elseif ($password !== $passwordConfirm) {
        $error = rastro_t('auth.reset.error.password_match');
    } elseif (!$usernameForToken) {
        $error = rastro_t('auth.reset.error.token');
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            rastro_update_user_password($usernameForToken, $hash);
            $pdo = db();
            $pdo->prepare('DELETE FROM password_resets WHERE username = ?')->execute([$usernameForToken]);
            header('Location: login.php?reset=ok');
            exit;
        } catch (Throwable $e) {
            $error = rastro_t('auth.reset.error.update');
            error_log('Erro ao redefinir senha: ' . $e->getMessage());
        }
    }
}

function rastro_validate_token(string $token): ?string {
    if ($token === '') return null;
    try {
        $pdo = db();
        $hash = hash('sha256', $token);
        $st = $pdo->prepare('SELECT username FROM password_resets WHERE token_hash = ? AND expires_at >= ? LIMIT 1');
        $st->execute([$hash, time()]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        return $row['username'];
    } catch (Throwable $e) {
        error_log('Erro ao validar token: ' . $e->getMessage());
        return null;
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(rastro_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(rastro_t('auth.reset.title'), ENT_QUOTES, 'UTF-8') ?></title>
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
      max-width:360px;
    }
    h1{margin:0 0 16px;font-size:18px}
    label{display:block;font-size:12px;margin-bottom:4px}
    input[type=password]{
      width:100%;padding:6px 8px;border-radius:8px;border:1px solid #334155;
      background:#020617;color:#e5e7eb;font-size:13px;margin-bottom:10px;
    }
    button{
      width:100%;padding:8px 10px;border-radius:999px;border:none;
      background:#22c55e;color:#022c22;font-weight:600;font-size:13px;cursor:pointer;
    }
    .error{color:#f97373;font-size:12px;margin-bottom:8px}
    .hint{font-size:11px;color:#94a3b8;margin-top:12px;text-align:center}
    .hint a{color:#93c5fd;text-decoration:none}
    .hint a:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="card">
    <h1><?= htmlspecialchars(rastro_t('auth.reset.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($showForm && $usernameForToken): ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <label for="password"><?= htmlspecialchars(rastro_t('auth.reset.form.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" name="password" id="password" required minlength="8">

        <label for="password_confirm"><?= htmlspecialchars(rastro_t('auth.reset.form.password_confirm'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" name="password_confirm" id="password_confirm" required minlength="8">

        <button type="submit"><?= htmlspecialchars(rastro_t('auth.reset.submit'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    <?php else: ?>
      <p style="font-size:13px;color:#cbd5f5;margin-top:0">
        <?= htmlspecialchars(rastro_t('auth.reset.info.invalid'), ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>
    <div class="hint"><a href="login.php"><?= htmlspecialchars(rastro_t('auth.back_to_login'), ENT_QUOTES, 'UTF-8') ?></a></div>
  </div>
</body>
</html>
