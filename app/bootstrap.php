<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
auth_session_start();

require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Logger.php';

Env::load(env_flag('ENV_ONLY', false) ? '/__nonexistent__' : env_file_path());

$rid = bin2hex(random_bytes(8));
$GLOBALS['logger'] = new Logger(logger_log_dir(), $rid);

$GLOBALS['logger']->info('request.start', [
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
]);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $GLOBALS['logger']->error('fatal', $err);
    }
    $GLOBALS['logger']->info('request.end');
});

if (env_flag('DEBUG', false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

@set_time_limit(300);

/** Helpers **/

function env_file_path(): string
{
    return __DIR__ . '/.env';
}

function storage_dir(): string
{
    // In no-file-writes mode, we still use /tmp for token cache, but storage dir exists for nginx/php
    return __DIR__ . '/storage';
}

function token_cache_path(): string
{
    if (env_flag('NO_FILE_WRITES', false) || env_flag('ENV_ONLY', false)) {
        return sys_get_temp_dir() . '/ohip_token_cache.json';
    }
    return storage_dir() . '/token_cache.json';
}

function logger_log_dir(): string
{
    if (env_flag('LOG_STDOUT', false) || env_flag('NO_FILE_WRITES', false)) {
        // Not used for file output; placeholder.
        return sys_get_temp_dir();
    }
    return storage_dir() . '/logs';
}

function env_get(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v === false) {
        $v = $_ENV[$key] ?? $default;
    }
    return (string)$v;
}

function env_flag(string $key, bool $default = false): bool
{
    $v = env_get($key, $default ? '1' : '0');
    return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
}

function app_client(): OhipClient
{
    require_once __DIR__ . '/src/OhipClient.php';

    $baseUrl = env_get('OHIP_BASE_URL');
    $tokenPath = env_get('OHIP_TOKEN_PATH', '/oauth/v1/tokens');
    $clientId = env_get('OHIP_CLIENT_ID');
    $clientSecret = env_get('OHIP_CLIENT_SECRET');
    $appKey = env_get('OHIP_APP_KEY');
    $hotelId = env_get('OHIP_HOTEL_ID');
    $enterpriseId = env_get('OHIP_ENTERPRISE_ID', 'BHE');
    $enterpriseHeader = env_get('OHIP_ENTERPRISE_HEADER', 'enterpriseId');
    $scope = env_get('OHIP_SCOPE', 'urn:opc:hgbu:ws:__myscopes__');

    $logger = $GLOBALS['logger'];

    return new OhipClient(
        baseUrl: $baseUrl,
        tokenPath: $tokenPath,
        clientId: $clientId,
        clientSecret: $clientSecret,
        appKey: $appKey,
        hotelId: $hotelId,
        enterpriseId: $enterpriseId,
        enterpriseHeaderName: $enterpriseHeader,
        scope: $scope,
        tokenCacheFile: token_cache_path(),
        logger: $logger,
        clockSkewSeconds: 30,
    );
}
