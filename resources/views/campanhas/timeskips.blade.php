@extends('layout')

@section('title', 'Time skips · ' . $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">
<style>
    #ts-pagina [hidden], #ts-janela [hidden], #ts-janela[hidden] { display: none !important; }
    .ts-barra { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 14px; }
    .ts-barra select { min-height: 36px; }
    .ts-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
    .ts-cartao { border: 1px solid var(--camp-borda, rgba(255,255,255,.14)); border-radius: 12px; padding: 12px; background: rgba(0, 0, 0, .22); display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .ts-cartao h3 { margin: 0; font-size: 1.05rem; flex: 1; overflow-wrap: anywhere; }
    .ts-cab { display: flex; align-items: center; gap: 8px; }
    .ts-status { font-size: .72rem; padding: 2px 8px; border-radius: 999px; background: rgba(255, 255, 255, .1); white-space: nowrap; }
    .ts-status.aberto { background: rgba(22, 163, 74, .35); }
    .ts-status.fechado { background: rgba(185, 28, 28, .4); }
    .ts-meta { font-size: .82rem; opacity: .75; }
    .ts-botoes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; align-items: center; }
    .ts-botoes select { min-height: 30px; font-size: .8rem; }
    .ts-janela { position: fixed; inset: 0; z-index: 1300; background: rgba(0, 0, 0, .65); display: flex; align-items: flex-start; justify-content: center; overflow: auto; padding: 24px 12px; }
    .ts-caixa { width: min(1180px, 100%); background: #160f17; border: 1px solid rgba(255, 255, 255, .15); border-radius: 14px; padding: 16px; box-sizing: border-box; }
    .ts-caixa-cab { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .ts-caixa-cab h2 { flex: 1; margin: 0; font-size: 1.2rem; overflow-wrap: anywhere; }
    .ts-jogo { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 16px; align-items: start; }
    @media (max-width: 900px) { .ts-jogo { grid-template-columns: minmax(0, 1fr); } }
    .ts-jogo h4 { margin: 16px 0 6px; font-size: 1rem; }
    .ts-jogo h5 { margin: 10px 0 4px; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; opacity: .7; }
    .ts-texto { white-space: pre-wrap; font-size: .86rem; opacity: .85; margin: 4px 0; }
    .ts-jogo details { border: 1px solid rgba(255, 255, 255, .1); border-radius: 10px; padding: 6px 10px; margin: 6px 0; background: rgba(255, 255, 255, .03); }
    .ts-jogo summary { cursor: pointer; font-weight: 700; font-size: .9rem; }
    .ts-ramos { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 8px; }
    .ts-ramo { text-align: left; padding: 10px; border-radius: 10px; border: 1px solid rgba(255, 255, 255, .15); background: rgba(0, 0, 0, .25); color: inherit; cursor: pointer; font: inherit; }
    .ts-ramo strong { display: block; margin-bottom: 3px; }
    .ts-ramo span { font-size: .78rem; opacity: .75; }
    .ts-ramo.ativo { background: rgba(185, 28, 28, .42); border-color: rgba(248, 113, 113, .85); }
    .ts-ramo:disabled { cursor: default; }
    .ts-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 8px 10px; border-radius: 10px; background: rgba(255, 255, 255, .04); margin-bottom: 5px; }
    .ts-item.tem { background: rgba(99, 102, 241, .2); }
    .ts-item.bloqueado { opacity: .5; }
    .ts-item strong { font-size: .92rem; }
    .ts-item p { margin: 3px 0 0; font-size: .8rem; opacity: .8; white-space: pre-wrap; }
    .ts-tags { display: inline-flex; flex-wrap: wrap; gap: 4px; margin-left: 6px; vertical-align: middle; }
    .ts-tag { font-size: .7rem; padding: 1px 7px; border-radius: 999px; background: rgba(255, 255, 255, .1); }
    .ts-tag.req { background: rgba(185, 28, 28, .35); }
    .ts-ganho { font-size: .76rem; color: #a5b4fc; margin-top: 3px; }
    .ts-passo { display: inline-flex; align-items: center; gap: 4px; }
    .ts-passo button { width: 32px; height: 32px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, .18); background: rgba(0, 0, 0, .3); color: inherit; cursor: pointer; font-size: 1rem; }
    .ts-passo button:disabled { opacity: .35; cursor: default; }
    .ts-passo b { min-width: 24px; text-align: center; }
    .ts-resumo { position: sticky; top: 12px; background: rgba(0, 0, 0, .35); border: 1px solid rgba(255, 255, 255, .12); border-radius: 12px; padding: 12px; font-size: .88rem; }
    .ts-contador { text-align: center; margin-bottom: 8px; }
    .ts-contador b { font-size: 2rem; }
    .ts-contador.estourou b { color: #f87171; }
    .ts-medidor { height: 8px; border-radius: 999px; background: rgba(255, 255, 255, .1); overflow: hidden; margin: 4px 0 10px; }
    .ts-medidor i { display: block; height: 100%; background: linear-gradient(90deg, #b91c1c, #f87171); }
    .ts-resumo label { display: flex; flex-direction: column; gap: 3px; font-size: .8rem; margin-bottom: 6px; }
    .ts-resumo table { width: 100%; border-collapse: collapse; margin: 6px 0; }
    .ts-resumo td { padding: 3px 4px; border-bottom: 1px solid rgba(255, 255, 255, .06); }
    .ts-resumo td:last-child { text-align: right; font-weight: 700; white-space: nowrap; }
    .ts-resumo .negativo { color: #f87171; }
    .ts-erros { color: #fca5a5; font-size: .8rem; margin: 6px 0; padding-left: 18px; }
    .ts-resumo textarea { min-height: 70px; resize: vertical; }
    .ts-resumo .camp-btn { width: 100%; margin-top: 6px; }
    .ts-tabela { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .ts-tabela th, .ts-tabela td { padding: 6px; border-bottom: 1px solid rgba(255, 255, 255, .08); text-align: left; vertical-align: top; }
    .ts-rolagem { overflow-x: auto; }
    /* editor de regras */
    .ts-ed label { display: flex; flex-direction: column; gap: 3px; font-size: .82rem; margin-bottom: 8px; min-width: 0; }
    .ts-ed textarea { min-height: 70px; resize: vertical; }
    .ts-ed-linha { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; }
    .ts-ed fieldset { border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; padding: 8px 10px; margin: 10px 0; }
    .ts-ed legend { font-weight: 700; padding: 0 6px; }
    .ts-ed details { border: 1px solid rgba(255, 255, 255, .1); border-radius: 8px; padding: 6px 8px; margin: 6px 0; background: rgba(255, 255, 255, .03); }
    .ts-ed summary { cursor: pointer; font-weight: 600; }
    .ts-ef { display: grid; grid-template-columns: minmax(120px, 2fr) repeat(3, minmax(60px, 1fr)) minmax(110px, 1.4fr) auto; gap: 6px; align-items: end; margin-bottom: 4px; }
    @media (max-width: 700px) { .ts-ef { grid-template-columns: 1fr 1fr; } }
    .ts-ef label { margin: 0; }
    .ts-dica { font-size: .78rem; opacity: .65; margin: 4px 0; }
    .ts-salvar-ed { position: sticky; bottom: 0; background: #160f17; padding: 10px 0 0; display: flex; gap: 8px; flex-wrap: wrap; }
</style>

<div class="camp-wrap camp-wrap-larga" id="ts-pagina">
    <a href="{{ route('campanhas.show', $campanha) }}" class="camp-voltar">← {{ $campanha->nome }}</a>
    <h1>Time skips</h1>
    <p class="camp-sub">{{ $souMestre ? 'Monte os seus time skips: escolhas, ramos, atividades, propriedades e salário. Comece do zero ou de um modelo pronto e edite tudo. Abra quando os jogadores puderem escolher.' : 'Escolha o ramo, gaste as escolhas e compre propriedades. As contas aparecem na hora.' }}</p>

    @unless ($tabela)
        <p class="camp-aviso">As tabelas de time skip ainda não existem no banco. Rode no phpMyAdmin o SQL da migração 2026_11_02_000023 (create_time_skips).</p>
    @endunless

    <p class="jg-msg" id="ts-msg" hidden></p>
    @if ($souMestre)
        <div class="ts-barra">
            <button type="button" class="camp-btn camp-btn-primario" id="ts-novo">+ Novo time skip</button>
            <select id="ts-modelo" aria-label="Começar de um modelo"><option value="">Começar de um modelo…</option></select>
        </div>
    @endif
    <div class="ts-lista" id="ts-lista"></div>
</div>

<div class="ts-janela" id="ts-janela" hidden>
    <div class="ts-caixa" role="dialog" aria-modal="true" aria-labelledby="ts-janela-titulo">
        <div class="ts-caixa-cab"><h2 id="ts-janela-titulo">Time skip</h2><button type="button" class="camp-btn camp-btn-pequeno" id="ts-fechar">Fechar</button></div>
        <div id="ts-janela-corpo"></div>
    </div>
</div>

<script type="application/json" id="ts-dados">{!! json_encode([
    'mestre'    => $souMestre,
    'eu'        => (int) auth()->id(),
    'csrf'      => csrf_token(),
    'skips'     => $skips,
    'respostas' => $respostas,
    'fichas'    => $fichas,
    'modelos'   => $modelos,
    'url'       => [
        'salvar' => route('campanhas.timeskips.salvar', $campanha),
        'base'   => url('/campanhas/' . $campanha->id . '/timeskips'),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ asset('js/timeskip-calc.js') }}?v={{ @filemtime(public_path('js/timeskip-calc.js')) }}"></script>
<script src="{{ asset('js/campanha-timeskip.js') }}?v={{ @filemtime(public_path('js/campanha-timeskip.js')) }}"></script>
@endsection
