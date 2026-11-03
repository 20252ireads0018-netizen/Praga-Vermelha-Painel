<?php

/*
 * Reclame aqui do painel (ReclamacaoController): qualquer conta registra um problema ou sugestão, todos veem a lista
 * e os administradores respondem e mudam a situação.
 */
return [
    // Quem responde (e-mails das contas, separados por vírgula); por padrão, os mesmos que aprovam o Pix
    'admins' => array_filter(array_map('trim', explode(',', (string) env('RECLAMACOES_ADMINS', env('PIX_ADMINS', 'xexplosion20@gmail.com'))))),

    'categorias' => [
        'bug'       => 'Erro no site',
        'gladio'    => 'Gladio (cartas e partidas)',
        'ficha'     => 'Fichas',
        'campanha'  => 'Campanhas e mesas',
        'livro'     => 'Livros e regras',
        'jogos'     => 'Cassino e minijogos',
        'pagamento' => 'Pagamentos',
        'sugestao'  => 'Sugestão',
        'outro'     => 'Outro',
    ],

    'status' => [
        'aberta'    => 'Aguardando resposta',
        'analise'   => 'Em análise',
        'resolvida' => 'Resolvida',
        'recusada'  => 'Não procede',
    ],
];
