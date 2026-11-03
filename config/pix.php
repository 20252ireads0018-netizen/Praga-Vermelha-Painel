<?php

/*
 * Compra do livro do sistema por Pix direto na sua chave (sem intermediário). Ver App\Support\Pix e PixController.
 * O site mostra o QR Code e o Pix copia e cola com o valor e um código do pedido; a pessoa paga, clica em "Já paguei"
 * e um administrador confere o extrato e aprova em /admin/pix. Sem PIX_CHAVE, a compra por Pix fica desligada.
 *
 * PIX_CHAVE: e-mail, CPF/CNPJ (só números), celular no formato +5511999999999 ou a chave aleatória.
 * PIX_NOME e PIX_CIDADE: o nome do recebedor e a cidade, como no banco (sem acento; até 25 e 15 letras).
 */
return [
    'chave'  => env('PIX_CHAVE', ''),
    'nome'   => env('PIX_NOME', 'PRAGA VERMELHA'),
    'cidade' => env('PIX_CIDADE', 'SAO PAULO'),

    // Quem aprova os pagamentos (e-mails das contas, separados por vírgula)
    'admins' => array_filter(array_map('trim', explode(',', (string) env('PIX_ADMINS', 'xexplosion20@gmail.com')))),
];
