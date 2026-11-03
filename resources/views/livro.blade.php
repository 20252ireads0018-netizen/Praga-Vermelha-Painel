@extends('layout')

@section('title', $titulo . ' | Praga Vermelha')

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/leitor.css') }}?v={{ @filemtime(public_path('css/leitor.css')) }}">

<section class="pv-reader">
    <div class="pv-container">

        <div class="pv-reader__bar">
            <div>
                <span class="pv-eyebrow">Praga Vermelha RPG</span>
                <h1 class="pv-reader__title">{{ $titulo }}</h1>
            </div>

            <div class="pv-reader__actions">
                <a href="{{ route('livro.pdf', $versao) }}" target="_blank" rel="noopener" class="pv-btn pv-btn--outline">
                    Abrir em nova aba
                </a>
                <a href="{{ route('livro.pdf', $versao) }}?baixar=1" class="pv-btn pv-btn--primary">
                    Baixar PDF
                </a>
                @if($completo)
                    <form method="POST" action="{{ route('livro.sair') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="pv-btn pv-btn--outline">Sair</button>
                    </form>
                @else
                    <a href="{{ route('livro.acesso') }}" class="pv-btn pv-btn--outline">Tenho a chave</a>
                @endif
            </div>
        </div>

        <div class="pv-reader__app" id="leitor"
             data-pdf="{{ route('livro.pdf', $versao) }}"
             data-worker="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js"
             data-chave="{{ $chave }}"
             data-talentos="{{ route('livro.talentos') }}"
             data-talentos-pagina="{{ $completo ? 'pagina' : 'pagina_players' }}">

            {{-- Barra lateral --}}
            <aside class="pv-reader__side" id="leitor-side">
                <div class="pv-reader__tabs" role="tablist">
                    <button type="button" class="is-active" data-tab="sumario" role="tab">Sumário</button>
                    <button type="button" data-tab="marcadores" role="tab">
                        Marcadores <span class="pv-reader__count" id="leitor-count">0</span>
                    </button>
                    <button type="button" data-tab="talentos" role="tab">Talentos</button>
                </div>

                <div class="pv-reader__panel" data-panel="sumario">
                    <ul class="pv-reader__list" id="leitor-sumario"></ul>
                </div>

                <div class="pv-reader__panel" data-panel="marcadores" hidden>
                    <ul class="pv-reader__list" id="leitor-marcadores"></ul>
                    <p class="pv-reader__empty" id="leitor-vazio">
                        Nenhum marcador ainda. Selecione um trecho de texto e clique em "Marcar trecho", ou use a estrela (<x-icone n="estrela"/>) / tecla <kbd>B</kbd> para destacar a página atual.
                    </p>
                </div>

                {{-- Talentos do livro (config/talentos_livro.php): pesquisa e salto para a página --}}
                <div class="pv-reader__panel" data-panel="talentos" hidden>
                    <input type="search" class="pv-reader__busca" id="leitor-talentos-busca" placeholder="Pesquisar talento, requisito ou efeito…" autocomplete="off" aria-label="Pesquisar talentos">
                    <ul class="pv-reader__list" id="leitor-talentos"></ul>
                    <p class="pv-reader__empty" id="leitor-talentos-vazio">Carregando talentos…</p>
                </div>
            </aside>

            {{-- Área principal --}}
            <div class="pv-reader__main">
                <div class="pv-reader__toolbar">
                    <button type="button" class="pv-tool" data-act="side" title="Mostrar/ocultar painel"><x-icone n="menu"/></button>

                    <div class="pv-tool-group">
                        <button type="button" class="pv-tool" data-act="prev" title="Página anterior (←)">‹</button>
                        <input type="number" id="leitor-pagina" class="pv-reader__page" min="1" value="1" aria-label="Número da página">
                        <span class="pv-reader__total" id="leitor-total">/ –</span>
                        <button type="button" class="pv-tool" data-act="next" title="Próxima página (→)">›</button>
                    </div>

                    <div class="pv-tool-group">
                        <button type="button" class="pv-tool" data-act="zoomOut" title="Diminuir zoom">−</button>
                        <span class="pv-reader__zoom" id="leitor-zoom">100%</span>
                        <button type="button" class="pv-tool" data-act="zoomIn" title="Aumentar zoom">+</button>
                        <button type="button" class="pv-tool" data-act="fit" title="Ajustar à largura">↔</button>
                    </div>

                    <div class="pv-tool-group pv-tool-group--end">
                        <button type="button" class="pv-tool pv-tool--mark" data-act="mark" aria-pressed="false" title="Destacar esta página (B)"><x-icone n="estrela"/></button>
                        <button type="button" class="pv-tool" data-act="full" title="Tela cheia"><x-icone n="tela-cheia"/></button>
                    </div>
                </div>

                <div class="pv-reader__stage" id="leitor-stage" tabindex="0">
                    <div class="pv-reader__pages" id="leitor-pages"></div>
                </div>

                {{-- Botão flutuante ao selecionar texto --}}
                <button type="button" class="pv-reader__pop" id="leitor-pop" hidden><x-icone n="estrela"/> Marcar trecho</button>

                <div class="pv-reader__status" id="leitor-status">Carregando livro…</div>
            </div>
        </div>

    </div>
</section>

{{-- Sumário customizado (versão de jogadores). Sem ele, o leitor usa o padrão. --}}
@if(!empty($sumario))
<script type="application/json" id="leitor-sumario-dados">@json($sumario)</script>
@endif
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="{{ asset('js/leitor.js') }}?v={{ @filemtime(public_path('js/leitor.js')) }}"></script>
@endsection