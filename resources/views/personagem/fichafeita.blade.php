@extends('layout')

@section('conteudo')
@php
    $fisicas = [
        'luta' => 'Luta', 'resiliencia' => 'Resiliência', 'acrobacia' => 'Acrobacia',
        'atletismo' => 'Atletismo', 'agarrar' => 'Agarrar', 'arremesso' => 'Arremesso',
        'desviar' => 'Desviar', 'contra_ataque' => 'Contra ataque', 'bloquear' => 'Bloquear',
        'iniciativa' => 'Iniciativa', 'furtividade' => 'Furtividade', 'enxergar' => 'Enxergar',
        'olfato' => 'Olfato', 'audicao' => 'Audição',
    ];
    $intelectuais = [
        'maldicao_hab' => 'Maldição', 'precisao' => 'Precisão', 'investigacao' => 'Investigação',
        'sobrevivencia' => 'Sobrevivência', 'construcao' => 'Construção', 'procura' => 'Procura',
        'intuicao' => 'Intuição', 'tecnologia' => 'Tecnologia', 'religiao' => 'Religião',
        'raciocinio' => 'Raciocínio', 'medicina' => 'Medicina', 'primeiros_socorros' => 'Primeiros socorros',
        'culinaria' => 'Culinária', 'dirigir' => 'Dirigir', 'explosivos' => 'Explosivos',
    ];
    $introspectivas = [
        'fotografia' => 'Fotografia', 'labia' => 'Lábia', 'barganhar' => 'Barganhar',
        'manipulacao' => 'Manipulação', 'intimidar' => 'Intimidar', 'arte' => 'Arte',
        'disfarce' => 'Disfarce', 'performance' => 'Performance', 'detectar_mentira' => 'Detectar mentira',
    ];

    // Aceita array, Collection ou texto JSON (a ficha abre igual para o dono e para o mestre,
    // mesmo quando o model não tem cast e o controller que a renderiza não decodifica).
    $paraLista = function ($valor) {
        if ($valor instanceof \Illuminate\Support\Collection) {
            $valor = $valor->toArray();
        }
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }
        return is_array($valor) ? $valor : [];
    };

    $habilidades = $paraLista($personagem->habilidades ?? []);
    $itens = $paraLista($personagem->itens ?? []);

    $atributos = [
        'forca' => 'Força', 'constituicao' => 'Constituição', 'destreza' => 'Destreza',
        'conhecimento' => 'Conhecimento', 'carisma' => 'Carisma', 'percepcao' => 'Percepção',
        'alma' => 'Alma',
    ];

    // Atributos vitais: Vida, Sanidade e Aura (mesmas colunas usadas no CampanhaController)
    // mais os personalizados, se a ficha tiver. Os personalizados são lidos de forma tolerante:
    // aceita as colunas vitais_extras, vitais_personalizados ou atributos_vitais, com itens
    // {nome, atual, max|maximo|total, cor}.
    $vitais = [
        ['nome' => 'Vida',     'atual' => $personagem->vida_atual ?? null,     'max' => $personagem->vida_maxima ?? null,     'cor' => '#b91c1c'],
        ['nome' => 'Sanidade', 'atual' => $personagem->sanidade_atual ?? null, 'max' => $personagem->sanidade_maxima ?? null, 'cor' => '#22a05a'],
        ['nome' => 'Aura',     'atual' => $personagem->aura_atual ?? null,     'max' => $personagem->aura_maxima ?? null,     'cor' => '#7c5cff'],
    ];

    $vitaisExtras = $paraLista(
        $personagem->vitais_extras
        ?? $personagem->vitais_personalizados
        ?? $personagem->atributos_vitais
        ?? []
    );
    foreach ($vitaisExtras as $extra) {
        if (!is_array($extra) || empty($extra['nome'])) {
            continue;
        }
        $cor = $extra['cor'] ?? '#888888';
        // só aceita cores hexadecimais, para não injetar CSS arbitrário
        if (!is_string($cor) || !preg_match('/^#[0-9a-fA-F]{3,8}$/', $cor)) {
            $cor = '#888888';
        }
        $vitais[] = [
            'nome'  => $extra['nome'],
            'atual' => $extra['atual'] ?? null,
            'max'   => $extra['max'] ?? $extra['maximo'] ?? $extra['total'] ?? null,
            'cor'   => $cor,
        ];
    }

    // Maldições do personagem. O campo "maldicoes" guarda texto no formato:
    //   "Maldição do Justiceiro (Ameaça 2); Outra Maldição (Ameaça 0)"
    // (separadores aceitos: ; , ou quebra de linha). Se o nome bater com uma maldição do catálogo
    // (config/maldicoes.php) ela é exibida completa, liberando as ameaças até o nível escolhido.
    $catalogoMaldicoes = config('maldicoes', []);
    $maldicoesEscolhidas = collect(preg_split('/[;,\n]+/', (string) ($personagem->maldicoes ?? '')))
        ->map(fn ($n) => trim($n))
        ->filter(fn ($n) => $n !== '' && mb_strtolower($n) !== 'nenhuma')
        ->map(function ($entrada) use ($catalogoMaldicoes) {
            $nome = $entrada;
            $ameaca = 0;
            if (preg_match('/^(.*?)\s*\(\s*Amea[çc]a\s*(\d+)\s*\)$/iu', $entrada, $m)) {
                $nome = trim($m[1]);
                $ameaca = (int) $m[2];
            }
            $slug = \Illuminate\Support\Str::slug($nome);
            return [
                'nome' => $nome,
                'slug' => $slug,
                'ameaca' => $ameaca,
                'maldicao' => $catalogoMaldicoes[$slug] ?? null,
            ];
        })
        ->values();
    $maldicoesComFicha = $maldicoesEscolhidas->filter(fn ($m) => $m['maldicao'] !== null)->values();

    // Variáveis opcionais enviadas pela campanha (CampanhaController@verFicha):
    //   $voltarUrl -> para onde o botão "Voltar" leva
    //   $donoNome  -> preenchido quando o mestre está vendo a ficha de outro jogador (somente leitura)
    $voltarUrl = $voltarUrl ?? route('personagens.index');
    $donoNome  = $donoNome ?? null;
