<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class XeroService
{
    private string $authHeader;

    public function __construct()
    {
        $clientId = config('services.xero.client_id');
        $clientSecret = config('services.xero.client_secret');
        $this->authHeader = 'Basic ' . base64_encode("$clientId:$clientSecret");
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
        $refreshToken = $this->getSetting('xero_refresh_token');
        if (!$refreshToken) return null;

        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function revokeToken(): bool
    {
        $refreshToken = $this->getSetting('xero_refresh_token');
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
                Setting::updateOrCreate(
                    ['key' => "xero_$key"],
                    ['value' => $tokenData[$key]]
                );
            }
        }
    }

    private function getSetting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }
}
