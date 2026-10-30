<?php

use App\Http\Problem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (
            ValidationException $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return Problem::response(
                    $request,
                    422,
                    'validasi-gagal',
                    'Isi permintaan tidak valid',
                    'Satu atau lebih field tidak memenuhi kontrak.',
                    ['errors' => $e->errors()],
                );
            }
        });

        $exceptions->render(function (
            NotFoundHttpException $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return Problem::response(
                    $request,
                    404,
                    'tidak-ditemukan',
                    'Sumber daya tidak ditemukan',
                    'Tidak ada sumber daya pada alamat ini.',
                );
            }
        });
    })->create();
