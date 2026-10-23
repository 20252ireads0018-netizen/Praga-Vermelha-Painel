@extends('layout')

@section('title', 'Painel — Praga Vermelha')

@section('conteudo')

    {{-- Abertura --}}
    <section class="pv-hero">
        <div class="pv-container pv-hero__inner">
            <span class="pv-eyebrow">Painel do jogador</span>
            <h1 class="pv-hero__title" id="titulo">Dashboard</h1>
            <p class="pv-hero__lead">
                Crie novos personagens, revise as fichas que você já montou
                e consulte as regras do sistema.
            </p>
            <div class="pv-hero__actions">
                <a href="/fichas" class="pv-btn pv-btn--primary" id="Ficha">Criar ficha</a>
                <a href="{{ route('personagens.index') }}" class="pv-btn pv-btn--ghost">Minhas fichas</a>
            </div>
        </div>
    </section>

    {{-- Atalhos --}}
    <section class="pv-section">
        <div class="pv-container">
            <div class="pv-section__head">
                <h2 class="pv-section__title">Por onde começar</h2>
                <p class="pv-section__desc">Escolha uma ação para continuar sua campanha.</p>
            </div>

            <div class="pv-grid pv-grid--2">

                <article class="pv-card">
                    <div class="pv-card__icon">✠</div>
                    <h3 class="pv-card__title">Criar ficha</h3>
                    <p class="pv-card__text">Monte um novo personagem do zero, escolhendo atributos e origem.</p>
                    <a href="/fichas" class="pv-card__link">Criar personagem ▸</a>
                </article>

                <article class="pv-card">
                    <div class="pv-card__icon">☰</div>
                    <h3 class="pv-card__title">Minhas fichas</h3>
                    <p class="pv-card__text">Veja, abra e edite os personagens que você já criou.</p>
                    <a href="{{ route('personagens.index') }}" class="pv-card__link">Ver personagens ▸</a>
                </article>

                <article class="pv-card">
                    <div class="pv-card__icon">📖</div>
                    <h3 class="pv-card__title">Livro do sistema</h3>
                    <p class="pv-card__text">Consulte as regras e o cenário durante a criação ou a mesa.</p>
                    <a href="{{ route('livro.index') }}" class="pv-card__link">Abrir livro ▸</a>
                </article>

                <article class="pv-card">
                    <div class="pv-card__icon">🎲</div>
                    <h3 class="pv-card__title">Tabletop</h3>
                    <p class="pv-card__text">Mesa virtual para jogar com seu grupo. Em breve.</p>
                    <a href="#" class="pv-card__link">Abrir tabletop ▸</a>
                </article>

            </div>
        </div>
    </section>

@endsection