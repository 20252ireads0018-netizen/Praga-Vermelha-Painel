@extends('layout')

@section('title', 'Campanhas')

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">

<div class="camp-wrap">
    <header class="camp-topo">
        <h1>Campanhas</h1>
        <p class="camp-sub">Seu código de jogador: <strong class="camp-codigo">{{ $user->codigo_jogador }}</strong>
            <span class="camp-dica">— passe este código ao mestre para ser convidado.</span></p>
    </header>

    @include('campanhas._alertas')

    @if ($convites->isNotEmpty())
        <section class="camp-card">
            <h2>Convites pendentes</h2>
            @foreach ($convites as $c)
                <div class="camp-linha">
                    <div>
                        <strong>{{ $c->nome }}</strong>
                        <span class="camp-dica">mestre: {{ $c->mestre->name ?? '—' }}</span>
                    </div>
                    <div class="camp-acoes">
                        <form method="POST" action="{{ route('campanhas.aceitar', $c) }}">@csrf
                            <button class="camp-btn camp-btn-primario">Aceitar</button>
                        </form>
                        <form method="POST" action="{{ route('campanhas.recusar', $c) }}">@csrf
                            <button class="camp-btn">Recusar</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    @if ($pedidosLivro->isNotEmpty())
        <section class="camp-card">
            <h2>Pedidos para ler o livro do mestre</h2>
            <p class="camp-dica">Estes jogadores das suas campanhas clicaram em "Sou mestre". Aprovar libera para eles o livro completo, com os segredos do mestre.</p>
            @foreach ($pedidosLivro as $p)
                <div class="camp-linha">
                    <div>
                        <strong>{{ $p->name }}</strong>
                        <span class="camp-dica">pediu em {{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m/Y') }}</span>
                    </div>
                    <div class="camp-acoes">
                        <form method="POST" action="{{ route('livro.mestre.decidir', $p->id) }}">@csrf
                            <input type="hidden" name="acao" value="aprovar">
                            <button class="camp-btn camp-btn-primario">Aprovar</button>
                        </form>
                        <form method="POST" action="{{ route('livro.mestre.decidir', $p->id) }}">@csrf
                            <input type="hidden" name="acao" value="recusar">
                            <button class="camp-btn">Recusar</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="camp-card">
        <h2>Campanhas que eu mestro</h2>
        @forelse ($minhas as $c)
            <a class="camp-item" href="{{ route('campanhas.show', $c) }}">
                <div>
                    <strong>{{ $c->nome }}</strong>
                    @if ($c->sessao_ativa)<span class="camp-badge camp-badge-on">sessão ativa</span>@endif
                    <div class="camp-dica">{{ \Illuminate\Support\Str::limit($c->descricao, 110) }}</div>
                </div>
                <span class="camp-dica">{{ $c->jogadores_count }} jogador(es)</span>
            </a>
        @empty
            <p class="camp-vazio">Você ainda não criou nenhuma campanha.</p>
        @endforelse

        <details class="camp-detalhes">
            <summary class="camp-btn camp-btn-primario">+ Nova campanha</summary>
            <form method="POST" action="{{ route('campanhas.store') }}" class="camp-form">
                @csrf
                <label>Nome
                    <input type="text" name="nome" maxlength="80" required value="{{ old('nome') }}">
                </label>
                <label>Descrição
                    <textarea name="descricao" rows="3" maxlength="2000">{{ old('descricao') }}</textarea>
                </label>
                <button class="camp-btn camp-btn-primario">Criar campanha</button>
            </form>
        </details>
    </section>

    <section class="camp-card">
        <h2>Campanhas em que eu jogo</h2>
        @forelse ($participo as $c)
            <a class="camp-item" href="{{ route('campanhas.show', $c) }}">
                <div>
                    <strong>{{ $c->nome }}</strong>
                    @if ($c->sessao_ativa)<span class="camp-badge camp-badge-on">sessão ativa</span>@endif
                    <div class="camp-dica">mestre: {{ $c->mestre->name ?? '—' }}</div>
                </div>
            </a>
        @empty
            <p class="camp-vazio">Você não participa de nenhuma campanha ainda.</p>
        @endforelse
    </section>
</div>
@endsection