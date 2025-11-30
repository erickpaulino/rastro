<?php
require __DIR__ . '/config.php';

$successMessage = null;
$error = null;
$emailValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim($_POST['email'] ?? '');

    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $error = rastro_t('auth.forgot.error.invalid_email');
    } else {
        $username = rastro_username_by_email($emailValue);
        if ($username) {
            $token = rastro_create_reset_token($username);
            if ($token) {
                $link = rastro_app_url() . '/reset_password.php?token=' . urlencode($token);
                rastro_send_reset_email($emailValue, $username, $link);
            }
        }
        $successMessage = rastro_t('auth.reset.notice.sent');
        $emailValue = '';
    }
}

function rastro_create_reset_token(string $username): ?string {
    try {
        $pdo = db();
        $pdo->prepare('DELETE FROM password_resets WHERE username = ?')->execute([$username]);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = time();
        $expires = $now + 3600;
        $st = $pdo->prepare('INSERT INTO password_resets (username, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)');
        $st->execute([$username, $hash, $expires, $now]);
        return $token;
    } catch (Throwable $e) {
        error_log('Erro ao criar token de redefinição: ' . $e->getMessage());
        return null;
    }
}

function rastro_send_reset_email(string $to, string $username, string $link): void {
    global $MAIL_FROM;
    $subject = rastro_t('email.reset.subject');
    $body = rastro_t('email.reset.body', ['username' => $username, 'link' => $link]);
    $headers = "From: {$MAIL_FROM}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, $subject, $body, $headers);
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(rastro_html_lang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(rastro_t('auth.forgot.title'), ENT_QUOTES, 'UTF-8') ?></title>
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
    input[type=email]{
      width:100%;padding:6px 8px;border-radius:8px;border:1px solid #334155;
      background:#020617;color:#e5e7eb;font-size:13px;margin-bottom:10px;
    }
    button{
      width:100%;padding:8px 10px;border-radius:999px;border:none;
      background:#22c55e;color:#022c22;font-weight:600;font-size:13px;cursor:pointer;
    }
    .error{color:#f97373;font-size:12px;margin-bottom:8px}
    .info{color:#34d399;font-size:12px;margin-bottom:8px}
    .hint{font-size:11px;color:#94a3b8;margin-top:12px;text-align:center}
    .hint a{color:#93c5fd;text-decoration:none}
    .hint a:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="card">
    <h1><?= htmlspecialchars(rastro_t('auth.forgot.heading'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p style="font-size:13px;color:#cbd5f5;margin-top:0">
      <?= htmlspecialchars(rastro_t('auth.forgot.description'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
      <div class="info"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="email"><?= htmlspecialchars(rastro_t('auth.forgot.email'), ENT_QUOTES, 'UTF-8') ?></label>
      <input type="email" name="email" id="email" required value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit"><?= htmlspecialchars(rastro_t('auth.forgot.submit'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    <div class="hint"><a href="login.php"><?= htmlspecialchars(rastro_t('auth.back_to_login'), ENT_QUOTES, 'UTF-8') ?></a></div>
  </div>
</body>
</html>
