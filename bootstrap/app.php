<?php

use App\Http\Middleware\CheckUserPermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantDatabase;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'check.permission' => CheckUserPermission::class,
        ]);
        $middleware->api([
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->web(append: [
            SetTenantDatabase::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login', [
            'tenant' => request()->route('tenant') ?? request()->segment(1) ?? 'arsystem'
        ]));
        $middleware->redirectUsersTo(function () {
            $user = request()->user();
            if ($user) {
                $targetSetting = $user->appSetting ?: ($user->appSettings->first() ?: null);
                if ($targetSetting && $targetSetting->is_active) {
                    return route('dashboard', ['tenant' => strtolower($targetSetting->base_url)]);
                }
            }
            return route('dashboard', [
                'tenant' => request()->route('tenant') ?? request()->segment(1) ?? 'arsystem'
            ]);
        });
        $middleware->validateCsrfTokens(except: [
            // 'rizalbreeder/broadcasting/*',
            // 'rizalbreeder/invoice-report',
            // 'rizal_breeder_ar/api/insertpayment',
            // 'lapsaonbreeder/broadcasting/*',
            // 'lapsaonbreeder/invoice-report',
            // 'lapsaon_breeder_ar/api/insertpayment',
            // 'canhayuponbreeder/broadcasting/*',
            // 'canhayuponbreeder/invoice-report',
            // 'canhayupon_breeder_ar/api/insertpayment',
            // 'hatcherybreeder/broadcasting/*',
            // 'hatcherybreeder/invoice-report',
            // 'hatchery_breeder_ar/api/insertpayment',
            // 'bilarbreeder/broadcasting/*',
            // 'bilarbreeder/invoice-report',
            // 'invoice-report',
            // 'api/insertcustomerledger',
            // 'bilar_breeder_ar/api/insertpayment',
            'api/*', // Or wildcard for all API routes
            '*/adjustment/*/sync-sales',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if ($request->routeIs('adjustment.syncSales')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            $tenant = $request->route('tenant') ?? $request->segment(1) ?? 'feedmill';
            return redirect()->guest(route('session.expired', ['tenant' => $tenant]));
        });
        $exceptions->renderable(function (NotFoundHttpException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Not Found.'], 404);
            }
            return Inertia::render('PageNotFound')->toResponse(request())->setStatusCode(404);
        });

        $exceptions->renderable(function (AccessDeniedHttpException $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            return Inertia::render('Forbidden')->toResponse(request())->setStatusCode(403);
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 403) {
                if (request()->expectsJson()) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }
                return Inertia::render('Forbidden')->toResponse(request())->setStatusCode(403);
            }
        });
    })
    ->withProviders([
        BroadcastServiceProvider::class,
    ])->create();
