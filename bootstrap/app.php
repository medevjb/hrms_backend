<?php

use App\Exceptions\AttendanceConflictException;
use App\Exceptions\AttendanceWindowException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // docs/PRD.md §139.2 — every API error shares one envelope: message, errors, code.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'code' => 'VALIDATION_FAILED',
            ], $e->status);
        });

        // docs/PRD.md §139.5 — the existing record travels in `data` so the
        // frontend can render correct state from the error alone.
        $exceptions->render(function (AttendanceConflictException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
                'data' => $e->record ? new AttendanceRecordResource($e->record) : null,
            ], 409);
        });

        $exceptions->render(function (AttendanceWindowException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'OUTSIDE_CHECKIN_WINDOW',
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'UNAUTHENTICATED',
            ], 401);
        });

        // Catch-all: any api/* JSON error that didn't go through a render() callback
        // above (403 from a policy, 404 from route-model binding, 429 from throttling,
        // an uncaught 500, ...) still gets a `code` per docs/PRD.md §139.2.
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if (! $request->is('api/*') || ! $response->headers->get('Content-Type')) {
                return $response;
            }

            if (! str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
                return $response;
            }

            $payload = json_decode($response->getContent() ?: '{}', true) ?? [];

            if (array_key_exists('code', $payload)) {
                return $response;
            }

            $payload['code'] = match ($response->getStatusCode()) {
                403 => 'UNAUTHORIZED',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                429 => 'THROTTLED',
                default => $response->getStatusCode() >= 500 ? 'SERVER_ERROR' : 'ERROR',
            };

            $response->setData($payload);

            return $response;
        });
    })->create();
