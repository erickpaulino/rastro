<?php
require __DIR__ . '/config.php';

$successMessage = null;
$error = null;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim($_POST['email'] ?? '');

    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        $username = rastro_username_by_email($emailValue);
        if ($username) {
            $token = rastro_create_reset_token($username);
            if ($token) {
                $link = rastro_app_url() . '/reset_password.php?token=' . urlencode($token);
                rastro_send_reset_email($emailValue, $username, $link);
            }
        }
        $successMessage = 'Se o e-mail informado estiver cadastrado, você receberá as instruções em instantes.';
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
    $subject = 'Rastro Timeline - Redefinição de senha';
    $body = "Olá {$username},\n\nRecebemos um pedido para redefinir sua senha no Rastro Timeline.\n" .
        "Clique no link abaixo para criar uma nova senha (válido por 1 hora):\n{$link}\n\n" .
        "Se você não solicitou esta ação, ignore este e-mail.\n";
    $headers = "From: {$MAIL_FROM}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, $subject, $body, $headers);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Recuperar senha • Rastro</title>
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
    <h1>Redefinir senha</h1>
    <p style="font-size:13px;color:#cbd5f5;margin-top:0">
      Informe o e-mail associado à sua conta. Vamos enviar um link para criar uma nova senha.
    </p>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
      <div class="info"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="email">E-mail</label>
      <input type="email" name="email" id="email" required value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit">Enviar link</button>
    </form>
    <div class="hint"><a href="login.php">Voltar ao login</a></div>
  </div>
</body>
</html>
