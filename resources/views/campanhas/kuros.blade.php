@extends('layout')

@section('title', 'Kuros · ' . $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">
<style>
    .kr-barra { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 14px; }
    .kr-barra input, .kr-barra select { min-height: 36px; }
    .kr-barra .kr-busca { flex: 1 1 200px; min-width: 0; }
    .kr-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
    .kr-cartao { border: 1px solid var(--camp-borda, rgba(255,255,255,.14)); border-radius: 12px; padding: 12px; background: rgba(0, 0, 0, .22); display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .kr-cartao h3 { margin: 0; font-size: 1.05rem; overflow-wrap: anywhere; }
    .kr-meta { font-size: .82rem; opacity: .75; }
    .kr-botoes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; }
    .kr-dono { font-size: .8rem; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .kr-dono select { min-height: 30px; font-size: .8rem; max-width: 100%; }
    .kr-janela { position: fixed; inset: 0; z-index: 1300; background: rgba(0, 0, 0, .65); display: flex; align-items: flex-start; justify-content: center; overflow: auto; padding: 24px 12px; }
    .kr-janela[hidden] { display: none; }
    .kr-caixa { width: min(1100px, 100%); background: #160f17; border: 1px solid rgba(255, 255, 255, .15); border-radius: 14px; padding: 16px; box-sizing: border-box; }
    .kr-caixa-cab { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .kr-caixa-cab h2 { flex: 1; margin: 0; font-size: 1.2rem; }
    .kr-editor { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 16px; }
    @media (max-width: 860px) { .kr-editor { grid-template-columns: minmax(0, 1fr); } }
    .kr-editor label { display: flex; flex-direction: column; gap: 3px; font-size: .85rem; margin-bottom: 8px; }
    .kr-editor textarea { min-height: 60px; resize: vertical; }
    .kr-linha { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; }
    .kr-grupo { border-top: 1px solid rgba(255, 255, 255, .1); padding-top: 10px; margin-top: 10px; }
    .kr-grupo h4 { margin: 0 0 6px; font-size: .95rem; display: flex; justify-content: space-between; gap: 8px; }
    .kr-cont { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 8px; align-items: center; font-size: .85rem; padding: 3px 0; }
    .kr-passo { display: inline-flex; align-items: center; gap: 4px; }
    .kr-passo button { width: 28px; height: 28px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, .18); background: rgba(0, 0, 0, .3); color: inherit; cursor: pointer; }
    .kr-passo b { min-width: 20px; text-align: center; }
    .kr-dica { font-size: .78rem; opacity: .65; grid-column: 1 / -1; }
    .kr-chips { display: flex; flex-wrap: wrap; gap: 4px; }
    .kr-chip { min-height: 30px; padding: 0 10px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, .15); background: rgba(0, 0, 0, .25); color: inherit; cursor: pointer; font-size: .8rem; }
    .kr-chip.ativo { background: rgba(185, 28, 28, .5); border-color: rgba(248, 113, 113, .8); }
    .kr-habs { max-height: 340px; overflow: auto; display: flex; flex-direction: column; gap: 4px; }
    .kr-hab { padding: 6px 8px; border-radius: 8px; background: rgba(255, 255, 255, .04); }
    .kr-hab.tem { background: rgba(99, 102, 241, .18); }
    .kr-hab-cab { display: flex; align-items: center; gap: 8px; }
    .kr-hab-cab strong { flex: 1; font-size: .88rem; }
    .kr-hab p { margin: 4px 0 0; font-size: .8rem; opacity: .8; }
    .kr-req { font-size: .75rem; color: #fca5a5; }
    .kr-ficha { background: rgba(0, 0, 0, .3); border: 1px solid rgba(255, 255, 255, .12); border-radius: 12px; padding: 12px; font-size: .9rem; }
    .kr-ficha h3 { margin: 0 0 4px; }
    .kr-ficha table { width: 100%; border-collapse: collapse; margin: 6px 0; }
    .kr-ficha td { padding: 2px 6px; border-bottom: 1px solid rgba(255, 255, 255, .06); }
    .kr-ficha td:nth-child(even) { text-align: right; font-weight: 700; }
    .kr-ficha .kr-sub { font-weight: 700; margin-top: 10px; }
    .kr-avisos { color: #fca5a5; font-size: .82rem; margin: 6px 0; padding-left: 18px; }
    .kr-escolhas { display: flex; gap: 10px; flex-wrap: wrap; font-size: .82rem; margin: 6px 0; }
    .kr-escolhas span { padding: 2px 8px; border-radius: 999px; background: rgba(255, 255, 255, .08); }
    .kr-escolhas .estourou { background: rgba(185, 28, 28, .55); }
    .kr-vida { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 8px 0; }
    .kr-vida input { width: 90px; }
    .kr-ficha .kr-habil-item { margin: 6px 0; }
    .kr-ficha .kr-habil-item p { margin: 2px 0 0; font-size: .8rem; opacity: .8; }
    /* valores calculados pelo interpretador e selos de onde veio cada bônus */
    .kr-calc { display: inline-block; padding: 0 5px; border-radius: 5px; background: rgba(239, 68, 68, .18); color: #fecaca; font-weight: 700; white-space: nowrap; }
    .kr-selo { display: inline-block; margin-left: 2px; padding: 0 5px; border-radius: 9px; background: rgba(255, 255, 255, .08); font-size: .72rem; opacity: .85; white-space: nowrap; }
    .kr-testes { font-size: .72rem; opacity: .85; }

    /* editor compacto: identidade + Salvar sempre à vista, o resto em abas; a prévia acompanha a rolagem */
    .kr-editor { align-items: start; }
    .kr-form { min-width: 0; }
    .kr-topo-form { position: sticky; top: -16px; z-index: 2; display: grid; grid-template-columns: minmax(0, 2fr) 70px 80px minmax(0, 1fr) minmax(0, 1fr) auto; gap: 6px; align-items: end;
        background: #160f17; padding: 4px 0 8px; border-bottom: 1px solid rgba(255, 255, 255, .1); }
    .kr-topo-form label { margin: 0; font-size: .75rem; }
    .kr-topo-form input { min-height: 34px; padding: 4px 8px; }
    .kr-editor input, .kr-editor select, .kr-editor textarea { width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; }
    .kr-topo-form .camp-btn { min-height: 34px; white-space: nowrap; }
    @media (max-width: 640px) {
        .kr-topo-form { position: static; grid-template-columns: 1fr 1fr; }
        .kr-topo-form .kr-nome, .kr-topo-form .camp-btn { grid-column: 1 / -1; }
        .kr-caixa { padding: 12px; }
        .kr-janela { padding: 12px 6px; }
    }
    .kr-abas { display: flex; flex-wrap: wrap; gap: 4px; margin: 10px 0 8px; }
    .kr-abas button { flex: 1 1 auto; min-height: 32px; padding: 0 10px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, .14); background: rgba(0, 0, 0, .25); color: inherit; cursor: pointer; font-size: .82rem; }
    .kr-abas button.ativa { background: var(--camp-acento); border-color: var(--camp-acento); color: #fff; font-weight: 700; }
    .kr-abas span { font-size: .72rem; opacity: .85; }
    .kr-abas .estourou { color: #fecaca; font-weight: 700; }
    .kr-aba h4 { margin: 10px 0 4px; font-size: .85rem; display: flex; justify-content: space-between; gap: 8px; }
    .kr-grade { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2px 14px; }
    .kr-grade-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    @media (max-width: 640px) { .kr-grade, .kr-grade-3 { grid-template-columns: minmax(0, 1fr); } }
    .kr-grade label { margin: 4px 0; font-size: .78rem; }
    .kr-cont { padding: 2px 0; border-bottom: 1px solid rgba(255, 255, 255, .05); }
    .kr-passo button { width: 24px; height: 24px; }
    .kr-busca-hab { width: 100%; margin-bottom: 6px; }
    .kr-aba .kr-habs { max-height: min(60vh, 520px); }
    #kr-previa { position: sticky; top: 0; max-height: calc(100vh - 90px); overflow: auto; }
    @media (max-width: 860px) { #kr-previa { position: static; max-height: none; } }

    /* ficha compacta */
    .kr-numeros { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; margin: 8px 0; }
    .kr-numeros div { padding: 6px 8px; border-radius: 8px; background: rgba(255, 255, 255, .05); display: flex; flex-direction: column; }
    .kr-numeros span { font-size: .72rem; opacity: .7; }
    .kr-numeros b { font-size: 1.05rem; }
    .kr-linha-ficha { display: flex; justify-content: space-between; gap: 10px; padding: 3px 2px; border-bottom: 1px solid rgba(255, 255, 255, .06); font-size: .84rem; }
    .kr-linha-ficha span { opacity: .8; }
    .kr-linha-ficha b { text-align: right; }
    .kr-ficha .kr-sub small { font-weight: 400; font-size: .72rem; opacity: .6; margin-left: 4px; }
    .kr-danos, .kr-chips-rolar { display: grid; gap: 4px; margin: 4px 0; }
    .kr-chips-rolar { grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); }
    .kr-rolar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 0 6px; padding: 5px 8px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, .12);
        background: rgba(255, 255, 255, .04); color: inherit; cursor: pointer; font: inherit; font-size: .8rem; text-align: left; }
    .kr-rolar:hover { border-color: var(--camp-acento); background: rgba(155, 34, 38, .22); }
    .kr-rolar b { font-size: .9rem; }
    .kr-rolar .kr-selo, .kr-rolar .kr-testes { flex-basis: 100%; margin: 0; padding: 0; background: none; font-size: .66rem; }
    .kr-rolar-dano { font-size: .85rem; }
    .kr-rolou { margin: 6px 0; display: flex; flex-direction: column; gap: 3px; }
    .kr-rolagem { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 8px; align-items: baseline; padding: 4px 8px; border-radius: 6px; background: rgba(0, 0, 0, .3); font-size: .8rem; opacity: .7; }
    .kr-rolagem.nova { opacity: 1; border: 1px solid rgba(248, 113, 113, .5); animation: kr-rolou .35s ease; }
    .kr-rolagem .kr-conta { font-family: ui-monospace, Consolas, monospace; font-size: .74rem; opacity: .8; overflow-wrap: anywhere; }
    .kr-rolagem b { font-size: 1rem; }
    @keyframes kr-rolou { from { transform: scale(.96); background: rgba(155, 34, 38, .5); } to { transform: none; } }
    .kr-ficha details > summary { cursor: pointer; font-weight: 700; font-size: .86rem; }
    .kr-texto { margin-top: 6px; }
    .kr-texto p { margin: 3px 0 0; font-size: .82rem; opacity: .85; }
    .kr-estado { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
    .kr-estado label { display: flex; flex-direction: column; gap: 3px; }
    .kr-vida { margin: 0; }
</style>

<div class="camp-wrap camp-wrap-larga" id="kr-pagina">
    <a href="{{ route('campanhas.show', $campanha) }}" class="camp-voltar">← {{ $campanha->nome }}</a>
    <h1>Kuros</h1>
    <p class="camp-sub">{{ $souMestre ? 'Crie, organize e entregue Kuros aos jogadores. A ficha é calculada pelas regras do livro: tipo, círculos, aprimoramentos e habilidades sobrenaturais.' : 'Os Kuros que o mestre entregou a você. Você controla a vida atual e as anotações deles.' }}</p>

    @unless ($tabela)
        <p class="camp-aviso">A tabela de Kuros ainda não existe no banco. Rode no phpMyAdmin o SQL da migração 2026_11_02_000021 (create_kuros).</p>
    @endunless

    <p class="jg-msg" id="kr-msg" hidden></p>
    <div class="kr-barra">
        @if ($souMestre)
            <button type="button" class="camp-btn camp-btn-primario" id="kr-novo">+ Novo Kuro</button>
            <select id="kr-pronto" aria-label="Kuros prontos do livro"><option value="">Kuro pronto do livro…</option></select>
        @endif
        <input type="search" class="kr-busca" id="kr-busca" placeholder="Pesquisar nome, Daemon, habilidade…" aria-label="Pesquisar">
        <select id="kr-filtro-tipo" aria-label="Filtrar por tipo"><option value="">Todos os tipos</option></select>
        <select id="kr-filtro-daemon" aria-label="Filtrar por Daemon"><option value="">Todos os Daemons</option></select>
        @if ($souMestre)
            <select id="kr-filtro-pasta" aria-label="Filtrar por pasta"><option value="">Todas as pastas</option></select>
        @endif
    </div>
    <div class="kr-lista" id="kr-lista"></div>
</div>

<div class="kr-janela camp-vars" id="kr-janela" hidden>
    <div class="kr-caixa" role="dialog" aria-modal="true" aria-labelledby="kr-janela-titulo">
        <div class="kr-caixa-cab"><h2 id="kr-janela-titulo">Kuro</h2><button type="button" class="camp-btn camp-btn-pequeno" id="kr-fechar">Fechar</button></div>
        <div id="kr-janela-corpo"></div>
    </div>
</div>

<script type="application/json" id="kr-dados">{!! json_encode([
    'mestre'  => $souMestre,
    'csrf'    => csrf_token(),
    'kuros'   => $kuros,
    'membros' => $membros,
    'cfg'     => config('kuros'),
    'url'     => [
        'salvar' => route('campanhas.kuros.salvar', $campanha),
        'base'   => url('/campanhas/' . $campanha->id . '/kuros'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('js/kuros-calc.js') }}?v={{ @filemtime(public_path('js/kuros-calc.js')) }}"></script>
<script src="{{ asset('js/campanha-kuros.js') }}?v={{ @filemtime(public_path('js/campanha-kuros.js')) }}"></script>
@endsection
