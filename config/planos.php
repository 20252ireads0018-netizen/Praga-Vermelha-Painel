<?php

/**
 * Planos da conta (ver App\Support\Plano).
 *
 *  - teste:     plano completo, sem anúncios e sem limites, nos primeiros dias da conta;
 *  - gratis:    depois do teste: vê anúncios, até 5 fichas e 1 campanha (como mestre);
 *  - vitalicio: quem comprou o info-produto do livro do sistema (aplicou a chave do livro logado):
 *               sem anúncios, sem limites, para sempre, e libera todos os livros em info-produto.
 */
return [
    'dias_teste' => 45,

    // Contas criadas antes do sistema de planos ganham o teste a partir desta data
    'lancamento' => env('PLANOS_LANCAMENTO', '2026-10-29'),

    'limites_gratis' => [
        'fichas'    => 5,
        'campanhas' => 1,
    ],

    // Página de venda do info-produto do livro do sistema (vazio: o botão de compra não aparece)
    'compra_livro_url' => env('LIVRO_COMPRA_URL', ''),

    // Google AdSense do plano grátis (vazio: nenhum anúncio é carregado). Ex.: ca-pub-1234567890123456
    'adsense_cliente' => env('ADSENSE_CLIENTE', ''),
    'adsense_slot'    => env('ADSENSE_SLOT', ''),

    // Livros físicos: links de redirecionamento para a loja (url vazia: aparece como "em breve")
    'livros_fisicos' => [
        ['titulo' => 'Livro do Sistema',  'descricao' => 'Regras e cenário de Praga Vermelha', 'url' => env('LIVRO_FISICO_SISTEMA_URL', '')],
        ['titulo' => 'O Cavaleiro Negro', 'descricao' => 'Livro de histórias',                 'url' => env('LIVRO_FISICO_CAVALEIRO_URL', '')],
        ['titulo' => 'O Mundo de Carne',  'descricao' => 'Livro de histórias',                 'url' => env('LIVRO_FISICO_CARNE_URL', '')],
    ],
];
