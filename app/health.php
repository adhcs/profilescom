<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "OK\n";
echo "PHP_VERSION=" . PHP_VERSION . "\n";
echo "CURL_LOADED=" . (extension_loaded('curl') ? '1' : '0') . "\n";
echo "OPENSSL_LOADED=" . (extension_loaded('openssl') ? '1' : '0') . "\n";
echo "APP_VERSION=20260306-135223
";

echo "ENV_ONLY=" . (env_flag('ENV_ONLY', false) ? '1' : '0') . "\n";
echo "LOG_STDOUT=" . (env_flag('LOG_STDOUT', false) ? '1' : '0') . "\n";
echo "TOKEN_CACHE_PATH=" . token_cache_path() . "\n";
echo "STORAGE_DIR=" . storage_dir() . "\n";
echo "STORAGE_WRITABLE=" . (is_writable(storage_dir()) ? '1' : '0') . "
";
echo "TEST_TOKEN_URL=/test-token.php\n";

echo "OHIP_TOKEN_PATH=" . (getenv("OHIP_TOKEN_PATH") ?: "") . "\n";
echo "OHIP_PUT_MODE=" . (getenv("OHIP_PUT_MODE") ?: "auto") . "\n";
echo "OHIP_MERGE_EXISTING_PROFILES=" . (getenv("OHIP_MERGE_EXISTING_PROFILES") ?: "1") . "\n";
echo "APP_LOGIN_EMAIL=" . (getenv("APP_LOGIN_EMAIL") ?: "") . "\n";
echo "AUTH_ENABLED=" . ((getenv("APP_LOGIN_EMAIL") && getenv("APP_LOGIN_PASSWORD")) ? "1" : "0") . "\n";
