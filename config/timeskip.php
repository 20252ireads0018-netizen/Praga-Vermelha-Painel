<?php

/*
 * Time skip: modelos prontos que o mestre carrega e depois edita à vontade (cada campanha guarda a sua cópia).
 *
 * Formato (o mesmo que o editor do mestre grava; App\Support\TimeSkipCalc limpa e calcula):
 *   escolhas        escolhas que todo personagem tem
 *   meses, fator    salário próprio por "meses" meses, recebido em "fator" % (impostos, gastos...)
 *   parametros      texto: parâmetros iniciais para quem perdeu o personagem
 *   bonus           texto: bônus absolutos;  bonus_efeitos: os ganhos desses bônus que entram nos totais
 *   ramos           [id, nome, descricao]: o jogador escolhe um (ou nenhum)
 *   atividades      [id, grupo, nome, descricao, ramo (id exigido), custo (escolhas por vez), max (vezes; 0 = sem limite),
 *                    requisito (texto que o mestre confere), fator (novo % do salário, ex.: mercenário), efeitos]
 *   propriedades    [id, nome, descricao, preco (R$ por marco), ramo, max (marcos), requisito, efeitos (por marco)]
 *   efeitos         [rotulo, valor, a_cada (a cada N vezes/marcos), limite (teto do ganho; 0 = sem), tipo]
 *                   tipo: '' (ganho que aparece nos totais), escolhas (escolhas extras), salario (R$ a mais no salário
 *                   mensal), salarios (salários inteiros a mais), dinheiro (R$ direto)
 */

$e = fn (string $rotulo, float $valor, int $aCada = 1, float $limite = 0, string $tipo = '') =>
    ['rotulo' => $rotulo, 'valor' => $valor, 'a_cada' => $aCada, 'limite' => $limite, 'tipo' => $tipo];
$at = fn (string $id, string $grupo, string $nome, string $descricao, string $ramo = '', array $efeitos = [], int $max = 0, int $custo = 1, string $requisito = '', int $fator = 0) =>
    compact('id', 'grupo', 'nome', 'descricao', 'ramo', 'custo', 'max', 'requisito', 'fator', 'efeitos');
$pr = fn (string $id, string $nome, int $preco, array $efeitos, string $ramo = '', string $descricao = '') =>
    ['id' => $id, 'nome' => $nome, 'descricao' => $descricao, 'preco' => $preco, 'ramo' => $ramo, 'max' => 0, 'requisito' => '', 'efeitos' => $efeitos];

$san = fn (float $v) => $e('Sanidade total e atual', $v);

