@extends('layout')

@section('conteudo')
@php
    // $maldicoes (config detalhado) e $lista (itens visíveis) vêm do MaldicaoController@index
    $maldicoes        = $maldicoes ?? [];
    $lista            = $lista ?? [];
    $ehMestre         = $ehMestre ?? false;
    $temChave         = $temChave ?? false;
    $podeVerRestritas = $podeVerRestritas ?? false;
    $chaveStatus      = session('chave_status');

    // Raridades existentes no catálogo, na ordem em que aparecem
    $raridades = collect($lista)
        ->map(fn ($i) => $maldicoes[$i['slug'] ?? '']['raridade'] ?? null)
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

    .chave-bloco{
        margin: 0 0 16px;
        padding: 10px 12px;
        background: rgba(0,0,0,.25);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 8px;
        font-size: .85rem;
    }
    .chave-bloco form{ display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0; }
    .chave-bloco input[type=text]{ flex: 1 1 160px; min-width: 0; box-sizing: border-box; }
    .chave-bloco .chave-titulo{ margin: 0 0 6px; font-size: .72rem; letter-spacing: 1px; text-transform: uppercase; color: var(--tinta-fraca); }
    .chave-bloco .chave-msg{ margin: 8px 0 0; color: var(--tinta-fraca); }
    .chave-bloco .chave-ok{ color: var(--sangue-4); }

    .maldicao-meta{ margin: 2px 0 0; font-size: .78rem; color: var(--tinta-fraca); }
    .maldicao-tag{
        display: inline-block;
        margin: 6px 6px 0 0;
        padding: 1px 8px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 999px;
        font-size: .62rem;
        letter-spacing: .5px;
        color: var(--tinta-fraca);
    }
    .maldicao-tag.restrita{ border-color: rgba(220,70,70,.4); }
    div.maldicao-indice-card{ cursor: default; }

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
                <span class="maldicao-etiqueta">{{ count($lista) }} {{ count($lista) === 1 ? 'registro' : 'registros' }}</span>
            </div>
        </div>

        <hr>

        {{-- ========== CHAVE DE MALDIÇÕES ========== --}}
        <div class="chave-bloco">
            <p class="chave-titulo">Chave de maldições</p>
            @if($ehMestre)
                <span>Você é mestre: todas as maldições estão visíveis.</span>
            @else
                @auth
                    <form method="POST" action="{{ route('maldicoes.chave') }}">
                        @csrf
                        @if($temChave)
                            <span class="chave-ok">Chave aceita</span>
                            <input type="hidden" name="chave" value="">
                            <button type="submit" class="filtro-chip">Remover chave</button>
                        @else
                            <input type="text" name="chave" maxlength="80" autocomplete="off" placeholder="Chave do mestre" aria-label="Chave de maldições">
                            <button type="submit" class="filtro-chip">Usar chave</button>
                        @endif
                    </form>
                @endauth
            @endif

            @if($chaveStatus === 'aceita')
                <p class="chave-msg chave-ok">Chave aceita: maldições não catalogadas liberadas.</p>
            @elseif($chaveStatus === 'invalida')
                <p class="chave-msg">Essa chave não corresponde a nenhuma campanha.</p>
            @elseif($chaveStatus === 'removida')
                <p class="chave-msg">Chave removida.</p>
            @endif
        </div>

        {{-- ========== ÍNDICE ========== --}}
        <section class="maldicao-secao">
            <h2 class="maldicao-secao-titulo">Catálogo</h2>

            @if(count($lista) === 0)
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
                    @foreach($lista as $item)
                        @php
                            $slug         = $item['slug'] ?? null;
                            $maldicao     = $slug ? ($maldicoes[$slug] ?? []) : [];
                            $raridade     = $maldicao['raridade'] ?? '';
                            $raridadeSlug = \Illuminate\Support\Str::of($raridade)->ascii()->lower()->slug('');
                            $nomeExibido  = $item['nome'] ?? ($maldicao['nome'] ?? '');
                            $tag          = $slug ? 'a' : 'div';
                        @endphp

                        <{{ $tag }} @if($slug) href="{{ route('maldicoes.show', $slug) }}" @endif
                           class="maldicao-indice-card maldicao-pagina raridade-{{ $raridadeSlug }}"
                           data-nome="{{ $nomeExibido }} {{ $item['apelido'] ?? '' }}"
                           data-raridade="{{ $raridade }}">

                            @if($raridade !== '')
                                <span class="maldicao-raridade">{{ $raridade }}</span>
                            @endif

                            <h3 class="maldicao-indice-nome">{{ $nomeExibido }}</h3>

                            @if(!empty($item['categoria']))
                                <p class="maldicao-meta">{{ $item['categoria'] }}</p>
                            @endif
                            @if(!empty($item['orgao']))
                                <p class="maldicao-meta">Órgão hospedado: {{ $item['orgao'] }}</p>
                            @endif
                            @if(!empty($item['apelido']))
                                <p class="maldicao-meta">Apelido: {{ $item['apelido'] }}</p>
                            @endif

                            <span>
                                @if(!$slug)<span class="maldicao-tag">Sem página detalhada</span>@endif
                                @if(!empty($item['restrita']))<span class="maldicao-tag restrita">Não catalogada</span>@endif
                            </span>
                        </{{ $tag }}>
                    @endforeach
                </div>

                <p class="tecnica-vazia" id="maldicao-sem-resultado" hidden>Nenhuma maldição encontrada.</p>
            @endif
        </section>

    </div>
</div>

@if(count($lista) > 0)
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
            .normalize("NFD").replace(/[̀-ͯ]/g, "")
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
