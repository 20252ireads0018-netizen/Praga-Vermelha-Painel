@extends('layout')

@section('title', ($partida->jogo === 'gladio' ? 'Gladio' : 'Pela Dama') . ' · ' . $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">
<link rel="stylesheet" href="{{ asset('css/jogos.css') }}?v={{ @filemtime(public_path('css/jogos.css')) }}">

<div class="camp-wrap camp-wrap-larga jg jg-partida-pagina" id="jg-partida">
    <header class="jg-topo jg-topo--partida">
        <a href="{{ route('campanhas.jogos.index', $campanha) }}" class="camp-voltar">← Jogos da campanha</a>
        <h1>{{ $partida->jogo === 'gladio' ? '⚔️ Gladio' : '♛ Pela Dama' }}</h1>
        <p class="jg-status" id="jg-status">Carregando…</p>
    </header>
    <p class="jg-msg" id="jg-msg" hidden></p>
    <div id="jg-jogo" class="{{ $partida->jogo === 'gladio' ? 'gl' : 'pd' }}"></div>
</div>

<script type="application/json" id="jg-partida-dados">{!! json_encode([
    'eu'      => (int) auth()->id(),
    'csrf'    => csrf_token(),
    'dados'   => $dados,
    'imagens' => $imagens,
    'playmats' => $playmats ?? (object) [], // lado => imagem do playmat do jogador
    'url'     => [
        'estado' => route('campanhas.jogos.estado', [$campanha, $partida]),
        'acao'   => route('campanhas.jogos.acao', [$campanha, $partida]),
        'lobby'  => route('campanhas.jogos.index', $campanha),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('js/jogos-comum.js') }}?v={{ @filemtime(public_path('js/jogos-comum.js')) }}"></script>
<script src="{{ asset('js/jogos-partida.js') }}?v={{ @filemtime(public_path('js/jogos-partida.js')) }}"></script>
@endsection
