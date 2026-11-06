<?php
declare(strict_types=1);

require_once __DIR__ . '/Logger.php';

final class OhipClient
{
    public function __construct(
        private string $baseUrl,
        private string $tokenPath,
        private string $clientId,
        private string $clientSecret,
        private string $appKey,
        private string $hotelId,
        private string $enterpriseId,
        private string $enterpriseHeaderName,
        private string $scope,
        private string $tokenCacheFile,
        private Logger $logger,
        private int $clockSkewSeconds = 30,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
        $dir = dirname($this->tokenCacheFile);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }

    /** @return array{tokenObtained:bool, tokenPrefix:string, expiresAt:int|null} */
    public function testToken(): array
    {
        $token = $this->getAccessToken();
        $cached = $this->readTokenCache();
        $expiresAt = is_array($cached) && isset($cached['expires_at']) ? (int)$cached['expires_at'] : null;

        return [
            'tokenObtained' => true,
            'tokenPrefix' => substr($token, 0, 12) . '...',
            'expiresAt' => $expiresAt,
        ];
    }

/** @return array{status:int, headers:array<string,string>, body:mixed} */
public function getReservation(string $reservationId): array
{
    $path = "/rsv/v1/hotels/" . rawurlencode($this->hotelId) . "/reservations/" . rawurlencode($reservationId);
    return $this->callJson('GET', $path, null);
}


    /** @return array{status:int, headers:array<string,string>, body:mixed} */
    

/** @return array{status:int, headers:array<string,string>, body:mixed} */



public function getProfile(string $profileId): array
{
    $template = (string)(getenv('OHIP_PROFILE_GET_PATH') ?: '/crm/v1/profiles/{profileId}?hotelId={hotelId}');
    $path = str_replace(
        ['{profileId}', '{hotelId}'],
        [rawurlencode($profileId), rawurlencode($this->hotelId)],
        $template
    );
    return $this->callJson('GET', $path, null);
}

/**
 * Determine profile type using the actual OHIP profile response.
 * Unknown or ambiguous types are rejected rather than guessed.
 */
public function detectProfileType(string $profileId): array
{
    $response = $this->getProfile($profileId);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('Profile lookup failed (HTTP ' . $response['status'] . ') for profile ' . $profileId);
    }
    $body = $response['body'];
    if (!is_array($body)) throw new RuntimeException('Profile lookup returned non-JSON data');

    $candidates = [];
    $walk = function ($node, int $depth = 0) use (&$walk, &$candidates): void {
        if (!is_array($node) || $depth > 7) return;
        foreach ($node as $key => $value) {
            if (in_array(strtolower((string)$key), ['profiletype', 'customertype'], true) && is_string($value)) {
                $normalized = strtolower(preg_replace('/[^a-z]/i', '', $value));
                if (in_array($normalized, ['company', 'corporate'], true)) $candidates['Company'] = true;
                if (in_array($normalized, ['travelagent', 'travelagency', 'agent'], true)) $candidates['TravelAgent'] = true;
            }
            if (is_array($value)) $walk($value, $depth + 1);
        }
    };
    $walk($body);
    if (count($candidates) !== 1) {
        throw new RuntimeException('Cannot confidently classify profile ' . $profileId . ' as Company or TravelAgent from OHIP response');
    }
    return ['type' => array_key_first($candidates), 'response' => $response];
}

/** GET current reservation, preserve roomStay and existing links, then attach the detected profile type. */
public function prepareReservationLink(string $reservationId, string $profileId, string $profileType): array
{
    if (!in_array($profileType, ['Company', 'TravelAgent'], true)) {
        throw new InvalidArgumentException('Unsupported profile type');
    }
    $get = $this->getReservation($reservationId);
    if ($get['status'] < 200 || $get['status'] >= 300) {
        throw new RuntimeException('Reservation GET failed (HTTP ' . $get['status'] . ')');
    }
    $body = $get['body'];
    $reservation = $body['reservations']['reservation'][0] ?? null;
    if (!is_array($reservation)) throw new RuntimeException('Unexpected reservation GET structure');
    $roomStay = $reservation['roomStay'] ?? null;
    if (!is_array($roomStay)) throw new RuntimeException('Reservation has no roomStay; refusing unsafe PUT');

    $existing = $reservation['reservationProfiles']['reservationProfile'] ?? [];
    if (!is_array($existing)) $existing = [];
    // Preserve existing links. Skip an exact duplicate.
    $alreadyLinked = false;
    foreach ($existing as $entry) {
        if (!is_array($entry) || strcasecmp((string)($entry['reservationProfileType'] ?? ''), $profileType) !== 0) continue;
        foreach (($entry['profileIdList'] ?? []) as $id) {
            if (is_array($id) && (string)($id['id'] ?? '') === $profileId) $alreadyLinked = true;
        }
    }
    if (!$alreadyLinked) {
        $existing[] = [
            'reservationProfileType' => $profileType,
            'profileIdList' => [['id' => $profileId, 'type' => (string)(getenv('OHIP_PROFILE_ID_TYPE') ?: 'Profile')]],
        ];
    }
    $payload = ['reservations' => [[
        'reservationIdList' => [['type' => 'Reservation', 'id' => $reservationId]],
        'reservationProfiles' => ['reservationProfile' => $existing],
        'roomStay' => $roomStay,
    ]]];
    return ['payload' => $payload, 'alreadyLinked' => $alreadyLinked, 'existingCount' => count($existing)];
}

