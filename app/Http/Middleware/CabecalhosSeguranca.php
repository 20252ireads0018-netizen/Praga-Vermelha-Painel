<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de proteção em todas as respostas do site:
 * - nosniff: o navegador não "adivinha" o tipo de um arquivo enviado (imagem servida como script);
 * - SAMEORIGIN: outro site não consegue abrir o painel dentro de um iframe para enganar cliques (aprovar Pix etc.);
 * - Referrer-Policy: links externos não recebem o endereço completo (com códigos de sala);
 * - Permissions-Policy: câmera, microfone e localização desligados (o site não usa);
 * - HSTS: o navegador só volta ao site por HTTPS (o Cloudflare já redireciona o HTTP).
 * A política de conteúdo (CSP) fica de fora: os scripts embutidos e os vídeos do YouTube parariam de funcionar.
 */
class CabecalhosSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);
        $h = $resposta->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        if (!$h->has('X-Frame-Options')) {
            $h->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if (str_starts_with((string) config('app.url'), 'https://') && app()->environment('production')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $resposta;
    }
}
