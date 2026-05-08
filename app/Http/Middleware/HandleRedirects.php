<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    /**
     * Paths that should never be redirected.
     */
    protected array $skipPrefixes = [
        'admin',
        'livewire',
        'api',
        '_ignition',
        'telescope',
        'horizon',
        'build',
        'storage',
        'vendor',
        'up',         // health check
    ];

    /**
     * Asset extensions to skip.
     */
    protected array $skipExtensions = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'map', 'webp', 'avif',
        'xml', 'txt', 'pdf',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip non-GET/HEAD
        if (! in_array($request->method(), ['GET', 'HEAD'])) {
            return $next($request);
        }

        $path = ltrim($request->getPathInfo(), '/');

        // Skip admin and special routes
        foreach ($this->skipPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // Skip static assets by extension
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension && in_array(strtolower($extension), $this->skipExtensions)) {
            return $next($request);
        }

        // Look up active redirect (gracefully skip if table missing)
        try {
            $normalized = '/' . $path;
            $redirect = Redirect::where('is_active', true)
                ->where('source_url', $normalized)
                ->first();
        } catch (\Throwable) {
            return $next($request);
        }

        if ($redirect) {
            // Record hit asynchronously-safe (no exception breaks the redirect)
            try {
                $redirect->recordHit();
            } catch (\Throwable) {
                // Never block user due to tracking failure
            }

            $target = $redirect->target_url;

            // If target is relative, make it absolute
            if (! str_starts_with($target, 'http://') && ! str_starts_with($target, 'https://')) {
                $target = '/' . ltrim($target, '/');
            }

            return redirect($target, $redirect->status_code);
        }

        return $next($request);
    }
}
