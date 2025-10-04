<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class XeroController extends Controller
{
    private $authorizationUrl;
    private $authorizationHeader;

    public function __construct()
    {
        $this->authorizationUrl = "https://login.xero.com/identity/connect/authorize?response_type=code&client_id=CLIENT_ID&redirect_uri=REDIRECT_URI&scope=SCOPES&state=STATE";
        $this->authorizationHeader = "Basic " . base64_encode(config('services.xero.client_id') . ":" . config('services.xero.client_secret'));
    }

    public function connect()
    {
        session(['authorization_state' => uniqid("auth_state_")]);

        $authorizationUrl = str_replace(
            ['CLIENT_ID', 'REDIRECT_URI', 'STATE', 'SCOPES'],
            [config('services.xero.client_id'), route('xero.callback'), session('authorization_state'), "openid offline_access profile email accounting.transactions"],
            $this->authorizationUrl
        );

        return redirect($authorizationUrl);
    }

    public function callback(Request $request)
    {
        if ($request->get('state') != session('authorization_state')) {
            dd("Authorization state does not match");
        }

        if ($request->has('error')) {
            dd($request->all());
        }

        $code = $request->get('code');

        $response = Http::withHeaders([
            "Authorization" => $this->authorizationHeader
        ])->asForm()->post('https://identity.xero.com/connect/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => route('xero.callback'),
        ]);

        $responseData = $response->json();

        if (
            !empty($responseData)
            && isset($responseData['access_token'])
            && isset($responseData['refresh_token'])
            && isset($responseData['token_type'])
            && isset($responseData['scope'])
            && isset($responseData['expires_in'])
            && isset($responseData['id_token'])
        ) {
            $keys = [
                'access_token',
                'refresh_token',
                'expires_in',
                'token_type',
                'scope',
                'id_token',
            ];

            foreach ($keys as $key) {
                Setting::updateOrCreate(
                    ['key' => 'xero_' . $key],
                    ['value' => $responseData[$key]]
                );
            }

            return redirect()->route('welcome');
        } else {
            dd("FUNCTION NAME : callback; Error: ", $responseData);
        }
    }

    public function refreshTokens()
    {
        $refreshToken = Setting::where('key', 'xero_refresh_token')->first()?->value;
        if (!empty($refreshToken)) {

            $response = Http::withHeaders([
                "Authorization" => $this->authorizationHeader
            ])->asForm()->post('https://identity.xero.com/connect/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken
            ]);

            $responseData = $response->json();
            if (
                !empty($responseData)
                && isset($responseData['access_token'])
                && isset($responseData['refresh_token'])
                && isset($responseData['token_type'])
                && isset($responseData['scope'])
                && isset($responseData['expires_in'])
                && isset($responseData['id_token'])
            ) {
                $keys = [
                    'access_token',
                    'refresh_token',
                    'expires_in',
                    'token_type',
                    'scope',
                    'id_token',
                ];

                foreach ($keys as $key) {
                    Setting::updateOrCreate(
                        ['key' => 'xero_' . $key],
                        ['value' => $responseData[$key]]
                    );
                }

                return redirect()->route('welcome');
            } else {
                dd("FUNCTION NAME : refreshTokens; Error: ", $responseData);
            }
        } else {
            dd("FUNCTION NAME : refreshTokens; Error: No Refresh Tokens Found");
        }
    }

    public function disconnect()
    {
        $refreshToken = Setting::where('key', 'xero_refresh_token')->first()->value;
        if (!empty($refreshToken)) {

            $response = Http::withHeaders([
                "Authorization" => $this->authorizationHeader
            ])->asForm()->post('https://identity.xero.com/connect/revocation', [
                'token' => $refreshToken
            ]);

            if ($response->status() == 200) {
                Setting::where('key', 'xero_access_token')->delete();
                Setting::where('key', 'xero_refresh_token')->delete();
                Setting::where('key', 'xero_expires_in')->delete();
                Setting::where('key', 'xero_token_type')->delete();
                Setting::where('key', 'xero_scope')->delete();
                Setting::where('key', 'xero_id_token')->delete();
                return redirect()->route('welcome');
            } else {
                dd("FUNCTION NAME : disconnect; Error: Request Failed", $response->clientError(), $response->serverError());
            }
        } else {
            dd("FUNCTION NAME : disconnect; Error: No Refresh Tokens Found");
        }
    }

    public function sendInvoice() {}
}
