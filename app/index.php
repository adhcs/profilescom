<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
auth_require_login();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>OHIP Batch</title>
<style>
body{font-family:system-ui;margin:24px;max-width:1100px}
input,select{width:100%;padding:10px;box-sizing:border-box;margin-top:6px}
label{display:block;margin-top:12px;font-weight:600}
.row{display:flex;gap:16px}.col{flex:1}
.btn{margin-top:12px;padding:10px 14px;cursor:pointer;font-weight:700}
.hint{color:#555;font-size:13px}
code{background:#f6f6f6;padding:2px 6px;border-radius:6px}
</style>
</head>
<body>
<h1>Batch attach Company or Travel Agent profiles</h1>
<p class="hint">
  <a href="settings.php">Settings</a>
  · <a href="health.php">Health</a> · <a href="logout.php">Logout</a> · <a href="test-token.php">Test token</a>
  <?php if (!env_flag('LOG_STDOUT', false)): ?>
    · <a href="logs.php">Logs</a>
  <?php endif; ?>
</p>

<form method="post" action="update.php" enctype="multipart/form-data">
  <div class="row">
    <div class="col">
      <label>Dry run</label>
      <select name="dry_run">
        <option value="1">Yes</option>
        <option value="0">No</option>
      </select>
    </div>
    <div class="col">
      <label>CSV file</label>
      <input type="file" name="csv" accept=".csv,text/csv" required />
      <p class="hint">Headers: <code>reservationId</code>, <code>profileId</code>. Profile type is retrieved from OPERA Cloud automatically. Dry run performs GET requests but does not PUT.</p>
    </div>
  </div>

  <button class="btn" type="submit">Run batch</button>
</form>
<hr style="margin:18px 0">
<p class="hint">APP_VERSION: <code><?php echo htmlspecialchars(trim(@file_get_contents(__DIR__ . '/VERSION.txt'))); ?></code></p>
</body>
</html>