@endphp

<style>
    /* Formatação da ficha de leitura (escopada em .ficha-leitura para não afetar outras telas) */
    .ficha-leitura .ficha-header.colapsavel { margin: 1.25rem 0 .75rem; }
    .ficha-leitura .ficha-header.colapsavel h1 { font-size: 1.9rem; margin: 0; }

    /* Barra de ações (voltar / baixar) */
    .ficha-leitura .ficha-acoes {
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem;
    }
    .ficha-leitura .botao-baixar-ficha {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .45rem .9rem; font: inherit; font-size: .9rem; color: inherit;
        text-decoration: none; cursor: pointer;
        background: rgba(255, 255, 255, .04);
        border: 1px solid rgba(189, 3, 3, .55); border-radius: 8px;
    }
    .ficha-leitura .botao-baixar-ficha:hover { background: rgba(189, 3, 3, .2); }

    /* Informações técnicas */
    .ficha-leitura .info-tecnica-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: .75rem;
    }
    .ficha-leitura .info-tecnica-card {
        display: flex; flex-direction: column; gap: .35rem;
        padding: .75rem .9rem;
        background: rgba(255, 255, 255, .03);
        border: 1px solid rgba(189, 3, 3, .35);
        border-radius: 8px;
    }
    .ficha-leitura .info-tecnica-card .rotulo {
        font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; opacity: .7;
    }
    .ficha-leitura .info-tecnica-card .valor { font-size: 1.1rem; }
    .ficha-leitura .maldicao-links { display: flex; flex-wrap: wrap; gap: .4rem; }

    /* Atributos vitais */
    .ficha-leitura .vitais-lista { display: flex; flex-direction: column; gap: 1rem; }
    .ficha-leitura .vital-topo {
        display: flex; justify-content: space-between; align-items: baseline;
        font-size: .85rem; letter-spacing: .06em; text-transform: uppercase; margin-bottom: .3rem;
    }
    .ficha-leitura .vital-valor { font-weight: 700; letter-spacing: 0; }
    .ficha-leitura .vital-trilho {
        height: 12px; border-radius: 999px; overflow: hidden;
        background: rgba(255, 255, 255, .08);
    }
    .ficha-leitura .vital-barra { height: 100%; border-radius: 999px; transition: width .3s; }
    .ficha-leitura .vital-detalhe {
        display: flex; justify-content: center; gap: 1.5rem; margin-top: .5rem; font-size: .9rem;
    }
    .ficha-leitura .vital-detalhe span { opacity: .7; }
    .ficha-leitura .vital-detalhe strong {
        display: inline-block; min-width: 3rem; text-align: center; margin-left: .35rem;
        padding: .25rem .6rem; border-radius: 6px;
        background: rgba(255, 255, 255, .04); border: 1px solid rgba(189, 3, 3, .35);
    }

    /* Atributos */
    .ficha-leitura .atributos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: .75rem;
    }
    .ficha-leitura .atributo-card {
        display: flex; flex-direction: column; align-items: center; gap: .2rem;
        padding: .75rem .5rem;
        background: rgba(255, 255, 255, .03);
        border: 1px solid rgba(189, 3, 3, .35);
        border-radius: 8px; text-align: center;
    }
    .ficha-leitura .atributo-nome { font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; opacity: .8; }
    .ficha-leitura .atributo-valor-display { font-size: 1.6rem; font-weight: 700; }
    .ficha-leitura .atributo-mod-display { font-size: 1rem; }

    /* Habilidades */
    .ficha-leitura .habilidades-categoria-titulo { margin: 1rem 0 .5rem; font-size: 1.1rem; }
    .ficha-leitura .habilidades-grid-leitura {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: .5rem;
    }
    .ficha-leitura .habilidade-linha {
        display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: .6rem;
        padding: .45rem .7rem;
        background: rgba(255, 255, 255, .03);
        border: 1px solid rgba(189, 3, 3, .25);
        border-radius: 6px;
    }
    .ficha-leitura .habilidade-mod { opacity: .65; font-size: .85rem; }
    .ficha-leitura .habilidade-valor { font-weight: 700; min-width: 1.5rem; text-align: right; }

    /* Itens */
    .ficha-leitura .lista-itens { display: flex; flex-direction: column; gap: .6rem; }
    .ficha-leitura .item-mostrar-card {
        padding: .75rem .9rem;
        background: rgba(255, 255, 255, .03);
        border: 1px solid rgba(189, 3, 3, .3);
        border-radius: 8px;
    }
    .ficha-leitura .item-mostrar-topo { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .ficha-leitura .item-mostrar-nome { margin: 0; }
    .ficha-leitura .item-mostrar-texto { margin: .4rem 0 0; }
    .ficha-leitura .descricao-texto { white-space: pre-line; line-height: 1.55; }

    /* Maldições */
    .ficha-leitura .maldicao-escolhidas { display: flex; flex-direction: column; gap: .75rem; }
    .ficha-leitura .maldicao-bloco {
        border: 1px solid rgba(189, 3, 3, .35); border-radius: 8px; padding: .6rem .9rem;
        background: rgba(255, 255, 255, .03);
    }
    .ficha-leitura .maldicao-bloco-titulo {
        cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: .5rem;
    }
    .ficha-leitura .maldicao-bloco-corpo { margin-top: .75rem; display: flex; flex-direction: column; gap: .6rem; }
</style>

<div class="container">
    <div class="card ficha-card ficha-leitura">

        <div class="ficha-acoes">
            <a href="{{ $voltarUrl }}" class="botao-voltar">← Voltar</a>

            {{-- Só o dono baixa a ficha (o mestre vê em somente leitura) --}}
            @unless($donoNome)
                <a href="{{ route('personagens.exportar', $personagem->id) }}" class="botao-baixar-ficha" title="Baixar esta ficha como arquivo">⬇ Baixar ficha</a>
            @endunless
        </div>

        @if($donoNome)
            <p class="ficha-subinfo" style="text-align:center; margin: .5rem 0 0;">
                Ficha de <strong>{{ $donoNome }}</strong> &middot; somente leitura
            </p>
        @endif

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>{{ $personagem->nome }}</h1>
            <p class="ficha-subinfo">
                {{ $personagem->raca ?? 'Raça não definida' }}
                @if($personagem->profissao && $personagem->profissao !== 'Selecione')
                    &middot; {{ $personagem->profissao }}
                @endif
                &middot; {{ $personagem->idade ?? '?' }} anos
            </p>
        </div>

        <hr style="width:100%">

        <div class="ficha-header colapsavel">
            <h1>Informações Técnicas</h1>
        </div>
        <div class="info-tecnica-grid">
            <div class="info-tecnica-card">
                <span class="rotulo">Porte Físico</span>
                <span class="valor">{{ $personagem->porte_fisico ?? '—' }}</span>
            </div>
            <div class="info-tecnica-card">
                <span class="rotulo">Nível Mundano</span>
                <span class="valor">{{ $personagem->nivel_mundano ?? 1 }}</span>
            </div>
            <div class="info-tecnica-card">
                <span class="rotulo">XP Mundana</span>
                <span class="valor">{{ $personagem->experiencia_mundana ?? 0 }}</span>
            </div>
            <div class="info-tecnica-card">
                <span class="rotulo">BE</span>
                <span class="valor">{{ $personagem->be ?? 0 }}</span>
            </div>
            <div class="info-tecnica-card">
                <span class="rotulo">Maldições</span>
                @if($maldicoesEscolhidas->isEmpty())
                    <span class="valor">Nenhuma</span>
                @else
                    <div class="maldicao-links">
                        @foreach($maldicoesEscolhidas as $m)
                            @if($m['maldicao'])
                                <a href="{{ route('maldicoes.show', $m['slug']) }}" class="maldicao-link" title="Ver página completa da maldição">{{ $m['nome'] }} · Ameaça {{ $m['ameaca'] }}</a>
                            @else
                                <span class="maldicao-link sem-ficha">{{ $m['nome'] }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <hr style="width:100%">

        <div class="ficha-header colapsavel">
            <h1>Atributos Vitais</h1>
        </div>
        <div class="vitais-lista">
            @foreach($vitais as $v)
                @php
                    $atual = $v['atual'] !== null ? (int) $v['atual'] : null;
                    $max   = $v['max'] !== null ? (int) $v['max'] : null;
                    $pct   = ($atual !== null && $max !== null && $max > 0)
                        ? max(0, min(100, round($atual / $max * 100)))
                        : 0;
                @endphp
                <div class="vital">
                    <div class="vital-topo">
                        <span>{{ $v['nome'] }}</span>
                        <span class="vital-valor">{{ $atual ?? '—' }} / {{ $max ?? '—' }}</span>
                    </div>
                    <div class="vital-trilho">
                        <div class="vital-barra" style="width: {{ $pct }}%; background: {{ $v['cor'] }};"></div>
                    </div>
                    <div class="vital-detalhe">
                        <span>Atual <strong>{{ $atual ?? '—' }}</strong></span>
                        <span>Máx <strong>{{ $max ?? '—' }}</strong></span>
                    </div>
                </div>
            @endforeach
        </div>

        @if($maldicoesComFicha->isNotEmpty())
            <hr style="width:100%">

            <div class="ficha-header colapsavel">
                <h1>Maldições</h1>
            </div>
            <div class="maldicao-escolhidas">
                @foreach($maldicoesComFicha as $m)
                    @include('partials.maldicao-bloco', [
                        'maldicao' => $m['maldicao'],
                        'slug' => $m['slug'],
                        'ameacaMax' => $m['ameaca'],
                        'aberto' => $maldicoesComFicha->count() === 1,
                    ])
                @endforeach
            </div>
        @endif

        <hr style="width:100%">

        <div class="ficha-header colapsavel">
            <h1>Atributos</h1>
        </div>
        <div class="atributos-grid">
            @foreach($atributos as $chave => $label)
                <div class="atributo-card">
                    <span class="atributo-nome">{{ $label }}</span>
                    <span class="atributo-valor-display">{{ $personagem->$chave ?? 0 }}</span>
                    <span class="atributo-nome">Mod</span>
                    <span class="atributo-mod-display">{{ isset($personagem->$chave) ? intdiv((int) $personagem->$chave, 10) : 0 }}</span>
                </div>
            @endforeach
            <div class="atributo-card">
                <span class="atributo-nome" style="color: rgba(189, 3, 3, 0.658);">Sorte</span>
                <span class="atributo-valor-display">{{ $personagem->sorte ?? '—' }}</span>
            </div>
        </div>

        <hr style="width:100%">

        <div class="ficha-header colapsavel">
            <h1>Habilidades</h1>
        </div>

        @foreach(['Físicas' => $fisicas, 'Intelectuais' => $intelectuais, 'Introspectivas' => $introspectivas] as $categoriaNome => $lista)
            <h3 class="habilidades-categoria-titulo">{{ $categoriaNome }}</h3>
            <div class="habilidades-grid-leitura">
                @foreach($lista as $chave => $label)
                    @php $valor = (int) ($habilidades[$chave] ?? 0); @endphp
                    <div class="habilidade-linha somente-leitura">
                        <span class="habilidade-nome">{{ $label }}</span>
                        <span class="habilidade-mod">{{ $valor >= 0 ? '+' . $valor : $valor }}</span>
                        <span class="habilidade-valor">{{ $valor }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach

        <hr style="width:100%">

        <div class="ficha-header colapsavel">
            <h1>Itens e Ações</h1>
        </div>

        @if(count($itens) > 0)
            <div class="lista-itens">
                @foreach($itens as $item)
                    <div class="item-mostrar-card">
                        <div class="item-mostrar-topo">
                            <h4 class="item-mostrar-nome">{{ $item['nome'] ?? 'Item' }}</h4>
                            @if(!empty($item['categoria']))
                                <span class="personagem-card-tag">{{ $item['categoria'] }}</span>
                            @endif
                        </div>
                        @if(!empty($item['descricao']))
                            <p class="item-mostrar-texto">{{ $item['descricao'] }}</p>
                        @endif
                        @if(!empty($item['modificacoes']))
                            <p class="item-mostrar-texto"><strong>Modificações:</strong> {{ $item['modificacoes'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p class="lista-vazia">Nenhum item registrado.</p>
        @endif

        <hr style="width:100%">

        <div class="ficha-header colapsavel">
            <h1>Descrição</h1>
        </div>
        <p class="descricao-texto">{{ $personagem->descricao ?? 'Sem descrição.' }}</p>

    </div>
</div>
@endsection