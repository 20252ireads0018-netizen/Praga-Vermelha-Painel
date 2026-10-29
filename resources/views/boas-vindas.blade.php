@extends('layout')

@section('title', 'Boas-vindas — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container" style="max-width:820px">
        <span class="pv-eyebrow">Boas-vindas, {{ auth()->user()->name }}</span>
        <h1 class="pv-section__title">O que te traz a Praga Vermelha?</h1>
        <p class="pv-section__desc">Escolha para começarmos pelo caminho certo. Dá para fazer as duas coisas depois.</p>

        <form method="POST" action="{{ route('boas-vindas.salvar') }}" class="bv-escolhas">
            @csrf
            <button type="submit" name="objetivo" value="jogar" class="bv-escolha">
                <span class="bv-escolha__icone">🎲</span>
                <strong>Quero jogar o RPG</strong>
                <span>Criar fichas, montar campanhas e jogar com o grupo no painel.</span>
            </button>
            <button type="submit" name="objetivo" value="livros" class="bv-escolha">
                <span class="bv-escolha__icone">📖</span>
                <strong>Quero comprar os livros</strong>
                <span>O livro do sistema e os livros de histórias, digitais ou físicos.</span>
            </button>
        </form>

        @if ($diasTeste > 0)
            <p class="bv-nota">Sua conta tem {{ $diasTeste }} {{ $diasTeste === 1 ? 'dia' : 'dias' }} de acesso completo, sem anúncios e sem limites.
                Comprando o livro do sistema, esse acesso fica para sempre. <a href="{{ route('planos.index') }}">Ver planos</a></p>
        @endif
    </div>
</section>
@endsection
