<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apresentação — Praga Vermelha RPG</title>
    <meta name="description" content="Conheça o Praga Vermelha RPG: fichas que se calculam sozinhas, campanhas online, Kuros, minijogos e o livro do sistema.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/favicon.ico">
<style>
/* Apresentação do sistema para gravar vídeo (Dia X / ADS): cenas em sequência, só com animações de CSS.
   Espaço pausa, ← → trocam de cena, F põe em tela cheia; ?cena=N abre direto numa cena. */
:root { --sangue: #9b2226; --sangue-claro: #ef4444; --tinta: #f3e8e8; --suave: rgba(243, 232, 232, .65); --fundo: #0b0607; --cartao: #170d0f; --linha: rgba(255, 255, 255, .1); --ouro: #e9c46a; }
* { box-sizing: border-box; }
html, body { margin: 0; height: 100%; background: var(--fundo); color: var(--tinta); font-family: Inter, system-ui, sans-serif; overflow: hidden; }
.palco { position: fixed; inset: 0; display: grid; place-items: center; background: radial-gradient(ellipse at 50% 30%, #2a0b0e 0%, #0b0607 62%); }
.palco::before { content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .35;
    background-image: radial-gradient(rgba(239, 68, 68, .5) 1px, transparent 1.5px); background-size: 46px 46px; animation: poeira 40s linear infinite; }
@keyframes poeira { to { background-position: 460px -460px; } }

.cena { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2.2vh; padding: 6vh 6vw 12vh; text-align: center;
    opacity: 0; visibility: hidden; transition: opacity .7s ease, visibility .7s; }
.cena.ativa { opacity: 1; visibility: visible; }
.cena:not(.ativa) * { animation-play-state: paused !important; }
.palco.pausado .cena * { animation-play-state: paused !important; }
h1, h2 { font-family: Cinzel, serif; margin: 0; letter-spacing: .04em; }
h2 { font-size: clamp(1.8rem, 4.6vw, 3.6rem); }
.sub { font-size: clamp(1rem, 2vw, 1.5rem); color: var(--suave); max-width: 900px; margin: 0; }
.ativa .entra { animation: entra .9s cubic-bezier(.2, .8, .2, 1) both; }
.ativa .entra.d1 { animation-delay: .25s; } .ativa .entra.d2 { animation-delay: .5s; } .ativa .entra.d3 { animation-delay: .8s; } .ativa .entra.d4 { animation-delay: 1.1s; }
@keyframes entra { from { opacity: 0; transform: translateY(26px) scale(.98); filter: blur(4px); } }

/* 1. título */
.marca { font-size: clamp(3rem, 11vw, 8.5rem); font-weight: 900; line-height: .95; color: #fff; text-shadow: 0 0 40px rgba(239, 68, 68, .55);
    background: linear-gradient(180deg, #fff 30%, #fca5a5 60%, var(--sangue) 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
.ativa .marca { animation: acende 2.4s ease both; }
@keyframes acende { 0% { opacity: 0; letter-spacing: .4em; filter: blur(10px); } 60% { opacity: 1; filter: blur(0); } 100% { letter-spacing: .04em; } }
.cruz { font-size: clamp(2.5rem, 6vw, 4.5rem); color: var(--sangue-claro); }
.ativa .cruz { animation: pulsa 2s ease-in-out infinite; }
@keyframes pulsa { 50% { transform: scale(1.12); text-shadow: 0 0 30px var(--sangue-claro); } }
.gotas { position: absolute; top: 0; left: 0; right: 0; height: 30vh; pointer-events: none; }
.gotas i { position: absolute; top: -10px; width: 6px; border-radius: 0 0 6px 6px; background: linear-gradient(var(--sangue), #5c0b0f); }
.ativa .gotas i { animation: escorre 3.6s ease-in both; }
@keyframes escorre { from { height: 0; } to { height: var(--h); } }

/* 2. ficha */
.ficha { width: min(720px, 92vw); background: var(--cartao); border: 1px solid var(--linha); border-radius: 18px; padding: 22px 26px; text-align: left; box-shadow: 0 20px 60px rgba(0, 0, 0, .6); }
.ficha-cab { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 14px; }
.ficha-cab strong { font-family: Cinzel, serif; font-size: 1.5rem; }
.ficha-cab span { color: var(--suave); }
.barra { display: grid; grid-template-columns: 130px 1fr 52px; gap: 12px; align-items: center; margin: 8px 0; font-size: 1.05rem; }
.barra .trilho { height: 12px; border-radius: 99px; background: rgba(255, 255, 255, .07); overflow: hidden; }
.barra .trilho i { display: block; height: 100%; width: var(--v); border-radius: 99px; background: linear-gradient(90deg, #5c0b0f, var(--sangue-claro)); transform-origin: left; }
.ativa .barra .trilho i { animation: enche 1.4s cubic-bezier(.2, .8, .2, 1) both; animation-delay: var(--d); }
@keyframes enche { from { transform: scaleX(0); } }
.barra b { text-align: right; font-variant-numeric: tabular-nums; }
.selo { display: inline-block; padding: 3px 12px; border-radius: 99px; background: rgba(155, 34, 38, .3); border: 1px solid rgba(239, 68, 68, .45); font-size: .95rem; }

/* 3. dados */
.mesa-dados { display: flex; gap: 4vw; align-items: center; justify-content: center; flex-wrap: wrap; }
.dado { width: clamp(110px, 16vw, 170px); aspect-ratio: 1; display: grid; place-items: center; border-radius: 26px; font-family: Cinzel, serif; font-size: clamp(2.2rem, 5vw, 3.6rem); font-weight: 900;
    background: linear-gradient(145deg, #c1272d, #5c0b0f); box-shadow: inset 0 -10px 20px rgba(0, 0, 0, .35), 0 18px 40px rgba(155, 34, 38, .45); position: relative; }
.ativa .dado { animation: rola 1.6s cubic-bezier(.3, 1.4, .5, 1) both; }
.ativa .dado:nth-child(2) { animation-delay: .2s; } .ativa .dado:nth-child(3) { animation-delay: .4s; }
@keyframes rola { 0% { transform: translateY(-60vh) rotate(-540deg) scale(.6); } 70% { transform: translateY(0) rotate(12deg); } 85% { transform: rotate(-6deg); } }
.dado span { position: absolute; top: 10px; left: 14px; font-family: Inter, sans-serif; font-size: .9rem; font-weight: 600; opacity: .8; }
.conta { font-family: ui-monospace, Consolas, monospace; font-size: clamp(1.1rem, 2.4vw, 1.8rem); padding: 12px 22px; border-radius: 12px; background: rgba(0, 0, 0, .45); border: 1px solid var(--linha); }
.conta b { color: var(--ouro); }

/* 4. campanha: grade do mapa com fichas andando e o chat */
.campanha { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 22px; width: min(1100px, 94vw); align-items: stretch; }
.mapa { position: relative; aspect-ratio: 16 / 10; border-radius: 16px; border: 1px solid var(--linha); overflow: hidden;
    background: linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 10% 16%, linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 10% 16%, #140a0b; }
.peca { position: absolute; width: 8%; aspect-ratio: 1; border-radius: 50%; display: grid; place-items: center; font-weight: 700; color: #fff; box-shadow: 0 0 0 3px rgba(0, 0, 0, .5), 0 0 18px var(--c); background: var(--c); }
.ativa .peca { animation: anda 7s ease-in-out infinite alternate; }
@keyframes anda { 0%, 15% { transform: translate(0, 0); } 55%, 100% { transform: translate(var(--x), var(--y)); } }
.kuro-mapa { position: absolute; right: 12%; top: 22%; width: 14%; aspect-ratio: 1; border-radius: 30%; background: radial-gradient(circle at 40% 35%, #444, #050505); box-shadow: 0 0 40px rgba(239, 68, 68, .7); }
.ativa .kuro-mapa { animation: respira 2.4s ease-in-out infinite; }
@keyframes respira { 50% { transform: scale(1.08); box-shadow: 0 0 70px rgba(239, 68, 68, .9); } }
.chat { display: flex; flex-direction: column; gap: 10px; justify-content: flex-end; text-align: left; }
.msg { padding: 10px 14px; border-radius: 14px; background: var(--cartao); border: 1px solid var(--linha); font-size: 1.02rem; }
.msg b { color: var(--sangue-claro); }
.ativa .msg { animation: entra .6s ease both; }
.ativa .msg:nth-child(1) { animation-delay: .6s; } .ativa .msg:nth-child(2) { animation-delay: 1.8s; } .ativa .msg:nth-child(3) { animation-delay: 3s; } .ativa .msg:nth-child(4) { animation-delay: 4.2s; }

/* 5. Kuros: o texto da habilidade ganha o valor calculado */
.kuro-texto { width: min(860px, 92vw); text-align: left; font-size: clamp(1.05rem, 2vw, 1.4rem); line-height: 1.6; background: var(--cartao); border: 1px solid var(--linha); border-radius: 18px; padding: 22px 26px; }
.kuro-texto strong { font-family: Cinzel, serif; display: block; margin-bottom: 6px; font-size: 1.3em; }
.calc { display: inline-block; padding: 0 8px; border-radius: 8px; background: rgba(239, 68, 68, .2); color: #fecaca; font-weight: 700; }
.ativa .calc { animation: carimbo .7s cubic-bezier(.3, 1.6, .5, 1) both; }
.ativa .calc.c1 { animation-delay: 1s; } .ativa .calc.c2 { animation-delay: 1.8s; } .ativa .calc.c3 { animation-delay: 2.6s; }
@keyframes carimbo { from { transform: scale(2.4); opacity: 0; } }
.numeros { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; }
.numeros div { min-width: 130px; padding: 12px 16px; border-radius: 14px; background: rgba(255, 255, 255, .05); border: 1px solid var(--linha); }
.numeros span { display: block; color: var(--suave); font-size: .9rem; }
.numeros b { font-size: 1.8rem; font-family: Cinzel, serif; }

/* 6. Gladio */
.cartas { display: flex; gap: 2.4vw; perspective: 1200px; }
.carta { width: clamp(120px, 15vw, 190px); aspect-ratio: 5 / 7; position: relative; transform-style: preserve-3d; }
.ativa .carta { animation: vira 1.2s cubic-bezier(.3, 1.3, .5, 1) both; }
.ativa .carta:nth-child(2) { animation-delay: .35s; } .ativa .carta:nth-child(3) { animation-delay: .7s; }
@keyframes vira { from { transform: rotateY(180deg) translateY(40px); } }
.carta > div { position: absolute; inset: 0; border-radius: 14px; backface-visibility: hidden; display: flex; flex-direction: column; justify-content: space-between; padding: 12px; border: 2px solid rgba(233, 196, 106, .6); }
.carta .frente { background: linear-gradient(160deg, #2a1215, #0f0708); }
.carta .verso { transform: rotateY(180deg); background: repeating-linear-gradient(45deg, #5c0b0f 0 10px, #3a0709 10px 20px); }
.carta .nome { font-family: Cinzel, serif; font-size: clamp(.85rem, 1.4vw, 1.1rem); text-align: left; }
.carta .arte { flex: 1; margin: 8px 0; border-radius: 8px; display: grid; place-items: center; font-size: clamp(2rem, 4vw, 3.2rem); background: radial-gradient(circle, rgba(239, 68, 68, .35), transparent 70%); }
.carta .forca { display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; }
.carta .forca b { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; background: var(--sangue); }
.carta .forca b + b { background: #1f6f3a; }
.ativa .carta.ataca { animation: vira 1.2s cubic-bezier(.3, 1.3, .5, 1) .7s both, golpe 1.1s ease-in-out 3s 2; }
@keyframes golpe { 40% { transform: translateY(-40px) rotate(-4deg); } 55% { transform: translateY(10px) rotate(2deg); } }

/* 7. planos */
.planos { display: flex; gap: 18px; flex-wrap: wrap; justify-content: center; }
.plano { width: min(300px, 88vw); padding: 22px; border-radius: 18px; background: var(--cartao); border: 1px solid var(--linha); text-align: left; }
.plano.destaque { border-color: rgba(239, 68, 68, .7); box-shadow: 0 0 50px rgba(155, 34, 38, .45); }
.ativa .plano.destaque { animation: entra .9s ease .8s both, brilho 2.6s ease-in-out 2s infinite; }
@keyframes brilho { 50% { box-shadow: 0 0 80px rgba(239, 68, 68, .7); } }
.plano h3 { font-family: Cinzel, serif; margin: 0 0 6px; font-size: 1.4rem; }
.plano .preco { font-size: 2.2rem; font-weight: 700; margin: 8px 0; color: #fff; }
.plano ul { margin: 0; padding-left: 18px; color: var(--suave); line-height: 1.7; }

/* 8. chamada */
.url { font-family: Cinzel, serif; font-size: clamp(1.3rem, 3.6vw, 2.6rem); padding: 14px 30px; border-radius: 16px; border: 2px solid var(--sangue-claro); color: #fff; text-decoration: none;
    box-shadow: 0 0 40px rgba(239, 68, 68, .4); }
.ativa .url { animation: entra .9s ease .5s both, brilho 2.4s ease-in-out 1.5s infinite; }

/* controles (somem sozinhos para a gravação; voltam ao mexer o mouse) */
.controles { position: fixed; left: 50%; bottom: 18px; transform: translateX(-50%); z-index: 5; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 99px;
    background: rgba(0, 0, 0, .6); border: 1px solid var(--linha); backdrop-filter: blur(6px); transition: opacity .4s; }
.controles.some { opacity: 0; pointer-events: none; }
.controles button { min-width: 40px; height: 36px; padding: 0 12px; border-radius: 99px; border: 1px solid var(--linha); background: rgba(255, 255, 255, .06); color: var(--tinta); cursor: pointer; font: inherit; font-size: .9rem; }
.controles button:hover { background: var(--sangue); }
.pontos { display: flex; gap: 6px; margin: 0 6px; }
.pontos i { width: 9px; height: 9px; border-radius: 50%; background: rgba(255, 255, 255, .25); cursor: pointer; }
.pontos i.ativo { background: var(--sangue-claro); box-shadow: 0 0 8px var(--sangue-claro); }
.progresso { position: fixed; left: 0; top: 0; height: 4px; z-index: 5; background: var(--sangue-claro); width: 0; }
@media (max-width: 760px) { .campanha { grid-template-columns: 1fr; } .chat { display: none; } .barra { grid-template-columns: 96px 1fr 44px; } }
@media (prefers-reduced-motion: reduce) { *, *::before { animation-duration: .01s !important; animation-iteration-count: 1 !important; } }
</style>
</head>
<body>
@php
    $precoCheio = \App\Support\CompraLivro::precoTexto(\App\Support\CompraLivro::precoCheio());
    $precoAgora = \App\Support\CompraLivro::precoTexto();
    $dias = (int) config('planos.dias_teste', 45);
    $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'praga-vermelha.websight.com.br';
@endphp
<div class="progresso" id="progresso"></div>
<main class="palco" id="palco">

    <section class="cena" data-tempo="7">
        <div class="gotas" aria-hidden="true">
            @foreach ([[8, 120], [19, 70], [33, 160], [47, 90], [58, 140], [71, 60], [84, 130], [93, 80]] as [$x, $h])
                <i style="left: {{ $x }}%; --h: {{ $h }}px"></i>
            @endforeach
        </div>
        <div class="cruz">✠</div>
        <h1 class="marca">Praga Vermelha</h1>
        <p class="sub entra d3">Um sistema de RPG de horror sobrenatural, com painel online para jogar de verdade.</p>
    </section>

    <section class="cena" data-tempo="8">
        <h2 class="entra">A ficha se calcula sozinha</h2>
        <div class="ficha entra d1">
            <div class="ficha-cab"><strong>Helena Sorrow</strong><span>Nível 7 · Ascendente de Belial</span></div>
            @foreach ([['Força', 62, '.2s'], ['Destreza', 78, '.45s'], ['Constituição', 55, '.7s'], ['Conhecimento', 84, '.95s'], ['Alma', 91, '1.2s']] as [$a, $v, $d])
                <div class="barra"><span>{{ $a }}</span><span class="trilho"><i style="--v: {{ $v }}%; --d: {{ $d }}"></i></span><b>{{ $v }}</b></div>
            @endforeach
            <p class="entra d4" style="margin: 14px 0 0"><span class="selo">Maldição de Belial · 3 técnicas</span> <span class="selo">Aura 140</span> <span class="selo">Sanidade 68</span></p>
        </div>
        <p class="sub entra d4">Atributos, habilidades, maldições e talentos: você escolhe, o painel faz as contas.</p>
    </section>

    <section class="cena" data-tempo="7">
        <h2 class="entra">Dados na mesa, para todos verem</h2>
        <div class="mesa-dados">
            <div class="dado"><span>d100</span>37</div>
            <div class="dado"><span>d10</span>8</div>
            <div class="dado"><span>d6</span>5</div>
        </div>
        <div class="conta entra d4">Luta: d100 <b>37</b> − 50 = <b>−13</b></div>
    </section>

    <section class="cena" data-tempo="9">
        <h2 class="entra">Campanhas online com o seu grupo</h2>
        <div class="campanha entra d1">
            <div class="mapa">
                <div class="kuro-mapa" aria-hidden="true"></div>
                <div class="peca" style="left: 10%; top: 60%; --c: #2563eb; --x: 220%; --y: -120%">H</div>
                <div class="peca" style="left: 18%; top: 75%; --c: #16a34a; --x: 320%; --y: -60%">M</div>
                <div class="peca" style="left: 6%; top: 30%; --c: #ca8a04; --x: 260%; --y: 40%">T</div>
            </div>
            <div class="chat">
                <div class="msg"><b>Mestre:</b> O ar fica pesado. Algo se mexe no escuro.</div>
                <div class="msg"><b>Helena:</b> Vou testar Intuição.</div>
                <div class="msg"><b>Painel:</b> Intuição d100 22 − 40 = −18</div>
                <div class="msg"><b>Mestre:</b> É um Infestador. Rolem iniciativa!</div>
            </div>
        </div>
        <p class="sub entra d4">Mapa, fichas do grupo, iniciativa, trilha sonora e chat, tudo no navegador.</p>
    </section>

    <section class="cena" data-tempo="8">
        <h2 class="entra">Kuros que se montam pelas regras do livro</h2>
        <div class="kuro-texto entra d1">
            <strong>Infecção ×4</strong>
            Ao acertar um alvo, ele rola 1d100 com chance igual a sua ameaça vezes 10 <span class="calc c1">= 20</span>.
            Se cumprir, vira um Kuro da sua espécie em 24 (−2 por escolha) <span class="calc c2">= 18</span> horas,
            a menos que receba primeiros socorros (D30 −25 por escolha) <span class="calc c3">= D0</span>.
        </div>
        <div class="numeros entra d3">
            <div><span>PV</span><b>100</b></div><div><span>Constituição</span><b>90</b></div><div><span>Luta</span><b>+50</b></div><div><span>Dano</span><b>5d10+8</b></div>
        </div>
    </section>

    <section class="cena" data-tempo="8">
        <h2 class="entra">Minijogos entre as sessões</h2>
        <div class="cartas">
            <div class="carta"><div class="frente"><span class="nome">Carniçal</span><span class="arte">☠</span><span class="forca"><b>2</b><b>3</b></span></div><div class="verso"></div></div>
            <div class="carta ataca"><div class="frente"><span class="nome">Cavaleiro Negro</span><span class="arte">⚔</span><span class="forca"><b>5</b><b>4</b></span></div><div class="verso"></div></div>
            <div class="carta"><div class="frente"><span class="nome">Monolisco</span><span class="arte">👁</span><span class="forca"><b>4</b><b>6</b></span></div><div class="verso"></div></div>
        </div>
        <p class="sub entra d3">Gladio, o jogo de cartas do cenário, e Pela Dama, o xadrez amaldiçoado. Contra amigos ou contra o robô.</p>
    </section>

    <section class="cena" data-tempo="8">
        <h2 class="entra">Comece de graça</h2>
        <div class="planos">
            <div class="plano entra d1"><h3>Teste completo</h3><div class="preco">{{ $dias }} dias</div><ul><li>Tudo liberado, sem limites</li><li>Sem anúncios</li><li>Grátis ao criar a conta</li></ul></div>
            <div class="plano destaque"><h3>Vitalício</h3><div class="preco">{{ $precoAgora }}@if ($precoAgora !== $precoCheio) <s style="font-size: .5em; opacity: .6">{{ $precoCheio }}</s>@endif</div><ul><li>Livro do sistema em PDF</li><li>Painel sem anúncios, para sempre</li><li>Todos os livros de histórias</li></ul></div>
        </div>
    </section>

    <section class="cena" data-tempo="9">
        <div class="cruz">✠</div>
        <h2 class="entra">Sua próxima campanha começa aqui</h2>
        <a class="url" href="{{ route('register') }}">{{ $host }}</a>
        <p class="sub entra d3">Crie a conta e jogue {{ $dias }} dias com tudo liberado.</p>
    </section>
</main>

<div class="controles" id="controles">
    <button type="button" data-ir="-1" aria-label="Cena anterior">◀</button>
    <button type="button" id="tocar" aria-label="Pausar">❚❚</button>
    <button type="button" data-ir="1" aria-label="Próxima cena">▶</button>
    <span class="pontos" id="pontos"></span>
    <button type="button" id="cheia">Tela cheia</button>
    <a href="/" style="color: var(--suave); font-size: .85rem; padding: 0 6px">Sair</a>
</div>

<script>
(function () {
    var cenas = [].slice.call(document.querySelectorAll('.cena'));
    var palco = document.getElementById('palco'), barra = document.getElementById('progresso'), pontos = document.getElementById('pontos');
    var botao = document.getElementById('tocar'), controles = document.getElementById('controles');
    var atual = 0, tocando = true, decorrido = 0, ultimo = performance.now();
    var inicio = parseInt(new URLSearchParams(location.search).get('cena'), 10);
    cenas.forEach(function (c, i) { var p = document.createElement('i'); p.title = 'Cena ' + (i + 1); p.onclick = function () { ir(i); }; pontos.appendChild(p); });

    function ir(i) {
        atual = (i + cenas.length) % cenas.length;
        decorrido = 0;
        cenas.forEach(function (c, k) {
            c.classList.remove('ativa');
            if (k === atual) { void c.offsetWidth; c.classList.add('ativa'); } // reinicia as animações da cena
        });
        [].forEach.call(pontos.children, function (p, k) { p.classList.toggle('ativo', k === atual); });
    }
    function tique(t) {
        var dt = (t - ultimo) / 1000; ultimo = t;
        if (tocando) {
            decorrido += dt;
            var dura = +cenas[atual].dataset.tempo || 7;
            barra.style.width = Math.min(100, decorrido / dura * 100) + '%';
            if (decorrido >= dura) ir(atual + 1);
        }
        requestAnimationFrame(tique);
    }
    function alternar() {
        tocando = !tocando;
        palco.classList.toggle('pausado', !tocando);
        botao.textContent = tocando ? '❚❚' : '▶︎';
        botao.setAttribute('aria-label', tocando ? 'Pausar' : 'Continuar');
    }
    botao.onclick = alternar;
    controles.addEventListener('click', function (e) { var b = e.target.closest('[data-ir]'); if (b) ir(atual + +b.dataset.ir); });
    document.getElementById('cheia').onclick = function () {
        if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen().catch(function () {});
    };
    document.addEventListener('keydown', function (e) {
        if (e.key === ' ') { e.preventDefault(); alternar(); }
        else if (e.key === 'ArrowRight') ir(atual + 1);
        else if (e.key === 'ArrowLeft') ir(atual - 1);
        else if (e.key === 'f' || e.key === 'F') document.getElementById('cheia').click();
    });
    // os controles somem depois de 2,5 s parados (para gravar a tela limpa) e voltam ao mexer o mouse
    var esconder;
    function mostrar() { controles.classList.remove('some'); clearTimeout(esconder); esconder = setTimeout(function () { controles.classList.add('some'); }, 2500); }
    ['mousemove', 'touchstart', 'keydown'].forEach(function (ev) { document.addEventListener(ev, mostrar, { passive: true }); });
    mostrar();
    ir(inicio > 0 ? inicio - 1 : 0);
    requestAnimationFrame(tique);
})();
</script>
</body>
</html>
