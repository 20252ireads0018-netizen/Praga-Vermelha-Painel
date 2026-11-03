<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * O "throttle:N,M" padrão do Laravel conta todas as rotas do mesmo usuário num contador só: 30 jogadas de Gladio
 * num minuto bloqueavam o chat (limite 30), e o autosave do tabletop comia o limite das rolagens. Aqui cada rota
 * tem o próprio contador, com os mesmos limites escritos em routes/web.php.
 */
class LimitePorRota extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $rota = $request->route();

        return parent::resolveRequestSignature($request) . '|' . ($rota?->getName() ?? $rota?->uri() ?? '');
    }
}
