<?php

/*
 * Guia acadêmico e estudos na AMA: matérias da grade e Talentos de Grade.
 * Regras: por período (6 meses) até 5 matérias; cada matéria dá, por aula, o bônus indicado, até 25 pontos em cada
 * habilidade; "requisitos" são matérias concluídas antes (ids) e condições da ficha (texto, conferidas pelo jogador
 * e pelo mestre). Ao concluir o período (prova com média 7,00), o jogador escolhe um Talento de Grade das matérias que
 * fez. Os estudos acabam com no mínimo 4 períodos.
 *
 * bonus: lista de { hab: [chaves], v }. Com mais de uma chave, o jogador escolhe uma ("resiliência ou reação").
 * Chaves de habilidade da ficha (luta, raciocinio, maldicao...) somam nas rolagens; as demais (aura, be, sanidade_total,
 * deslocamento, reacao, mod_conhecimento, mod_carisma) aparecem no resumo para aplicar onde valem.
 */

$b = fn (array|string $hab, float $v) => ['hab' => (array) $hab, 'v' => $v];

return [
    'max_materias_periodo' => 5,
    'max_por_habilidade'   => 25,
    'periodos_minimos'     => 4,

    // nomes das chaves que não são habilidades da ficha
    'extras' => [
        'aura' => 'Aura', 'be' => 'BE', 'sanidade_total' => 'Sanidade total', 'deslocamento' => 'Deslocamento',
        'reacao' => 'Reação', 'mod_conhecimento' => 'Mod. de Conhecimento', 'mod_carisma' => 'Mod. de Carisma',
    ],

    'materias' => [
        1  => ['nome' => 'História da Hexomancia', 'bonus' => [$b('raciocinio', 2)], 'req' => [], 'cond' => 'Conhecimento 45', 'aulas' => 15],
        2  => ['nome' => 'História da Hexomancia II', 'bonus' => [$b('raciocinio', 3), $b('be', 1)], 'req' => [1], 'cond' => '', 'aulas' => 6],
        3  => ['nome' => 'Especialização Histórica-Hexomante', 'bonus' => [$b('raciocinio', 4), $b('be', 2)], 'req' => [2, 25], 'cond' => '', 'aulas' => 6],
        4  => ['nome' => 'Manutenção de Aura', 'bonus' => [$b('aura', 2), $b('maldicao', 1)], 'req' => [], 'cond' => 'Nível de Hexomante 3', 'aulas' => 20],
        5  => ['nome' => 'Manutenção de Aura II', 'bonus' => [$b('aura', 4), $b('maldicao', 2)], 'req' => [4], 'cond' => 'Nível de Hexomante 7', 'aulas' => 10],
        6  => ['nome' => 'Especialização em Manutenção de Aura', 'bonus' => [$b('aura', 5), $b('maldicao', 3)], 'req' => [5, 24], 'cond' => 'Nível de Hexomante 10', 'aulas' => 6],
        7  => ['nome' => 'Combate Físico Mundano', 'bonus' => [$b(['resiliencia', 'reacao'], 2), $b(['luta', 'precisao'], 2)], 'req' => [], 'cond' => '10 em Luta', 'aulas' => 15],
        8  => ['nome' => 'Táticas Avançadas de Combate', 'bonus' => [$b(['resiliencia', 'reacao'], 3), $b(['luta', 'precisao'], 4)], 'req' => [7], 'cond' => '', 'aulas' => 10],
        9  => ['nome' => 'Especialização em Combate', 'bonus' => [$b(['resiliencia', 'reacao'], 5), $b(['luta', 'precisao'], 6)], 'req' => [8], 'cond' => '', 'aulas' => 6],
        10 => ['nome' => 'Hexomancia Prática', 'bonus' => [$b('maldicao', 3), $b('sanidade_total', 1)], 'req' => [], 'cond' => '', 'aulas' => 15],
        11 => ['nome' => 'Hexomancia Prática II', 'bonus' => [$b('maldicao', 5), $b('sanidade_total', 2)], 'req' => [10, 24], 'cond' => '', 'aulas' => 12],
        12 => ['nome' => 'Especialização em Hexomancia', 'bonus' => [$b('maldicao', 6), $b('sanidade_total', 2)], 'req' => [11], 'cond' => '', 'aulas' => 6],
        13 => ['nome' => 'Tópicos investigativos', 'bonus' => [$b('investigacao', 4), $b('raciocinio', 2)], 'req' => [], 'cond' => 'BE 6', 'aulas' => 12],
        14 => ['nome' => 'Primeiros socorros e conhecimento médico', 'bonus' => [$b('medicina', 3), $b('primeiros-socorros', 3)], 'req' => [], 'cond' => 'BE 7', 'aulas' => 16],
        15 => ['nome' => 'Medicina Hexomante', 'bonus' => [$b('medicina', 4), $b('primeiros-socorros', 4)], 'req' => [14], 'cond' => '', 'aulas' => 12],
        16 => ['nome' => 'Pilotagem de veículos para Hexomantes', 'bonus' => [$b('dirigir', 4)], 'req' => [], 'cond' => '', 'aulas' => 12],
        17 => ['nome' => 'Círculos de Hexomante', 'bonus' => [$b('maldicao', 2)], 'req' => [1, 10, 24], 'cond' => '', 'aulas' => 6],
        18 => ['nome' => 'Técnicas de Alto Círculo Hexomante', 'bonus' => [$b('maldicao', 3)], 'req' => [11, 25], 'cond' => '', 'aulas' => 12],
        19 => ['nome' => 'Diálogo e Lidar com Naturais', 'bonus' => [$b('labia', 4), $b('manipulacao', 4), $b('intimidar', 4)], 'req' => [], 'cond' => '', 'aulas' => 12],
        20 => ['nome' => 'Teoria Kurotica', 'bonus' => [$b('raciocinio', 3), $b('be', 0.5), $b('sanidade_total', -1)], 'req' => [1, 24], 'cond' => '', 'aulas' => 16],
        21 => ['nome' => 'Aprofundamento Daemonico', 'bonus' => [$b('raciocinio', 4), $b('be', 0.5), $b('sanidade_total', -2)], 'req' => [20], 'cond' => '', 'aulas' => 16],
        22 => ['nome' => 'Lendas Sobrenaturais e Literatura Sobrenatural', 'bonus' => [$b('raciocinio', 2), $b('labia', 2), $b('be', 1.5), $b(['mod_conhecimento', 'mod_carisma'], 0.5)], 'req' => [20, 1], 'cond' => '', 'aulas' => 8],
        23 => ['nome' => 'Assassinato e Infiltração', 'bonus' => [$b('furtividade', 2), $b(['precisao', 'luta'], 2), $b('deslocamento', 0.5)], 'req' => [], 'cond' => '', 'aulas' => 12],
        24 => ['nome' => 'Hexomancia Teórica', 'bonus' => [$b('raciocinio', 2), $b('maldicao', 1)], 'req' => [], 'cond' => '', 'aulas' => 16],
        25 => ['nome' => 'Hexomancia Teórica II', 'bonus' => [$b('raciocinio', 3), $b('maldicao', 2)], 'req' => [24], 'cond' => '', 'aulas' => 8],
        26 => ['nome' => 'Linguagens sobrenaturais, Encantamentos e Sinais de Hexomante', 'bonus' => [$b('maldicao', 1)], 'req' => [], 'cond' => '', 'aulas' => 10],
        27 => ['nome' => 'Fenômenos Sobrenaturais', 'bonus' => [$b('maldicao', 1)], 'req' => [], 'cond' => '', 'aulas' => 8],
        28 => ['nome' => 'Alquimia Hexomante', 'bonus' => [$b('medicina', 1), $b('culinaria', 2)], 'req' => [], 'cond' => '', 'aulas' => 10],
        29 => ['nome' => 'Metais Hexomantes', 'bonus' => [$b('resiliencia', 1)], 'req' => [], 'cond' => '', 'aulas' => 10],
        30 => ['nome' => 'Itens e Artefatos Amaldiçoados', 'bonus' => [$b('maldicao', 1)], 'req' => [], 'cond' => '', 'aulas' => 8],
        31 => ['nome' => 'Demônios, Entidades e Pactos', 'bonus' => [], 'req' => [20], 'cond' => '', 'aulas' => 12],
        32 => ['nome' => 'Manipulação de Pactos', 'bonus' => [$b('labia', 2)], 'req' => [31], 'cond' => '', 'aulas' => 10],
        33 => ['nome' => 'Sentimentos negativos e Origens Hexomantes', 'bonus' => [$b('maldicao', 1)], 'req' => [], 'cond' => '', 'aulas' => 12],
    ],

    // Talentos de Grade: "linha" é a matéria cujo Currículo o talento usa (ela e as que dependem dela, direta ou
    // indiretamente, contam +1 cada quando concluídas)
    'talentos' => [
        ['nome' => 'Historiador Hexomante', 'linha' => 1, 'descricao' => 'Você conhece a história da Hexomancia, Hexomantes famosos, a criação de técnicas, rituais e métodos amaldiçoados antigos. Você compreende como a Hexomancia interage com a humanidade em todos os seus períodos. A cada hora de treino em Hexomancia, você tem +4 em Experiência de arte para cada Currículo em História da Hexomancia. Você pode escolher um ramo adicional em Intelectual e escolher talentos como se o tivesse.'],
        ['nome' => 'Auromante', 'linha' => 4, 'descricao' => 'Escolha uma (+1 por currículo em Manutenção de Aura) técnica de alguma maldição sua. Ela custa 1/6 a menos para cada Currículo seu em Manutenção de Aura.'],
        ['nome' => 'Estudioso Hexomante', 'linha' => 24, 'descricao' => 'Para cada currículo em Hexomancia Teórica, receba um talento de Hexomancia.'],
        ['nome' => 'Tropa de combate da AMA', 'linha' => 7, 'descricao' => 'Você pode escolher, se tiver níveis em lutador, um ramo adicional e escolher talentos como se também o tivesse. A cada Currículo em Combate Físico Mundano, você recebe +1 talento de Lutador que possa receber.'],
        ['nome' => 'Hexomante Federal', 'linha' => 10, 'descricao' => 'Ao testar uma nova técnica, mesmo que você falhe na suposição de lógica ou no teste de maldição, você pode re-rolar o teste ou dar outra suposição gastando 15 em aura. A cada 2 Currículos em Hexomancia Prática, receba +1 vantagem em testes de maldição ou +1 talento de Hexomancia que possa receber.'],
        ['nome' => 'Investigador Federal', 'linha' => 13, 'descricao' => 'Você aprendeu a investigar eventos e casos criminais que envolvem Hexomancia e o sobrenatural. Você pode usar Hexomancia ao invés de Investigação. A cada 2 Currículos em Tópicos investigativos, você recebe 1 vantagem em testes de investigação.'],
        ['nome' => 'Hexomante da Saúde', 'linha' => 14, 'descricao' => 'A cada Currículo, você recebe um talento de Intelectual-Médico. Técnicas de maldições suas que Curem PVs curam seu currículo em Primeiros socorros e conhecimento médico dobrado adicionalmente.'],
        ['nome' => 'Piloto Hexomante', 'linha' => 16, 'descricao' => 'Reduza a ação de uso necessária para pilotar em -1 por currículo em Pilotagem de Veículos para Hexomantes. Você não tem penalidades por usar técnicas de sua maldição enquanto dirige.'],
        ['nome' => 'Circulador', 'linha' => 17, 'descricao' => 'Um especialista em círculos de Hexomante. Você aumenta em valor igual ao dobro de seu currículo em Círculos de Hexomante todo valor numérico de vantagem, dano ou cura que usuários de círculos de Hexomancia seus recebam.'],
        ['nome' => 'Tecnicista', 'linha' => 18, 'descricao' => 'Você se especializou nas grandes e mais complexas técnicas da Hexomancia. Reduza em -1 a escala de ação de uso (-1 para cada Currículo seu em Técnicas de Alto Círculo Hexomante) para uma técnica a sua escolha (+1 para cada Currículo em questão) dentre: Mundo Conjurado, Marca Daemonica, Encantamentos Amaldiçoados, Ancoração de Maldição.'],
        ['nome' => 'Diplomata Hexomante', 'linha' => 19, 'descricao' => 'Você se especializou na área de convencimento e lidar com pessoas com uso de Hexomancia. Bônus de Carisma ou Penalidades de carisma que você aplicar com técnicas de maldição são dobrados (em currículo 3 para Diálogo e Lidar com Naturais, considere triplicados).'],
        ['nome' => 'Exorcista Hexomante', 'linha' => 20, 'descricao' => 'Você se especializou em conhecer e lidar com Kuros. Técnicas de maldição suas possuem uma vantagem, para cada Currículo seu em Teoria Kurotica, contra Kuros irracionais. Você tem -1 na escala de ação de uso para ataques e manobras contra Kuros irracionais.'],
        ['nome' => 'Literário da Maldição', 'linha' => 22, 'descricao' => 'Aumente seu limite de leitura de livros em valor igual ao triplo do seu Currículo em Lendas Sobrenaturais e Literatura Sobrenatural. Em currículo 3, você não tem limite de leitura de livros contanto que eles sejam Livros de habilidade para Maldição ou Livros de talento para a Arte marcial Hexomancia.'],
        ['nome' => 'Assassino Hexomante', 'linha' => 23, 'descricao' => 'Você foi treinado na arte de matar sem combate usando Hexomancia. Suas Técnicas de Hexomancia que causem dano possuem margem de crítico adicional igual a seu currículo em Assassinato e Infiltração vezes 6. Em currículo 3, Se uma técnica de dano sua deixar um alvo com 1/4 ou menos dos PVs, o alvo entra em morrendo com 0 PVs.'],
    ],

    // ===== Patentes e crescimento na AMA (aba "Patente e salário" do painel AMA) =====
    // Cada patente tem graus I a V. O salário da patente é sugerido ao escolhê-la; o jogador pode mudar à vontade.
    'graus' => ['I', 'II', 'III', 'IV', 'V'],
    'patentes' => [
        1 => ['nome' => 'Recruta', 'salario' => 1650.00, 'bonificacao' => '300,00 reais por escala',
              'descricao' => 'A massa de manobra básica e comum da AMA, maior parte morre neste cargo. Composto principalmente por crianças e recém amaldiçoados.',
              'beneficios' => "Dormitório em base militar, água encanada, uma refeição.\nRecruta III: direito a curso de direção, dirigir em missão, porte de itens amaldiçoados de até duas modificações."],
        2 => ['nome' => 'Cabo', 'salario' => 2140.50, 'bonificacao' => '450 reais por escala',
              'descricao' => 'Uma patente de maior renome e visibilidade do que um recruta. Alguns gerentes maiores da AMA passam a te olhar sabendo que você não morreu no início de suas missões.',
              'beneficios' => "Dormitório aprimorado, três refeições por dia, porte de animais amaldiçoados.\nCabo III: porte de itens amaldiçoados de até 4 modificações, direito de não mitigação pública."],
        3 => ['nome' => 'Sargento', 'salario' => 4350.33, 'bonificacao' => '500 reais por escala',
              'descricao' => 'Uma patente de maior respeito e que já é capaz de lidar com situações sérias, de nível estatal, com a devida capacidade e preparo.',
              'beneficios' => "Direito de direção de veículos amaldiçoados. Direito de convocar reunião com coronéis ou inferiores. Direito de ordenar patentes inferiores. Porte de itens amaldiçoados de até 6 modificações.\nSargento III: direito de não mitigar maldição em locais públicos."],
        4 => ['nome' => 'Capitão', 'salario' => 6950.40, 'bonificacao' => '1000 reais por escala',
              'descricao' => 'Capitães são a primeira classe olhada com real respeito na AMA. Capazes, raros, eficientes e necessários. Apenas 12% dos agentes chega a essa patente.',
              'beneficios' => 'Direito de convocar reunião com comandantes ou inferiores, porte livre de itens amaldiçoados, direito de escrita e registro de informações amaldiçoadas.'],
        5 => ['nome' => 'Coronel', 'salario' => 10235.55, 'bonificacao' => '2300 reais por escala',
              'descricao' => 'Geralmente, coronéis assumem a função de liderar postos inteiros da AMA ou liderar missões federais por completo. Apenas 8% dos agentes chega a essa patente.',
              'beneficios' => "Direito de usar técnicas banidas sem restrições com boa fé, direito de convocar reuniões com generais, direito de solicitar soltura de prisioneiros Hexomantes, direito de punir e disciplinar Hexomantes de cargo inferior.\nCoronel III: acesso a comandar equipes de 2-20 Hexomantes."],
        6 => ['nome' => 'Comandante', 'salario' => 15653.32, 'bonificacao' => '3000 reais por escala',
              'descricao' => 'Geralmente designados para missões de alta importância na América do Sul. Apenas 6% dos agentes chega a essa patente.',
              'beneficios' => "Voz de prisão e todos os benefícios anteriores mesmo quando em outros países da América do Sul, convocar reunião com marechais ou inferiores, direito de livre uso de Fisiomancia, direito de assistir reuniões quaisquer.\nComandante III: acesso a exército particular de Hexomantes, jato ou helicóptero amaldiçoado particular."],
        7 => ['nome' => 'General', 'salario' => 29540.30, 'bonificacao' => '8950 reais por escala',
              'descricao' => 'Designados para missões de caráter internacional de larga escala, podendo ser enviados a missões na Europa, Ásia, África e qualquer lugar do mundo. Apenas 3% dos agentes chega a essa patente.',
              'beneficios' => "Porte de até 1 dose de anti-ônico, permissão de visita e conhecimento sobre qualquer departamento amaldiçoado de qualquer país pacífico com o Brasil, permissão de regimento de pactos amaldiçoados, permissão de criação de marcas da obediência, convocar reunião com qualquer patente.\nGeneral III: acesso a Entrelinhas particulares e viagem extra-dimensional."],
        8 => ['nome' => 'Vice-Presidente', 'salario' => 48504.44, 'bonificacao' => '29.400 reais por escala',
              'descricao' => 'Esta patente ocupa as cadeiras do topo do alto escalão da AMA. Não diretores de setor, não diretores de município, nem de estado, mas sim diretores de país, tomando decisões e participando em missões de caráter mundial e extra dimensional. Menos do que 1% dos agentes chega a essa patente.',
              'beneficios' => 'Direito de ordem e manuseio de recursos amaldiçoados e armamento entre estados, direito de subir ou descer outros agentes de patente, direito de livrar até 10 agentes do serviço obrigatório da AMA, direito de livre acesso a qualquer fonte de conhecimento sobrenatural nacional, incluindo livros banidos e ocultados pelo governo.'],
        9 => ['nome' => 'Marechal', 'salario' => 98665.45, 'bonificacao' => '65.455,44 reais por escala',
              'descricao' => 'O Marechal do Brasil é o braço direito e segundo no comando da AMA. Enquanto vice-presidentes formam o conselho nacional, apenas o marechal compõe controle direto da agência junto com o presidente.',
              'beneficios' => 'Acesso completo a qualquer ação ou recurso, diante somente da permissão do presidente.'],
        10 => ['nome' => 'Presidente', 'salario' => 398555.55, 'bonificacao' => '', 'descricao' => '', 'beneficios' => '', 'sem_grau' => true],
    ],
    // Regras de crescimento na patente (texto de referência mostrado na aba)
    'patente_regras' => [
        ['titulo' => 'Divisão por patente', 'texto' => "Além da divisão por círculo (que mede apenas a habilidade Hexomântica), há a divisão por patente: ela mede a competência intelectual, combatente e Hexomântica do agente e determina seus direitos, renda, benefícios, deveres e garantias.\nAgentes promissores podem fazer uma prova teórica e uma prova de combate, a critério do mestre, para aumentar a patente. A prova teórica (nota de 0 a 10) diz quantas patentes o agente sobe; a de combate diz se ele pode passar para a patente conquistada. Algumas patentes de trabalho só intelectual não exigem prova teórica."],
        ['titulo' => 'Ganho de patente por nota', 'texto' => "0-5: +0 patente\n6-7: +1 patente\n8-9: +2 patentes\n10-12: +3 patentes\n13-14: +5 patentes\n15 ou mais: +6 patentes"],
        ['titulo' => 'Direito a subir patente (nota da missão)', 'texto' => "Notas: E-, E, E+, D-, D, D+, C-, C, C+, B-, B, B+, A-, A, A+, S-, S, S+, SS-, SS, SS+.\nPatente até 4: pede nova patente com missão A- ou superior.\nPatente 5 e 6: com missão S- ou superior.\nPatente 7 e 8: com missão S+ ou superior.\nPatente 9 e 10: somente com missão SS ou superior."],
        ['titulo' => 'Perda de nota em missão', 'texto' => "Em geral o mestre decide se a missão foi bem sucedida (a partir da nota B), mas alguns acontecimentos tiram notas:\nAlerta de inimigos em missão furtiva: -2 notas\nMorte de inocente: -3 notas\nObjetivo não cumprido: -8 notas\nObjetivo cumprido parcialmente: -5 notas\nBaixa de agente: -4 notas\nChamado de reforços: -3 notas\nDano ao patrimônio público: -3 notas"],
        ['titulo' => 'Investimentos e empréstimos', 'texto' => 'O agente pode investir, a juros compostos de 10% (+5% por patente) ao mês, até 3.500 reais, limite que dobra a cada patente. O mesmo limite e os mesmos juros valem para empréstimos.'],
        ['titulo' => 'Doação para a AMA', 'texto' => 'A cada 10.000,00 doados, receba os bônus de um catálogo, +1 mão erguida em votações e +1 nível de exército. Acumulativo até 30.000,00 (+10.000,00 por patente).'],
        ['titulo' => 'Grau e escala de patente', 'texto' => 'Cada patente tem um grau: Recruta I (mais baixo) a Recruta V (mais alto), o que define algumas regras de bonificação. A escala é o número da patente (Recruta é a escala 1, a mais baixa).'],
    ],
];
