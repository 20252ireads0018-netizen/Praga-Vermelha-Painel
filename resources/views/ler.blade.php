@extends('layout')

@section('title', $livro['titulo'] . ' — Praga Vermelha')

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/leitor.css') }}">

<section class="pv-reader">
    <div class="pv-container">

        <div class="pv-reader__bar">
            <h1 class="pv-reader__title">{{ $livro['titulo'] }}</h1>
            <div class="pv-reader__actions">
                <a href="{{ $livro['url'] }}?baixar=1" class="pv-btn pv-btn--outline">Baixar PDF</a>
            </div>
        </div>

        <div class="pv-reader__app" id="leitor"
             data-pdf="{{ $livro['url'] }}"
             data-worker="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js"
             data-chave="{{ $livro['chave'] }}">

            <aside class="pv-reader__side">
                <div class="pv-reader__tabs">
                    <button type="button" class="is-active" data-tab="sumario">Sumário</button>
                    <button type="button" data-tab="marcadores">Marcadores
                        <span class="pv-reader__count" id="leitor-count">0</span>
                    </button>
                </div>

                <div class="pv-reader__panel" data-panel="sumario">
                    <ul class="pv-reader__list" id="leitor-sumario"></ul>
                </div>

                <div class="pv-reader__panel" data-panel="marcadores" hidden>
                    <ul class="pv-reader__list" id="leitor-marcadores"></ul>
                    <p class="pv-reader__empty" id="leitor-vazio">
                        Nenhum marcador ainda. Selecione um trecho do texto e clique em
                        “Marcar trecho”, ou use a estrela / a tecla <kbd>B</kbd> para marcar a página.
                    </p>
                </div>
            </aside>

            <div class="pv-reader__main">
                <div class="pv-reader__toolbar">
                    <div class="pv-tool-group">
                        <button type="button" class="pv-tool" data-act="side" title="Mostrar/ocultar painel"><x-icone n="menu"/></button>
                    </div>
                    <div class="pv-tool-group">
                        <button type="button" class="pv-tool" data-act="prev" title="Página anterior">‹</button>
                        <input type="number" class="pv-reader__page" id="leitor-pagina" min="1" value="1" aria-label="Página">
                        <span class="pv-reader__total" id="leitor-total">/ 0</span>
                        <button type="button" class="pv-tool" data-act="next" title="Próxima página">›</button>
                    </div>
                    <div class="pv-tool-group">
                        <button type="button" class="pv-tool" data-act="zoomOut" title="Diminuir">−</button>
                        <span class="pv-reader__zoom" id="leitor-zoom">100%</span>
                        <button type="button" class="pv-tool" data-act="zoomIn" title="Aumentar">+</button>
                        <button type="button" class="pv-tool" data-act="fit" title="Ajustar à largura">⤢</button>
                    </div>
                    <div class="pv-tool-group pv-tool-group--end">
                        <button type="button" class="pv-tool pv-tool--mark" data-act="mark" title="Marcar página (B)"><x-icone n="estrela"/></button>
                        <button type="button" class="pv-tool" data-act="full" title="Tela cheia"><x-icone n="tela-cheia"/></button>
                    </div>
                </div>

                <div class="pv-reader__stage" id="leitor-stage" tabindex="0">
                    <div class="pv-reader__pages" id="leitor-pages"></div>
                </div>

                <button type="button" class="pv-reader__pop" id="leitor-pop" hidden>Marcar trecho</button>
                <div class="pv-reader__status" id="leitor-status">Carregando livro…</div>
            </div>
        </div>
    </div>
</section>

<script type="application/json" id="leitor-sumario-dados">@json($livro['sumario'])</script>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="{{ asset('js/leitor.js') }}?v={{ @filemtime(public_path('js/leitor.js')) }}"></script>
@endsection