@extends('layout')

@section('title', 'Minijogos · ' . $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">
<link rel="stylesheet" href="{{ asset('css/minijogos.css') }}?v={{ @filemtime(public_path('css/minijogos.css')) }}">

<div class="camp-wrap camp-wrap-larga mj" id="mj">
    <a href="{{ route('campanhas.show', $campanha) }}" class="camp-voltar">← {{ $campanha->nome }}</a>
    <h1>Minijogos</h1>
    <p class="camp-sub">Cassino da Rua Escarlate. Apostas fictícias com o Dinheiro dos seus personagens: tudo aqui vale só dentro do RPG.</p>
    <div class="mj-letreiro" aria-live="off"><span class="mj-letreiro-rotulo">Ganhando agora</span><span class="mj-letreiro-texto" id="mj-letreiro"></span></div>

    <div class="mj-topo">
        <label class="mj-com">Apostar com
            <select id="mj-com" aria-label="Com o Dinheiro de quem apostar"></select>
        </label>
        <div class="mj-saldo" aria-live="polite"><span>Dinheiro</span><strong id="mj-saldo">R$ 0</strong></div>
    </div>
    <p class="jg-msg" id="mj-msg" hidden></p>
    <div class="mj-tentacao" id="mj-tentacao" hidden></div>

    <div class="mj-abas" role="tablist">
        <button type="button" role="tab" data-aba="mines" aria-selected="true"><span class="mj-ico" data-ico="gema"></span>Mines<small>Campo amaldiçoado</small></button>
        <button type="button" role="tab" data-aba="aviator" aria-selected="false"><span class="mj-ico" data-ico="corvo"></span>Aviator<small>Voo do corvo</small></button>
        <button type="button" role="tab" data-aba="slot" aria-selected="false"><span class="mj-ico" data-ico="vassago"></span>Caça-níquel<small>Roleta do profano</small></button>
    </div>

    {{-- Mines --}}
    <section class="mj-painel" id="mj-mines" data-painel="mines">
        <div class="mj-controles">
            <label>Aposta (R$)<input type="number" min="1" step="1" value="100" class="mj-aposta" data-jogo="mines"></label>
            <div class="mj-rapidos" data-jogo="mines"><button type="button" data-mult="0.5">½</button><button type="button" data-mult="2">2×</button><button type="button" data-mult="max">Máx.</button></div>
            <label>Minas<select id="mj-minas"></select></label>
            <div class="mj-info" id="mj-mines-info">Cada casa segura aumenta o prêmio. Achou um Kuro, perdeu a aposta.</div>
            <button type="button" class="camp-btn camp-btn-primario mj-acao" id="mj-mines-botao">Começar</button>
        </div>
        <div class="mj-grade" id="mj-grade" aria-label="Campo de 5 por 5"></div>
    </section>

    {{-- Aviator --}}
    <section class="mj-painel" id="mj-aviator" data-painel="aviator" hidden>
        <div class="mj-controles">
            <label>Aposta (R$)<input type="number" min="1" step="1" value="100" class="mj-aposta" data-jogo="aviator"></label>
            <div class="mj-rapidos" data-jogo="aviator"><button type="button" data-mult="0.5">½</button><button type="button" data-mult="2">2×</button><button type="button" data-mult="max">Máx.</button></div>
            <label>Sacar sozinho em (opcional)<input type="number" min="1.01" step="0.01" placeholder="Ex.: 2,00" id="mj-auto"></label>
            <div class="mj-info">O corvo sobe e o multiplicador cresce. Saque antes que a maldição o derrube.</div>
            <button type="button" class="camp-btn camp-btn-primario mj-acao" id="mj-aviator-botao">Voar</button>
        </div>
        <div class="mj-ceu" id="mj-ceu">
            <svg class="mj-curva" viewBox="0 0 400 220" preserveAspectRatio="none" aria-hidden="true"><path id="mj-rastro" d=""/><path id="mj-rastro-linha" d=""/></svg>
            <div class="mj-corvo" id="mj-corvo"><span class="mj-ico" data-ico="corvo"></span></div>
            <div class="mj-mult" id="mj-mult">1,00×</div>
            <div class="mj-historico" id="mj-historico" aria-label="Últimas quedas"></div>
        </div>
    </section>

    {{-- Caça-níquel --}}
    <section class="mj-painel" id="mj-slot" data-painel="slot" hidden>
        <div class="mj-controles">
            <label>Aposta (R$)<input type="number" min="1" step="1" value="100" class="mj-aposta" data-jogo="slot"></label>
            <div class="mj-rapidos" data-jogo="slot"><button type="button" data-mult="0.5">½</button><button type="button" data-mult="2">2×</button><button type="button" data-mult="max">Máx.</button></div>
            <button type="button" class="camp-btn camp-btn-primario mj-acao" id="mj-slot-botao">Girar</button>
            <table class="mj-tabela" id="mj-tabela" aria-label="Tabela de prêmios"></table>
        </div>
        <div class="mj-maquina">
            <div class="mj-rolos" id="mj-rolos">
                <div class="mj-rolo"><div class="mj-fita"></div></div>
                <div class="mj-rolo"><div class="mj-fita"></div></div>
                <div class="mj-rolo"><div class="mj-fita"></div></div>
            </div>
            <div class="mj-linha" aria-hidden="true"></div>
            <div class="mj-resultado" id="mj-slot-resultado" aria-live="polite"></div>
        </div>
    </section>
</div>

<script type="application/json" id="mj-dados">{!! json_encode($dados + [
    'csrf' => csrf_token(),
    'url'  => url('/campanhas/' . $campanha->id . '/minijogos'),
    'chave' => 'mj-' . $campanha->id,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('js/campanha-minijogos.js') }}?v={{ @filemtime(public_path('js/campanha-minijogos.js')) }}"></script>
@endsection
