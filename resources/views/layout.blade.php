<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Praga Vermelha')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ @filemtime(public_path('css/style.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/cards.css') }}?v={{ @filemtime(public_path('css/cards.css')) }}">
    {{-- Ícones próprios (public/img/icones.svg): <x-icone n="..."/> nas páginas, icone('...') nos scripts --}}
    <style>.ico{display:inline-block;width:1.15em;height:1.15em;vertical-align:-.2em;flex:none;overflow:visible}</style>
    <script>/* deslizantes: a parte cheia do trilho segue o valor (--p), inclusive quando um script muda o valor */
    (function(){var p=function(i){var mn=+i.min||0,mx=i.max===''?100:+i.max,v=+i.value;i.style.setProperty('--p',Math.max(0,Math.min(100,(v-mn)/((mx-mn)||1)*100))+'%');};
    var tudo=function(){document.querySelectorAll('input[type=range]').forEach(p);};
    document.addEventListener('input',function(e){if(e.target.type==='range')p(e.target);});
    document.addEventListener('DOMContentLoaded',tudo);setInterval(tudo,700);})();
    window.icone=function(n,c){return '<svg class="ico'+(c?' '+c:'')+'" aria-hidden="true" focusable="false"><use href="{{ asset('img/icones.svg') }}?v={{ @filemtime(public_path('img/icones.svg')) }}#i-'+n+'"></use></svg>';};</script>
    {{-- ?v= com a data do arquivo: cada atualização do CSS da ficha chega sem cache antigo --}}
    <link rel="stylesheet" href="{{ asset('css/Ficha.css') }}?v={{ @filemtime(public_path('css/Ficha.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/Maldicao.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tabletop.css') }}">
    <link rel="stylesheet" href="{{ asset('css/planos.css') }}?v={{ @filemtime(public_path('css/planos.css')) ?: 1 }}">
    {{-- Anúncios só no plano grátis (depois dos 45 dias, sem o livro). Com os anúncios automáticos ligados no AdSense, o mesmo script mostra anúncios de tela cheia entre as páginas, de tempos em tempos --}}
    @if (config('planos.adsense_cliente') && \App\Support\Plano::veAnuncios(auth()->user()))
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('planos.adsense_cliente') }}" crossorigin="anonymous"></script>
    @endif

    <style>
        /* Dropdown da conta (Entrar / Criar conta / Sair) */
        .pv-nav__item--conta .pv-dropdown { left: auto; right: 0; min-width: 220px; }
        .pv-nav__item--conta .pv-dropdown__link { display: block; width: 100%; box-sizing: border-box; text-align: left; }
        .pv-conta__icone { display: inline-block; margin-right: 6px; opacity: .85; }
        .pv-conta__nome { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: bottom; }
        .pv-dropdown__form { margin: 0; }
        .pv-dropdown__form button.pv-dropdown__link {
            background: none; border: 0; font: inherit; color: inherit; cursor: pointer;
        }
        .pv-dropdown__sep { height: 1px; margin: 6px 12px; background: rgba(255,255,255,.08); }
        /* Submenu flutuante com as campanhas da pessoa (ao passar o mouse em "Campanhas") */
        .pv-dropdown__item--sub { position: relative; }
        .pv-sub-caret { font-size: .7rem; opacity: .7; margin-left: 6px; }
        .pv-subdropdown {
            position: absolute; top: -8px; left: 100%; min-width: 240px; max-height: 70vh; overflow-y: auto;
            padding: 8px; margin-left: 4px; background: var(--pv-surface); border: 1px solid var(--pv-border);
            border-radius: var(--pv-radius); box-shadow: var(--pv-shadow);
            opacity: 0; visibility: hidden; transition: opacity .15s, visibility .15s; z-index: 1200;
        }
        .pv-subdropdown::before { content: ''; position: absolute; top: 0; bottom: 0; left: -12px; width: 12px; }
        .pv-dropdown__item--sub:hover > .pv-subdropdown,
        .pv-dropdown__item--sub:focus-within > .pv-subdropdown { opacity: 1; visibility: visible; }
        @media (max-width: 900px) {
            .pv-subdropdown { position: static; opacity: 1; visibility: visible; margin: 0 0 4px 12px; max-height: none; box-shadow: none; }
        }
        @media (max-width: 900px) {
            .pv-nav__item--conta .pv-dropdown { right: auto; min-width: 0; }
        }
    </style>
