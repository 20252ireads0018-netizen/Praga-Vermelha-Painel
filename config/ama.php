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
];
