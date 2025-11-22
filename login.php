<?php
require __DIR__ . '/config.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';

    global $RASTRO_USERS;

    if (isset($RASTRO_USERS[$u]) && $RASTRO_USERS[$u] === $p) {
        $_SESSION['rastro_user'] = $u;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuário ou senha inválidos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Login • Rastro</title>
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
    .hint{font-size:11px;color:#64748b;margin-top:8px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Rastro • Login</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="username">Usuário</label>
      <input type="text" name="username" id="username" required>

      <label for="password">Senha</label>
      <input type="password" name="password" id="password" required>

      <button type="submit">Entrar</button>
      <div class="hint">Ajuste o usuário/senha em <code>config.php</code>.</div>
    </form>
  </div>
</body>
</html>
