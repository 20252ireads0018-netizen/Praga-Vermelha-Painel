@extends('layout')

@section('title', ($partida->jogo === 'gladio' ? 'Gladio' : 'Pela Dama') . ' · ' . $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">
<link rel="stylesheet" href="{{ asset('css/jogos.css') }}?v={{ @filemtime(public_path('css/jogos.css')) }}">

<div class="camp-wrap camp-wrap-larga jg jg-partida-pagina{{ request()->boolean('mini') ? ' gl-foco gl-mini-mesa' : '' }}" id="jg-partida">
    <header class="jg-topo jg-topo--partida">
        <a href="{{ route('campanhas.jogos.index', $campanha) }}" class="camp-voltar">← Jogos da campanha</a>
        <h1>@if ($partida->jogo === 'gladio')<x-icone n="espadas"/> Gladio @else ♛ Pela Dama @endif</h1>
        <p class="jg-status" id="jg-status">Carregando…</p>
        @if ($partida->jogo === 'gladio')
            <button type="button" class="camp-btn camp-btn-pequeno jg-tela-cheia" id="jg-pausar" hidden title="Só o mestre vê: ninguém age e o relógio para enquanto estiver pausada"><x-icone n="pausa"/> Pausar</button>
            <button type="button" class="camp-btn camp-btn-pequeno jg-tela-cheia" id="jg-miniatura" hidden title="Sai da mesa e continua jogando numa janelinha, em qualquer página do site">⇲ Miniatura</button>
            <button type="button" class="camp-btn camp-btn-pequeno jg-tela-cheia" id="jg-tela-cheia" title="Só a mesa, sem a aba de acontecimentos (Esc sai)"><x-icone n="tela-cheia"/> Tela cheia</button>
        @endif
    </header>
    <p class="jg-msg" id="jg-msg" hidden></p>
    <div id="jg-jogo" class="{{ $partida->jogo === 'gladio' ? 'gl' : 'pd' }}"></div>
    @if ($partida->jogo === 'gladio')
        {{-- chat da partida e bolão de quem assiste (fora da mesa: a mesa redesenha a cada jogada) --}}
        <aside class="gl-social" id="gl-social">
            <section class="gl-chat" aria-label="Chat da partida">
                <h3>Chat</h3>
                <ol class="gl-chat-lista" id="gl-chat-lista"></ol>
                <form class="gl-chat-form" id="gl-chat-form" autocomplete="off">
                    <input type="text" name="texto" maxlength="200" placeholder="Escreva para a mesa…" aria-label="Mensagem">
                    <button class="camp-btn camp-btn-pequeno">Enviar</button>
                </form>
            </section>
            <section class="gl-bolao" id="gl-bolao" hidden aria-label="Bolão de quem assiste"></section>
        </aside>
    @endif
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
        'chat'   => route('campanhas.jogos.chat', [$campanha, $partida]),
        'bolao'  => route('campanhas.jogos.bolao', [$campanha, $partida]),
        'lobby'  => route('campanhas.jogos.index', $campanha),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('js/jogos-comum.js') }}?v={{ @filemtime(public_path('js/jogos-comum.js')) }}"></script>
<script src="{{ asset('js/jogos-partida.js') }}?v={{ @filemtime(public_path('js/jogos-partida.js')) }}"></script>
@endsection
