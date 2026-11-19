<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Browser Back/Forward fix (global).
 *
 * Dynamic HTML pages must never be served from the browser's HTTP cache or the
 * back-forward cache (bfcache). With `no-store`, pressing Back/Forward makes the
 * browser request the page again, so the server re-checks the session and the
 * database (used voting code, submitted/locked evaluation, ...) and shows the
 * current state instead of an old form that looks submittable.
 *
 * File downloads/streams keep their own headers. This is only a UX layer: every
 * write endpoint still validates state on the server.
 */
class PreventStaleHtmlCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        if ($type === '' || str_contains($type, 'text/html') || str_contains($type, 'application/json')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
