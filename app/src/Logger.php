<?php
declare(strict_types=1);

final class Logger
{
    public function __construct(private string $logDir, private string $rid)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }
    }

    /** @param array<string,mixed> $ctx */
    public function info(string $msg, array $ctx = []): void { $this->write('INFO', $msg, $ctx); }

    /** @param array<string,mixed> $ctx */
    public function error(string $msg, array $ctx = []): void { $this->write('ERROR', $msg, $ctx); }

    /** @param array<string,mixed> $ctx */
    private function write(string $level, string $msg, array $ctx): void
    {
        $line = ['ts' => gmdate('Y-m-d\TH:i:s\Z'), 'level' => $level, 'rid' => $this->rid, 'msg' => $msg];
        if ($ctx) $line['ctx'] = $ctx;

        $json = json_encode($line, JSON_UNESCAPED_SLASHES);
        if ($json === false) $json = '{"ts":"' . gmdate('c') . '","level":"' . $level . '","rid":"' . $this->rid . '","msg":"(log json_encode failed)"}';

        $logStdout = getenv('LOG_STDOUT');
        $noFile = getenv('NO_FILE_WRITES');

        $toStdout = in_array(strtolower((string)$logStdout), ['1','true','yes','on'], true) || in_array(strtolower((string)$noFile), ['1','true','yes','on'], true);
        if ($toStdout) {
            error_log($json);
            return;
        }

        $file = $this->logDir . DIRECTORY_SEPARATOR . 'app-' . gmdate('Y-m-d') . '.log';
        @file_put_contents($file, $json . PHP_EOL, FILE_APPEND);
    }
}
