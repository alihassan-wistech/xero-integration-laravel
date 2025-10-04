<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsXeroConnected
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $xeroRefreshToken = Setting::where('key', 'xero_refresh_token')->first();
        if (empty($xeroRefreshToken)) {
            return redirect()->route('xero.connect');
        }
        return $next($request);
    }
}