return [
    'modelos' => [
        'dez_anos' => [
            'nome' => 'Time skip de 10 anos',
            'config' => [
                'descricao' => 'Dez anos se passam. Cada personagem segue um ramo, gasta suas escolhas em atividades e compra propriedades com o dinheiro que juntou.',
                'escolhas' => 20,
                'meses' => 120,
                'fator' => 60,
                'parametros' => "Para jogadores que perderam os personagens (aplicados antes de qualquer bônus de time skip):\n10 níveis mundanos.\n10 níveis de arte marcial a distribuir.\n10 mil reais.\n150 mil reais se sobreviveu à temporada.\nCabo I.\nFormação acadêmica na AMA concluída.\n1 maldição ou 10 propriedades Fisiomantes.\n8 formas da arte Intomante que escolher.",
                'bonus' => "Todo jogador ganha esses bônus sem gastar qualquer recurso:\n+2 níveis mundanos.\nSalário próprio por 120 meses (reduzido a 60% por impostos, gastos e ocorrências diversas).\n1 prova de patente que vale para os 10 anos.\n3 testes revelados.\n+3 ameaça de Maldição.",
                'bonus_efeitos' => [
                    $e('Níveis mundanos', 2), $e('Provas de patente', 1), $e('Testes revelados', 3), $e('Ameaça de Maldição', 3),
                ],
                'ramos' => [
                    ['id' => 'politico', 'nome' => 'Vida política', 'descricao' => 'Cadeiras de votação no seu setor da AMA, escolhas políticas e decisões que podem mudar o rumo dos Hexomantes e dos trabalhos da AMA.'],
                    ['id' => 'militar', 'nome' => 'Vida militar', 'descricao' => 'Equipes, exércitos e esquadrões organizados e dirigidos por você a fim de exorcizar Kuros libertos no incidente, além de enfrentar países em conflitos Hexomantes armados.'],
                    ['id' => 'intelectual', 'nome' => 'Vida intelectual', 'descricao' => 'Pesquisas científicas e Hexomantes, estudos que mudam o rumo do mundo Hexomante em um mundo alterado pela abundância de Kuros, Vassago e os rastros do incidente.'],
                ],
                'atividades' => [
                    // militares
                    $at('formar_exercito', 'Atividades militares', 'Formar exército', 'Para cada escolha, some 50 Hexomantes ao seu exército.', 'militar', [$e('Hexomantes no exército', 50)]),
                    $at('melhorar_exercito', 'Atividades militares', 'Melhorar exército', 'Para cada escolha, aumente o nível de seu exército em +1.', 'militar', [$e('Nível de exército', 1)]),
                    $at('porte_absoluto', 'Atividades militares', 'Porte absoluto', 'Você tem porte livre de qualquer armamento Hexomante, e acesso a todas as lojas relacionadas.', 'militar', [], 1),
                    $at('contatos_militares', 'Atividades militares', 'Contatos militares', 'Com o nascimento do movimento caça mundos, muitas armas são vendidas para quem caça Kuros. Compre armas e proteções amaldiçoadas por metade do preço.', 'militar', [], 1),
                    $at('experiencia_guerra', 'Atividades militares', 'Experiência de guerra', 'Toda vez que fosse receber níveis de arte, receba +1 adicional.', 'militar', [], 1),
                    $at('votacao_militar', 'Atividades militares', 'Votação militar', 'Você possui +1 mão erguida em votações para cada 2 níveis de seu melhor exército.', 'militar', [], 1),
                    // intelectuais
                    $at('catalogador', 'Atividades intelectuais', 'Catalogador', 'Adquira +1 catálogo garantido e instantâneo por escolha.', 'intelectual', [$e('Catálogos garantidos', 1)]),
                    $at('publicacao', 'Atividades intelectuais', 'Publicação', 'Você contribuiu intelectualmente com artigos e livros para a AMA. Publique +1 livro adicional. Para cada livro, aumente seu faturamento na AMA em +15%.', 'intelectual', [$e('Livros publicados', 1), $e('Faturamento na AMA (%)', 15)]),
                    $at('ganho_sabedoria', 'Atividades intelectuais', 'Ganho de sabedoria', 'Toda vez que fosse receber uma aula de grade curricular, receba +1 adicional.', 'intelectual', [], 1),
                    $at('conhecimentos', 'Atividades intelectuais', 'Conhecimentos da experiência', 'Ganhe 2 em BE por escolha, limite +15.', 'intelectual', [$e('BE', 2, 1, 15)]),
                    $at('descoberta_influente', 'Atividades intelectuais', 'Descoberta influente', 'Você possui +1 mão erguida em votações para cada 3 catálogos que tiver.', 'intelectual', [], 1),
                    $at('manuseio_artefatos', 'Atividades intelectuais', 'Manuseio de artefatos', 'Você tem permissão para manusear Artefatos Ancestrais Hexomantes.', 'intelectual', [], 1, 4, '9 catálogos'),
                    // políticas
                    $at('contatos_patente', 'Atividades políticas', 'Contatos de patente', 'Toda vez que fosse receber uma patente, receba +1 adicional.', 'politico', [], 1),
                    $at('influencia', 'Atividades políticas', 'Influência', 'Receba +1 mão erguida por votação para cada escolha.', 'politico', [$e('Mãos erguidas em votação', 1)]),
                    $at('votacao_favoravel', 'Atividades políticas', 'Votação favorável', 'Escolha uma maldição: portadores daquela maldição recebem +10% de faturamento na AMA.', 'politico', [$e('Maldições favorecidas', 1)]),
                    $at('convocar_reuniao', 'Atividades políticas', 'Convocar reunião', 'Você pode convocar reuniões do seu setor da AMA uma vez por sessão.', 'politico', [], 1, 3),
                    $at('influencia_monetaria', 'Atividades políticas', 'Influência monetária', 'Você recebe +10% em faturamento na AMA por escolha.', 'politico', [$e('Faturamento na AMA (%)', 10)]),
                    $at('beneficios', 'Atividades políticas', 'Benefícios e regalias', "Escolha +2 pessoas por escolha, elas recebem benefícios de governo:\n• Direito de uso de técnicas banidas 3 (+escolhas) vezes por mês, fora de calamidade.\n• Porte de qualquer item amaldiçoado.\n• Direito de vagar com Kuros em boa fé.\n• Direito de uso de Fisiomancia (em sigilo público), somente se você investir 5 escolhas.\n• Direito de uso de Fisiomancia Etária, Kurotica e Monetária, somente se você investir 10 escolhas.", 'politico', [$e('Pessoas com benefícios de governo', 2)]),
                    $at('politicas_publicas', 'Atividades políticas', 'Políticas públicas', 'Você pode, uma vez por sessão, sugerir uma lei pública para seu estado e convocar uma reunião para tentar aprová-la. Seus votos não contam nessa reunião.', 'politico', [], 1),
                    $at('empresa', 'Atividades políticas', 'Empresa', 'Você adquire uma empresa. Uma vez a cada 1 (+escolhas) sessão, ela lhe exigirá alguma intervenção para seguir funcionando. Aumente seu salário em valor igual às escolhas vezes 1000.', 'politico', [$e('Salário mensal (R$)', 1000, 1, 0, 'salario')]),
                    $at('voz_prisao', 'Atividades políticas', 'Voz de prisão e soltura', 'Você pode soltar prisioneiros Hexomantes com pena inferior a 20 anos. Você tem voz de prisão obrigatória com qualquer patente inferior à sua em escalas.', 'politico', [], 1),
                    $at('parceiro_politico', 'Atividades políticas', 'Parceiro político', 'Por escolha, você tem a parceria de algum NPC a critério do mestre, que tende a votar junto de você e lhe apoiar politicamente.', 'politico', [$e('Parceiros políticos', 1)]),
                    $at('escudeiro', 'Atividades políticas', 'Escudeiro', 'Um vice, representante, escudeiro, embaixador. Você possui um ajudante que lhe auxilia em absolutamente tudo. Por escolha: +0,5 BE, +5% faturamento, +0,5 mãos erguidas em votação, +1 escala de patente.', 'politico', [$e('BE', 0.5), $e('Faturamento na AMA (%)', 5), $e('Mãos erguidas em votação', 0.5), $e('Escalas de patente', 1)]),
                    $at('teleportadores', 'Atividades políticas', 'Teleportadores', 'Uma vez a cada 48 horas, você pode usar um teletransportador de alcance estadual gratuitamente.', 'politico', [], 1),
                    // gerais
                    $at('vida_mercenaria', 'Atividades gerais', 'Vida Mercenária', 'Você se tornou um mercenário: alguém que renega os benefícios da AMA a fim de autonomia. Diminua seu gasto em imposto em 30%: seu salário próprio de time skip é reduzido em apenas 30% ao invés do comum. Para cada duas escolhas, receba +1 talento de criminoso.', '', [$e('Talentos de criminoso', 1, 2)], 0, 1, 'Nível de criminoso 6', 70),
                    $at('patente', 'Atividades gerais', 'Patente', 'Para cada duas escolhas, receba uma escala de patente.', '', [$e('Escalas de patente', 1, 2)]),
                    $at('catalogos', 'Atividades gerais', 'Catálogos', 'Por escolha, o personagem ganha o direito de testar algum catálogo até que consiga, recebendo os bônus de acordo com a regra. Com 5 catálogos, adquire direito de voto na AMA.', '', [$e('Catálogos para testar', 1)]),
                    $at('trabalho', 'Atividades gerais', 'Trabalho', 'Você focou em missões, resolver problemas e se fazer útil para a AMA, ganhando dinheiro e qualidade de vida. Receba +3 salários por escolha.', '', [$e('Salários extras', 3, 1, 0, 'salarios')], 5),
                    $at('descansar', 'Atividades gerais', 'Descansar e relaxar', 'Recupere, negando penalidades da insanidade, 15 em sanidade por escolha.', '', [$e('Sanidade recuperada', 15)]),
                    $at('treinamento', 'Atividades gerais', 'Treinamento intenso', 'Receba 1 nível de arte por escolha para distribuir. Você não pode aumentar o nível de uma única arte mais do que 6 vezes dessa forma.', '', [$e('Níveis de arte', 1)]),
                    $at('estudos', 'Atividades gerais', 'Estudos Hexomantes', 'Para cada escolha, prossiga +3 aulas na sua grade curricular de matérias da AMA.', '', [$e('Aulas da grade curricular', 3)]),
                    $at('perder_maldicao', 'Atividades gerais', 'Perder maldição', 'Você perde uma maldição, não podendo ficar com 0 a menos que escolha isso 6 vezes.', '', [$e('Maldições perdidas', 1)]),
                    $at('ganhar_maldicao', 'Atividades gerais', 'Ganhar maldição', 'Gaste 5 escolhas, role uma maldição aleatória. Se escolher 8 vezes, escolha uma maldição de raridade máxima incomum.', '', [$e('Maldições roladas', 1)], 0, 5),
                    $at('ganho_tecnica', 'Atividades gerais', 'Ganho de técnica', 'Receba uma técnica Hexomante, ou 1 aprimoramento secreto, ou 2 propriedades Fisiomantes, ou uma conjectura de formas a cada 2 escolhas.', '', [$e('Técnicas (ou equivalente)', 1, 2)]),
                ],
                'propriedades' => [
                    $pr('casa', 'Casa', 125000, [$e('Escolhas extras', 2, 1, 0, 'escolhas')]),
                    $pr('veiculo', 'Veículo', 70000, [$e('Escolhas extras', 1, 1, 0, 'escolhas'), $san(4)]),
                    $pr('filhos', 'Filhos', 40000, [$san(7)]),
                    $pr('parceira', 'Esposa/Parceira', 50000, [$san(8), $e('Escolhas extras', 1, 1, 0, 'escolhas')]),
                    $pr('viagem', 'Viagem de férias', 55000, [$san(11)]),
                    $pr('galpao', 'Galpão amaldiçoado', 155000, [$e('Níveis de arte', 2)], 'militar'),
                    $pr('alojamento', 'Alojamento de exército', 110000, [$e('Nível de exército', 3)], 'militar', 'Alojamento de exército em 5 cidades diferentes por marco.'),
                    $pr('laboratorio', 'Laboratório de pesquisas', 175000, [$e('Aulas da grade curricular', 7), $e('BE', 2), $e('Catálogos garantidos', 1)], 'intelectual'),
                    $pr('arquivo', 'Arquivo pessoal', 95000, [$e('Limite de livros de talento ou habilidade para leitura', 1), $e('Livros', 1)], 'intelectual'),
                    $pr('camara', 'Câmara de partido', 75000, [$e('Faturamento na AMA (%)', 15), $e('Escalas de patente', 2), $e('Segundos de fala no senado', 30), $e('Mãos erguidas em votação', 1)], 'politico'),
                ],
            ],
        ],
    ],
];
