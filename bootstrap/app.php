<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // كوكيز جلسات النظام (الموظف / رمز الإدارة) تُكتب وتُقرأ كقيم HMAC موقّعة بنفسها،
        // فلا نريد أن يشفّرها Laravel مرة ثانية.
        $middleware->encryptCookies(except: ['et_emp_session', 'et_wp_admin_pin']);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
