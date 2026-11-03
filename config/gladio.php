<?php

return [
    // Única conta que cria e edita cartas do Gladio (e a banlist). As cartas dela valem para todas as campanhas;
    // os outros mestres e jogadores só usam essas cartas.
    'dono_cartas' => env('GLADIO_DONO_CARTAS', 'xexplosion20@gmail.com'),
];
