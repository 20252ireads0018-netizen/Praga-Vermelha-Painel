@extends('layout')

@section('title', 'Planos — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Praga Vermelha RPG</span>
            <h1 class="pv-section__title">Planos e livros</h1>
            <p class="pv-section__desc">Jogue de graça, teste tudo por {{ $diasPlano }} dias ou compre o livro do sistema e tenha acesso para sempre.</p>
        </div>

        @if (session('ok'))
            <div class="pl-alerta pl-alerta--ok">{{ session('ok') }}</div>
        @endif
        @if (session('limite'))
            <div class="pl-alerta pl-alerta--limite">{{ session('limite') }}</div>
        @endif

        @auth
            <div class="pl-atual">
                <div>
                    <small class="pv-eyebrow">Seu plano</small>
                    <div class="pl-atual__nome">{{ \App\Support\Plano::nome($plano) }}</div>
                </div>
                <div class="pl-atual__uso">
                    @if ($plano === 'vitalicio')
                        Livro do sistema comprado{{ $compradoEm ? ' em ' . $compradoEm->format('d/m/Y') : '' }}: sem anúncios, sem limites e todos os livros liberados.
                        <a href="{{ route('livro.pdf', 'completo') }}?baixar=1">Baixar o PDF</a>
                    @elseif ($plano === 'teste')
                        Faltam {{ $diasTeste }} {{ $diasTeste === 1 ? 'dia' : 'dias' }} de acesso completo. Depois, a conta passa para o plano grátis.
                    @else
                        Fichas: {{ $fichas }} de {{ $limites['fichas'] }} · Campanhas como mestre: {{ $campanhas }} de {{ $limites['campanhas'] }} · com anúncios
                    @endif
                </div>
            </div>
        @endauth

        <div class="pl-grade">
            <article class="pl-plano {{ $plano === 'gratis' ? 'pl-plano--atual' : '' }}">
                <h2 class="pl-plano__titulo">Grátis</h2>
                <div class="pl-plano__preco">R$ 0</div>
                <ul>
                    <li>Até {{ $limites['fichas'] }} fichas</li>
                    <li>{{ $limites['campanhas'] }} campanha como mestre</li>
                    <li>Livro do sistema na versão de jogadores</li>
                    <li>Com anúncios</li>
                </ul>
                @guest
                    <a href="{{ route('register') }}" class="pv-btn pv-btn--outline">Criar conta</a>
                @endguest
            </article>

            <article class="pl-plano {{ $plano === 'teste' ? 'pl-plano--atual' : '' }}">
                <h2 class="pl-plano__titulo">Teste completo <span class="pl-selo">{{ $diasPlano }} dias</span></h2>
                <div class="pl-plano__preco">Grátis ao criar a conta</div>
                <ul>
                    <li>Fichas e campanhas sem limite</li>
                    <li>Sem anúncios</li>
                    <li>Todas as ferramentas do painel</li>
                    <li>Depois dos {{ $diasPlano }} dias, vira o plano grátis</li>
                </ul>
                @guest
                    <a href="{{ route('register') }}" class="pv-btn pv-btn--primary">Começar o teste</a>
                @endguest
            </article>

            <article class="pl-plano pl-plano--destaque {{ $plano === 'vitalicio' ? 'pl-plano--atual' : '' }}">
                <h2 class="pl-plano__titulo">Vitalício <span class="pl-selo">livro do sistema</span></h2>
                <div class="pl-plano__preco">{{ $preco }} · compra única do livro do sistema (info-produto)</div>
                <ul>
                    <li>Painel sem anúncios, para sempre</li>
                    <li>Fichas e campanhas sem limite</li>
                    <li>PDF do livro do sistema completo, para baixar</li>
                    <li>Todos os livros de histórias em info-produto</li>
                </ul>
                @if ($plano !== 'vitalicio')
                    @if (!$compraDisponivel)
                        <span class="pv-btn pv-btn--outline is-breve">Compra em breve</span>
                    @else
                        @auth
                            <form method="POST" action="{{ route('livro.comprar') }}">
                                @csrf
                                <button type="submit" class="pv-btn pv-btn--primary" style="width:100%">Comprar por {{ $preco }}</button>
                            </form>
                            <small class="pl-atual__uso">Pix, boleto ou cartão. Depois de pagar, volte a esta página: o plano é ativado na hora em que o pagamento confirma.</small>
                        @else
                            <a href="{{ route('login') }}" class="pv-btn pv-btn--primary">Entre na conta para comprar por {{ $preco }}</a>
                        @endauth
                    @endif
                @else
                    <a href="{{ route('livro.pdf', 'completo') }}?baixar=1" class="pv-btn pv-btn--primary">Baixar o PDF do livro</a>
                    <a href="{{ route('livro.index') }}" class="pv-btn pv-btn--outline">Ler no site</a>
                @endif
            </article>
        </div>
    </div>
</section>

<section class="pv-section pv-section--alt">
    <div class="pv-container">
        <div class="pv-section__head">
            <h2 class="pv-section__title">Livros físicos</h2>
            <p class="pv-section__desc">Prefere o livro na mão? Compre a versão impressa na loja.</p>
        </div>
        <div class="pl-fisicos">
            @foreach ($fisicos as $livro)
                <div class="pl-fisico">
                    <strong>{{ $livro['titulo'] }}</strong>
                    <small>{{ $livro['descricao'] ?? '' }}</small>
                    @if (!empty($livro['url']))
                        <a href="{{ $livro['url'] }}" class="pv-btn pv-btn--outline" target="_blank" rel="noopener">Comprar livro físico</a>
                    @else
                        <span class="pv-btn pv-btn--outline is-breve">Em breve</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
