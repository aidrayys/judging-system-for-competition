<?php

namespace App\Http\Middleware;

use App\Services\JudgingPeriod;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks judge evaluation writes when the judging period is closed by the administrator.
 *
 * The state is read from the persisted `competition_settings` row on every
 * request. Read-only pages remain accessible.
 */
class EnsureJudgingOpen
{
    public function __construct(private readonly JudgingPeriod $period) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->period->isOpen()) {
            return $next($request);
        }

        $message = 'The judging period is currently closed. Please contact the administrator to reopen it.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'read_only' => true,
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