</head>
<body class="pv-body{{ request()->boolean('mini') ? ' pv-mini' : '' }}">

    <header class="pv-header">
        <div class="pv-container pv-header__inner">

            <a href="/" class="pv-brand">
                <span class="pv-brand__mark">✠</span>
                <span class="pv-brand__text">
                    <span class="pv-brand__title">Praga Vermelha</span>
                    <span class="pv-brand__sub">RPG</span>
                </span>
            </a>

            <input type="checkbox" id="pv-menu-toggle" class="pv-menu-toggle">
            <label for="pv-menu-toggle" class="pv-menu-button" aria-label="Abrir menu">
                <span></span><span></span><span></span>
            </label>

            <nav class="pv-nav" aria-label="Navegação principal">
                <ul class="pv-nav__list">

                    <li class="pv-nav__item">
                        <a href="/" class="pv-nav__link">Início</a>
                    </li>

                    @if (Route::has('videos.index'))
                    <li class="pv-nav__item">
                        <a href="{{ route('videos.index') }}" class="pv-nav__link">Vídeos explicativos</a>
                    </li>
                    @endif

                    <li class="pv-nav__item pv-has-dropdown">
                        <a href="#" class="pv-nav__link">Praga Vermelha RPG <span class="pv-caret">▾</span></a>
                        <div class="pv-dropdown">
                            <a href="{{ route('livro.index') }}" class="pv-dropdown__link">
                                <strong>Livro do sistema</strong>
                                <small>Leia as regras e o cenário</small>
                            </a>
                            <a href="{{ route('fichas.create') }}" class="pv-dropdown__link">
                                <strong>Criar ficha</strong>
                                <small>Monte um novo personagem</small>
                            </a>
                            <a href="{{ route('personagens.index') }}" class="pv-dropdown__link">
                                <strong>Minhas fichas</strong>
                                <small>Veja seus personagens criados</small>
                            </a>
                            <a href="{{ route('planos.index') }}" class="pv-dropdown__link">
                                <strong>Planos e livros</strong>
                                <small>Livro do sistema, histórias e livros físicos</small>
                            </a>
                            <a href="{{ route('maldicoes.index') }}" class="pv-dropdown__link">
                                <strong>Maldições</strong>
                                <small>Consulte as maldições e suas ameaças</small>
                            </a>
                            <a href="{{ route('tabletop.index') }}" class="pv-dropdown__link">
                                <strong>Tabletop</strong>
                                <small>Mesa virtual para jogar</small>
                            </a>
                            {{-- Ao passar o mouse, um submenu lista as campanhas em que a pessoa está (ver AppServiceProvider) --}}
                            <div class="pv-dropdown__item--sub">
                                <a href="{{ route('campanhas.index') }}" class="pv-dropdown__link">
                                    <strong>Campanhas @auth<span class="pv-sub-caret">▸</span>@endauth</strong>
                                    <small>Mestre, jogadores, fichas e sons</small>
                                </a>
                                @auth
                                <div class="pv-subdropdown" aria-label="Suas campanhas">
                                    @forelse (($campanhasAtalho ?? collect()) as $c)
                                        <a href="{{ route('campanhas.show', $c) }}" class="pv-dropdown__link">
                                            <strong>{{ $c->nome }}</strong>
                                            <small>{{ (int) $c->mestre_id === (int) auth()->id() ? 'você é o mestre' : 'jogador' }}{{ $c->sessao_ativa ? ' · sessão ativa' : '' }}</small>
                                        </a>
                                    @empty
                                        <span class="pv-dropdown__link"><small>Você ainda não está em nenhuma campanha.</small></span>
                                    @endforelse
                                </div>
                                @endauth
                            </div>
                        </div>
                    </li>

                    <li class="pv-nav__item pv-has-dropdown">
                        <a href="#" class="pv-nav__link">Histórias <span class="pv-caret">▾</span></a>
                        <div class="pv-dropdown">
                            <a href="{{ route('historias.ler', 'o-cavaleiro-negro') }}" class="pv-dropdown__link">
                                <strong>O Cavaleiro Negro</strong>
                                <small>Livro</small>
                            </a>
                            <a href="{{ route('historias.ler', 'o-mundo-de-carne') }}" class="pv-dropdown__link">
                                <strong>O Mundo de Carne</strong>
                                <small>Livro</small>
                            </a>
                            <a href="#" class="pv-dropdown__link">
                                <strong>Contos da Ruína</strong>
                                <small>Coletânea de contos</small>
                            </a>
                        </div>
                    </li>

                    {{-- Conta: Entrar / Criar conta (visitante) ou nome + Sair (logado) --}}
                    @auth
                        <li class="pv-nav__item pv-has-dropdown pv-nav__item--conta">
                            <a href="#" class="pv-nav__link">
                                <span class="pv-conta__icone"><x-icone n="conta"/></span><span class="pv-conta__nome">{{ auth()->user()->name }}</span>
                                <span class="pv-caret">▾</span>
                            </a>
                            <div class="pv-dropdown">
                                <a href="{{ route('personagens.index') }}" class="pv-dropdown__link">
                                    <strong>Minhas fichas</strong>
                                    <small>Seus personagens</small>
                                </a>
                                <a href="{{ route('tabletop.index') }}" class="pv-dropdown__link">
                                    <strong>Minhas mesas</strong>
                                    <small>Mesas virtuais salvas</small>
                                </a>
                                <a href="{{ route('campanhas.index') }}" class="pv-dropdown__link">
                                    <strong>Minhas campanhas</strong>
                                    <small>Campanhas e convites</small>
                                </a>
                                @php $planoMenu = \App\Support\Plano::de(auth()->user()); @endphp
                                <a href="{{ route('planos.index') }}" class="pv-dropdown__link">
                                    <strong>Meu plano</strong>
                                    <small class="pv-plano-selo">{{ \App\Support\Plano::nome($planoMenu) }}@if ($planoMenu === 'teste') · {{ \App\Support\Plano::diasDeTeste(auth()->user()) }} dias restantes @endif</small>
                                </a>
                                @if (Route::has('reclamacoes.index'))
                                    <a href="{{ route('reclamacoes.index') }}" class="pv-dropdown__link">
                                        <strong>Reclame aqui</strong>
                                        <small>Problemas, sugestões e respostas</small>
                                    </a>
                                @endif
                                <div class="pv-dropdown__sep"></div>
                                <form action="{{ route('logout') }}" method="POST" class="pv-dropdown__form">
                                    @csrf
                                    <button type="submit" class="pv-dropdown__link">
                                        <strong>Sair</strong>
                                        <small>Encerrar a sessão</small>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @else
                        <li class="pv-nav__item pv-has-dropdown pv-nav__item--conta">
                            <a href="#" class="pv-nav__link">
                                <span class="pv-conta__icone"><x-icone n="conta"/></span>Conta <span class="pv-caret">▾</span>
                            </a>
                            <div class="pv-dropdown">
                                <a href="{{ route('login') }}" class="pv-dropdown__link">
                                    <strong>Entrar</strong>
                                </a>
                                <a href="{{ route('register') }}" class="pv-dropdown__link">
                                    <strong>Criar conta</strong>
                                </a>
                            </div>
                        </li>
                    @endauth

                </ul>
            </nav>
        </div>
    </header>

    <main class="pv-main">
        @yield('conteudo')

        @unless (request()->boolean('mini')) @include('partials.anuncio') @endunless

        <script src="{{ asset('js/script.js') }}"></script>
        @yield('script')
    </main>
    @auth
        @if (!request()->boolean('mini') && !request()->routeIs('campanhas.jogos.partida') && \Illuminate\Support\Facades\Route::has('campanhas.jogos.ativas'))
            {{-- miniatura do Gladio: a partida em andamento acompanha o jogador pelo site --}}
            <div id="jg-mini" data-url="{{ route('campanhas.jogos.ativas') }}" hidden></div>
            <link rel="stylesheet" href="{{ asset('css/jogos-mini.css') }}?v={{ @filemtime(public_path('css/jogos-mini.css')) }}">
            <script src="{{ asset('js/jogos-mini.js') }}?v={{ @filemtime(public_path('js/jogos-mini.js')) }}" defer></script>
        @endif
    @endauth

    <footer class="pv-footer">
        <div class="pv-container pv-footer__inner">
            <div class="pv-footer__brand">
                <span class="pv-brand__mark">✠</span>
                <span>Praga Vermelha RPG</span>
            </div>
            @if (Route::has('reclamacoes.index'))
                <a href="{{ route('reclamacoes.index') }}" class="pv-footer__reclame">Achou um problema? Reclame aqui</a>
            @endif
            <p>&copy; {{ date('Y') }} João Victor Barreto Pimentel. Todos os direitos reservados.</p>
        </div>
    </footer>

</body>
</html>