public function putReservationAttachProfile(string $reservationId, string $profileId, string $profileType, array $payload): array
{
    $path = '/rsv/v1/hotels/' . rawurlencode($this->hotelId) . '/reservations/' . rawurlencode($reservationId);
    $this->logger->info('ohip.put.profile', ['reservationId' => $reservationId, 'profileId' => $profileId, 'type' => $profileType]);
    return $this->callJson('PUT', $path, $payload);
}

    /** @return array{status:int, headers:array<string,string>, body:mixed} */
    public function callJson(string $method, string $pathOrUrl, ?array $body): array
    {
        $token = $this->getAccessToken();

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'x-app-key' => $this->appKey,
            'x-hotelid' => $this->hotelId,
            $this->enterpriseHeaderName => $this->enterpriseId,
        ];

        $url = preg_match('#^https?://#i', $pathOrUrl) ? $pathOrUrl : ($this->baseUrl . $pathOrUrl);

        $this->logger->info('ohip.request', ['method' => $method, 'url' => $url]);
        $resp = $this->curlJson($method, $url, $headers, $body);
        $this->logger->info('ohip.response', ['status' => $resp['status'], 'url' => $url]);

        return $resp;
    }

    
/** @return array<int,array<string,mixed>> */
private function extractReservationProfiles(mixed $body): array
{
    if (!is_array($body)) return [];
    $reservations = $body['reservations'] ?? null;
    if (!is_array($reservations)) return [];
    $reservationList = $reservations['reservation'] ?? null;
    if (!is_array($reservationList) || empty($reservationList)) return [];
    $first = $reservationList[0] ?? null;
    if (!is_array($first)) return [];
    $rp = $first['reservationProfiles'] ?? null;
    if (!is_array($rp)) return [];
    $list = $rp['reservationProfile'] ?? [];
    return is_array($list) ? $list : [];
}


/** @return array<string,mixed>|null */
private function extractRoomStay(mixed $body): ?array
{
    if (!is_array($body)) return null;
    $reservations = $body['reservations'] ?? null;
    if (!is_array($reservations)) return null;
    $reservationList = $reservations['reservation'] ?? null;
    if (!is_array($reservationList) || empty($reservationList)) return null;
    $first = $reservationList[0] ?? null;
    if (!is_array($first)) return null;
    $roomStay = $first['roomStay'] ?? null;
    return is_array($roomStay) ? $roomStay : null;
}

private function reservationProfilesHas(array $profiles, string $profileId, string $reservationProfileType): bool
{
    foreach ($profiles as $p) {
        if (!is_array($p)) continue;
        if (strtolower((string)($p['reservationProfileType'] ?? '')) !== strtolower($reservationProfileType)) continue;
        $ids = $p['profileIdList'] ?? null;
        if (!is_array($ids)) continue;
        foreach ($ids as $idObj) {
            if (!is_array($idObj)) continue;
            if ((string)($idObj['id'] ?? '') === $profileId) return true;
        }
    }
    return false;
}

private function getAccessToken(): string
    {
        $cached = $this->readTokenCache();
        if (
            is_array($cached) &&
            isset($cached['access_token'], $cached['expires_at']) &&
            time() < ((int)$cached['expires_at'] - $this->clockSkewSeconds)
        ) {
            return (string)$cached['access_token'];
        }

        $url = $this->baseUrl . $this->tokenPath;

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'x-app-key' => $this->appKey,
            // OHIP token endpoint requires Basic Auth (client_id:client_secret)
            'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
            'x-hotelid' => $this->hotelId,
            $this->enterpriseHeaderName => $this->enterpriseId,
        ];

        $form = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => $this->scope,
        ]);

        $this->logger->info('ohip.token.request', ['url' => $url]);
        $raw = $this->curlRaw('POST', $url, $headers, $form);
        $this->logger->info('ohip.token.response', ['status' => $raw['status']]);

        $json = json_decode($raw['body'], true);
        if (!is_array($json) || !isset($json['access_token'])) {
            throw new RuntimeException("Token response missing access_token. HTTP {$raw['status']}: {$raw['body']}");
        }

        $expiresIn = (int)($json['expires_in'] ?? 3600);
        $cache = [
            'access_token' => (string)$json['access_token'],
            'expires_at' => time() + max(60, $expiresIn),
            'obtained_at' => time(),
        ];
        $this->writeTokenCache($cache);

        return (string)$json['access_token'];
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    private function curlRaw(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Failed to init curl');

        $headerLines = [];
        foreach ($headers as $k => $v) $headerLines[] = "{$k}: {$v}";

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
        ]);

        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error: {$err}");
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'status' => $status,
            'headers' => $this->parseHeaders(substr($raw, 0, $headerSize)),
            'body' => substr($raw, $headerSize),
        ];
    }

    /** @return array{status:int, headers:array<string,string>, body:mixed} */
    private function curlJson(string $method, string $url, array $headers, ?array $body): array
    {
        $rawBody = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES);
        $raw = $this->curlRaw($method, $url, $headers, $rawBody);

        $decoded = json_decode($raw['body'], true);
        $outBody = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw['body'];

        return ['status' => $raw['status'], 'headers' => $raw['headers'], 'body' => $outBody];
    }

    /** @return array<string,string> */
    private function parseHeaders(string $rawHeaders): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($rawHeaders)) ?: [];
        $headers = [];
        foreach ($lines as $line) {
            if (stripos($line, 'HTTP/') === 0) continue;
            $pos = strpos($line, ':');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if ($k !== '') $headers[$k] = $v;
        }
        return $headers;
    }

    private function readTokenCache(): ?array
    {
        if (!is_file($this->tokenCacheFile)) return null;
        $json = @file_get_contents($this->tokenCacheFile);
        if (!is_string($json)) return null;
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function writeTokenCache(array $data): void
    {
        @file_put_contents($this->tokenCacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
