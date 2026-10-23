@extends('layout')

@section('conteudo')
@php
    // $maldicoes vem do MaldicaoController@index (config/maldicoes.php)
    $maldicoes = $maldicoes ?? [];

    // Raridades existentes no catálogo, na ordem em que aparecem
    $raridades = collect($maldicoes)
        ->pluck('raridade')
        ->filter(fn ($r) => is_string($r) && trim($r) !== '')
        ->unique()
        ->values();
@endphp

<style>
    .maldicao-filtros{
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 18px;
    }

    .maldicao-busca{
        width: 100%;
        box-sizing: border-box;
    }

    .maldicao-filtro-raridade{
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .filtro-chip{
        cursor: pointer;
        background: #1f1212;
        border: 1px solid var(--linha);
        border-radius: 999px;
        padding: 5px 14px;

        font-family: var(--fonte-titulo);
        font-size: .68rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--tinta-fraca);

        transition: .25s;
    }

    .filtro-chip:hover{
        border-color: var(--sangue-2);
        color: var(--tinta);
    }

    .filtro-chip.ativo{
        background: rgba(189, 3, 3, .22);
        border-color: var(--sangue-2);
        color: var(--sangue-4);
    }

    .maldicao-contagem{
        margin: 0;
        font-size: .8rem;
        color: var(--tinta-fraca);
    }

    .maldicao-indice-card[hidden]{
        display: none !important;
    }
</style>

<div class="container">
    <div class="card ficha-card maldicao-card maldicao-pagina">

        <div class="maldicao-navegacao">
            <a href="{{ url('/') }}" class="botao-voltar">← Início</a>
        </div>

        {{-- ========== CABEÇALHO ========== --}}
        <div class="maldicao-header">
            <span class="maldicao-selo">✠</span>
            <h1 class="maldicao-titulo">Maldições</h1>
            <div class="maldicao-etiquetas">
                <span class="maldicao-etiqueta">{{ count($maldicoes) }} {{ count($maldicoes) === 1 ? 'registro' : 'registros' }}</span>
            </div>
        </div>

        <hr>

        {{-- ========== ÍNDICE ========== --}}
        <section class="maldicao-secao">
            <h2 class="maldicao-secao-titulo">Catálogo</h2>

            @if(count($maldicoes) === 0)
                <p class="tecnica-vazia">Nenhuma maldição cadastrada ainda.</p>
            @else

                {{-- Pesquisa e filtro --}}
                <div class="maldicao-filtros">
                    <input type="search" id="maldicao-busca" class="maldicao-busca"
                           placeholder="Pesquisar maldição por nome" autocomplete="off">

                    @if($raridades->isNotEmpty())
                        <div class="maldicao-filtro-raridade" id="maldicao-filtro-raridade" role="group" aria-label="Filtrar por raridade">
                            <button type="button" class="filtro-chip ativo" data-raridade="">Todas</button>
                            @foreach($raridades as $r)
                                <button type="button" class="filtro-chip" data-raridade="{{ $r }}">{{ $r }}</button>
                            @endforeach
                        </div>
                    @endif

                    <p class="maldicao-contagem" id="maldicao-contagem" aria-live="polite"></p>
                </div>

                <div class="maldicao-indice" id="maldicao-indice">
                    @foreach($maldicoes as $slug => $maldicao)
                        @php
                            $raridade     = $maldicao['raridade'] ?? '';
                            $raridadeSlug = \Illuminate\Support\Str::of($raridade)->ascii()->lower()->slug('');
                            $primeiro     = preg_split('/\R+/', trim($maldicao['descricao'] ?? ''))[0] ?? '';
                            $resumo       = \Illuminate\Support\Str::limit(trim($primeiro), 150);
                            $qtdAmeacas   = count($maldicao['ameacas'] ?? []);
                            $qtdTecnicas  = count($maldicao['tecnicas'] ?? []);
                            $nomeExibido  = $maldicao['nome'] ?? $slug;
                        @endphp

                        <a href="{{ route('maldicoes.show', $slug) }}"
                           class="maldicao-indice-card maldicao-pagina raridade-{{ $raridadeSlug }}"
                           data-nome="{{ $nomeExibido }}"
                           data-raridade="{{ $raridade }}">

                            @if($raridade !== '')
                                <span class="maldicao-raridade">{{ $raridade }}</span>
                            @endif

                            <h3 class="maldicao-indice-nome">{{ $nomeExibido }}</h3>

                            @if($resumo !== '')
                                <p class="maldicao-indice-resumo">{{ $resumo }}</p>
                            @endif

                            <span class="maldicao-indice-rodape">
                                {{ $qtdAmeacas }} {{ $qtdAmeacas === 1 ? 'ameaça' : 'ameaças' }}
                                · {{ $qtdTecnicas }} {{ $qtdTecnicas === 1 ? 'técnica' : 'técnicas' }}
                            </span>
                        </a>
                    @endforeach
                </div>

                <p class="tecnica-vazia" id="maldicao-sem-resultado" hidden>Nenhuma maldição encontrada.</p>
            @endif
        </section>

    </div>
</div>

@if(count($maldicoes) > 0)
<script>
(function(){
    const busca    = document.getElementById("maldicao-busca");
    const filtro   = document.getElementById("maldicao-filtro-raridade");
    const contagem = document.getElementById("maldicao-contagem");
    const vazio    = document.getElementById("maldicao-sem-resultado");
    const cards    = Array.from(document.querySelectorAll("#maldicao-indice .maldicao-indice-card"));

    let raridadeAtiva = "";

    function normalizar(texto){
        return (texto || "")
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .trim();
    }

    function filtrar(){
        const termo = normalizar(busca.value);
        let visiveis = 0;

        cards.forEach(function(card){
            const okNome     = termo === "" || normalizar(card.dataset.nome).indexOf(termo) !== -1;
            const okRaridade = raridadeAtiva === "" || card.dataset.raridade === raridadeAtiva;
            const mostrar    = okNome && okRaridade;

            card.hidden = !mostrar;
            if(mostrar) visiveis++;
        });

        vazio.hidden = visiveis > 0;
        contagem.textContent = visiveis === cards.length
            ? ""
            : "Mostrando " + visiveis + " de " + cards.length;
    }

    busca.addEventListener("input", filtrar);

    if(filtro){
        filtro.addEventListener("click", function(e){
            const botao = e.target.closest(".filtro-chip");
            if(!botao) return;

            raridadeAtiva = botao.dataset.raridade;
            filtro.querySelectorAll(".filtro-chip").forEach(function(b){
                b.classList.toggle("ativo", b === botao);
            });
            filtrar();
        });
    }

    filtrar();
})();
</script>
@endif
@endsection