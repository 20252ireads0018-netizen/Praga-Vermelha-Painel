<?php

/*
 * Catálogo de maldições.
 * Local: config/maldicoes.php  (acesso via config('maldicoes'))
 * A chave de cada item é o slug (Str::slug do nome), usado na URL: /maldicoes/{slug}
 * Para cadastrar uma nova maldição, basta adicionar outro item neste array.
 *
 * Campos:
 *  nome, raridade (Comum|Incomum|Rara|Exótica|Impossível), descricao,
 *  ameacas[] => [ nivel, dado?, regra?, efeitos[] => [rotulo?, texto, marca?], penalidade? ]
 *  tecnicas[] => (vazio por enquanto; mantido para compatibilidade com a view)
 */
return [

    'maldicao-do-justiceiro' => [
        'nome' => 'Maldição do Justiceiro',
        'raridade' => 'Rara',
        'descricao' =>
            "Com essa maldição, o portador é forçado a seguir as leis e regras gerais da nação social a qual vive.\n" .
            "Ele é impedido de infringir as regras sociais do meio em que vive nos mais mínimos detalhes, e por muitas vezes isso pode lhe atrapalhar.\n" .
            "Sua aura e as propriedades nela imbuídas encarnam a lei, justiça e ética, e aplica estes conceitos conforme manipulá-la.\n" .
            "Esta maldição também se manifesta raramente da forma oposta, obrigando o portador a contrariar a lei sempre que puder.",

        'ameacas' => [
            [
                'nivel' => 0,
                'dado' => '1d2',
                'regra' => 'Role <strong>1d2</strong>. Se o resultado for 1, aplique o primeiro efeito abaixo. Se for 2, aplique o último efeito desta ameaça.',
                'efeitos' => [
                    [
                        'marca' => '1',
                        'rotulo' => 'Resultado 1 — Lei',
                        'texto' => 'No país, estado ou cidade que estiver, você deve sempre seguir qualquer lei imposta legitimamente pelo governo legítimo do país. Você só é livre deste efeito se estiver em uma área a qual nenhum país ou organização social legítima domina.',
                    ],
                    [
                        'marca' => '2',
                        'rotulo' => 'Resultado 2 — Contra a Lei',
                        'texto' => 'O caso contrário é que você deve sempre contrariar por completo a lei do país o qual está. Se você tiver a oportunidade de cometer um crime, você deve cometê-lo. Para efeitos que citem cumprir a lei, considere o oposto.',
                    ],
                ],
                'penalidade' => 'Para descumprimento de qualquer uma das regras da maldição, você perde sua ameaça +5 em perda de PVs e 3 vezes esse valor em aura e sanidade atual.',
            ],
        ],

        'tecnicas' => [],
    ],

    'campo-de-forca-eterno' => [
        'nome' => 'Campo de força eterno',
        'raridade' => 'Impossível',
        'descricao' =>
            "Sua aura Daemonica cria ao seu redor um campo de força invisível que impede que qualquer coisa o toque. Isso parece ótimo, já que pode lhe proteger de possíveis afrontas externas.\n" .
            "Mas pense melhor: como você vai comer se a comida não se aproxima? Como vai vestir roupas? Seus pés nem sequer tocam o chão.\n" .
            "Todos os portadores desta maldição morrem se não aprenderem a reduzir o campo de força antes de morrerem de fome.",

        'ameacas' => [
            [
                'nivel' => 0,
                'efeitos' => [
                    [
                        'rotulo' => 'Campo de força',
                        'texto' => 'Você possui um campo de força que nega qualquer dano que não seja Congelante, Flamejante ou tóxico. Danos e condições que venham de dentro (danos que narrativamente comecem de dentro do seu corpo, como toxinas consumidas e etc.) são eficazes normalmente.',
                    ],
                    [
                        'rotulo' => 'Sem contato',
                        'texto' => 'Você não pode tocar, equipar, vestir ou manusear nenhum item de nenhum gênero, por isso também não pode consumir qualquer item consumível ou alimento.',
                    ],
                    [
                        'rotulo' => 'Flutuação',
                        'texto' => 'Seus pés não tocam o chão, então você não é afetado por terreno difícil.',
                    ],
                    [
                        'rotulo' => 'Tamanho da barreira',
                        'texto' => 'Sua barreira começa de 1 por 1 e aumenta em +1 metro de raio por ameaça. Você não pode entrar em ambientes que não comportem sua barreira sem quebrá-los ou arrombá-los.',
                    ],
                    [
                        'rotulo' => 'Custo de aura',
                        'texto' => 'A barreira gasta a sua aura para se manter. Você perde metade da sua aura total inicial e de toda quantia de aura que receber.',
                    ],
                ],
            ],
        ],

        'tecnicas' => [],
    ],

    'visao-astral' => [
        'nome' => 'Visão astral',
        'raridade' => 'Impossível',
        'descricao' =>
            "O portador dessa maldição constantemente vê as almas das pessoas vivas e das mortas. Provocando paranoia, perturbação, problemas com sono e tranquilidade, e insanidade absurdamente prematura.\n" .
            "As almas aparecem na forma de pequenas aparições espectrais semitransparentes que surgem na forma de mini chamas de cores diversas que variam a depender das emoções do alvo.",

        'ameacas' => [
            [
                'nivel' => 0,
                'efeitos' => [
                    [
                        'rotulo' => 'Almas dos vivos',
                        'texto' => 'Você constantemente vê as almas das pessoas. Ao ver um ser vivo pela primeira vez, você sofre sua Ameaça +1 em dano de sanidade.',
                    ],
                    [
                        'rotulo' => 'Almas dos mortos',
                        'texto' => 'Todo início de cena, há uma chance de 15% (+15% por ameaça) de você ver a alma de algum morto, sofrendo sua ameaça +3 em dano de sanidade.',
                    ],
                ],
            ],
        ],

        'tecnicas' => [],
    ],

    'ladrao-de-dor' => [
        'nome' => 'Ladrão de Dor',
        'raridade' => 'Incomum',
        'descricao' =>
            "Os portadores desta maldição absorvem propriedades negativas de elementos ao toque para suas próprias mentes. Isso resulta na capacidade de retirar dor, ferimentos, doenças, a custo de sua mente ser carregada pelo peso destas características.\n" .
            "Geralmente forçados a se isolar ou se privar de contato humano, os amaldiçoados desta condição vivem vidas infelizes e sofridas, sem nenhum contato humano.",

        'ameacas' => [
            [
                'nivel' => 0,
                'efeitos' => [
                    [
                        'rotulo' => 'Cura por toque',
                        'texto' => 'Toda vez que você tocar em um alvo que não tenha PVs completos, seja sem querer ou com ação bônus, você cura sua ameaça +2 PVs dele, perdendo o mesmo valor em sanidade.',
                    ],
                    [
                        'rotulo' => 'Retirar condições',
                        'texto' => 'Tocar um alvo com uma das condições: Atordoado ou variantes, Sangrando ou variantes, Enjoado, Exausto, Amedrontado, Confuso, Provocado retira dele a condição. Mas você sofre dano de sanidade igual a sua ameaça +3.',
                    ],
                    [
                        'rotulo' => 'Alvos amaldiçoados',
                        'texto' => 'Tocar um alvo amaldiçoado causa sua ameaça +2 dobrada em dano de sanidade.',
                    ],
                ],
            ],
        ],

        'tecnicas' => [],
    ],

    'invocacao-involuntaria-material' => [
        'nome' => 'Invocação involuntária material',
        'raridade' => 'Exótica',
        'descricao' =>
            "Uma maldição peculiar que invoca e utiliza objetos invocados para ferir o portador. Portadores desta maldição podem repentinamente ser surpreendidos por um tiro de uma arma de fogo surpresa. Ou ser esmagados por uma geladeira que caiu do céu.\n" .
            "Uma maldição que põe em risco e geralmente mata todos os seus portadores antes de 5 anos de posse.",

        'ameacas' => [
            [
                'nivel' => 0,
                'efeitos' => [
                    [
                        'rotulo' => 'Objeto invocado',
                        'texto' => 'A cada início de cena, você tem uma chance de 20% (+10% por ameaça) de que um objeto de até tamanho pequeno a escolha do mestre seja invocado e desfira um ataque contra você, permanecendo no chão após isso. Caso seja uma arma, o objeto causa dano equivalente ao dela. Você deve reagir ao ataque considerando uma dificuldade de (D40 -20 por ameaça). A categoria de tamanho máximo do objeto aumenta em +1 por ameaça.',
                    ],
                    [
                        'rotulo' => 'Objeto que não é arma',
                        'texto' => 'Se o objeto que te atacar não for uma arma, o dano dele é de 1d4 (+1 passo e +2 dados por categoria de tamanho) e a margem de crítico é 1/2x (+5/+1x por categoria).',
                    ],
                ],
            ],
        ],

        'tecnicas' => [],
    ],

    'retracao-aquatica' => [
        'nome' => 'Retração aquática',
        'raridade' => 'Rara',
        'descricao' =>
            "Espíritos Daemonicos mudam as propriedades da sua aura, fazendo com que seu corpo afaste e mova água para longe de você. Inicialmente é difícil beber água, e você causa mal-estar em seres vivos à base de água próximos a você.\n" .
            "Depois se torna impossível beber água, nadar, e você ativamente ejeta seres vivos próximos a você. Portadores dessa maldição frequentemente se isolam da sociedade e sobrevivem à base de mitigação.",

        'ameacas' => [
            [
                'nivel' => 0,
                'efeitos' => [
                    [
                        'rotulo' => 'Líquidos',
                        'texto' => 'Para consumir líquidos ou manipular objetos com líquidos dentro, você deve testar atletismo (D50 -15 por ameaça).',
                    ],
                    [
                        'rotulo' => 'Seres próximos',
                        'texto' => 'Seres vivos que consumam água e que estejam num raio de 3 (+ameaça dobrada) metros de você têm desvantagem (+1 desvantagem para cada duas ameaças) em qualquer teste que façam.',
                    ],
                ],
            ],
            [
                'nivel' => 2,
                'efeitos' => [
                    [
                        'rotulo' => 'Impossível nadar',
                        'texto' => 'É impossível nadar. Se você tentar, você cai, sofre dano de queda e fica ao fundo da superfície com água.',
                    ],
                    [
                        'rotulo' => 'Mal-estar',
                        'texto' => 'Seres no alcance da ameaça 0 recebem a condição Enjoado, e a cada turno devem testar resiliência (D50 -15 por ameaça). Se falharem duas vezes na mesma cena, ficam inconscientes.',
                    ],
                ],
            ],
            [
                'nivel' => 4,
                'efeitos' => [
                    [
                        'rotulo' => 'Ejeção',
                        'texto' => 'Seres que sofram os efeitos da ameaça 2 também são ejetados a metros iguais a sua ameaça dobrada, sofrendo dano de queda adequado ao colidir com algo ou alguém.',
                    ],
                ],
            ],
        ],

        'tecnicas' => [],
    ],

    'maldicao-quebrante' => [
        'nome' => 'Maldição Quebrante',
        'raridade' => 'Incomum',
        'descricao' =>
            "Sua aura se impregna no seu corpo, pertences e arredores para destruir e quebrar tudo que há ao seu redor.\n" .
            "Seus ossos se partem aleatoriamente, seus pertences e itens se partem sem explicação. É difícil que portadores a longo prazo desta maldição mantenham saúde física duradoura. Geralmente vivem em hospitais, ou isolamento. E não vivem muito.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Chance de quebra', 'texto' => 'O mestre tem chances que começam em valor igual a sua ameaça +1 de quebrar qualquer item que você esteja equipando ou armazenando consigo.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Mini ruptura', 'texto' => 'Se a chance de quebra se concretizar, ao invés de um item, você pode sofrer uma mini ruptura corporal de algum músculo pequeno, causando perda de PVs totais iguais a sua ameaça vezes 2. Estes PVs só são recuperáveis com cirurgias médicas de 4 horas de duração e medicina (D0).'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Osso quebrado', 'texto' => 'Se a chance de quebra se concretizar e for direcionada a dano ao seu corpo, sempre que sofrer esta chance você sofre a sequela de osso quebrado.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Quebra em área', 'texto' => 'Se a chance de quebra se concretizar, 1d4 +2 itens num raio de metros iguais a sua ameaça vezes 2 vão se quebrar e se tornar inúteis. Os itens são escolhidos pelo mestre.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Quebra obrigatória', 'texto' => 'Todo início de cena, todos os efeitos de ameaça vão se concretizar obrigatoriamente. Toda vez que você sacar ou tocar um item, ele se quebra.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'visao-amaldicoada' => [
        'nome' => 'Visão amaldiçoada',
        'raridade' => 'Comum',
        'descricao' =>
            "Seus olhos são amaldiçoados com a capacidade de ver a Aura Daemonica que gera as maldições na aura dos seres vivos e objetos.\n" .
            "Presenciar os horrores da aura Daemonica e sua origem nefasta e incompreensível enlouquece praticamente todos os portadores desta maldição.\n" .
            "Esta é a maldição mais comum e simples já catalogada por humanos.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Visão de Aura Daemonica', 'texto' => 'Você recebe Visão de Aura Daemonica.'],
                ['rotulo' => 'Dano de sanidade', 'texto' => 'Sempre que você vir um portador de maldição, Kuro ou item amaldiçoado pela primeira vez, sofre dano de sanidade igual ao dobro da ameaça do portador (dano mínimo: 3). Tocar diretamente um item amaldiçoado ou portador de maldição dobra este dano.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Aura no ar', 'texto' => 'Você consegue ver não só a aura Daemonica presente nos seus portadores, mas também a que flui no ar antes de infectar qualquer elemento natural. Para cada cena com olhos abertos, você perde sua ameaça dobrada em dano de sanidade.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Detalhes vívidos', 'texto' => 'Você consegue ver detalhes vívidos da aura Daemonica ao seu redor. Você sempre sabe a tensão espiritual dos locais que está. Você sabe as últimas 3 técnicas usadas por seres e elementos que veja. Você sabe a ameaça de seres e objetos que veja. Dobre o dano de sanidade proveniente da ameaça 0.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Tipo de espírito', 'texto' => 'Você consegue ver o tipo de cada espírito Daemonico, logo, os respectivos Daemons aos quais pertencem.'],
                ['rotulo' => 'Agitação dos espíritos', 'texto' => 'Se você vê os espíritos, seus movimentos corporais causam agitações a eles, como se fossem vento ao abanar de uma mão. A cada início de cena, há chance igual a sua ameaça vezes 10 de você causar um fenômeno sobrenatural do Daemon cuja presença prevalece na área.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'multiplicacao-conceitual' => [
        'nome' => 'Multiplicação Conceitual',
        'raridade' => 'Impossível',
        'descricao' => 'Nesta maldição, os espíritos Daemonicos presentes em sua aura multiplicam conceitos abstratos dos seus arredores e corpo para lhe atrapalhar. Em algum momento, a distância entre você até um alvo pode dobrar, um dano sofrido pode ser multiplicado, ou a quantidade de balas que é disparada contra você.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Valor de Multiplicação', 'texto' => 'Suas técnicas (mas não sua ameaça) usam um valor de Multiplicação igual a 0,2 e aumenta em +0,2 até um limite de 2,00 para cada 2 níveis de maldição.'],
                ['rotulo' => 'Chances do mestre', 'texto' => 'O mestre possui um número de chances igual a sua ameaça +3. A cada cena suas chances reiniciam, o mestre pode gastar uma chance para aplicar um dos efeitos a seguir.'],
                ['rotulo' => 'Multiplicar distância', 'texto' => 'Multiplique (por sua ameaça) a distância entre você e um elemento a escolha do mestre. Na prática, isso te faz ser arremessado em deslocamento igual a sua ameaça +1 vezes 5 metros, e sofrer dano de queda adequado se colidir com algo.'],
                ['rotulo' => 'Multiplicar ataques', 'texto' => 'Ao sofrer um ataque, multiplique o número de ataques por sua ameaça. Essa chance gasta 2 chances ao invés de uma.'],
                ['rotulo' => 'Multiplicar peso', 'texto' => 'O mestre escolhe entre você ou um ser ou objeto no alcance. Este ser, seja quem for, sofre sua ameaça +1 vezes 15 em desvantagem para testes de destreza enquanto estiver no alcance. Caso seja um objeto, carregá-lo custa um teste de atletismo (D60 -25 por ameaça).'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Multiplicar dano', 'texto' => 'Você, ou um aliado no alcance, tem dano sofrido por um ataque dobrado. Essa chance custa três chances ao invés do comum.'],
                ['rotulo' => 'Multiplicar dificuldade', 'texto' => 'Multiplicando peso, ou distância, os demônios dificultam a execução de uma ação sua ou de um alvo no alcance em valor igual a sua ameaça +1 vezes 15. Este valor é aplicado como dificuldade extra para o teste ou como vantagem para o teste opositor.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maquinismo' => [
        'nome' => 'Maquinismo',
        'raridade' => 'Exótica',
        'descricao' =>
            "Na obtenção da maldição, um processo absurdamente doloroso, agonizante e enlouquecedor lhe ocorre. Ele substitui seus ossos por engrenagens, seu cérebro por um sistema e seus sentimentos por nada.\n" .
            "Não tem mais sentimentos, não pensa mais como ser humano, é indiferente e isolado de tudo que é natural e mundano.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Máquina', 'texto' => 'Sendo uma máquina, você não precisa mais comer, beber, descansar ou respirar. Ainda envelhece (representado por seus sistemas enferrujando, quebrando e desgastando), mas é imune às seguintes condições: Sangrando, Atordoado, Ofuscado, Enjoado, Afogando-se.'],
                ['rotulo' => 'Sem sanidade e aura', 'texto' => 'Você não possui mais pontos de sanidade ou aura. Qualquer talento, técnica ou gasto de algum destes atributos vitais é inacessível para você.'],
                ['rotulo' => 'Sem experiência', 'texto' => 'Você não recebe mais experiência mundana, experiência de arte ou experiência de maldição. Para adquirir talentos e recursos análogos a artes marciais e técnicas, deve usar um Aprimoramento.'],
                ['rotulo' => 'Aprimoramento', 'texto' => 'Para ser aprimorado, é necessário um teste de construção de um terceiro. A dificuldade é (D60 -15 por aprimoramento já aplicado), e requisita 2 (+1 por aprimoramento já aplicado) horas, 1 material de construção adequado e nível 2 (+1 por aprimoramento aplicado) em construtor.'],
                ['rotulo' => 'Conserto', 'texto' => 'Você não recupera PVs de nenhuma forma comum. Ao invés disso, você deve ser consertado com um material de construção adequado, 1 hora e um teste de construção (D50 -5 por aprimoramento).'],
                ['rotulo' => 'Requisitos', 'texto' => 'Ao invés de requisitos de nível de maldição, os requisitos são equivalentes, mas em número de aprimoramentos necessários.'],
                ['rotulo' => 'Outras maldições', 'texto' => 'Se você tiver outras maldições, você não usa aura para manipulá-las, e sim, usos por dia. Toda técnica sua tem 1 uso diário por aprimoramento seu em Maquinismo. Você não pode escolher aprimoramentos extras, apenas usar aprimoramentos grátis. Você recebe aprimoramentos grátis iguais a seus aprimoramentos de maquinismo em suas outras maldições.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'sem-identidade' => [
        'nome' => 'Sem identidade',
        'raridade' => 'Rara',
        'descricao' => 'Com esta maldição, você não tem mais um corpo individual. Sua forma física constantemente alterna entre diferentes corpos e aparências. O que retira de você sua identidade, capacidade de desenvolver habilidades próprias e lhe força a viver a pele de outras pessoas.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Ficha assumida', 'texto' => 'Assim que você adquire essa maldição, você adquire e assume a ficha completa de algum ser da mesma raça que você e que você conheça e já tenha tido ao menos 3 cenas de diálogo. Você assume a ficha completa contando com maldição, habilidades, atributos e talentos, mas mantém sua personalidade e aspectos narrativos.'],
                ['rotulo' => 'Troca de identidade', 'texto' => 'Todo início de cena, há uma chance de 10% (+15% por ameaça) de você trocar e se transformar em outra pessoa aleatória decidida pelo mestre. Você mantém a perda de qualquer atributo vital perdido ou gasto em um ser anterior.'],
                ['rotulo' => 'Transformações possíveis', 'texto' => 'Os seres disponíveis para transformação devem ser anotados pelo nome em uma aba chamada Transformações possíveis.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'ancestralismo' => [
        'nome' => 'Ancestralismo',
        'raridade' => 'Exótica',
        'descricao' => 'Essa maldição resgata a consciência e memórias de ancestrais do portador. Ancestrais que possuíam maldições são lembrados por espíritos diabólicos que aplicam a consciência deles a do portador. Sendo assim, caso eles desaprovem uma ação sua, podem causar sua derrocada.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Ancestrais', 'texto' => 'O mestre cria e determina o nome, personalidade e maldição de 4 (+1 por ameaça) ancestrais seus, além de uma ficha técnica contendo pontos de habilidade, atributos vitais, etc. A cada ação sua, há um julgamento deles, e caso algum deles desaprove, ele pode utilizar alguma técnica da maldição determinada pelo mestre em você.'],
                ['rotulo' => 'Maldição dos ancestrais', 'texto' => 'O nível de maldição de um ancestral é igual a sua ameaça vezes 3. A ameaça de maldição de um ancestral é igual a sua ameaça.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'mil-tendoes' => [
        'nome' => 'Mil tendões',
        'raridade' => 'Comum',
        'descricao' => 'Seu corpo é assolado com uma força física descomunal gerada por aura Daemonica. Seus músculos, ossos, tendões e órgãos recebem resistência parcial para resistir a essa força, contanto que não a use ao extremo. Caso use, é fácil que essa força descomunal acabe com seu corpo; mas não é só isso, a sua força vai afastá-lo de tudo que é real. Um aperto de mão pode destruir seu parceiro. Conexão física é totalmente impossível e o descontrole total de sua força pode causar problemas letais.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Força descontrolada', 'texto' => 'Você possui uma força e resistência física sobre-humana, mas seu corpo não tem controle ou adaptação completa dessa força.'],
                ['rotulo' => 'Teste de maldição', 'texto' => 'Sempre que você faz uma ação física, deve testar maldição (D50 -ameaça +1 vezes 10). Se falhar, causa sua ameaça +2 dobrada em dano de impacto irredutível ao objeto que usa para a ação, ou para o alvo dela. Exemplo: apertar a mão de alguém e falhar causa a ela o dano.'],
                ['rotulo' => 'Bônus', 'texto' => 'Você recebe sua ameaça +1 vezes 20 em testes de força, constituição e destreza.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Deslocamento excessivo', 'texto' => 'Sempre que se deslocar, o mestre pode gastar uma chance das quais ele possui em valor igual a sua ameaça +1 por cena. Se fizer, você se move o dobro da distância planejada e sofre o dano da ameaça 2.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Itens quebram', 'texto' => 'Sempre que fizer uma ação física e falhar no teste de maldição, itens que você esteja usando para a ação quebram instantaneamente. Além disso, você sofre sua ameaça dobrada em dano de impacto irredutível.'],
                ['rotulo' => 'Enjoado', 'texto' => 'Seu intestino é constantemente forte e contraído, isso lhe causa dificuldade para ir ao banheiro. Você está constantemente enjoado.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Osso quebrado', 'texto' => 'Quando você falhar em testes de maldição por 30 ou mais, você recebe a sequela de osso quebrado.'],
                ['rotulo' => 'Exausto', 'texto' => 'Seus órgãos são apertados por forte tensão, você está constantemente exausto.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Perda de membro', 'texto' => 'Quando você falhar no teste de maldição por 50 ou mais, receberá a sequela de perder um membro.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'hiper-sensacao' => [
        'nome' => 'Hiper sensação',
        'raridade' => 'Comum',
        'descricao' => 'Seus sentidos são absurdamente aguçados. Você sente dor como nunca antes, cada leve baque ou passo fornece uma dor imensurável. É necessário que se dope constantemente de remédios se quiser sequer sobreviver sem loucura a essa maldição. Luzes moderadas, sons altos, cheiros fortes podem facilmente lhe enjoar ou nocautear.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Dor', 'texto' => 'Você tem sentidos hiper aguçados. Toda vez que sofrer dano, você sofre dano de sanidade em valor igual.'],
                ['rotulo' => 'Reflexos', 'texto' => 'Você recebe ações de reflexo extras iguais a sua ameaça +1. Seus mods de percepção e destreza aumentam em valor igual a sua ameaça +1.'],
                ['rotulo' => 'Vantagem', 'texto' => 'Você tem uma vantagem em testes de destreza e percepção igual a +1 (+ameaça). Você pode trocar esta vantagem por -15 na rolagem em questão.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Ações de combate', 'texto' => 'Sempre que você fizer uma ação de combate sofre sua ameaça em pontos de dano de sanidade.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Audição aguçada', 'texto' => 'Sua audição é tão aguçada que você ouve muito mais do que deveria, até dormindo. Qualquer som pode o acordar ou impedir que durma. Você sempre descansa metade das horas normais.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Qualquer ação', 'texto' => 'Sempre que fizer uma ação qualquer, sofre sua ameaça em dano de sanidade.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'mundo-lento' => [
        'nome' => 'Mundo Lento',
        'raridade' => 'Rara',
        'descricao' => 'Enquanto sua mente funciona em velocidade normal, seus sentidos vêem o mundo como se ele fosse mais lento. Isso o força a pensar mais, a existir mais, a sofrer mais quando se machuca, o enlouquece e estressa pela impaciência de viver sempre em um mundo mais devagar.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Mundo mais lento', 'texto' => 'Você vê o mundo duas vezes mais devagar. Pessoas falando, agindo, sons, até mesmo os movimentos do seu próprio corpo.'],
                ['rotulo' => 'Dilatação temporal', 'texto' => 'Você tem um medidor de Dilatação temporal que começa em 2 e aumenta em +2 por ameaça.'],
                ['rotulo' => 'Custo em sanidade', 'texto' => 'Você perde seu medidor de Dilatação temporal em pontos de sanidade por cena de conversa, dano recebido ou cena em que você tiver alguma condição negativa.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'fala-profana' => [
        'nome' => 'Fala profana',
        'raridade' => 'Incomum',
        'descricao' => 'Aura Daemonica se impregna nas suas cordas vocais e língua. Provocando uma reação amaldiçoada toda vez que fala a combinação correta (ou errada) de palavras e sons.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Palavras amaldiçoadas', 'texto' => 'Sempre que você disser uma palavra que se encaixe em uma técnica dessa maldição, ela se ativa gastando sua aura, ações e todos os custos necessários, e os alvos são todos os alvos adequados no alcance.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maldicao-do-homem-velho' => [
        'nome' => 'Maldição do Homem velho',
        'raridade' => 'Rara',
        'descricao' =>
            "A aura Daemonica que infectou seu corpo o envelhece de forma absurdamente mais rápida do que o normal. Os portadores dessa maldição geralmente vivem metade, um quarto ou até um quinto da vida comum humana. E em casos extremos, portadores de 20 anos atingem os 60 alguns meses depois. É diverso o possível efeito que essa maldição pode ter em um amaldiçoado. Praticamente todos os portadores são obrigados a se tornar hexomantes e aprender a reversão, para tentar, dessa forma, manter suas vidas. Hexomantes mais poderosos conseguem viver pelo tempo que quiserem, contanto que seu único problema seja velhice. Mas aqueles que não conseguem…\n" .
            "Essa maldição tem taxa de mortalidade de praticamente 100% quando bebês ou crianças são amaldiçoados, visto que para eles é muito difícil aprender a reversão a tempo, já que suas mentes não envelhecem junto com o corpo.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Envelhecimento', 'texto' => 'Você envelhece 2 vezes mais rápido do que o comum.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Envelhecimento em área', 'texto' => 'Seres num raio de metros iguais a sua ameaça +10 envelhecem 1 ano todo início de cena. Incluindo plantas, que começam a crescer conforme você está próximo.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Envelhecimento acelerado', 'texto' => 'Você envelhece 3 vezes mais rápido do que o comum.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'mil-demonios' => [
        'nome' => 'Mil Demônios',
        'raridade' => 'Exótica',
        'descricao' =>
            "A maldição dos mil demônios invoca levas de demônios e Kuros sanguinários de tempos em tempos para lhe caçar e tirar sua vida.\n" .
            "Portadores dessa maldição comumente se isolam em centros da AMA, ou cometem suicídio para que não venham a ferir entes queridos. As criaturas surgem e atacam tudo e todos, forçando caos e morte.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Surgimento de Kuros', 'texto' => 'Todo início de cena, há a chance de surgir 1 Kuro de tipo igual a sua ameaça +1. Os círculos Kuroticos deles aumentam em valor igual a sua ameaça vezes 3.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'mau-trabalho' => [
        'nome' => 'Mau trabalho',
        'raridade' => 'Comum',
        'descricao' => 'Sua aura Daemonica atrai espíritos que estão sempre tramando sua derrocada e morte. Eventualmente, estes espíritos forçarão as maiores inconveniências para matar você e aqueles próximos a você.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Sorte travada', 'texto' => 'Sua sorte tem valor travado em 10 e não pode aumentar de jeito nenhum.'],
                ['rotulo' => 'Chances do mestre', 'texto' => 'A cada cena, o mestre possui 1 (+ameaça) chances. Essas chances podem ser usadas para causar inconveniências forçadas. Cada chance pode realizar efeitos narrativos de péssima sorte, ou algum efeito mecânico a seguir.'],
                ['rotulo' => '1 — Dano aumentado', 'texto' => 'Aumento de 1/2 de algum dano causado a você ou a algum aliado em cena.'],
                ['rotulo' => '2 — Dano reduzido', 'texto' => 'Redução de algum dano causado por você ou um aliado em cena em 1/2.'],
                ['rotulo' => '3 — Desvantagem', 'texto' => 'Desvantagem em algum teste feito por você ou aliado em cena.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => '4 — Ação cancelada', 'texto' => 'Cancelamento e perda de alguma ação sua ou de um aliado em cena (exceto a principal).'],
                ['rotulo' => '5 — Aura dobrada', 'texto' => 'Dobro de gasto de aura em alguma técnica ou talento seu ou de um aliado em cena.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => '6 — Inimigo resiste', 'texto' => 'Um inimigo que receberia Morrendo ou qualquer outra condição negativa simplesmente não a recebe.'],
                ['rotulo' => '7 — Morrendo', 'texto' => 'Um aliado ou você próprio entram em morrendo caso um ataque os tenha deixado com menos do que 1/4 dos PVs.'],
                ['rotulo' => '8 — Acidente', 'texto' => 'Algum acidente ocorrerá, causando seu nível de maldição vezes sua ameaça em dano de tipo a critério do mestre irredutível a você ou a um aliado. O alvo pode testar Reação para negar.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maldicao-do-pesar' => [
        'nome' => 'Maldição do Pesar',
        'raridade' => 'Comum',
        'descricao' => 'Suas dores físicas e psicológicas são sempre passadas para aqueles próximos de você. Feridas são transferidas para as suas pessoas amadas. Sua dor psicológica é sentida por todos ao seu redor.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Dano de sanidade fantasma', 'texto' => 'Sempre que você receber dano de sanidade, ele fica guardado em um valor fantasma. Em algum momento, o mestre pode repassar este dano para aliados seus em cena na quantia e forma que quiser. Este dano é irredutível.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Dano físico fantasma', 'texto' => 'Ao receber dano físico, ele fica guardado em um valor fantasma. Em algum momento, o mestre pode repassar este dano para aliados seus em cena na quantia e forma que quiser. Este dano é irredutível. Se o mestre repassar o dano, você recupera a quantidade perdida em PVs.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Fora da cena', 'texto' => 'Danos recebidos por você podem ser passados para aliados que não estejam na mesma cena.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Morte compartilhada', 'texto' => 'Quando você morre, o mestre pode matar algum aliado seu dentro ou fora da cena, contanto que este aliado dê consentimento.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'aura-de-medo' => [
        'nome' => 'Aura de Medo',
        'raridade' => 'Incomum',
        'descricao' =>
            "A aura Daemonica que impregna seu corpo cria ao seu redor um sentimento geral de medo, desconforto e ameaça.\n" .
            "Portadores dessa maldição não conseguem relações genuinamente amigáveis, não conseguem relações românticas saudáveis, são rejeitados em qualquer convívio social ou ambiente com pessoas mentalmente frágeis. Geralmente recorrem ao isolamento severo ou suicídio.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Carisma reduzido', 'texto' => 'Você tem Carisma reduzido a 10, assim como modificador de carisma reduzido a 1. Você tem desvantagem igual a sua ameaça +1 vezes 15 em testes de carisma.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Medo em área', 'texto' => 'Todo ser que comece a cena ou entre no alcance depois, sendo o alcance um raio de metros igual a sua ameaça vezes 3 de você e que tiver sanidade inferior a seu nível de maldição vezes 5 fica amedrontado até o fim da cena. A condição de Amedrontado aplica desvantagens iguais a sua ameaça aos alvos afetados.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Dano de sanidade', 'texto' => 'No efeito de ameaça 2, você causa dano de sanidade igual a sua ameaça dobrada a seres que sejam afetados.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Medo à distância', 'texto' => 'Só de te ver, ouvir, sentir seu cheiro, ver sua aura, uma foto, pintura, ou qualquer captura sua, o ser em questão sofre os efeitos da maldição.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    // ======================================================================
    // NOVAS MALDIÇÕES — colar dentro do array de config/maldicoes.php,
    // logo depois do item 'aura-de-medo' e antes do "];" final.
    // (Maldição do Justiceiro já estava cadastrada, por isso não repete aqui.)
    // ======================================================================

    'clones' => [
        'nome' => 'Clones',
        'raridade' => 'Exótica',
        'descricao' => 'E se existisse mais de um de você? Como você vai namorar um parceiro que outro de você também ama e também deseja exclusividade? Como vai atender a um serviço que só vai pagar um de você, mas que tem incontáveis iguais almejando? Clones seus, com seus objetivos, pensamentos e sonhos pode ser mais complicado do que parece.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Surgimento de clones', 'texto' => 'Uma vez por dia, há uma chance de 20% (+15% por ameaça) de surgir um clone seu. Seus clones possuem exatamente a mesma ficha que você tinha na hora do surgimento, tanto ficha física como mental, astral e psicológica. Seus clones não possuem a maldição Clones e não podem gerar novos clones através deles. Eles rolam outra maldição aleatória ao invés.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maldicao-do-som' => [
        'nome' => 'Maldição do Som',
        'raridade' => 'Comum',
        'descricao' => 'Todo som que você ouve ou produz é absurdamente intensificado. Seus passos são altíssimos, e você os ouve de maneira absurda. É bem provável que fique surdo ou enlouqueça com as batidas incessantes e escandalosas do seu coração. É impossível tentar ser discreto com uma maldição assim.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Sons intensificados', 'texto' => 'Todos os sons que você produz têm volume intensificado ao extremo. Batimentos cardíacos são audíveis ao se aproximar de você, seus passos podem ser escutados a metros de distância, e um grito pode ensurdecer e destruir o seu corpo e o de seres a quilômetros. Você perde qualquer ponto em furtividade ou vantagem que receba, e ao invés disso rola com desvantagem de +20 (+sua ameaça vezes 15).'],
                ['rotulo' => 'Dano de sanidade', 'texto' => 'Toda vez que você fizer uma ação, todo ser com audição num raio igual a sua ameaça +3 vezes 10 (este é seu alcance comum, outras técnicas citam isso) sofre sua ameaça +5 em dano de sanidade. Um ser que perca mais do que 10 pontos de sanidade dessa forma fica surdo de um ouvido; se perder 20, fica surdo de ambos.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Som perturbador', 'texto' => 'Seu corpo produz sons tão altos que desagradam e perturbam até mesmo a você próprio. Todo início de cena, você perde sua ameaça em sanidade.'],
                ['rotulo' => 'Descanso reduzido', 'texto' => 'Você descansa apenas metade do tempo que normalmente descansaria.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Quebra de materiais', 'texto' => 'Toda vez que fizer uma ação, andar ou falar, todos os objetos e estruturas de vidro, gesso ou aparelhos eletrônicos com estes materiais que estejam no alcance da sua ameaça vezes 10 se quebram e se tornam inúteis. Você também aplica os efeitos de dano de sanidade da ameaça 0 ao fazer essas coisas.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'audiencia' => [
        'nome' => 'Audiência',
        'raridade' => 'Rara',
        'descricao' => 'Sua aura Daemonica reduz as propriedades físicas e aura do seu corpo conforme você não é assistido ou psicologicamente seguido por seres racionais. Ser assistido ou seguido por seres aumenta sua aura, e portanto suas características físicas. Mas caso não seja, sua aura enfraquece e pode causar sua morte. Portadores dessa maldição praticamente não podem ficar sozinhos e isolados, devem estar sempre observados ou vistos por alguém.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Perda de aura', 'texto' => 'Para cada hora em que tiver menos do que 1 (+ameaça vezes 10) ser(es) racional(ais) lhe vendo (em pessoa ou por mídias), você perde 10 em aura total. Você recupera a aura perdida dessa forma assim que cumprir os requisitos de visualização.'],
                ['rotulo' => 'Bônus por espectador', 'texto' => 'A quantidade de seres racionais lhe vendo aumenta em +1 sua aura temporária até que deixem de lhe ver. Para cada ser lhe vendo, você também recebe os seguintes bônus: +1 em todas as habilidades; +0,5 em dano amaldiçoado irredutível aplicado a todas as ações de dano; +0,5 PVs temporários; +0,5 armadura natural; +0,5 deslocamento.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'perfeccionismo' => [
        'nome' => 'Perfeccionismo',
        'raridade' => 'Rara',
        'descricao' =>
            "Sua aura Daemonica é repleta de espíritos juízes que julgam suas ações, técnica e conhecimentos, e permitem que você conclua qualquer ação somente se ela for perfeita a seus critérios.\n" .
            "Perfeccionistas raramente conseguem uma vida de trabalho ou ações complexas comuns. São paralisados de agir sempre que não conseguem realizar algo logicamente perfeito.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Somente o perfeito', 'texto' => 'Toda vez que fizer uma ação que requisite algum teste, você rola os dados primeiro, e só consegue gastar os recursos, ações e concluir os efeitos se o resultado for crítico; caso contrário, você os gasta sem ter feito ação alguma.'],
                ['rotulo' => 'Margem de crítico', 'texto' => 'Em compensação, você recebe 20 (+20 por ameaça/+5 por nível de maldição) em margem de crítico para distribuir em até 3 (+ameaça) habilidades a sua escolha.'],
                ['rotulo' => 'Ataques', 'texto' => 'Ao desferir ataques, todos os ataques que não tenham ação de Mirar direcionada a Cabeça ou Pescoço são cancelados, mas você gasta os recursos mesmo assim.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maos-daemonicas' => [
        'nome' => 'Mãos Daemonicas',
        'raridade' => 'Exótica',
        'descricao' => 'As mãos do portador carregam carga de aura Daemonica intensa e constante, que infecta tudo o que toca. Objetos, árvores e superfícies tocadas diretamente tornam-se amaldiçoadas, perigosas e voláteis. Pessoas e animais tocados não demoram muito a se tornarem Kuros demoníacos.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Toque amaldiçoado', 'texto' => 'Em um alcance igual a sua ameaça, seres que você toque por uma ação ou que comecem seus turnos dentro do alcance têm uma chance de 10% (+10% por ameaça) de receber uma marca de maldição, e sempre que receberem, algo abaixo acontece:'],
                ['marca' => '1', 'rotulo' => 'Resultado 1', 'texto' => 'Receber uma maldição rolada aleatoriamente, caso não tenha uma maldição, e receber uma marca de maldição.'],
                ['marca' => '2', 'rotulo' => 'Resultado 2', 'texto' => 'Aumentar permanentemente a ameaça da própria maldição em +1 e receber uma marca de maldição, caso já possuam alguma maldição.'],
                ['marca' => '3', 'rotulo' => 'Resultado 3', 'texto' => 'Se o alvo for um objeto, ele recebe alguma modificação amaldiçoada aleatória.'],
                ['rotulo' => 'Marcas de maldição', 'texto' => 'Conceitualmente, são marcas de tensão espiritual que deturpam as capacidades hexomânticas do alvo ao intensificar a carga amaldiçoada dele, aumentando sua ameaça, amaldiçoando-o e deturpando suas capacidades.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'assimilacao-empatica' => [
        'nome' => 'Assimilação Empática',
        'raridade' => 'Comum',
        'descricao' =>
            "A aura Daemonica impregnada no corpo do portador absorve elementos, dores e propriedades do ambiente e as transfere diretamente ao portador.\n" .
            "Viver em ambientes de guerra, dor física, dor psicológica, estresse é praticamente impossível, e portadores dessa maldição geralmente recorrem ao suicídio ou isolamento severo.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Sanidade compartilhada', 'texto' => 'Sempre que um ser na cena receber dano de sanidade ou recuperar sanidade, você recebe ou perde 1/2 do valor.'],
                ['rotulo' => 'Kuros irracionais', 'texto' => 'A cada turno que você começar enxergando um Kuro irracional, você recebe o tipo dele +5 em dano de sanidade.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Dor alheia', 'texto' => 'Pela primeira vez que você começar o turno a ao menos 3 metros de um ser que foi ferido na mesma cena, você recebe sua ameaça em dano amaldiçoado irredutível. Só se ativa uma vez por ser ferido na cena. Funciona inversamente para cura de PVs com seres curados na mesma cena e dentro do alcance.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Alvo compartilhado', 'texto' => 'Quando um ser na cena é alvo de um ataque, técnica ou efeito e você está a pelo menos 3 metros do ocorrido, você também se torna alvo da ação.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Assimilar maldição', 'texto' => 'Se você compartilhar 3 cenas de diálogo com um ser amaldiçoado, há uma chance de 30% (+10% por ameaça) de você adquirir por 24 horas a maldição deste ser. Você só pode ter uma maldição adquirida dessa forma por vez.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'dependencia-animal' => [
        'nome' => 'Dependência Animal',
        'raridade' => 'Rara',
        'descricao' =>
            "Sua aura própria é suficiente somente para suas ações mais básicas e funcionamento comum do seu organismo. Sem aura própria para ações complexas, você depende da aura de animais e pode manipulá-la para o seu corpo. Mas sem animais ou plantas ao seu redor, você é inútil.\n" .
            "Em situações de perigo despreparado, é muito fácil que portadores desta maldição estejam em perigo exacerbado. Além de que atividades mais complexas do dia a dia são impossíveis sem o acompanhamento e preparo necessários.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Aura zerada', 'texto' => 'Você tem aura atual e total 0, e esse valor não pode aumentar nunca.'],
                ['rotulo' => 'Aura emprestada', 'texto' => 'Você pode gastar a aura de animais irracionais ou plantas num raio de 3 metros para talentos e outros gastos. Animais ou plantas de tamanho minúsculo possuem 10 em aura para você, e essa aura dobra por categoria de tamanho.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'parasitismo' => [
        'nome' => 'Parasitismo',
        'raridade' => 'Rara',
        'descricao' =>
            "Seu corpo real é uma casca disfuncional cujos órgãos não conseguem sustentar vida por muito tempo. Isso lhe impõe um contador que premedita uma morte inevitável e rápida. Você é obrigado a transferir sua alma e consciência para habitar corpos mortos e manter-se vivo.\n" .
            "Usuários dessa maldição dificilmente são vistos com bons olhos. É um forte causador de aversão saber que quem está do outro lado da mesa já habitou corpos que não lhe pertenciam para sobreviver.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Contador de morte', 'texto' => 'No seu corpo atual e em cada corpo que habitar, há um contador de 365 dias (-70 por ameaça). Se este contador chegar a 0, você morre.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'atracao-terrosa' => [
        'nome' => 'Atração Terrosa',
        'raridade' => 'Rara',
        'descricao' => 'A aura Daemonica que envolve seu corpo atrai terra, rochas, concreto e derivados da terra e do chão. Seus pés acumulam terra, por vezes ficam presos ao chão, estalactites e pedaços de teto voam na sua direção, balas e lâminas são atraídas na sua direção.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Atração de projéteis', 'texto' => 'Lâminas, projéteis e ataques feitos com itens metálicos ou de pedra são atraídos para você, impondo desvantagem obrigatória na reação e causando +1 dado de dano (+1 por ameaça).'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Pés pesados', 'texto' => 'Seus pés atraem a terra que está próxima a eles, dificultando sua movimentação. Você tem deslocamento reduzido em valor igual ao dobro da sua ameaça.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Detritos voadores', 'texto' => 'Pedaços soltos e fracos de construções, chão e casas se desprendem e voam em sua direção. Toda vez que falhar em uma ação, um pedaço de terra voa na sua direção e causa sua ameaça em dano de impacto irredutível.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Sem armas de terra', 'texto' => 'Você não consegue mais usar armas e objetos feitos de metal, rochas ou elementos que venham da terra, pois eles sempre são disfuncionalmente atraídos a você. Além disso, caso se aproxime de uma arma corpo a corpo em até 3 metros, ela é atraída na sua direção e você sofre um ataque dela que deve reagir com dificuldade igual a (D50 -30 por ameaça).'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'paradoxo' => [
        'nome' => 'Paradoxo',
        'raridade' => 'Impossível',
        'descricao' => 'Seu corpo, mente e alma estão constantemente oscilando entre períodos temporais diferentes. Em uma conversa, você pode ouvir a pessoa falando algo que disse a segundos atrás ou que ainda não havia dito. Ou, em níveis mais graves, pode simplesmente fazer seu corpo se mover até o passado da situação atual, ou ganhar de volta alguma ferida antiga. Em níveis graves você se torna um andarilho temporal sem casa ou rumo, e sua presença viaja indefinidamente por linhas temporais e períodos diferentes. Você pode ir parar no passado ou futuro de uma linha temporal totalmente diferente por acidente, quem sabe?',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Eventos', 'texto' => 'Há uma chance de 10% (+15% por ameaça) a cada início de cena de que você sofra um dos efeitos a seguir. Essa possibilidade se chama Evento, e pode ser ativada como consequência de outras técnicas.'],
                ['rotulo' => 'Ferida paradoxal', 'texto' => 'Você recebe uma ferida antiga novamente, fazendo ela se abrir no seu corpo como se fosse nova. Um dano que você já sofreu nessa cena é causado novamente a você.'],
                ['rotulo' => 'Ida ao futuro', 'texto' => 'Você desaparece da cena e só volta em 2d6 vezes ameaça minutos.'],
                ['rotulo' => 'Ida ao passado', 'texto' => 'Você volta no tempo para algum momento a critério do mestre no passado, mas também desaparece da cena e só volta em 2d6 vezes ameaça minutos.'],
                ['rotulo' => 'Retorno', 'texto' => 'Tanto para idas ao futuro como ao passado, você volta com dano de sanidade igual a sua ameaça +2 e vantagem em testes de conhecimento até o fim da cena.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Eventos por turno', 'texto' => 'Agora eventos têm chance de acontecer todo início de turno em combate, tendo as seguintes possibilidades:'],
                ['rotulo' => 'Ataque cancelado', 'texto' => 'Seu próximo ataque rebobina no tempo antes de atingir o alvo. Seu próximo ataque vai ser cancelado, mas você ainda gasta ações e aura.'],
                ['rotulo' => 'Ataque retornado', 'texto' => 'Um ataque que você já se esquivou parte em sua direção de novo. Sofra um ataque de algum inimigo a critério do mestre.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Novos eventos por cena', 'texto' => 'Nas tentativas de Eventos por início de cena, adicione as seguintes possibilidades:'],
                ['rotulo' => 'Envelhecimento', 'texto' => 'Você recebe o porte físico idoso até o fim da cena.'],
                ['rotulo' => 'Rejuvenescimento', 'texto' => 'Você recebe o porte físico infantil até o fim da cena.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Ida ao futuro (turnos)', 'texto' => 'Nas tentativas de Eventos por turno de combate, adicione a seguinte possibilidade: você some da cena e só volta após sua ameaça em turnos (contando turnos alheios).'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Enlentecer', 'texto' => 'Nas tentativas de Eventos por combate, adicione a seguinte possibilidade: você enlentece no tempo e começa a agir mais devagar, recebendo desvantagens iguais a sua ameaça em qualquer teste que faça no turno. Você não recebe mais dano de queda. Danos contínuos só são ativos a cada 1 rodada (+1 por ameaça).'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Alternância temporal', 'texto' => 'Nas tentativas de Eventos por cena, adicione a seguinte possibilidade: você vai para uma outra linha do tempo no passado ou futuro, abandonando a sua atual. O momento e a linha temporal em que você vai estar são decididos a critério do mestre. Em termos de jogo, se essa chance se concretizar, você morre.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    '5-chamas' => [
        'nome' => '5 Chamas',
        'raridade' => 'Comum',
        'descricao' => 'Seus 5 órgãos vitais principais estão constantemente gerando calor frequente e escalável. Eventualmente, se nada fizer, você queima internamente até morrer. Seus passos queimam o chão, sua respiração é quente como uma onda de calor, tocar água a esquenta e evapora quase instantaneamente. Contato físico com outras pessoas é quase impossível. Febre é constante, seu corpo não se acostuma. Portadores dessa maldição geralmente não sobrevivem a mais do que 5 anos com ela. Possui uma versão inversa chamada Corpo Frio.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Fome e sede', 'texto' => 'Você sente fome e sede em velocidade multiplicada pela sua ameaça +1, já que gerar calor custa energia e calorias.'],
                ['rotulo' => 'Toque flamejante', 'texto' => 'Itens e seres que você toque por 4 (-2 por ameaça) turnos e ações bônus sofrem sua ameaça +1 vezes 5 em dano flamejante irredutível.'],
                ['rotulo' => 'Medidor de Calor', 'texto' => 'Você tem um medidor de Calor que começa sempre em 0 e aumenta em +1 (+ameaça dobrada) por cena. Suas técnicas de 5 Chamas usam pontos de calor, mas você pode sempre trocar um ponto de calor por 10 em aura no custo.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Calor excessivo', 'texto' => 'Calor excessivo tira seu conforto e traz dor constante, suor e a impossibilidade de ficar parado confortavelmente. Você não pode descansar se não estiver submerso em uma banheira de gelo que deve ser recarregada a cada hora para que não evapore e acabe. Você perde sua ameaça vezes 3 em sanidade todos os dias.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Miragens', 'texto' => 'Miragens e distorção no ar acontecem passivamente ao seu redor. Testes de enxergar relacionados a você ou a qualquer elemento numa área igual a sua ameaça vezes 5 têm desvantagem.'],
                ['rotulo' => 'Febre', 'texto' => 'Todo início de cena, há uma chance de 5% (+10% por ameaça) de você adquirir a doença mundana Febre em progressão igual a sua ameaça vezes 5.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Alimentos queimam', 'texto' => 'Alimentos e água não congelados queimam e tornam-se podres se você tocá-los diretamente e comer normalmente. Você se torna incapaz de beber e comer água e comida não congelada.'],
                ['rotulo' => 'Dejetos escaldantes', 'texto' => 'Seu suor, sangue e outros dejetos corporais se tornam ferventes e escaldantes.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Efeito em área', 'texto' => 'Todos os seres num raio de metros igual a sua ameaça vezes 5 sofrem os mesmos efeitos negativos de maldição que você.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Calor triplicado', 'texto' => 'Seu medidor de calor sobe com o triplo da quantia comum.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'corpo-frio' => [
        'nome' => 'Corpo Frio',
        'raridade' => 'Comum',
        'descricao' => 'Versão inversa da maldição 5 Chamas: todo dano Flamejante é substituído por dano Congelante, e tudo o que citar marcadores de calor cita marcadores de frio. Seu corpo utiliza da sua energia corporal para retirar o calor de si mesmo, efetivamente matando-o. Seu corpo, sangue e ossos se tornam frios. Você desenvolve doenças, tem uma saúde e imunidade frágil e sua mente está sempre perturbada pelo constante e irreparável frio que o assola.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Fome', 'texto' => 'Você sente fome em velocidade multiplicada pela sua ameaça +1, já que gerar calor custa energia e calorias.'],
                ['rotulo' => 'Toque congelante', 'texto' => 'Itens e seres que você toque por 4 (-2 por ameaça) turnos e ações bônus sofrem sua ameaça +1 vezes 5 em dano Congelante irredutível.'],
                ['rotulo' => 'Medidor de Frio', 'texto' => 'Você tem um medidor de Frio que começa sempre em 0 e aumenta em +1 (+ameaça dobrada) por cena. Suas técnicas de Corpo Frio usam pontos de frio, mas você pode sempre trocar um ponto de frio por 10 em aura no custo.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Frio excessivo', 'texto' => 'Frio excessivo tira seu conforto e traz dor constante, calafrios e a impossibilidade de existir sem coberturas e aquecedores. Você não pode descansar se não estiver submerso em uma banheira de água fervente que deve ser recarregada a cada hora para que não congele e se torne inútil. Você perde sua ameaça vezes 3 em sanidade todos os dias.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Hipotermia', 'texto' => 'Todo início de cena, há uma chance de 5% (+10% por ameaça) de você adquirir a doença mundana Hipotermia em progressão igual a sua ameaça vezes 5.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Alimentos congelam', 'texto' => 'Alimentos e água congelam e tornam-se podres se você tocá-los diretamente e comer normalmente. Você se torna incapaz de beber e comer água e comida não fervente.'],
                ['rotulo' => 'Dejetos congelantes', 'texto' => 'Seu suor, sangue e outros dejetos corporais se tornam congelantes.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Efeito em área', 'texto' => 'Todos os seres num raio de metros igual a sua ameaça vezes 5 sofrem os mesmos efeitos negativos de maldição que você.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Frio triplicado', 'texto' => 'Seu medidor de frio sobe com o triplo da quantia comum.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'vampirismo' => [
        'nome' => 'Vampirismo',
        'raridade' => 'Rara',
        'descricao' => 'Pele pálida, presas afiadas, aspecto mortificado. Vampirismo o transforma na caricatura da figura mitológica do Vampiro. O Sol queima sua pele e o transforma em pó. Você não pode entrar em lugares sem permissão. Alho e estacas de madeira são capazes de lhe matar.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Luz do sol', 'texto' => 'Se começar um turno em contato direto com a luz do sol, você sofre sua ameaça +1 triplicada em dano flamejante irredutível. Além disso, está pegando fogo. Você nunca pode ganhar imunidade a dano flamejante, se não por técnicas dessa maldição.'],
                ['rotulo' => 'Estacas e alho', 'texto' => 'Ataques feitos com estacas de madeira ou com armas imbuídas em alho causam dano irredutível e sempre crítico.'],
                ['rotulo' => 'Alimentação', 'texto' => 'Você não pode comer alimentos comuns ou beber água. Só pode se alimentar de sangue. Você tem um medidor de sangue que começa em 0.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Permissão para entrar', 'texto' => 'Você sempre deve ter permissão de alguma autoridade relacionada a um lugar privado para que nele possa entrar.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Aspecto morto', 'texto' => 'Sua pele recebe um aspecto morto, cinzento e frio. Você recebe desvantagem absoluta em qualquer teste de carisma. Aumente em +1 desvantagem nas ameaças 3, 4 e 5.'],
                ['rotulo' => 'Sem reflexo', 'texto' => 'Você não pode aparecer em espelhos, fotos, filmagens ou reflexos gerais.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Sol letal', 'texto' => 'O dano causado pela luz do sol em você é sempre crítico.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'inversao' => [
        'nome' => 'Inversão',
        'raridade' => 'Exótica',
        'descricao' => 'O inverso assola seu corpo e mente. Você pode acabar fazendo algo inverso ao que queria originalmente, falar invertidamente, respirar inversamente e morrer, inverter seus batimentos cardíacos e muitos outros.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Chances do mestre', 'texto' => 'O mestre possui um número de chances igual a sua ameaça +3 por dia. Para cada ação ou situação em que você esteja, o mestre pode decidir gastar uma chance para aplicar um dos efeitos a seguir.'],
                ['rotulo' => 'Ação inversa', 'texto' => 'Você vai fazer o oposto narrativo do que você faria originalmente. Exemplo: atacar um inimigo? Não, atacar um aliado. Ação de primeiros socorros? Não, atacar o paciente. Andar para frente? Não, andar para trás.'],
                ['rotulo' => 'Respiração inversa', 'texto' => 'Você espira ao invés de inspirar, perdendo o fôlego e engasgando na ação. Você cancela o que estava fazendo e perde sua ameaça +1 vezes 10 em aura.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Gravidade invertida', 'texto' => 'Nas chances ativas, o mestre também pode escolher esta opção: você ascende aos céus subitamente em metros iguais a sua ameaça vezes 10 e cai em seguida, sofrendo dano de queda adequado.'],
                ['rotulo' => 'Equipamento invertido', 'texto' => 'O mestre seleciona algum ser na cena e você troca de equipamento e roupas com ele.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Posições invertidas', 'texto' => 'Nas chances ativas, o mestre também pode escolher esta opção: você troca de lugar com algum ser na cena.'],
                ['rotulo' => 'Inversão mecânica', 'texto' => 'O mestre inverte os valores de X pares de habilidades suas, um par para cada ameaça.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Batimentos inversos', 'texto' => 'Nas chances ativas, o mestre também pode escolher esta opção: seu coração bate inversamente uma vez, reunindo o sangue ao invés de espalhá-lo. Você testa resiliência (D50 -ameaça vezes 20); se falhar, você morre. Esse efeito gasta 5 chances.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'iluminacao' => [
        'nome' => 'Iluminação',
        'raridade' => 'Impossível',
        'descricao' => 'Olhos dourados e brilhantes como o sol são o que caracterizam os portadores dessa maldição. A Iluminação é um estado de constante compreensão de verdades absolutas e incontestáveis sobre tudo. Os iluminados olham para qualquer coisa e são encharcados com toneladas de informações sobre cada mínimo detalhe da composição e existência daquele elemento. Não é para tanto que iluminados enlouqueçam em menos de meses portando a maldição. O único jeito de inibi-la é com uso de drogas alucinógenas extremas que inibam pensamento claro ou cegando os próprios olhos. Pois se não, as informações e verdades sobrecarregam a mente, causam AVCs e loucura.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Verdades absolutas', 'texto' => 'Sempre que ver algo, seu cérebro o bombardeia de informações sobre este algo. Você pode fazer qualquer pergunta ao mestre sobre qualquer coisa que consiga ver e ele deve lhe responder honestamente.'],
                ['rotulo' => 'Sobrecarga mental', 'texto' => 'Sempre que precisar tomar uma ação, você deve testar raciocínio (D60 -30 por ameaça). Se falhar, perde sua ameaça em sanidade total. Se falhar por 50 ou mais, cancela a ação também.'],
                ['rotulo' => 'Sanidade amplificada', 'texto' => 'Toda vez que sofrer dano de sanidade, aumente-o em valor igual a sua ameaça +2.'],
                ['rotulo' => 'Inacessível', 'texto' => 'Qualquer técnica ou conceito primitivo da Iluminação é inacessível se você está: Amedrontado, Confuso, Cego (ou de olhos fechados) ou Provocado.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'atracao-eletrica' => [
        'nome' => 'Atração Elétrica',
        'raridade' => 'Comum',
        'descricao' => 'Essa maldição faz com que seu corpo atraia eletricidade passivamente. Se aproximar de uma tomada, aparelho eletrônico, celular ou fio de energia pode simplesmente lhe matar.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Fontes elétricas', 'texto' => 'Fontes elétricas têm um nível de tensão que vai de 1 a 5. Tocá-las diretamente o força a tomar o nível de tensão vezes 3 em dano eletrizante irredutível. Itens de borracha ou toque indireto anulam essa penalidade. Usar qualquer item metálico dobra qualquer dano eletrizante sofrido.'],
                ['rotulo' => 'Sem resistência', 'texto' => 'Você não pode receber imunidade ou resistência a dano eletrizante de qualquer forma que não venha dessa maldição.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Proximidade', 'texto' => 'Se aproximar a um raio de metros iguais a sua ameaça vezes 3 de uma fonte elétrica aplica os efeitos de dano da mesma.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Relâmpagos', 'texto' => 'Se estiver chovendo e você estiver em um lugar aberto, há uma chance igual a sua ameaça vezes 15 de você se tornar alvo de um relâmpago. Para reagir a ele, considere (D-700). O dano que ele causa é igual a sua ameaça vezes 10 em dano eletrizante irredutível.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Tensão aumentada', 'texto' => 'Aumente todos os níveis de tensão em +1.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Tensão e raios', 'texto' => 'Aumente todos os níveis de tensão em +3 ao invés do aumento original. Todo início de rodada em um local aberto, mesmo sem chuva, você tem chance de ser atingido por raios.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Descargas aleatórias', 'texto' => 'Descargas aleatórias de eletricidade podem ser disparadas através de elétrons no ar de uma área quilométrica, formando um relâmpago espontâneo na sua direção. Você sempre passa pela chance de ser atingido por um raio; estar em lugares fechados não lhe salva. Aumente todos os níveis de tensão em +8 ao invés dos aumentos comuns.'],
            ]],
            ['nivel' => 6, 'efeitos' => [
                ['rotulo' => 'Raios constantes', 'texto' => 'Não há mais chance de ser atingido por raios; na verdade, você SEMPRE conclui essa chance, e está sempre sendo atingido por raios.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'imortalidade' => [
        'nome' => 'Imortalidade',
        'raridade' => 'Impossível',
        'descricao' => 'Seu corpo é amaldiçoado por forças que o preservam incondicionalmente a fim de que sempre continue existindo. A mente humana não foi feita para existir por mais tempo do que o organismo comum sobrevive. A sanidade se deteriora ao ver a perda de entes queridos e se afasta da própria humanidade. As relações sociais são difíceis quando não se compartilha do único destino comum: a morte. A grande maioria dos detentores dessa maldição cometem suicídio antes de evoluí-la, pelo medo de passar do limite e fadar-se à existência eterna. O corpo humano começa a evoluir ainda vivo, e se tornar algo que não deveria. Em trezentos anos você já nem parece mais um ser humano, em mil anos você já nem é mais da mesma espécie.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Sem desgaste', 'texto' => 'Você não sofre mais penalidades por idade (apesar de sua aparência física ainda envelhecer), não sente mais fome, sede e cansaço.'],
                ['rotulo' => 'Perda de sanidade', 'texto' => 'Para cada ano acima de 100 no qual você estiver vivo, você perde 2 pontos de sanidade total.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Imunidades', 'texto' => 'Você se torna imune às condições: Ofuscado, Cego, Atordoado, Enjoado, Congelando. E não precisa mais de sangue para viver, por isso se torna imune a Sangrando. Você também não precisa mais respirar, portanto é imune a Afogando-se.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Sem Morrendo', 'texto' => 'Você não entra mais em Morrendo. Pontos de vida negativos não lhe dão penalidade alguma e você permanece consciente como normalmente. Você é imune a ferida grave e ferida recente.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Retorno da morte', 'texto' => 'Sempre que você morrer, se seu corpo não for totalmente destruído, você volta à vida após 1d100 +50 anos com todos os atributos vitais cheios e ficha idêntica à época da morte. Todos os seus membros perdidos são regenerados e você perde todas as suas sequelas permanentes.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Retorno rápido', 'texto' => 'Ao morrer, você retorna após apenas 1d10 meses.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Retorno imediato', 'texto' => 'Ao morrer, você volta no dia seguinte.'],
            ]],
            ['nivel' => 6, 'efeitos' => [
                ['rotulo' => 'Corpo destruído', 'texto' => 'Mesmo que seu corpo seja totalmente destruído, você sempre vai voltar à vida.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'curso-forcado' => [
        'nome' => 'Curso Forçado',
        'raridade' => 'Incomum',
        'descricao' =>
            "Tudo o que pode ser feito pelo seu corpo é definido por um curso. O curso de seu caminhar, de seus braços, pernas, boca, voz. Até mesmo o curso de suas mãos conforme arremessa algo ou move uma espada ou faca.\n" .
            "Esta maldição define os cursos possíveis com linhas (visíveis só para o amaldiçoado e quase transparentes) de cor amarela. Elas se estendem de seus membros até possíveis alvos de encontro, como algo que você possa socar, ou um caminho a ser traçado.\n" .
            "A questão é que a maldição faz com que você sempre siga o pior curso possível para o que você quer. Se você quer andar para um lado, vai andar para o outro. Se quer cortar um legume, vai usar a faca do pior jeito possível. Se quer atirar, vai acertar o mais longe que puder.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Habilidade falha', 'texto' => 'Todo início de cena, o mestre decide uma (+1 por ameaça) habilidade. Todos os seus testes nela falham automaticamente. O mestre deve escolher a habilidade mais inconveniente para você.'],
                ['rotulo' => 'Deslocamento forçado', 'texto' => 'O mestre tem um contador de Chances que começa em 2 por cena e aumenta equivalente a sua ameaça. Toda vez que você se deslocar, o mestre pode forçar uma chance e fazer você se deslocar para onde ele quiser usando o seu deslocamento e ação de movimento.'],
                ['rotulo' => 'Curso de itens', 'texto' => 'Sempre que você disparar, arremessar ou desequipar um item, o curso dele é manipulado. Ele tem acerto automático, mas no alvo que o mestre decidir, sendo o pior alvo possível no alcance de seu arremesso ou disparo.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Teletransporte forçado', 'texto' => 'Sempre que você se deslocar, há uma chance igual a 20% (+10% por ameaça) de que você seja teletransportado para algum ponto a escolha do mestre, num alcance igual a sua ameaça vezes 5. O mestre deve escolher o pior ponto possível para sua situação atual.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Falha crítica', 'texto' => 'Todo teste que você falhar em decorrência desta maldição, você falha com falha crítica automática.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'demonios-do-inferno' => [
        'nome' => 'Demônios do inferno',
        'raridade' => 'Exótica',
        'descricao' =>
            "Um demônio miserável habita seu corpo. Ele compartilha sua consciência e pensamentos mais íntimos com você, como se fossem um só. O objetivo dele é tomar o controle de seu corpo, roubá-lo de você. Ele está sempre tentando forçá-lo a dar controle, perturbá-lo ou seja o que for que precisar para tomar o controle.\n" .
            "Portadores dessa maldição geralmente buscam isolamento ou suicídio para evitar que o demônio consiga afetar a vida de seus amigos, família, e deturpar a memória que têm sobre eles.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'O demônio', 'texto' => 'Você possui um demônio que consegue falar na sua mente, ouvir e ver tudo que você conseguir. Ele tem uma ficha de Kuro irracional feita pelo mestre a critério dele, considerando tipo igual a 4 (ou 5, em ameaça 3) e círculos iguais ao dobro deste valor.'],
                ['rotulo' => 'Tomada de controle', 'texto' => 'Uma vez por cena, o demônio pode testar maldição vs resiliência; se ele suceder, controla o seu próximo turno. Toda vez que seu demônio te controlar, ele usa a sua ficha física, mas a própria ficha mental e astral.'],
                ['rotulo' => 'Vínculo', 'texto' => 'Se você morrer, seu demônio também morre. Seu demônio possui alguma maldição rolada aleatoriamente.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Controle até o fim da cena', 'texto' => 'Se seu demônio tomar controle de você com sucesso, ele controla seu corpo até o fim da cena. Você pode repetir o teste todo início de turno para retomar o controle.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Pactos', 'texto' => 'Seu demônio pode lhe oferecer pactos e diferentes características de acordo com a habilidade sobrenatural Demônio de pacto.'],
                ['rotulo' => 'Voz do demônio', 'texto' => 'Até 3 vezes por cena, ele pode tentar falar usando sua boca; você pode tentar suprimir usando resiliência vs maldição.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Dimensão do sono', 'texto' => 'Sempre que você dorme, sua mente vai para uma dimensão só com você e seu demônio, cuja estética é a critério do mestre. Se você morrer lá, sofre sua ameaça vezes 2 em dano de sanidade. Qualquer aura e recurso que gaste lá é gasto na vida real.'],
            ]],
            ['nivel' => 4, 'efeitos' => [
                ['rotulo' => 'Controle de 24 horas', 'texto' => 'Quando seu demônio tomar controle de você, ele o faz durante 24 horas.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Controle permanente', 'texto' => 'Quando seu demônio tomar controle de você, ele o faz para sempre, ou até que você entre em Morrendo.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'irrestricao' => [
        'nome' => 'Irrestrição',
        'raridade' => 'Rara',
        'descricao' =>
            "O seu corpo excede os seus limites naturais em todas as ações que você exerce. Sempre será obrigado a usar o máximo de sua capacidade quando puder. Este exercer constante e progressivamente maior força seu corpo a evoluir e se adaptar a grandes gastos de energia e a necessidade extrema de recuperá-la.\n" .
            "Pessoas com essa maldição geralmente sofrem acidentes por usar mais força do que deveriam em tarefas simples. Ou são ineficazes em batalha por usarem muita aura em ataques não promissores ou antes da hora certa. Em geral, possuem expectativas de vida consideravelmente menores.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Talentos mundanos', 'texto' => 'Para talentos mundanos que permitam qualquer gasto de aura, você sempre deve usar toda a aura que puder. Porém, aumente o valor de aura considerado em valor igual a sua ameaça +1 vezes 10. Exemplo: ao gastar 100 em aura em um golpe assinatura, considere ter gasto 110 caso sua ameaça seja 1.'],
                ['rotulo' => 'Aprimoramentos', 'texto' => 'Quando conjurar alguma técnica, exceto reversão de maldição e técnicas que a requisitam, você deve usar todos os aprimoramentos que puder. Porém, aumente-os em valor igual a sua ameaça +1.'],
                ['rotulo' => 'Contador de Irrestrição', 'texto' => 'Toda vez que você zerar ou negativar sua aura, receba um Contador de Irrestrição. Você recebe +10 em aura e fator de cura de aura por turno de +2 por contador de Irrestrição.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Todos os talentos', 'texto' => 'Sempre que fizer uma ação, você deve usar todos os talentos mundanos que puderem interagir com ela. Considere seu nível mundano para escalas destes talentos superior em valor igual a sua ameaça +1.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'tolice' => [
        'nome' => 'Tolice',
        'raridade' => 'Comum',
        'descricao' => 'Essa maldição lhe acomete com uma gigantesca quantidade de lentidão mental, curta memória e pouca capacidade de raciocínio lógico. Por si só existem várias implicações disso na vida do portador da maldição. Porém, vai muito além. Até mesmo sua capacidade hexomântica e capacidade de combate é reduzida por essa maldição.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Mente lenta', 'texto' => 'Seu valor de conhecimento é travado em 10, e seu BE é travado em 1. Você tem desvantagem igual a sua ameaça +1 vezes 15 em qualquer teste de conhecimento, percepção ou reações que faça.'],
            ]],
            ['nivel' => 2, 'efeitos' => [
                ['rotulo' => 'Esquecimento', 'texto' => 'Você se esquece, ao fim da cena, de qualquer coisa que nela ocorreu. Aplique as desvantagens de ameaça 0 a qualquer teste que faça.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'intensificacao-emocional' => [
        'nome' => 'Intensificação emocional',
        'raridade' => 'Incomum',
        'descricao' => 'Um brusco aumento na intensidade emocional faz com que tudo que você sinta seja levado ao extremo. Se você ficar triste, fica extremamente triste. Se ficar feliz, fica extremamente feliz. Pessoas com essa maldição raramente têm vidas fáceis, e muito comumente acabam em suicídio. A vida tende a dar, principalmente para amaldiçoados, muito mais momentos ruins do que bons para desfrutar.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Sanidade dobrada', 'texto' => 'Todo dano ou recuperação de sanidade que você receber é dobrado. Efeitos diferentes lhe assolam a depender da condição negativa ou positiva que tiver:'],
                ['rotulo' => 'Amedrontado', 'texto' => 'Você recebe sua ameaça +2 em aprimoramentos grátis.'],
                ['rotulo' => 'Entusiasmado', 'texto' => 'Você perde sua ameaça +2 aprimoramentos para toda técnica que não tiver mitigação na lógica; se tiver, você recebe os aprimoramentos ao invés.'],
                ['rotulo' => 'Provocado', 'texto' => 'Você recebe sua ameaça +1 vezes 15 em vantagem para testes contra quem está te provocando, mas o mesmo valor em desvantagem contra todo o resto.'],
                ['rotulo' => 'Confuso', 'texto' => 'Suas ações são controladas pelo mestre.'],
                ['rotulo' => 'Enjoado', 'texto' => 'Toda ação que fizer tem uma chance igual a sua ameaça +1 vezes 15 de ser cancelada, pois você começa a vomitar.'],
                ['rotulo' => 'Ferida grave', 'texto' => 'O desespero lhe acomete tremendamente, gerando um surto de adrenalina. Você perde as desvantagens para habilidades físicas e ao invés disso recebe-as como vantagem, mas as dobra para habilidades mentais e astrais.'],
                ['rotulo' => 'Condições emocionais', 'texto' => 'As condições citadas acima são usadas em textos dessa maldição como Condições emocionais.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'distorcao-sensorial' => [
        'nome' => 'Distorção sensorial',
        'raridade' => 'Incomum',
        'descricao' => 'Você é acometido ocasionalmente a distorções em seus sentidos, mudando a forma como você enxerga, ouve e sente. Em algum momento, você pode ver coisas que não existem; em outro, pode ouvir uma visão, ou ver um cheiro ou áudio. O que lhe acomete com paranoia, loucura, dificuldades em interações sociais e dificuldade em trabalhos comuns.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Sentidos distorcidos', 'texto' => 'A cada início de cena, o mestre possui sua ameaça +1 chances de mudar algo que você escuta, vê ou sente para algo que lhe atrapalharia, como mudar a fala de alguém ou dizer que você viu algo que não existe de verdade. Toda vez que isso acontecer, se você perceber que é mentira, com um teste de detectar mentiras (D30 -40 por ameaça), você recebe sua ameaça +2 em dano de sanidade.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maldicao-da-permanencia' => [
        'nome' => 'Maldição da Permanência',
        'raridade' => 'Rara',
        'descricao' =>
            "Uma maldição que torna permanente e durável tudo aquilo que assola seu portador. Feridas duram muito mais do que o normal, e não podem ser curadas normalmente. Doenças, tristeza, raiva, dor, tudo é permanente.\n" .
            "Portadores dessa maldição têm baixíssima expectativa de vida, visto que geralmente morrem na primeira doença ou ferida grave que receberem.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Tudo é permanente', 'texto' => 'Todo dano que você receber é afetado por feridas duradouras. Todo efeito que lhe afetar e que tiver duração de no mínimo 1 turno lhe afeta eternamente. Quando você recebe alguma condição negativa ou positiva, ela dura eternamente (exceto Morrendo, Inconsciente ou Ferida grave).'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Perdas irrecuperáveis', 'texto' => 'Dano de sanidade e perda de aura que você sofrer, de qualquer fonte, não podem ser recuperados.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'corpo-transmutado' => [
        'nome' => 'Corpo Transmutado',
        'raridade' => 'Exótica',
        'descricao' => 'A maldição transforma o seu corpo, alterando sua biologia e composição corporal de maneira totalmente discrepante da humana. Crescer antenas ao invés de olhos, patas ao invés de mãos, asas ao invés de braços, respirar somente na água. Portadores desta maldição estão fadados a nunca serem verdadeiramente humanos.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Transformações', 'texto' => 'A cada nova ameaça, você recebe uma nova característica de Transformação escolhida pelo mestre, dentre as seguintes:'],
                ['rotulo' => 'Antenas', 'texto' => 'Você não tem olhos, tem apenas antenas. Funcionam efetivamente da mesma forma, lhe fornecendo vantagem em testes de percepção e a capacidade de enxergar no escuro. Porém, você se torna incapaz de ver cores, ler ou discernir detalhes em superfícies e elementos visíveis.'],
                ['rotulo' => 'Asas', 'texto' => 'Você possui asas, porém elas substituem seus braços. Você não é capaz de equipar itens com as mãos. Porém, recebe deslocamento de voo igual a sua ameaça +3 vezes 3.'],
                ['rotulo' => 'Patas', 'texto' => 'Ao invés de mãos e pés, você possui patas ou cascos. Você é incapaz de equipar itens com as mãos. Porém, aumente seu dano corpo a corpo desarmado em +3 dados e +3 passos (+2 por ameaça) e seu deslocamento em valor igual a sua ameaça +1 vezes 4.'],
                ['rotulo' => 'Guelras', 'texto' => 'Você sofre da condição Afogando-se quando não está submerso em água. Em contrapartida, não sofre da mesma condição quando está de fato submerso. Negue as penalidades de deslocamento e quaisquer outras por estar submerso. Ter ao menos a cabeça submersa em água conta como estar submerso.'],
                ['rotulo' => 'Espinhos ósseos', 'texto' => 'Seus ossos possuem espinhos externos. Eles ferem seu corpo em movimentos descuidados e bruscos, porém podem ser usados como arma. Eles aumentam seu dano de ataques corpo a corpo desarmado em +2 passos. Porém, sempre que falhar num teste físico, sofre sua ameaça +1 em dano perfurante irredutível.'],
                ['rotulo' => 'Cauda', 'texto' => 'Você tem um rabo. Em outros animais, ele facilita o equilíbrio, mas a estrutura humana não foi feita para este tipo de componente. Você tem desvantagem em testes de destreza, porém vantagem em testes de constituição.'],
                ['rotulo' => 'Olhos de lente', 'texto' => 'Como moscas, seus olhos se dividem em várias sub-ramificações. Você tem desvantagem em testes de carisma. Porém, vantagem em testes de percepção.'],
                ['rotulo' => 'Escamas', 'texto' => 'Sua pele possui escamas, o que lhe ajuda a perder menos água e, logo, se cansar menos. Você precisa receber a condição Exausto duas vezes na mesma cena para ser afetado por ela. Porém, tem desvantagem em testes de carisma.'],
                ['rotulo' => 'Corpo frágil', 'texto' => 'Seu corpo é mais sensível e frágil, porém isso aumenta seu instinto de sobrevivência. Você recebe desvantagem em testes de constituição, porém vantagem em testes de percepção e reação.'],
                ['rotulo' => 'Tamanho avantajado', 'texto' => 'Aumente sua categoria de tamanho em +1. Este fator pode afetar você mais de uma vez.'],
                ['rotulo' => 'Tamanho reduzido', 'texto' => 'Diminua sua categoria de tamanho em -1. Este fator pode afetar você mais de uma vez.'],
                ['rotulo' => 'Fatores positivos', 'texto' => 'Os fatores acima são negativos de transformação corporal. Técnicas desta maldição permitem que você consiga fatores positivos, como os listados a seguir.'],
                ['rotulo' => 'Coração extra', 'texto' => 'Outro coração bombeia seu sangue de forma mais veloz, aumentando sua resistência e energia corporal. Seu mod de constituição aumenta em +2. Você recebe -15 em vantagem para testes de constituição e força, e sua aura total aumenta em +1/3. Você pode escolher este fator várias vezes.'],
                ['rotulo' => 'Cérebro extra', 'texto' => 'Você recebe um novo cérebro com capacidade de raciocínio que comporta sua consciência. Seu mod de conhecimento aumenta em +2. Você recebe vantagem em testes de conhecimento de -15.'],
                ['rotulo' => 'Músculos compactos', 'texto' => 'Seus músculos são compactados e otimizados. Você recebe mod de força +2, recebendo vantagem de -15 em testes de força. Seu limite de carregamento aumenta em +1/3. Você pode escolher este fator várias vezes.'],
                ['rotulo' => 'Corpo gelatinoso', 'texto' => 'Seu corpo é um estado que não é nem líquido e nem sólido, como uma massa de pão. Você recebe resistência a dano de impacto e cura sempre +1/2 adicional em qualquer fonte de cura. Porém, recebe fraqueza a dano cortante e seu dano desarmado é reduzido à metade.'],
                ['rotulo' => 'Corpo líquido', 'texto' => 'Seu corpo se torna líquido como água. Você se torna incapaz de portar ou equipar itens. Seu dano por ações físicas é sempre reduzido a 0. Você não pode realizar manobras. Porém, você se torna imune a dano físico que não seja flamejante, amaldiçoado ou eletrizante. Seu deslocamento é dobrado.'],
                ['rotulo' => 'Sangue adoecedor', 'texto' => 'Quando adquirir a condição Sangrando, seres num raio de metros igual a sua ameaça +2 recebem a condição Enjoado.'],
                ['rotulo' => 'Pulmões hipertrofiados', 'texto' => 'Seus pulmões são fortes e incansáveis, muito mais densos e dilatáveis do que os de um humano comum. Você tem um fator de regeneração de 5 (+1 por nível de maldição) em aura por turno.'],
                ['rotulo' => 'Sistema nervoso ampliado', 'texto' => 'Sistema nervoso e receptor ampliado: você recebe a maldição Hiper sensação.'],
                ['rotulo' => 'Sangue regenerativo', 'texto' => 'Você recebe fator de cura de PVs iguais ao seu nível de maldição pela metade.'],
                ['rotulo' => 'Armas naturais', 'texto' => 'Você recebe uma arma mundana a sua escolha. Você não precisa sacá-la, pois ela está com você sempre. Ela recebe seu nível de maldição pela metade em dados de dano extras nesta arma.'],
                ['rotulo' => 'Pele grossa', 'texto' => 'Você recebe +4 em armadura natural. Você pode escolher este fator várias vezes.'],
                ['rotulo' => 'Adaptação', 'texto' => 'Você recebe resistência a algum tipo de dano a sua escolha. Você pode escolher este fator várias vezes, até um máximo de três.'],
                ['rotulo' => 'Parasita', 'texto' => 'Você recebe a maldição Parasitismo.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'demonizacao' => [
        'nome' => 'Demonização',
        'raridade' => 'Comum',
        'descricao' =>
            "Espíritos de Agares transformam seu corpo e mente no conceito socialmente aceito de demônio. Sua pele se torna alguma variação de vermelho, cinza ou branco. Você recebe chifres, rabo pontudo, pode assumir a figura de animais e seres distorcidos.\n" .
            "Sua figura, mesmo que você seja bom, é repelida pelo bem. Você queima em cruzes, igrejas, hospitais e lugares que fazem o bem. E é recompensado e compelido a fazer o mal.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Carisma limitado', 'texto' => 'Seu carisma é limitado a 10.'],
                ['rotulo' => 'Lugares do bem', 'texto' => 'Você recebe sua ameaça +2 em dano amaldiçoado irredutível a cada turno que começar em alguma dessas construções: hospitais, igrejas, creches, centros de caridade. Ou imediatamente quando fizer uma ação puramente boa e benéfica para outros seres que não você, sem que tenha previamente qualquer recompensa atrelada a fazê-la.'],
                ['rotulo' => 'Símbolos religiosos', 'texto' => 'Se você estiver a 3 metros ou menos de símbolos religiosos de boa virtude, como cruzes, totens e etc., você também sofre o dano descrito até que se afaste.'],
                ['rotulo' => 'Técnicas bloqueadas', 'texto' => 'Você não pode ativar qualquer técnica desta maldição se estiver cumprindo alguma das condições que lhe infligem dano.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'maldicao-da-vontade' => [
        'nome' => 'Maldição da Vontade',
        'raridade' => 'Comum',
        'descricao' => 'Essa maldição altera a vontade do seu portador, amplificando ou reduzindo emoções que ele nutria a fim de executar ou não executar ações. Podendo forçá-lo a executar certas ações ou impedi-lo de executar o que quer.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Chances do mestre', 'texto' => 'A cada início de cena, o mestre recebe sua ameaça +1 em Chances. Ele pode gastar essas chances quando bem entender para concluir um dos seguintes efeitos.'],
                ['rotulo' => 'Impedimento', 'texto' => 'Ao declarar uma ação, ela é cancelada. Você ainda gasta a ação e quaisquer custos nela envolvidos.'],
                ['rotulo' => 'Forçar', 'texto' => 'O mestre declara uma ação que para você seja possível de ser concluída com apenas uma única ação. Você é obrigado a concluí-la, gastando quaisquer custos relacionados.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Aliados', 'texto' => 'O mestre pode passar a usar Chances com aliados em cena num raio de metros igual a sua ameaça dobrada.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'inercia-infinita' => [
        'nome' => 'Inércia infinita',
        'raridade' => 'Rara',
        'descricao' =>
            "Quando você corre e se movimenta, a resistência do ar e a força da gravidade limitam e deterioram a movimentação do seu corpo, fazendo com que perca força e velocidade. Com essa maldição, isso não acontece mais. Se você começar a andar ou correr, só será parado quando colidir com algo. É impossível perder velocidade com os freios naturais do corpo, e sua energia é constantemente conservada ao extremo.\n" .
            "Portadores dessa maldição geralmente não vivem muito. Correr, pular ou até mesmo dançar podem ser movimentos instáveis e que causam ferimentos graves.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Deslocamento obrigatório', 'texto' => 'Sempre que você fizer uma ação de movimentação, acumula 1/4 do deslocamento exercido como bônus de deslocamento obrigatório. Este bônus obrigatório é uma quantia de metros que você passa a se mover obrigatoriamente na última direção a qual você percorreu. Se colidir com algo ou alguém, sofre dano de queda adequado.'],
                ['rotulo' => 'Salto infinito', 'texto' => 'Sua distância de salto é infinita. Você só para após uma ação de salto se colidir com algo ou alguém, ou se usar Reversão de maldição.'],
                ['rotulo' => 'Desgaste', 'texto' => 'Você recebe sua ameaça (+1/5 metros percorridos) em perda de PVs toda vez que se deslocar metade do seu valor de constituição em metros ou mais.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Acúmulo de 1/3', 'texto' => 'Ao invés de 1/4, você acumula 1/3 do deslocamento exercido.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Acúmulo de 1/2', 'texto' => 'Ao invés de 1/3, você acumula 1/2 do deslocamento exercido.'],
            ]],
            ['nivel' => 5, 'efeitos' => [
                ['rotulo' => 'Acúmulo total', 'texto' => 'Ao invés de 1/2, você acumula todo o deslocamento exercido.'],
            ]],
            ['nivel' => 7, 'efeitos' => [
                ['rotulo' => 'Acúmulo dobrado', 'texto' => 'Ao invés de todo o deslocamento exercido, você acumula o dobro dele.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'cadaver' => [
        'nome' => 'Cadáver',
        'raridade' => 'Rara',
        'descricao' => 'Seu corpo é morto. Você não tem batimentos cardíacos, atividade cerebral ou fluxo sanguíneo. Seu corpo apodrece constantemente e gradativamente passa de cadáver a esqueleto completo. A maldição o mantém funcionando e com seus sentidos básicos, mas seu corpo segue cadavérico e podre.',
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Corpo morto', 'texto' => 'Você é um corpo comum, porém morto. Ainda tem a aparência de um ser vivo comum. Você não sente fome, sede ou cansaço.'],
                ['rotulo' => 'Carisma', 'texto' => 'Você tem desvantagem obrigatória em testes de carisma (+1 desvantagem por ameaça).'],
                ['rotulo' => 'Recuperação', 'texto' => 'Você só é capaz de recuperar atributos vitais se tiver Reversão de maldição ativada.'],
            ]],
            ['nivel' => 1, 'efeitos' => [
                ['rotulo' => 'Músculos expostos', 'texto' => 'Sua pele cai, você agora tem músculos e veias expostas.'],
                ['rotulo' => 'Imunidades', 'texto' => 'Você não sofre penalidades pelas condições: Cego, Surdo, Afogando-se, Enjoado, Sangrando, Exausto, Atordoado, Ofuscado.'],
                ['rotulo' => 'Desvantagens ampliadas', 'texto' => 'As desvantagens obrigatórias também passam a afetar testes de constituição e força.'],
                ['rotulo' => 'Dano e deslocamento', 'texto' => 'Seu dano corpo a corpo ou desarmado é sempre reduzido à metade, assim como seu deslocamento.'],
            ]],
            ['nivel' => 3, 'efeitos' => [
                ['rotulo' => 'Apenas ossos', 'texto' => 'Seus músculos caem. Você agora tem somente ossos.'],
                ['rotulo' => 'Perda de membro', 'texto' => 'A cada início de cena ou dano recebido, há uma chance de 20% (+8% por ameaça) de que você receba a sequela permanente de perder um membro.'],
                ['rotulo' => 'Ataque atravessa', 'texto' => 'Ao receber um ataque, há uma chance igual a sua ameaça vezes 10 (máximo de 70%) de o ataque passar por dentre seus ossos, sendo negado.'],
            ]],
        ],
        'tecnicas' => [],
    ],

    'anti-sobrenaturalismo' => [
        'nome' => 'Anti-sobrenaturalismo',
        'raridade' => 'Incomum',
        'descricao' =>
            "Sendo o sobrenatural a força corrosiva e adoecedora que é, é controverso pensar que isso seria uma maldição. Entretanto, dadas as conjecturas sociais da terra, ser avesso ao sobrenatural representa mazelas relevantes.\n" .
            "Ainda mais ao notar que sua maldição o torna imune somente àquilo que você não quer. Imagine querer curar sua grave ferida por um aliado Hexomante capaz de curar, mas não poder. Mas na hora de ser alvejado por raios e trovões da atração elétrica, é plenamente possível.",
        'ameacas' => [
            ['nivel' => 0, 'efeitos' => [
                ['rotulo' => 'Benefícios cancelados', 'texto' => 'Qualquer efeito sobrenatural (fenômenos, técnicas Hexomantes, efeitos de itens e elixires) que fosse lhe fazer bem (aumentar valores positivamente, curar valores, ou beneficiá-lo indiretamente, como voar para uma direção) é cancelado.'],
                ['rotulo' => 'Prejuízos mantidos', 'texto' => 'Se um efeito fosse lhe prejudicar, ele ainda ocorre normalmente.'],
            ]],
        ],
        'tecnicas' => [],
    ],

];