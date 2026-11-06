<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
auth_require_login();
session_start();

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

if (env_flag('LOG_STDOUT', false) || env_flag('NO_FILE_WRITES', false)) {
    http_response_code(400);
    echo "Logs are configured for stdout. Use container logs.";
    exit;
}

if (!($_SESSION['settings_ok'] ?? false)) {
    http_response_code(403);
    echo "Forbidden. Login at settings.php first.";
    exit;
}

$logDir = storage_dir() . '/logs';
$files = [];
if (is_dir($logDir)) {
    foreach (scandir($logDir) ?: [] as $f) {
        if (preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $f)) $files[] = $f;
    }
}
rsort($files);
$file = basename((string)($_GET['file'] ?? ($files[0] ?? '')));
$path = $file ? ($logDir . '/' . $file) : '';

$content = '';
if ($path && is_file($path)) {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) $content = implode("\n", array_slice($lines, max(0, count($lines) - 800)));
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Logs</title>
<style>body{font-family:system-ui;margin:24px;max-width:1200px}pre{white-space:pre-wrap;word-break:break-word;background:#f6f6f6;padding:12px;border-radius:12px}</style>
</head><body>
<h1>Logs</h1>
<p><a href="index.php">← Back</a> · <a href="settings.php">Settings</a></p>
<form method="get">
<select name="file" onchange="this.form.submit()">
<?php foreach ($files as $f): ?><option value="<?=h($f)?>" <?=$f===$file?'selected':''?>><?=h($f)?></option><?php endforeach; ?>
</select>
</form>
<pre><?=h($content)?></pre>
</body></html>
