@extends('layouts.app')

@section('title', 'Praga Vermelha RPG')

@section('conteudo')

{{-- HERO --}}
<section class="pv-hero">
    <div class="pv-container pv-hero__inner">
        <span class="pv-eyebrow">Um RPG de horror e ruína</span>
        <h1 class="pv-hero__title">Praga Vermelha</h1>
        <p class="pv-hero__lead">
            Um mundo consumido pela carne e pela escuridão. Leia o livro, crie seu personagem
            e mergulhe em histórias sombrias onde sobreviver já é uma vitória.
        </p>
        <div class="pv-hero__actions">
            <a href="/fichas" class="pv-btn pv-btn--primary">Criar ficha</a>
            <a href="#sistema" class="pv-btn pv-btn--ghost">Conhecer o sistema</a>
        </div>
    </div>
</section>

{{-- PRAGA VERMELHA RPG --}}
<section class="pv-section" id="sistema">
    <div class="pv-container">
        <header class="pv-section__head">
            <span class="pv-eyebrow">Sistema</span>
            <h2 class="pv-section__title">Praga Vermelha RPG</h2>
            <p class="pv-section__desc">Tudo o que você precisa para jogar, em um só lugar.</p>
        </header>

        <div class="pv-grid pv-grid--3">
            <article class="pv-card">
                <div class="pv-card__icon">📖</div>
                <h3 class="pv-card__title">Livro do sistema</h3>
                <p class="pv-card__text">Regras, classes, bestiário e o cenário completo de Praga Vermelha.</p>
                <a href="#" class="pv-card__link">Acessar o livro →</a>
            </article>

            <article class="pv-card">
                <div class="pv-card__icon">✒️</div>
                <h3 class="pv-card__title">Criar ficha</h3>
                <p class="pv-card__text">Monte seu personagem passo a passo e deixe tudo pronto para a mesa.</p>
                <a href="/fichas" class="pv-card__link">Criar nova ficha →</a>
            </article>

            <article class="pv-card">
                <div class="pv-card__icon">🗂️</div>
                <h3 class="pv-card__title">Minhas fichas</h3>
                <p class="pv-card__text">Consulte e gerencie todos os personagens que você já criou.</p>
                <a href="{{ route('personagens.index') }}" class="pv-card__link">Ver minhas fichas →</a>
            </article>
        </div>
    </div>
</section>

{{-- HISTÓRIAS --}}
<section class="pv-section pv-section--alt" id="historias">
    <div class="pv-container">
        <header class="pv-section__head">
            <span class="pv-eyebrow">Universo</span>
            <h2 class="pv-section__title">Histórias de Praga Vermelha</h2>
            <p class="pv-section__desc">Narrativas que expandem o mundo e a sua mesa.</p>
        </header>

        <div class="pv-grid pv-grid--3">
            <article class="pv-book">
                <div class="pv-book__cover pv-book__cover--a">
                    <span>O Cavaleiro Negro</span>
                </div>
                <div class="pv-book__body">
                    <h3 class="pv-book__title">O Cavaleiro Negro</h3>
                    <p class="pv-book__text">Um guerreiro sem nome cruza terras condenadas em busca de redenção.</p>
                    <a href="#" class="pv-card__link">Ler agora →</a>
                </div>
            </article>

            <article class="pv-book">
                <div class="pv-book__cover pv-book__cover--b">
                    <span>O Mundo de Carne</span>
                </div>
                <div class="pv-book__body">
                    <h3 class="pv-book__title">O Mundo de Carne</h3>
                    <p class="pv-book__text">Onde a praga se espalha, a própria terra passa a respirar e sangrar.</p>
                    <a href="#" class="pv-card__link">Ler agora →</a>
                </div>
            </article>

            <article class="pv-book">
                <div class="pv-book__cover pv-book__cover--c">
                    <span>Contos da Ruína</span>
                </div>
                <div class="pv-book__body">
                    <h3 class="pv-book__title">Contos da Ruína</h3>
                    <p class="pv-book__text">Histórias curtas sobre os restos de um mundo que ainda se recusa a morrer.</p>
                    <a href="#" class="pv-card__link">Ler agora →</a>
                </div>
            </article>
        </div>
    </div>
</section>

{{-- OUTROS RPGS --}}
<section class="pv-section" id="outros-rpgs">
    <div class="pv-container">
        <header class="pv-section__head">
            <span class="pv-eyebrow">Mais aventuras</span>
            <h2 class="pv-section__title">Outros RPGs</h2>
            <p class="pv-section__desc">Jogue outros sistemas com criadores de ficha dedicados.</p>
        </header>

        <div class="pv-grid pv-grid--2">
            <article class="pv-system">
                <div class="pv-system__badge">D&amp;D</div>
                <div class="pv-system__content">
                    <h3 class="pv-system__title">Dungeons &amp; Dragons</h3>
                    <p class="pv-card__text">O clássico da fantasia medieval. Crie seu herói e parta para a masmorra.</p>
                    <a href="#" class="pv-btn pv-btn--outline">Criar ficha de D&amp;D</a>
                </div>
            </article>

            <article class="pv-system">
                <div class="pv-system__badge">T20</div>
                <div class="pv-system__content">
                    <h3 class="pv-system__title">Tormenta</h3>
                    <p class="pv-card__text">Aventuras épicas em Arton. Monte seu personagem e enfrente a Tormenta.</p>
                    <a href="#" class="pv-btn pv-btn--outline">Criar ficha de Tormenta</a>
                </div>
            </article>
        </div>
    </div>
</section>

@endsection