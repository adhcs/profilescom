<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
auth_require_login();
require_once __DIR__ . '/src/OhipClient.php';

session_start();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

if (env_flag('ENV_ONLY', false)) {
    http_response_code(200);
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Settings disabled</title>
    <style>body{font-family:system-ui;margin:24px;max-width:820px}code{background:#f6f6f6;padding:2px 6px;border-radius:6px}</style>
    </head><body>
    <h1>Settings disabled</h1>
    <p>This deployment is configured for <b>environment variables only</b> (<code>ENV_ONLY=1</code>).</p>
    <p>Set these env vars in your container/platform:</p>
    <pre><?=
h(implode("\n", [
"OHIP_BASE_URL=https://...",
"OHIP_TOKEN_PATH=/oauth/v1/tokens",
"OHIP_CLIENT_ID=...",
"OHIP_CLIENT_SECRET=...",
"OHIP_APP_KEY=...",
"OHIP_HOTEL_ID=CHR",
"OHIP_ENTERPRISE_HEADER=enterpriseId",
"OHIP_ENTERPRISE_ID=BHE",
"OHIP_SCOPE=urn:opc:hgbu:ws:__myscopes__",
"SETTINGS_PASSWORD=changeme (optional, if you later disable ENV_ONLY)",
"LOG_STDOUT=1 (recommended in containers)",
]))
?></pre>
    <p><a href="index.php">← Back</a></p>
    </body></html>
    <?php
    exit;
}

$envFile = env_file_path();
$settingsPassword = env_get('SETTINGS_PASSWORD', 'changeme');

if (($_POST['action'] ?? '') === 'login') {
    $pw = (string)($_POST['password'] ?? '');
    if (hash_equals((string)$settingsPassword, $pw)) {
        $_SESSION['settings_ok'] = true;
        header('Location: settings.php');
        exit;
    }
    $err = 'Invalid password';
}

if (!($_SESSION['settings_ok'] ?? false)) {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Settings Login</title>
  <style>
    body{font-family:system-ui;margin:24px;max-width:720px}
    input{width:100%;padding:10px;margin-top:8px;box-sizing:border-box}
    button{padding:10px 14px;margin-top:12px;font-weight:700;cursor:pointer}
    .hint{color:#555;font-size:13px;margin-top:8px}
  </style>
</head>
<body>
  <h1>Settings Login</h1>
  <?php if (!empty($err)) echo '<p style="color:#a00">'.h($err).'</p>'; ?>
  <form method="post">
    <input type="hidden" name="action" value="login" />
    <label>Password</label>
    <input type="password" name="password" required />
    <button type="submit">Login</button>
  </form>
  <p class="hint">Default password is <code>changeme</code>. Change it after login.</p>
</body>
</html>
<?php
    exit;
}

$testResult = null;

if (($_POST['action'] ?? '') === 'test') {
    try {
        $client = app_client();
        $testResult = $client->testToken();
    } catch (Throwable $e) {
        $testResult = ['error' => $e->getMessage()];
    }
}

if (($_POST['action'] ?? '') === 'save') {
    $data = [
        'DEBUG' => trim((string)($_POST['DEBUG'] ?? '0')),
        'SETTINGS_PASSWORD' => (string)($_POST['SETTINGS_PASSWORD'] ?? 'changeme'),
        'OHIP_BASE_URL' => trim((string)($_POST['OHIP_BASE_URL'] ?? '')),
        'OHIP_TOKEN_PATH' => trim((string)($_POST['OHIP_TOKEN_PATH'] ?? '/oauth/v1/tokens')),
        'OHIP_CLIENT_ID' => (string)($_POST['OHIP_CLIENT_ID'] ?? ''),
        'OHIP_CLIENT_SECRET' => (string)($_POST['OHIP_CLIENT_SECRET'] ?? ''),
        'OHIP_APP_KEY' => (string)($_POST['OHIP_APP_KEY'] ?? ''),
        'OHIP_HOTEL_ID' => (string)($_POST['OHIP_HOTEL_ID'] ?? ''),
        'OHIP_ENTERPRISE_ID' => trim((string)($_POST['OHIP_ENTERPRISE_ID'] ?? 'BHE')),
        'OHIP_ENTERPRISE_HEADER' => trim((string)($_POST['OHIP_ENTERPRISE_HEADER'] ?? 'EnterpriseID')),
        'OHIP_SCOPE' => trim((string)($_POST['OHIP_SCOPE'] ?? 'urn:opc:hgbu:ws:__myscopes__')),
        'LOG_STDOUT' => trim((string)($_POST['LOG_STDOUT'] ?? '0')),
        'NO_FILE_WRITES' => trim((string)($_POST['NO_FILE_WRITES'] ?? '0')),
    ];

    $GLOBALS['logger']->info('settings.save', ['baseUrl' => $data['OHIP_BASE_URL'], 'hotelId' => $data['OHIP_HOTEL_ID']]);
    Env::write($envFile, $data);
    header('Location: settings.php?saved=1');
    exit;
}

$current = [
    'DEBUG' => env_get('DEBUG', '0'),
    'SETTINGS_PASSWORD' => env_get('SETTINGS_PASSWORD', 'changeme'),
    'OHIP_BASE_URL' => env_get('OHIP_BASE_URL', ''),
    'OHIP_TOKEN_PATH' => env_get('OHIP_TOKEN_PATH', '/oauth/v1/tokens'),
    'OHIP_CLIENT_ID' => env_get('OHIP_CLIENT_ID', ''),
    'OHIP_CLIENT_SECRET' => env_get('OHIP_CLIENT_SECRET', ''),
    'OHIP_APP_KEY' => env_get('OHIP_APP_KEY', ''),
    'OHIP_HOTEL_ID' => env_get('OHIP_HOTEL_ID', ''),
    'OHIP_ENTERPRISE_ID' => env_get('OHIP_ENTERPRISE_ID', 'BHE'),
    'OHIP_ENTERPRISE_HEADER' => env_get('OHIP_ENTERPRISE_HEADER', 'EnterpriseID'),
    'OHIP_SCOPE' => env_get('OHIP_SCOPE', 'urn:opc:hgbu:ws:__myscopes__'),
    'LOG_STDOUT' => env_get('LOG_STDOUT', '0'),
    'NO_FILE_WRITES' => env_get('NO_FILE_WRITES', '0'),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Settings</title>
  <style>
    body{font-family:system-ui;margin:24px;max-width:980px}
    label{display:block;margin-top:12px;font-weight:600}
    input,select{width:100%;padding:10px;margin-top:6px;box-sizing:border-box}
    .row{display:flex;gap:16px}.col{flex:1}
    .btn{margin-top:16px;padding:12px 16px;cursor:pointer;font-weight:700}
    .hint{color:#555;font-size:13px;margin-top:6px}
    code{background:#f6f6f6;padding:2px 6px;border-radius:6px}
  </style>
</head>
<body>
  <h1>Settings</h1>
  <p><a href="index.php">← Back</a> · <a href="health.php">Health</a> · <a href="logout.php">Logout</a><?php if (!env_flag('LOG_STDOUT', false)): ?> · <a href="logs.php">Logs</a><?php endif; ?></p>
  <?php if (isset($_GET['saved'])): ?><p style="color:green">Saved.</p><?php endif; ?>

  <form method="post">
    <div class="row">
      <div class="col">
        <label>DEBUG</label>
        <select name="DEBUG">
          <option value="0" <?=$current['DEBUG']==='0'?'selected':''?>>0</option>
          <option value="1" <?=$current['DEBUG']==='1'?'selected':''?>>1</option>
        </select>
      </div>
      <div class="col">
        <label>LOG_STDOUT</label>
        <select name="LOG_STDOUT">
          <option value="0" <?=$current['LOG_STDOUT']==='0'?'selected':''?>>0 (file logs)</option>
          <option value="1" <?=$current['LOG_STDOUT']==='1'?'selected':''?>>1 (stdout)</option>
        </select>
        <p class="hint">Recommended for containers.</p>
      </div>
      <div class="col">
        <label>NO_FILE_WRITES</label>
        <select name="NO_FILE_WRITES">
          <option value="0" <?=$current['NO_FILE_WRITES']==='0'?'selected':''?>>0</option>
          <option value="1" <?=$current['NO_FILE_WRITES']==='1'?'selected':''?>>1</option>
        </select>
        <p class="hint">If 1, token cache uses /tmp and logs go to stdout.</p>
      </div>
    </div>

    <label>Settings Password</label>
    <input name="SETTINGS_PASSWORD" value="<?=h($current['SETTINGS_PASSWORD'])?>" required />

    <label>OHIP Base URL</label>
    <input name="OHIP_BASE_URL" value="<?=h($current['OHIP_BASE_URL'])?>" required />

    <div class="row">
      <div class="col"><label>Hotel ID</label><input name="OHIP_HOTEL_ID" value="<?=h($current['OHIP_HOTEL_ID'])?>" required /></div>
      <div class="col"><label>Token Path</label><input name="OHIP_TOKEN_PATH" value="<?=h($current['OHIP_TOKEN_PATH'])?>" required /></div>
    </div>

    <div class="row">
      <div class="col"><label>Client ID</label><input name="OHIP_CLIENT_ID" value="<?=h($current['OHIP_CLIENT_ID'])?>" required /></div>
      <div class="col"><label>Client Secret</label><input name="OHIP_CLIENT_SECRET" value="<?=h($current['OHIP_CLIENT_SECRET'])?>" required /></div>
      <div class="col"><label>App Key</label><input name="OHIP_APP_KEY" value="<?=h($current['OHIP_APP_KEY'])?>" required /></div>
    </div>

    <div class="row">
      <div class="col"><label>Enterprise Header</label><input name="OHIP_ENTERPRISE_HEADER" value="<?=h($current['OHIP_ENTERPRISE_HEADER'])?>" required /></div>
      <div class="col"><label>Enterprise ID</label><input name="OHIP_ENTERPRISE_ID" value="<?=h($current['OHIP_ENTERPRISE_ID'])?>" required /></div>
    </div>

    <label>OAuth Scope</label>
    <input name="OHIP_SCOPE" value="<?=h($current['OHIP_SCOPE'])?>" required />

    <div class="row">
      <div class="col"><button class="btn" type="submit" name="action" value="save">Save</button></div>
      <div class="col"><button class="btn" type="submit" name="action" value="test">Test token</button></div>
    </div>
  </form>

  <?php if ($testResult !== null): ?>
    <h2>Test Result</h2>
    <pre><?=h(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))?></pre>
  <?php endif; ?>
</body>
</html>
