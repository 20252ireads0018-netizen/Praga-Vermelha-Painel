@extends('layout')

@section('title', 'Vídeos explicativos · Praga Vermelha')

@section('conteudo')
<style>
    .vd { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
    .vd h1 { font-family: var(--fonte-titulo); margin: 0 0 6px; }
    .vd-sub { color: var(--pv-muted, #a89494); margin: 0 0 22px; }
    .vd-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 22px; }
    .vd-item { display: flex; flex-direction: column; gap: 10px; padding: 12px; border-radius: 14px; background: var(--pv-surface, #1f1212); border: 1px solid var(--pv-border, #472020); }
    .vd-video { width: 100%; aspect-ratio: 9 / 16; border-radius: 10px; background: #000; display: block; object-fit: cover; }
    .vd-tema { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--pv-red-bright, #d63c3c); }
    .vd-item h2 { margin: 0; font-size: 1.1rem; font-family: var(--fonte-titulo); line-height: 1.3; }
    .vd-item p { margin: 0; font-size: .9rem; color: var(--pv-muted, #a89494); line-height: 1.45; }
    .vd-baixar { display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: auto; padding: 10px 14px; border-radius: 8px;
        background: var(--pv-red, #b62525); color: #fff; text-decoration: none; font-weight: 600; }
    .vd-baixar:hover { background: var(--pv-red-bright, #d63c3c); }
    .vd-tamanho { font-weight: 400; opacity: .8; font-size: .85em; }
</style>

<div class="vd">
    <h1>Vídeos explicativos</h1>
    <p class="vd-sub">Técnicas, maldições e regras do Praga Vermelha explicadas em poucos minutos. Assista aqui ou baixe para ver quando quiser.</p>

    <div class="vd-lista">
        @foreach ($videos as $v)
            <article class="vd-item" id="{{ $v['id'] }}">
                <video class="vd-video" controls playsinline preload="none" poster="{{ asset($v['capa']) }}?v={{ $v['versao'] }}">
                    <source src="{{ asset($v['arquivo']) }}?v={{ $v['versao'] }}" type="video/mp4">
                </video>
                <span class="vd-tema">{{ $v['tema'] }}</span>
                <h2>{{ $v['titulo'] }}</h2>
                <p>{{ $v['descricao'] }}</p>
                <a class="vd-baixar" href="{{ asset($v['arquivo']) }}" download="{{ $v['id'] }}.mp4">
                    <x-icone n="baixar"/> Baixar vídeo @if ($v['mb'])<span class="vd-tamanho">· {{ $v['mb'] }} MB</span>@endif
                </a>
            </article>
        @endforeach
    </div>
</div>
@endsection
