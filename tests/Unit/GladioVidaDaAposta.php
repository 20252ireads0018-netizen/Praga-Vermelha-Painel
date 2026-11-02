<?php

namespace Tests\Unit;

use App\Jogos\Gladio;

/**
 * Os testes de regras foram escritos quando a vida de cada jogador era a aposta. Hoje todos começam com Gladio::VIDA,
 * mas, para esses testes continuarem medindo o que medem, a mesa deles começa com a vida igual à aposta.
 */
trait GladioVidaDaAposta
{
    private function iniciar(array $cartas, int $aposta, array $decks, ?callable $embaralhar = null, array $nomes = [], array $comandantes = []): array
    {
        $e = Gladio::iniciar($cartas, $aposta, $decks, $embaralhar, $nomes, $comandantes);
        $vida = $aposta > 0 ? $aposta : Gladio::VIDA;
        foreach (array_keys($e['lados']) as $l) {
            $e['lados'][$l]['vida'] = $vida;
            $e['lados'][$l]['max'] = $vida;
        }
        $e['total'] = $vida * count($e['lados']);

        return $e;
    }
}
