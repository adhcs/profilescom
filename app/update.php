<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
auth_require_login();
require_once __DIR__ . '/src/OhipClient.php';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$dryRun = ((string)($_POST['dry_run'] ?? '1')) === '1';
if (!isset($_FILES['csv']) || (int)($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400); exit('CSV upload failed.');
}
$fh = fopen((string)$_FILES['csv']['tmp_name'], 'rb');
if ($fh === false) { http_response_code(400); exit('Cannot open CSV.'); }
$header = fgetcsv($fh, 0, ',', '"', '');
if (!is_array($header)) { http_response_code(400); exit('CSV header missing.'); }
$cols = array_map(static fn($v) => strtolower(trim(preg_replace('/^\\xEF\\xBB\\xBF/', '', (string)$v))), $header);
$idxRes = array_search('reservationid', $cols, true);
$idxProf = array_search('profileid', $cols, true);
if ($idxRes === false || $idxProf === false) {
    http_response_code(400); exit('Required CSV headers: reservationId,profileId');
}
$rows = []; $errors = []; $line = 1;
while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    $line++;
    if (count($row) === 1 && trim((string)$row[0]) === '') continue;
    $reservationId = trim((string)($row[$idxRes] ?? ''));
    $profileId = trim((string)($row[$idxProf] ?? ''));
    if ($reservationId === '' || $profileId === '') {
        $errors[] = "Line {$line}: reservationId and profileId required";
        continue;
    }
    $rows[] = ['reservationId' => $reservationId, 'profileId' => $profileId, 'line' => $line];
}
fclose($fh);
if ($errors) {
    http_response_code(400);
    echo '<h1>CSV errors</h1><ul>';
    foreach ($errors as $error) echo '<li>' . h($error) . '</li>';
    exit('</ul><a href="index.php">Back</a>');
}
$client = app_client(); $out = [];
foreach ($rows as $row) {
    $rid = $row['reservationId']; $pid = $row['profileId'];
    try {
        $profile = $client->detectProfileType($pid);
        $type = $profile['type'];
        $prepared = $client->prepareReservationLink($rid, $pid, $type);
        $result = ['line' => $row['line'], 'reservationId' => $rid, 'profileId' => $pid, 'detectedType' => $type];
        if ($dryRun) {
            $result['result'] = 'DRY_RUN';
            $result['alreadyLinked'] = $prepared['alreadyLinked'];
            $result['payload'] = $prepared['payload'];
        } elseif ($prepared['alreadyLinked']) {
            $result['result'] = 'ALREADY_LINKED';
        } else {
            $response = $client->putReservationAttachProfile($rid, $pid, $type, $prepared['payload']);
            $result['httpStatus'] = $response['status'];
            $result['result'] = ($response['status'] >= 200 && $response['status'] < 300) ? 'UPDATED' : 'FAILED';
            $result['response'] = $response['body'];
        }
        $out[] = $result;
        $GLOBALS['logger']->info('batch.row.result', ['reservationId'=>$rid, 'profileId'=>$pid, 'type'=>$type, 'result'=>$result['result']]);
    } catch (Throwable $e) {
        $out[] = ['line'=>$row['line'], 'reservationId'=>$rid, 'profileId'=>$pid, 'result'=>'FAILED', 'error'=>$e->getMessage()];
        $GLOBALS['logger']->error('batch.row.fail', ['reservationId'=>$rid, 'profileId'=>$pid, 'error'=>$e->getMessage()]);
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Batch Results</title><style>body{font-family:system-ui;margin:24px;max-width:1100px}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f5f6f8;padding:16px;border-radius:12px}</style></head>
<body><h1>Batch Results</h1><p><a href="index.php">Back</a> · <?= $dryRun ? 'Dry run: no PUT sent' : 'Live updates' ?></p>
<pre><?= h((string)json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) ?></pre></body></html>
