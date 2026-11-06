<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$next = (string)($_GET['next'] ?? '/');
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $token = (string)($_POST['csrf'] ?? '');

    if (!auth_csrf_verify($token)) {
        $err = 'Invalid session token. Please try again.';
    } elseif (auth_try_login($email, $password)) {
        $_SESSION['auth_ok'] = true;
        header('Location: ' . $next);
        exit;
    } else {
        $err = 'Invalid credentials.';
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login</title>
  <style>
    body{font-family:system-ui;margin:24px;max-width:520px}
    .card{border:1px solid #ddd;border-radius:14px;padding:18px}
    label{display:block;margin:10px 0 6px}
    input{width:100%;padding:10px;border:1px solid #ccc;border-radius:10px}
    button{margin-top:14px;padding:10px 14px;border:0;border-radius:10px;cursor:pointer}
    .err{color:#b00020;margin-top:10px}
    .muted{color:#666;font-size:13px;margin-top:10px}
  </style>
</head>
<body>
  <h1>OHIP Tool Login</h1>
  <div class="card">
    <form method="post" action="login.php?next=<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <label>Email</label>
      <input name="email" type="email" autocomplete="username" required>
      <label>Password</label>
      <input name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Sign in</button>
      <?php if ($err !== ''): ?><div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <div class="muted">Credentials are configured via environment variables.</div>
    </form>
  </div>
</body>
</html>
