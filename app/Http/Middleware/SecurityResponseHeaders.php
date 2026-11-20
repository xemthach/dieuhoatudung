<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Conservative headers that do not assume a production HTTPS/CSP deployment. */
class SecurityResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $homepageGeoEnabled = $request->routeIs('home')
            && (bool) setting('display.homepage_dealers_enabled', false)
            && (bool) setting('display.homepage_dealers_geolocation', true);
        $geo = ($request->is('dai-ly', 'dai-ly/*') || $homepageGeoEnabled) ? '(self)' : '()';
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation='.$geo);
        if ($request->routeIs('dealers.nearby')) $response->headers->set('Cache-Control', 'no-store, private');

        if ($request->is('admin/*') || $request->is('livewire/*') || $request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
