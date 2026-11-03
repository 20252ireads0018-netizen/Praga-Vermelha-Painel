@extends('layout')

@section('conteudo')
@php
    $paragrafos = preg_split('/\R+/', trim($maldicao['descricao'] ?? ''));
    $ameacas    = $maldicao['ameacas'] ?? [];
    $tecnicas   = $maldicao['tecnicas'] ?? [];
@endphp

<div class="container">
    <div class="card ficha-card maldicao-card">

        <div class="maldicao-navegacao">
            <a href="javascript:history.back()" class="botao-voltar">← Voltar</a>
            <a href="{{ route('maldicoes.index') }}" class="botao-voltar">Todas as maldições</a>
        </div>

        {{-- ========== CABEÇALHO ========== --}}
        <div class="maldicao-header">
            <span class="maldicao-selo">✠</span>
            <h1 class="maldicao-titulo">{{ $maldicao['nome'] }}</h1>
            <div class="maldicao-etiquetas">
                <span class="maldicao-raridade">Raridade {{ $maldicao['raridade'] }}</span>
                <span class="maldicao-etiqueta">Maldição</span>
            </div>
        </div>

        <hr>

        {{-- ========== DESCRIÇÃO ========== --}}
        <section class="maldicao-secao">
            <h2 class="maldicao-secao-titulo">Descrição</h2>
            <div class="maldicao-descricao">
                @foreach($paragrafos as $p)
                    @if(trim($p) !== '')
                        <p>{{ $p }}</p>
                    @endif
                @endforeach
            </div>
        </section>

        <hr>

        {{-- ========== CONCEITOS PRIMITIVOS (AMEAÇAS) ========== --}}
        <section class="maldicao-secao">
            <h2 class="maldicao-secao-titulo">Conceitos Primitivos</h2>

            <div class="ameaca-lista">
                @foreach($ameacas as $ameaca)
                    <article class="ameaca-card">
                        <div class="ameaca-topo">
                            <h3 class="ameaca-nivel">
                                Ameaça {{ $ameaca['nivel'] }}
                            </h3>
                            @if(!empty($ameaca['dado']))
                                <span class="ameaca-dado">{{ $ameaca['dado'] }}</span>
                            @endif
                        </div>

                        <div class="ameaca-corpo">
                            @if(!empty($ameaca['regra']))
                                <p class="ameaca-regra">{!! $ameaca['regra'] !!}</p>
                            @endif

                            @if(!empty($ameaca['efeitos']))
                                <ul class="efeito-lista">
                                    @foreach($ameaca['efeitos'] as $i => $efeito)
                                        <li class="efeito-item">
                                            <span class="efeito-marca">{{ $efeito['marca'] ?? ($i + 1) }}</span>
                                            <div class="efeito-conteudo">
                                                @if(!empty($efeito['rotulo']))
                                                    <p class="efeito-rotulo">{{ $efeito['rotulo'] }}</p>
                                                @endif
                                                <p class="efeito-texto">{{ $efeito['texto'] }}</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if(!empty($ameaca['penalidade']))
                                <div class="ameaca-penalidade">
                                    <span class="ameaca-penalidade-icone"><x-icone n="alerta"/></span>
                                    <div>
                                        <p class="ameaca-penalidade-titulo">Descumprimento</p>
                                        <p>{{ $ameaca['penalidade'] }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <hr>

        {{-- ========== LISTA DE TÉCNICAS ========== --}}
        <section class="maldicao-secao">
            <h2 class="maldicao-secao-titulo">Lista de Técnicas</h2>

            @if(empty($tecnicas))
                <p class="tecnica-vazia">Nenhuma técnica registrada para esta maldição.</p>
            @else
                <div class="tecnica-grid">
                    @foreach($tecnicas as $tecnica)
                        @php
                            // "efeitos" pode ser string simples ou array [texto, lista, nota]
                            $ef = $tecnica['efeitos'] ?? null;
                            if(is_string($ef)){
                                $ef = ['texto' => $ef];
                            }
                            $aprimoramentos = $tecnica['aprimoramentos'] ?? [];
                        @endphp

                        <article class="tecnica-card">
                            <div class="tecnica-topo">
                                <h3 class="tecnica-nome">{{ $tecnica['nome'] }}</h3>
                            </div>

                            @if(!empty($tecnica['custo']) || !empty($tecnica['tipo']))
                                <div class="tecnica-tags">
                                    @if(!empty($tecnica['tipo']))
                                        <span class="tecnica-tag">{{ $tecnica['tipo'] }}</span>
                                    @endif
                                    @if(!empty($tecnica['custo']))
                                        <span class="tecnica-tag custo">Custo: {{ $tecnica['custo'] }}</span>
                                    @endif
                                </div>
                            @endif

                            @if(!empty($tecnica['descricao']))
                                <p class="tecnica-resumo">{{ $tecnica['descricao'] }}</p>
                            @endif

                            <dl class="tecnica-dados">
                                @if(!empty($tecnica['nivel']))
                                    <div class="tecnica-dado">
                                        <dt>Nível de maldição requisitado</dt>
                                        <dd>{{ $tecnica['nivel'] }}</dd>
                                    </div>
                                @endif
                                @if(!empty($tecnica['materiais']))
                                    <div class="tecnica-dado">
                                        <dt>Materiais</dt>
                                        <dd>{{ $tecnica['materiais'] }}</dd>
                                    </div>
                                @endif
                                @if(!empty($tecnica['logica']))
                                    <div class="tecnica-dado largo">
                                        <dt>Lógica de obtenção</dt>
                                        <dd>{{ $tecnica['logica'] }}</dd>
                                    </div>
                                @endif
                                @if(!empty($tecnica['processo']))
                                    <div class="tecnica-dado largo">
                                        <dt>Processo</dt>
                                        <dd>{{ $tecnica['processo'] }}</dd>
                                    </div>
                                @endif
                            </dl>

                            @if(!empty($ef))
                                <div>
                                    <h4 class="tecnica-subtitulo">Efeitos</h4>
                                    <div class="tecnica-efeitos">
                                        @if(!empty($ef['texto']))
                                            <p>{{ $ef['texto'] }}</p>
                                        @endif
                                        @if(!empty($ef['lista']))
                                            <ul>
                                                @foreach($ef['lista'] as $linha)
                                                    <li>{{ $linha }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if(!empty($ef['nota']))
                                            <p class="nota">{{ $ef['nota'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div>
                                <h4 class="tecnica-subtitulo">Aprimoramentos</h4>
                                @if(empty($aprimoramentos))
                                    <p class="aprimoramento-nenhum">Nenhum.</p>
                                @else
                                    <ul class="aprimoramento-lista">
                                        @foreach($aprimoramentos as $apr)
                                            <li class="aprimoramento-item">
                                                <span class="aprimoramento-custo">{{ $apr['custo'] ?? '' }}</span>
                                                <div>
                                                    <p class="aprimoramento-texto">{{ $apr['texto'] ?? '' }}</p>
                                                    @if(!empty($apr['requisito']))
                                                        <p class="aprimoramento-requisito">
                                                            <strong>Requisito:</strong> {{ $apr['requisito'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

    </div>
</div>
@endsection