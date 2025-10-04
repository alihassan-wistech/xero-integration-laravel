<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class XeroService
{
    private string $authHeader;
    private string $accessTokenHeader;

    public function __construct()
    {
        $clientId = config('services.xero.client_id');
        $clientSecret = config('services.xero.client_secret');
        $this->authHeader = 'Basic ' . base64_encode("$clientId:$clientSecret");

        if ($this->isAccessTokenExpired()) {
            $tokens = $this->refreshToken();
            $this->storeTokens($tokens);
        }

        $accessToken = $this->getSetting('access_token');
        $this->accessTokenHeader = 'Bearer ' . $accessToken;
    }

    public function getAuthorizationUrl(): string
    {
        $state = uniqid('auth_state_');
        session(['authorization_state' => $state]);

        return sprintf(
            'https://login.xero.com/identity/connect/authorize?response_type=code&client_id=%s&redirect_uri=%s&scope=%s&state=%s',
            config('services.xero.client_id'),
            route('xero.callback'),
            urlencode('openid offline_access profile email accounting.transactions'),
            $state
        );
    }

    public function exchangeCode(string $code): ?array
    {
        return $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => route('xero.callback'),
        ]);
    }

    public function refreshToken(): ?array
    {
        $refreshToken = $this->getSetting('refresh_token');
        if (!$refreshToken) return null;

        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function revokeToken(): bool
    {
        $refreshToken = $this->getSetting('refresh_token');
        if (!$refreshToken) return false;

        $response = Http::withHeaders([
            'Authorization' => $this->authHeader,
        ])->asForm()->post('https://identity.xero.com/connect/revocation', [
            'token' => $refreshToken,
        ]);

        if ($response->successful()) {
            Setting::where('key', 'like', 'xero_%')->delete();
            return true;
        }
        return false;
    }

    private function requestToken(array $data): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->authHeader,
        ])->asForm()->post('https://identity.xero.com/connect/token', $data);

        $json = $response->json();
        return $response->successful() && isset($json['access_token']) ? $json : null;
    }

    public function storeTokens(array $tokenData): void
    {
        foreach (['access_token', 'refresh_token', 'expires_in', 'token_type', 'scope', 'id_token'] as $key) {
            if (isset($tokenData[$key])) {
                $this->setSetting($key, $tokenData[$key]);
            }
        }

        $this->accessTokenHeader = 'Bearer ' . $tokenData['access_token'];
    }

    public function getSetting(string $key): ?string
    {
        return Setting::where('key', "xero_" . $key)?->value('value');
    }

    public function setSetting(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => "xero_$key"],
            ['value' => $value]
        );
    }

    public function isAccessTokenExpired(): bool
    {
        $expiresIn = $this->getSetting('expires_in');
        return $expiresIn && time() > (time() + $expiresIn);
    }

    public function getInvoices($tenantId)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->accessTokenHeader,
            'Xero-Tenant-Id' => $tenantId
        ])->get('https://api.xero.com/api.xro/2.0/Invoices');
        return $response->json();
    }

    public function getConnections($checkDB = true)
    {
        $tenants = $this->getSetting('tenants');
        if (!empty($tenants) && $checkDB) {
            return json_decode($tenants);
        } else {
            $response = Http::withHeaders([
                'Authorization' => $this->accessTokenHeader,
            ])->get('https://api.xero.com/connections');
            return $response->json();
        }
    }

    public function storeConnections($tenants)
    {
        $this->setSetting("tenants", json_encode($tenants));
    }
}
