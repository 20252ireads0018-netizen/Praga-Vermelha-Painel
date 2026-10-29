<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O webhook do Asaas chega sem sessão: a autenticação é o token no header "asaas-access-token"
        $middleware->validateCsrfTokens(except: ['webhooks/asaas']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON também para requisições AJAX que pedem JSON (ex.: salvamento automático da ficha).
        // Antes, um erro de validação virava redirecionamento e o autosave mostrava "Salvo ✓" sem ter salvo.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
