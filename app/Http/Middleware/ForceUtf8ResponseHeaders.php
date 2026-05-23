<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceUtf8ResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType === '') {
            return $response;
        }

        $isTextLike = str_starts_with($contentType, 'text/')
            || str_starts_with($contentType, 'application/json')
            || str_starts_with($contentType, 'application/xml')
            || str_starts_with($contentType, 'application/javascript');

        if ($isTextLike && ! str_contains(strtolower($contentType), 'charset=')) {
            $response->headers->set('Content-Type', rtrim($contentType, '; ').'; charset=UTF-8');
        }

        return $response;
    }
}

