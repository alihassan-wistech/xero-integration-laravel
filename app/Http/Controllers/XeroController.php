<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\XeroService;

class XeroController extends Controller
{
    private XeroService $xero;

    public function __construct(XeroService $xero)
    {
        $this->xero = $xero;
    }

    public function connect()
    {
        return redirect($this->xero->getAuthorizationUrl());
    }

    public function callback(Request $request)
    {
        if ($request->get('state') !== session('authorization_state')) {
            abort(403, 'Invalid state');
        }

        if ($request->has('error')) {
            abort(400, 'Xero returned an error');
        }

        $tokens = $this->xero->exchangeCode($request->get('code'));
        if (!$tokens) abort(500, 'Failed to exchange authorization code');

        $this->xero->storeTokens($tokens);
        $tenants = $this->xero->getConnections(false);
        $this->xero->storeConnections($tenants);

        return redirect()->route('welcome');
    }

    public function refreshTokens()
    {
        $tokens = $this->xero->refreshToken();
        if (!$tokens) abort(500, 'Failed to refresh tokens');

        $this->xero->storeTokens($tokens);
        return redirect()->route('welcome');
    }

    public function disconnect()
    {
        if (!$this->xero->revokeToken()) {
            abort(500, 'Failed to revoke token');
        }

        return redirect()->route('welcome');
    }
}
