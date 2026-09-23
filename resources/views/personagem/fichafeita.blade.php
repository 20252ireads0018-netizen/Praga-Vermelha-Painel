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

    $habilidades = $personagem->habilidades ?? [];
    $itens = $personagem->itens ?? [];

    $atributos = [
        'forca' => 'Força', 'constituicao' => 'Constituição', 'destreza' => 'Destreza',
        'conhecimento' => 'Conhecimento', 'carisma' => 'Carisma', 'percepcao' => 'Percepção',
        'alma' => 'Alma',
    ];
@endphp

<div class="container">
    <div class="card ficha-card">

        <a href="{{ route('personagens.index') }}" class="botao-voltar">← Voltar</a>

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
                <span class="valor">{{ $personagem->maldicoes ?? 'Nenhuma' }}</span>
            </div>
        </div>

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