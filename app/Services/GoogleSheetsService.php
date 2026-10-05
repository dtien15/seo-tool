<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;

/** Google Sheets API dùng Service Account (không cần thư viện ngoài). */
class GoogleSheetsService
{
    private const API = 'https://sheets.googleapis.com/v4/spreadsheets/';

    private array $account;

    public function __construct(array $serviceAccount)
    {
        if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            throw new \RuntimeException('File JSON Service Account không hợp lệ.');
        }
        $this->account = $serviceAccount;
    }

    public static function fromSettings(): self
    {
        $json = Settings::get('google_service_account', '');
        $data = $json ? json_decode($json, true) : null;
        if (!is_array($data)) {
            throw new \RuntimeException('Chưa cấu hình Google Service Account trong Cài đặt hệ thống.');
        }
        return new self($data);
    }

    public static function serviceEmail(): ?string
    {
        $json = Settings::get('google_service_account', '');
        $data = $json ? json_decode($json, true) : null;
        return is_array($data) ? ($data['client_email'] ?? null) : null;
    }

    private function token(): string
    {
        $cacheKey = 'google_token_' . md5($this->account['client_email']);
        $cached = json_decode((string)Settings::get($cacheKey, ''), true);
        if (is_array($cached) && ($cached['exp'] ?? 0) > time() + 60) {
            return $cached['token'];
        }
        $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode([
            'iss' => $this->account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $signature = '';
        if (!openssl_sign($header . '.' . $claims, $signature, $this->account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Không ký được JWT với private key của Service Account.');
        }
        $jwt = $header . '.' . $claims . '.' . $b64($signature);
        $res = http_request('POST', 'https://oauth2.googleapis.com/token', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
            'timeout' => 20,
        ]);
        $token = $res['json']['access_token'] ?? null;
        if (!$token) {
            throw new \RuntimeException('Google từ chối xác thực: ' . ($res['json']['error_description'] ?? $res['error'] ?: 'HTTP ' . $res['status']));
        }
        Settings::set($cacheKey, json_encode(['token' => $token, 'exp' => $now + (int)($res['json']['expires_in'] ?? 3600)]));
        return $token;
    }

    private function call(string $method, string $url, ?array $body = null): array
    {
        $res = http_request($method, $url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->token(), 'Content-Type' => 'application/json'],
            'body' => $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 20,
        ]);
        if ($res['error'] !== '') {
            throw new \RuntimeException('Không kết nối được Google Sheets: ' . $res['error']);
        }
        if ($res['status'] >= 400) {
            $msg = $res['json']['error']['message'] ?? ('HTTP ' . $res['status']);
            if ($res['status'] === 403 || $res['status'] === 404) {
                $msg .= ' — Hãy chia sẻ Google Sheet (quyền Người chỉnh sửa) cho email ' . $this->account['client_email'];
            }
            throw new \RuntimeException('Google Sheets: ' . $msg);
        }
        return is_array($res['json']) ? $res['json'] : [];
    }

    public static function range(string $tab, string $cells): string
    {
        return "'" . str_replace("'", "''", $tab) . "'!" . $cells;
    }

    public function meta(string $spreadsheetId): array
    {
        return $this->call('GET', self::API . rawurlencode($spreadsheetId) . '?fields=properties.title,sheets.properties');
    }

    public function getValues(string $spreadsheetId, string $range): array
    {
        $res = $this->call('GET', self::API . rawurlencode($spreadsheetId) . '/values/' . rawurlencode($range));
        return $res['values'] ?? [];
    }

    public function updateValues(string $spreadsheetId, string $range, array $rows, string $mode = 'RAW'): void
    {
        $this->call('PUT', self::API . rawurlencode($spreadsheetId) . '/values/' . rawurlencode($range) . '?valueInputOption=' . $mode, ['values' => $rows]);
    }

    /** Ghi nhiều vùng một lần. $data = [[range, rows], ...] */
    public function updateMany(string $spreadsheetId, array $data): void
    {
        if (!$data) {
            return;
        }
        $this->call('POST', self::API . rawurlencode($spreadsheetId) . '/values:batchUpdate', [
            'valueInputOption' => 'RAW',
            'data' => array_map(fn($d) => ['range' => $d[0], 'values' => $d[1]], $data),
        ]);
    }

    public function appendValues(string $spreadsheetId, string $range, array $rows): array
    {
        return $this->call(
            'POST',
            self::API . rawurlencode($spreadsheetId) . '/values/' . rawurlencode($range) . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            ['values' => $rows]
        );
    }

    public function batchUpdate(string $spreadsheetId, array $requests): array
    {
        return $this->call('POST', self::API . rawurlencode($spreadsheetId) . ':batchUpdate', ['requests' => $requests]);
    }
}
