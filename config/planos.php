<?php

/**
 * Planos da conta (ver App\Support\Plano).
 *
 *  - teste:     plano completo, sem anúncios e sem limites, nos primeiros dias da conta;
 *  - gratis:    depois do teste: vê anúncios, até 5 fichas e 1 campanha (como mestre);
 *  - vitalicio: quem pagou o info-produto do livro do sistema (Asaas):
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

    // Preço do info-produto do livro do sistema (cobrança única no Asaas, ver config/asaas.php)
    'preco_livro' => (float) env('LIVRO_PRECO', 6.99),

    // Google AdSense do plano grátis: o ID de publicador da conta (é público, vai no HTML; o .env pode trocar).
    // Sem o bloco (ADSENSE_SLOT), os anúncios automáticos ligados no AdSense escolhem onde aparecer.
    'adsense_cliente' => env('ADSENSE_CLIENTE') ?: 'ca-pub-4781960143966285',
    'adsense_slot'    => env('ADSENSE_SLOT', ''),

    // Livros físicos: links de redirecionamento para a loja (url vazia: aparece como "em breve")
    'livros_fisicos' => [
        ['titulo' => 'Livro do Sistema',  'descricao' => 'Regras e cenário de Praga Vermelha', 'url' => env('LIVRO_FISICO_SISTEMA_URL', '')],
        ['titulo' => 'O Cavaleiro Negro', 'descricao' => 'Livro de histórias',                 'url' => env('LIVRO_FISICO_CAVALEIRO_URL', '')],
        ['titulo' => 'O Mundo de Carne',  'descricao' => 'Livro de histórias',                 'url' => env('LIVRO_FISICO_CARNE_URL', '')],
    ],
];
