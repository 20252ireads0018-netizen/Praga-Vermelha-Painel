@extends('layout')

@section('title', $campanha->nome)

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/campanha.css') }}?v={{ @filemtime(public_path('css/campanha.css')) }}">

@php
    $uid = auth()->id();
    $inicial = fn ($texto) => mb_strtoupper(mb_substr(trim((string) $texto) ?: '?', 0, 1));
@endphp

<div class="camp-wrap camp-wrap-larga" id="camp-app"
     data-estado-url="{{ route('campanhas.estado', $campanha) }}"
     data-fichas-estado-url="{{ route('campanhas.fichas.estado', $campanha) }}"
     @if (Route::has('campanhas.rolagens.index'))
     data-rolagens-url="{{ route('campanhas.rolagens.index', $campanha) }}"
     @endif
     data-som-url="{{ route('campanhas.sons.arquivo', [$campanha, '__ID__']) }}"
     data-sessao="{{ $campanha->sessao_ativa ? 1 : 0 }}"
     data-csrf="{{ csrf_token() }}">

    {{-- ===== Banner do topo ===== --}}
    @php
        // Imagem de fundo do banner (quando o mestre enviou uma)
        $bannerUrl = !empty($campanha->banner_path) && Route::has('campanhas.banner')
            ? route('campanhas.banner', $campanha) . '?v=' . optional($campanha->updated_at)->timestamp
            : null;
    @endphp
    <header class="camp-topo camp-topo-banner {{ $bannerUrl ? 'camp-topo-com-banner' : '' }}"
            @if ($bannerUrl) style="background-image: linear-gradient(180deg, rgba(10, 6, 6, .3) 0%, rgba(10, 6, 6, .55) 55%, rgba(10, 6, 6, .88) 100%), url('{{ $bannerUrl }}');" @endif><meta charset="utf-8">
        <div class="camp-topo-info">
            <a href="{{ route('campanhas.index') }}" class="camp-voltar">← Campanhas</a>
            <h1>{{ $campanha->nome }}
                <span class="camp-badge {{ $campanha->sessao_ativa ? 'camp-badge-on' : '' }}">
                    {{ $campanha->sessao_ativa ? 'sessão ativa' : 'sem sessão' }}
                </span>
            </h1>
            @if ($campanha->descricao)<p class="camp-sub">{{ $campanha->descricao }}</p>@endif
        </div>

        <div class="camp-topo-meta">
            <div class="camp-meta-item">
                <span class="camp-meta-rotulo">Mestre</span>
                <strong>{{ $campanha->mestre->name ?? '—' }}</strong>
            </div>
            <div class="camp-meta-item">
                <span class="camp-meta-rotulo">Jogadores</span>
                <strong>{{ $campanha->participantes->where('status', 'ativo')->count() }}</strong>
            </div>
            <div class="camp-meta-item">
                <span class="camp-meta-rotulo">Fichas</span>
                <strong>{{ $vinculos->count() }}</strong>
            </div>
        </div>
    </header>

    @include('campanhas._alertas')

    <div class="camp-grade">

        {{-- ================= Coluna principal ================= --}}
        <div class="camp-coluna camp-coluna-principal">

            {{-- ===== Fichas ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab">
                    <h2>{{ $souMestre ? 'Fichas da campanha' : 'Minhas fichas na campanha' }}</h2>
                    <span class="camp-contagem">{{ $vinculos->count() }}</span>
                </div>

                <div id="camp-sync-aviso" hidden>
                    <span>A lista de fichas mudou.</span>
                    <button type="button" class="camp-btn camp-btn-primario" id="camp-sync-recarregar">Atualizar página</button>
                </div>

                <div class="camp-fichas-grade">
                    @forelse ($vinculos as $v)
                        @php
                            $ficha    = $v->ficha;
                            $nomeFic  = $ficha->nome ?? 'Ficha removida';
                            $ehDono   = (int) $v->user_id === $uid;
                            // Colunas reais da tabela de fichas (Personagem)
                            $stats = [
                                'pv'       => [$ficha->vida_atual ?? null,     $ficha->vida_maxima ?? null],
                                'aura'     => [$ficha->aura_atual ?? null,     $ficha->aura_maxima ?? null],
                                'sanidade' => [$ficha->sanidade_atual ?? null, $ficha->sanidade_maxima ?? null],
                            ];
                            $fotoUrl  = $v->foto_path
                                ? route('campanhas.fichas.foto', [$campanha, $v]) . '?v=' . optional($v->updated_at)->timestamp
                                : '';

                            // Assinatura igual à calculada em CampanhaController@fichasEstado (evita "piscar" sem mudança)
                            $statsAssin = [];
                            foreach ($stats as $chave => [$atual, $max]) {
                                $statsAssin[$chave] = [
                                    'atual' => $atual !== null ? (int) $atual : null,
                                    'max'   => $max !== null ? (int) $max : null,
                                ];
                            }
                            $assinatura = md5(json_encode([
                                $nomeFic,
                                $statsAssin,
                                optional($ficha?->updated_at)->timestamp ?? 0,
                                $ficha?->id,
                            ]));

                            // Dono edita pela ficha normal; o mestre abre a versão somente leitura da campanha.
                            $linkFicha = '';
                            if ($ficha) {
                                if ($ehDono) {
                                    $linkFicha = route('personagens.show', $v->ficha_id);
                                } elseif ($souMestre) {
                                    $linkFicha = route('campanhas.fichas.ver', [$campanha, $v]);
                                }
                            }
                        @endphp
                        <div class="camp-linha camp-linha-clicavel camp-ficha-item">
                            <button type="button" class="camp-clicavel"
                                    data-ficha-abrir
                                    data-id="{{ $v->id }}"
                                    data-nome="{{ $nomeFic }}"
                                    data-jogador="{{ $v->user->name ?? '' }}"
                                    @foreach ($stats as $chave => [$atual, $max])
                                    data-{{ $chave }}="{{ $atual }}"
                                    data-{{ $chave }}-max="{{ $max }}"
                                    @endforeach
                                    data-assinatura="{{ $assinatura }}"
                                    data-foto="{{ $fotoUrl }}"
                                    data-dono="{{ $ehDono ? 1 : 0 }}"
                                    data-upload="{{ $ehDono ? route('campanhas.fichas.foto.enviar', [$campanha, $v]) : '' }}"
                                    data-link="{{ $linkFicha }}"
                                    data-vitais="{{ ($souMestre || $ehDono) && Route::has('campanhas.fichas.vitais') ? route('campanhas.fichas.vitais', [$campanha, $v]) : '' }}"
                                    aria-haspopup="dialog">
                                <span class="camp-avatar">
                                    @if ($fotoUrl)
                                        <img src="{{ $fotoUrl }}" alt="" loading="lazy">
                                    @else
                                        {{ $inicial($nomeFic) }}
                                    @endif
                                </span>
                                <span class="camp-clicavel-info">
                                    <strong>{{ $nomeFic }}</strong>
                                    <span class="camp-dica">de {{ $v->user->name ?? '—' }}</span>
                                </span>
                                <span class="camp-badge camp-badge-on js-ficha-atualizada" hidden>atualizada</span>
                                <span class="camp-seta" aria-hidden="true">›</span>
                            </button>

                            @if ($souMestre || $ehDono)
                                <form method="POST" action="{{ route('campanhas.fichas.desvincular', [$campanha, $v]) }}">
                                    @csrf @method('DELETE')
                                    <button class="camp-btn camp-btn-pequeno">Desvincular</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="camp-vazio">{{ $souMestre ? 'Nenhuma ficha vinculada ainda.' : 'Você ainda não vinculou nenhuma ficha.' }}</p>
                    @endforelse
                </div>

                @if ($minhasFichas->isNotEmpty())
                    <form method="POST" action="{{ route('campanhas.fichas.vincular', $campanha) }}" class="camp-form camp-form-linha">
                        @csrf
                        <select name="ficha_id" required>
                            <option value="">Escolha uma das suas fichas…</option>
                            @foreach ($minhasFichas as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                        <button class="camp-btn camp-btn-primario">Vincular ficha</button>
                    </form>
                @else
                    <p class="camp-dica">Você não tem fichas disponíveis para vincular. <a href="{{ route('fichas.create') }}">Criar uma ficha</a></p>
                @endif
            </section>

            {{-- ===== Tabletops ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab">
                    <h2>Tabletops</h2>
                    <span class="camp-contagem">{{ $mesas->count() }}</span>
                </div>

                <div class="camp-mesas-grade">
                    @forelse ($mesas as $m)
                        <div class="camp-linha camp-linha-clicavel camp-mesa-item">
                            {{-- Abre o painel rápido pela direita (só mover peças, ao vivo); de lá dá para abrir o tabletop inteiro --}}
                            <button type="button" class="camp-clicavel js-tt-rapido"
                                    data-sala-url="{{ route('campanhas.mesas.sala', [$campanha, $m]) }}"
                                    data-nome="{{ $m->nome }}">
                                <span class="camp-clicavel-info"><strong>{{ $m->nome }}</strong></span>
                                <span class="camp-dica">{{ $m->codigo ? 'ao vivo' : 'Mover peças' }}</span>
                                <span class="camp-seta" aria-hidden="true">›</span>
                            </button>
                            @if ($souMestre)
                                <form method="POST" action="{{ route('campanhas.mesas.desvincular', [$campanha, $m]) }}">
                                    @csrf @method('DELETE')
                                    <button class="camp-btn camp-btn-pequeno">Desvincular</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="camp-vazio">Nenhum tabletop vinculado.</p>
                    @endforelse
                </div>

                @if ($souMestre)
                    <div class="camp-forms-duplos">
                        <form method="POST" action="{{ route('campanhas.mesas.criar', $campanha) }}" class="camp-form camp-form-linha">
                            @csrf
                            <input type="text" name="nome" placeholder="Nome do novo tabletop" maxlength="80" required>
                            <button class="camp-btn camp-btn-primario">Criar tabletop</button>
                        </form>

                        @if ($minhasMesas->isNotEmpty())
                            <form method="POST" action="{{ route('campanhas.mesas.vincular', $campanha) }}" class="camp-form camp-form-linha">
                                @csrf
                                <select name="mesa_id" required>
                                    <option value="">Ou vincule um tabletop existente…</option>
                                    @foreach ($minhasMesas as $m)
                                        <option value="{{ $m->id }}">{{ $m->nome }}</option>
                                    @endforeach
                                </select>
                                <button class="camp-btn">Vincular</button>
                            </form>
                        @endif
                    </div>
                @endif
            </section>

            @if (Route::has('campanhas.jogos.index'))
            {{-- ===== Jogos do universo: Gladio e Pela Dama ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab"><h2>Jogos</h2></div>
                <a href="{{ route('campanhas.jogos.index', $campanha) }}" class="camp-clicavel">
                    <span class="camp-clicavel-info">
                        <strong><x-icone n="espadas"/> Gladio · ♛ Pela Dama</strong>
                        <span class="camp-dica">Duelo de cartas com apostas de Dinheiro e xadrez com peças criadas por você. Jogue ao vivo contra alguém da mesa.</span>
                    </span>
                    <span class="camp-seta" aria-hidden="true">›</span>
                </a>
            </section>
            @endif

            @if (Route::has('campanhas.kuros.index'))
            {{-- ===== Kuros: o mestre cria e entrega; o jogador vê os que recebeu ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab"><h2>Kuros</h2></div>
                <a href="{{ route('campanhas.kuros.index', $campanha) }}" class="camp-clicavel">
                    <span class="camp-clicavel-info">
                        <strong>Kuros e criaturas do profano</strong>
                        <span class="camp-dica">{{ $souMestre ? 'Crie Kuros pelas regras do livro (ficha calculada sozinha), use os prontos e entregue aos jogadores.' : 'Veja e controle os Kuros que o mestre entregou a você.' }}</span>
                    </span>
                    <span class="camp-seta" aria-hidden="true">›</span>
                </a>
            </section>
            @endif

            @if (Route::has('campanhas.minijogos.index'))
            {{-- ===== Minijogos: apostas fictícias com o Dinheiro dos personagens ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab"><h2>Minijogos</h2></div>
                @if (!$campanha->cassinoLiberado(auth()->id()))
                <p class="camp-dica">O mestre fechou o cassino desta campanha para você.</p>
                @else
                <a href="{{ route('campanhas.minijogos.index', $campanha) }}" class="camp-clicavel">
                    <span class="camp-clicavel-info">
                        <strong>Mines, Aviator e caça-níquel</strong>
                        <span class="camp-dica">Aposte o Dinheiro dos seus personagens (fictício, só dentro do RPG).</span>
                    </span>
                    <span class="camp-seta" aria-hidden="true">›</span>
                </a>
                @endif
            </section>
            @endif

            @if (Route::has('campanhas.timeskips.index'))
            {{-- ===== Time skips: o mestre monta as regras; cada jogador escolhe ramo, atividades e propriedades ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab"><h2>Time skips</h2></div>
                <a href="{{ route('campanhas.timeskips.index', $campanha) }}" class="camp-clicavel">
                    <span class="camp-clicavel-info">
                        <strong>Passagem de tempo</strong>
                        <span class="camp-dica">{{ $souMestre ? 'Monte time skips com escolhas, ramos, atividades e propriedades (ou comece do modelo de 10 anos) e veja o que cada jogador escolheu.' : 'Escolha o ramo, gaste as escolhas e compre propriedades quando o mestre abrir um time skip.' }}</span>
                    </span>
                    <span class="camp-seta" aria-hidden="true">›</span>
                </a>
            </section>
            @endif

            @if (Route::has('campanhas.rolagens.index'))
            {{-- ===== Pote de dados (público + potes secretos) — na coluna principal para aproveitar a largura ===== --}}
            <section class="camp-card" id="camp-dados" data-mestre="{{ $souMestre ? 1 : 0 }}">
                <div class="camp-card-cab">
                    <h2><x-icone n="dados"/> Pote de dados</h2>
                    <span class="camp-dica js-pote-rotulo">rolagens públicas</span>
                </div>

                @php $temIniciativa = Route::has('campanhas.iniciativas.index'); @endphp
                @if ($temIniciativa)
                <div class="camp-dados-abas" role="tablist">
                    <button type="button" class="camp-btn camp-btn-pequeno ativo" role="tab" data-aba-dados="dados"><x-icone n="dados"/> Dados</button>
                    <button type="button" class="camp-btn camp-btn-pequeno" role="tab" data-aba-dados="iniciativa"><x-icone n="espadas"/> Iniciativa</button>
                </div>
                @endif
                <div class="js-aba-dados-painel" data-painel="dados">
                @php
                    // Potes secretos que a pessoa pode ver (o mestre vê todos); vazio até a migration rodar
                    $potesSecretos = \App\Http\Controllers\CampanhaRolagemController::potesVisiveis($campanha, $uid);
                    $poteInicial   = (int) session('pote_ativo', 0);
                @endphp
                <div class="camp-potes-abas js-potes-abas" data-pote-inicial="{{ $poteInicial ?: '' }}">
                    <button type="button" class="camp-btn camp-btn-pequeno js-pote-aba ativo" data-pote=""><x-icone n="globo"/> Público</button>
                    @foreach ($potesSecretos as $p)
                        <button type="button" class="camp-btn camp-btn-pequeno js-pote-aba" data-pote="{{ $p['id'] }}"
                                title="Criado por {{ $p['criador'] }}{{ count($p['membros']) ? ' · com ' . collect($p['membros'])->pluck('nome')->implode(', ') : ' · só quem criou (e o mestre)' }}"><x-icone n="cadeado"/> {{ $p['nome'] }}</button>
                    @endforeach
                    @if (Route::has('campanhas.potes.criar'))
                    <details class="camp-pote-novo">
                        <summary class="camp-btn camp-btn-pequeno">＋ Pote secreto</summary>
                        <form method="POST" action="{{ route('campanhas.potes.criar', $campanha) }}" class="camp-form">
                            @csrf
                            <label>Nome do pote
                                <input type="text" name="nome" maxlength="60" required placeholder="ex.: Só eu e o mestre">
                            </label>
                            <div class="camp-pote-membros">
                                <span class="camp-dica">Quem mais pode ver (o mestre sempre vê):</span>
                                @if (!$souMestre)
                                    <label class="camp-pote-membro"><input type="checkbox" checked disabled> {{ $campanha->mestre->name ?? 'Mestre' }} (mestre)</label>
                                @endif
                                @foreach ($campanha->participantes->where('status', 'ativo') as $pj)
                                    @if ((int) $pj->user_id !== (int) $uid)
                                        <label class="camp-pote-membro"><input type="checkbox" name="membros[]" value="{{ $pj->user_id }}"> {{ $pj->user->name ?? '—' }}</label>
                                    @endif
                                @endforeach
                            </div>
                            <button class="camp-btn camp-btn-primario">Criar pote</button>
                        </form>
                    </details>
                    @endif
                </div>
                @foreach ($potesSecretos as $p)
                    @if ($souMestre || (int) $p['criador_id'] === (int) $uid)
                        <form method="POST" action="{{ route('campanhas.potes.excluir', [$campanha, $p['id']]) }}" class="camp-pote-excluir js-pote-excluir" data-pote="{{ $p['id'] }}" hidden
                              onsubmit="return confirm('Excluir este pote secreto e as rolagens feitas nele?')">
                            @csrf @method('DELETE')
                            <span class="camp-dica">Pote de {{ $p['criador'] }}{{ count($p['membros']) ? ' · com ' . collect($p['membros'])->pluck('nome')->implode(', ') : '' }}</span>
                            <button class="camp-btn camp-btn-perigo camp-btn-pequeno">Excluir pote</button>
                        </form>
                    @endif
                @endforeach

                <script type="application/json" id="camp-fichas-rolagem">@json($fichasRolagem ?? [])</script>
                <form class="camp-dados-form js-dados-form" autocomplete="off">
                    <div class="camp-dados-hab js-dados-hab" hidden>
                        <label>Ficha
                            <select class="js-hab-ficha"></select>
                        </label>
                        <label>Habilidade
                            <select class="js-hab-habilidade"></select>
                        </label>
                        <button type="button" class="camp-btn camp-btn-pequeno js-hab-rolar">Rolar habilidade</button>
                    </div>
                    <div class="camp-dados-faces" role="group" aria-label="Tipo de dado">
                        @foreach ([4, 6, 8, 10, 12, 20, 100] as $faces)
                            <button type="button" class="camp-btn camp-btn-pequeno" data-lados="{{ $faces }}">d{{ $faces }}</button>
                        @endforeach
                    </div>

                    <div class="camp-dados-linha">
                        <label>Quantidade
                            <span class="camp-dados-qtd">
                                <button type="button" class="camp-btn" data-qtd-delta="-1" aria-label="Menos um dado">−</button>
                                <input type="number" class="js-dados-qtd" min="1" max="30" value="1" required>
                                <button type="button" class="camp-btn" data-qtd-delta="1" aria-label="Mais um dado">+</button>
                            </span>
                        </label>
                        <label>Faces
                            <input type="number" class="js-dados-lados" min="2" max="1000" value="20" required>
                        </label>
                    </div>

                    <div class="camp-dados-linha">
                        <label>Extras
                            <input type="text" class="js-dados-extras" maxlength="60" placeholder="ex.: +2 ou +3-1" title="Somados ao total">
                        </label>
                        <label>Descrição
                            <input type="text" class="js-dados-rotulo" maxlength="60" placeholder="opcional">
                        </label>
                    </div>

                    <p class="camp-dados-erro js-dados-erro" hidden></p>

                    <div class="camp-dados-rodape">
                        <span class="camp-dados-previa js-dados-previa">1d20</span>
                        <button type="button" class="camp-btn camp-btn-pequeno js-dados-limpar" hidden title="Apaga o histórico deste pote para todos. O histórico também recomeça sozinho a cada 20 rolagens."><x-icone n="vassoura"/> Limpar</button>
                        <button type="submit" class="camp-btn camp-btn-primario js-dados-rolar">Rolar</button>
                    </div>
                </form>

                <div class="camp-dados-lista js-dados-lista" aria-live="polite"></div>
                <p class="camp-vazio js-dados-vazio">Nenhuma rolagem ainda.</p>
                </div>

                @if ($temIniciativa)
                <div class="js-aba-dados-painel camp-ini" data-painel="iniciativa" hidden
                     data-url-ini="{{ route('campanhas.iniciativas.index', $campanha) }}">
                    <p class="camp-dica">Quem tirar menos começa.</p>
                    <form class="camp-ini-form js-ini-form" autocomplete="off">
                        <label>Personagem
                            <select class="js-ini-ficha"></select>
                        </label>
                        <button type="submit" class="camp-btn camp-btn-primario js-ini-rolar">Rolar iniciativa</button>
                    </form>
                    <p class="camp-dados-erro js-ini-erro" hidden></p>
                    <div class="camp-ini-topo">
                        <span class="camp-dica js-ini-contagem"></span>
                        @if ($souMestre)
                            <button type="button" class="camp-btn camp-btn-pequeno camp-btn-perigo js-ini-nova">Nova rodada</button>
                        @endif
                    </div>
                    <ol class="camp-ini-lista js-ini-lista"></ol>
                    <p class="camp-vazio js-ini-vazio">Ninguém rolou iniciativa ainda.</p>
                </div>
                @endif
            </section>
            @endif

            {{-- ===== Disputa de catálogo ===== --}}
            @if (Route::has('campanhas.catalogos.index'))
            @php $maldicoesDisputa = $maldicoesDisputa ?? []; @endphp
            <section class="camp-card" id="camp-catalogo"
                     data-url-index="{{ route('campanhas.catalogos.index', $campanha) }}"
                     data-url-store="{{ route('campanhas.catalogos.store', $campanha) }}"
                     data-disponiveis='@json($maldicoesDisputa)'>
                <div class="camp-card-cab">
                    <h2><x-icone n="pergaminho"/> Disputa de catálogo</h2>
                    <span class="camp-dica">ao vivo</span>
                </div>

                <form class="camp-form js-cat-form" autocomplete="off" @if (!count($maldicoesDisputa)) hidden @endif>
                    <label>Maldição
                        <select class="js-cat-maldicao">
                            @foreach ($maldicoesDisputa as $m)
                                <option value="{{ $m['slug'] }}">{{ $m['nome'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="cat-rotulo-linha"><span>Seu catálogo</span><span class="cat-palavras js-cat-palavras">0 palavras</span></span>
                        <textarea class="js-cat-texto" rows="5" maxlength="5000" placeholder="Descreva a maldição (mínimo 30 caracteres)"></textarea>
                    </label>
                    <p class="camp-dados-erro js-cat-erro" hidden></p>
                    <button type="submit" class="camp-btn camp-btn-primario js-cat-enviar">Enviar catálogo</button>
                </form>
                <p class="camp-vazio js-cat-sem-acesso" @if (count($maldicoesDisputa)) hidden @endif>Você já tem acesso a todas as maldições; acompanhe a disputa abaixo.</p>

                <div class="cat-resultado js-cat-resultado" hidden></div>

                <div class="cat-lista js-cat-lista" aria-live="polite"></div>
                <p class="camp-vazio js-cat-vazio">Nenhum envio ainda.</p>
            </section>
            @endif
        </div>

        {{-- ================= Coluna lateral ================= --}}
        <aside class="camp-coluna camp-coluna-lateral">

            {{-- ===== Sessão e som ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab">
                    <h2>Sessão</h2>
                    <span class="camp-badge {{ $campanha->sessao_ativa ? 'camp-badge-on' : '' }}">
                        {{ $campanha->sessao_ativa ? 'ativa' : 'inativa' }}
                    </span>
                </div>

                @if ($souMestre)
                    <div class="camp-acoes">
                        @if ($campanha->sessao_ativa)
                            <form method="POST" action="{{ route('campanhas.sessao.encerrar', $campanha) }}">@csrf
                                <button class="camp-btn camp-btn-perigo">Encerrar sessão</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('campanhas.sessao.iniciar', $campanha) }}">@csrf
                                <button class="camp-btn camp-btn-primario">Iniciar sessão</button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($campanha->sessao_ativa)
                    <div class="camp-som-painel">
                        <button type="button" class="camp-btn js-ativar-som"><x-icone n="som"/> Ativar som da sessão</button>
                        <label class="camp-vol">Volume
                            <input type="range" class="js-volume" min="0" max="1" step="0.05" value="0.8">
                        </label>
                        <span class="camp-dica js-som-status"></span>
                    </div>
                @else
                    <p class="camp-vazio">Os efeitos sonoros ficam disponíveis enquanto a sessão estiver ativa.</p>
                @endif

                @if ($souMestre && Route::has('campanhas.cena'))
                    {{-- Cenas: animação de tela cheia para quem está na página da campanha --}}
                    <form class="cena-form" id="cena-form" data-url="{{ route('campanhas.cena', $campanha) }}">
                        <h3 class="cena-form-titulo">Animação para os jogadores</h3>
                        <div class="cena-tipos" role="radiogroup" aria-label="Animação">
                            <label><input type="radio" name="tipo" value="descobriu" checked> <span><x-icone n="lupa"/> Você descobriu…</span></label>
                            <label><input type="radio" name="tipo" value="matar"> <span><x-icone n="adaga"/> Como você quer matar ele?</span></label>
                        </div>
                        <label class="cena-campo">Texto <span class="camp-dica">(o que foi descoberto, ou quem vai morrer)</span>
                            <input type="text" name="texto" maxlength="300" placeholder="Ex.: o selo de Vassago no porão">
                        </label>
                        <label class="cena-campo">Título <span class="camp-dica">(opcional, troca o título padrão)</span>
                            <input type="text" name="titulo" maxlength="80" placeholder="Você descobriu…">
                        </label>
                        <label class="cena-campo">Para
                            <select name="alvo">
                                <option value="">Todos na página</option>
                                @foreach ($campanha->participantes->where('status', 'ativo') as $p)
                                    @if ($p->user)<option value="{{ $p->user->id }}">{{ $p->user->name }}</option>@endif
                                @endforeach
                            </select>
                        </label>
                        <div class="cena-acoes">
                            <button type="submit" class="camp-btn camp-btn-primario">Mostrar</button>
                            <button type="button" class="camp-btn camp-btn-pequeno" id="cena-previa">Prévia só para mim</button>
                        </div>
                        <span class="camp-dica" id="cena-status" aria-live="polite"></span>
                    </form>
                @endif
            </section>

            {{-- ===== Jogadores ===== --}}
            <section class="camp-card">
                <div class="camp-card-cab">
                    <h2>Mestre e jogadores</h2>
                    <span class="camp-contagem">{{ $campanha->participantes->count() + 1 }}</span>
                </div>

                <div class="camp-linha camp-jogador">
                    <span class="camp-avatar">{{ $inicial($campanha->mestre->name ?? '') }}</span>
                    <div class="camp-jogador-info">
                        <strong>{{ $campanha->mestre->name ?? '—' }}</strong>
                    </div>
                    <span class="camp-badge">mestre</span>
                </div>

                @foreach ($campanha->participantes as $p)
                    <div class="camp-linha camp-jogador">
                        <span class="camp-avatar">{{ $inicial($p->user->name ?? '') }}</span>
                        <div class="camp-jogador-info">
                            <strong>{{ $p->user->name ?? 'Usuário removido' }}</strong>
                        </div>
                        <div class="camp-acoes">
                            <span class="camp-badge {{ $p->status === 'ativo' ? 'camp-badge-on' : '' }}">
                                {{ $p->status === 'ativo' ? 'jogador' : 'convite pendente' }}
                            </span>
                            @if ($souMestre && $p->status === 'ativo' && AppModelsCampanha::temControleCassino())
                                @php $cassinoAberto = (bool) ($p->cassino ?? true); @endphp
                                <form method="POST" action="{{ route('campanhas.jogadores.cassino', [$campanha, $p->user_id]) }}"
                                      title="{{ $cassinoAberto ? 'Fechar o cassino (minijogos) para esta pessoa' : 'Liberar o cassino (minijogos) para esta pessoa' }}">
                                    @csrf
                                    <input type="hidden" name="liberar" value="{{ $cassinoAberto ? 0 : 1 }}">
                                    <button class="camp-btn camp-btn-pequeno {{ $cassinoAberto ? '' : 'camp-btn-cassino-fechado' }}">{{ $cassinoAberto ? 'Cassino: liberado' : 'Cassino: fechado' }}</button>
                                </form>
                            @endif
                            @if ($souMestre)
                                <form method="POST" action="{{ route('campanhas.jogadores.remover', [$campanha, $p->user_id]) }}"
                                      onsubmit="return confirm('Remover esta pessoa da campanha? As fichas dela vinculadas aqui também serão desvinculadas.')">
                                    @csrf @method('DELETE')
                                    <button class="camp-btn camp-btn-perigo camp-btn-pequeno">{{ $p->status === 'ativo' ? 'Remover' : 'Cancelar' }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($souMestre)
                    <form method="POST" action="{{ route('campanhas.convidar', $campanha) }}" class="camp-form camp-form-linha">
                        @csrf
                        <input type="text" name="jogador" placeholder="Código de jogador (ou nome de usuário)" maxlength="100" required value="{{ old('jogador') }}">
                        <button class="camp-btn camp-btn-primario">Convidar</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('campanhas.sair', $campanha) }}"
                          onsubmit="return confirm('Sair desta campanha?')">
                        @csrf @method('DELETE')
                        <button class="camp-btn camp-btn-perigo">Sair da campanha</button>
                    </form>
                @endif
            </section>

            @if (Route::has('campanhas.trilha'))
            {{-- ===== Trilha: música ou vídeo que o mestre toca para todos (public/js/campanha-trilha.js) ===== --}}
            <style>
                .trilha-status { display: flex; align-items: center; gap: 12px; padding: 10px 12px; margin-bottom: 8px; border-radius: 10px;
                    background: linear-gradient(135deg, rgba(122, 21, 21, .28), rgba(0, 0, 0, .35)); border: 1px solid var(--camp-borda); }
                .trilha-disco { flex: none; width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; color: #f2d7a0;
                    background: radial-gradient(circle, #2b1717 0 22%, #0d0a0a 23% 60%, #241515 61%); border: 1px solid #472020; }
                .trilha-disco .ico { width: 18px; height: 18px; }
                #camp-trilha[data-tocando="1"] .trilha-disco { animation: trilha-gira 3.5s linear infinite; box-shadow: 0 0 12px rgba(182, 37, 37, .45); }
                @keyframes trilha-gira { to { transform: rotate(360deg); } }
                .trilha-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
                .trilha-info strong { overflow-wrap: anywhere; font-size: .98rem; }
                .trilha-progresso { display: flex; align-items: center; gap: 8px; }
                .trilha-volume { display: inline-flex; align-items: center; gap: 6px; font-size: .8rem; color: var(--camp-texto-suave); }
                .trilha-volume input { width: 90px; }
                .trilha-acoes { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; margin: 0 0 6px; }
                .trilha-linha { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 6px 0; }
                .trilha-linha select { flex: 1 1 200px; min-width: 0; }
                .trilha-barra { flex: 1; min-width: 100px; }
                .trilha-tempo { font-size: .8rem; color: var(--camp-texto-suave); font-variant-numeric: tabular-nums; white-space: nowrap; }
                .trilha-repetir { display: inline-flex; align-items: center; font-size: .85rem; cursor: pointer; }
                @media (prefers-reduced-motion: reduce) { #camp-trilha[data-tocando="1"] .trilha-disco { animation: none; } }
                .trilha-video { aspect-ratio: 16 / 9; width: 100%; border-radius: 10px; overflow: hidden; background: #000; margin: 6px 0; }
                .trilha-video[hidden] { display: none; }
                .trilha-link { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
                .trilha-link input { flex: 1 1 160px; min-width: 0; }
                #camp-trilha [hidden] { display: none !important; }
                .trilha-erro { color: #fca5a5; font-size: .85rem; min-height: 1em; margin: 4px 0 0; }
            </style>
            <section class="camp-card" id="camp-trilha" data-config="{{ json_encode([
                'mestre'    => $souMestre,
                'csrf'      => csrf_token(),
                'estadoUrl' => route('campanhas.estado', $campanha),
                'trilhaUrl' => route('campanhas.trilha', $campanha),
                'sonsUrl'   => route('campanhas.sons.arquivo', [$campanha, '__ID__']),
                'sons'      => $souMestre ? $sons->map(fn ($s) => ['id' => $s->id, 'nome' => $s->nome])->values() : [],
            ]) }}">
                <div class="camp-card-cab"><h2><x-icone n="nota"/> Trilha</h2></div>
                <div class="trilha-status">
                    <span class="trilha-disco" aria-hidden="true"><x-icone n="nota"/></span>
                    <div class="trilha-info">
                        <strong id="trilha-titulo">Nada tocando agora.</strong>
                        <div class="trilha-progresso">
                            <input type="range" class="trilha-barra" id="trilha-barra" min="0" max="1" value="0" aria-label="Posição" @unless ($souMestre) disabled @endunless>
                            <span class="trilha-tempo" id="trilha-tempo"></span>
                        </div>
                    </div>
                </div>
                <div class="trilha-acoes">
                    <button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" id="trilha-ouvir" hidden title="O navegador só toca som depois de um clique"><x-icone n="som"/> Ouvir a trilha</button>
                    <label class="trilha-volume" title="Volume (só para você)"><x-icone n="som"/><input type="range" id="trilha-volume" min="0" max="100" aria-label="Volume"></label>
                </div>
                <div class="trilha-video" id="trilha-video" hidden><div id="trilha-yt"></div></div>
                @if ($souMestre)
                    <div class="trilha-linha">
                        <select id="trilha-fonte" aria-label="O que tocar"></select>
                        <button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" id="trilha-tocar"><x-icone n="play"/> Tocar para todos</button>
                        <button type="button" class="camp-btn camp-btn-pequeno" id="trilha-apagar-link" hidden>Tirar da lista</button>
                    </div>
                    <div class="trilha-linha">
                        <button type="button" class="camp-btn camp-btn-pequeno" id="trilha-pausar" disabled><x-icone n="pausa"/> Pausar</button>
                        <button type="button" class="camp-btn camp-btn-pequeno" id="trilha-parar" disabled><x-icone n="parar"/> Parar</button>
                        <label class="trilha-repetir"><input type="checkbox" id="trilha-loop"> Repetir</label>
                    </div>
                    <form class="trilha-link" id="trilha-link-form">
                        <input type="text" name="url" maxlength="300" placeholder="Link do YouTube" aria-label="Link do YouTube">
                        <input type="text" name="titulo" maxlength="100" placeholder="Nome (opcional)" aria-label="Nome do vídeo">
                        <button class="camp-btn camp-btn-pequeno">+ Guardar vídeo</button>
                    </form>
                    <p class="camp-dica">Áudios: envie em Efeitos sonoros (até 20 MB) e escolha aqui. Quem está na página da campanha ouve junto; cada um ajusta o próprio volume.</p>
                    <p class="trilha-erro" id="trilha-erro"></p>
                @endif
            </section>
            <script src="{{ asset('js/campanha-trilha.js') }}?v={{ @filemtime(public_path('js/campanha-trilha.js')) }}" defer></script>
            @endif

            {{-- ===== Efeitos sonoros (mestre) ===== --}}
            @if ($souMestre)
                <section class="camp-card">
                    <div class="camp-card-cab">
                        <h2>Efeitos sonoros</h2>
                        <span class="camp-contagem">{{ $sons->count() }}</span>
                    </div>

                    @php $creditosCoringa = collect(config('sons_coringa', []))->pluck('credito', 'arquivo'); @endphp
                    {{-- Lista compacta (grade de botões) e recolhível; o estado aberto/fechado fica lembrado no navegador --}}
                    <details class="camp-sons js-sons-detalhes" open>
                        <summary class="camp-btn camp-btn-pequeno" title="Mostrar ou ocultar a lista de sons"><x-icone n="som"/> {{ $sons->count() }} {{ $sons->count() === 1 ? 'som' : 'sons' }} <span class="camp-sons-seta">▾</span></summary>
                        <div class="camp-sons-grade">
                            @forelse ($sons as $s)
                                <div class="camp-som-chip" title="{{ $s->nome }} · {{ number_format($s->tamanho / 1024, 0) }} KB{{ ($creditosCoringa[$s->arquivo] ?? '') ? ' · ' . $creditosCoringa[$s->arquivo] : '' }}">
                                    <button type="button" class="camp-btn camp-btn-primario camp-btn-pequeno camp-som-tocar"
                                            data-som-tocar="{{ route('campanhas.sons.tocar', [$campanha, $s]) }}"
                                            @disabled(!$campanha->sessao_ativa)><x-icone n="play"/> {{ $s->nome }}</button>
                                    <form method="POST" action="{{ route('campanhas.sons.excluir', [$campanha, $s]) }}" onsubmit="return confirm('Excluir o som “{{ $s->nome }}”?')">
                                        @csrf @method('DELETE')
                                        <button class="camp-btn camp-btn-perigo camp-btn-pequeno camp-som-x" title="Excluir" aria-label="Excluir {{ $s->nome }}">✕</button>
                                    </form>
                                </div>
                            @empty
                                <p class="camp-vazio">Nenhum som enviado.</p>
                            @endforelse
                        </div>
                    </details>

                    <div class="camp-acoes">
                        <button type="button" class="camp-btn" data-som-parar="{{ route('campanhas.sons.parar', $campanha) }}"
                                @disabled(!$campanha->sessao_ativa)>■ Parar som</button>
                    </div>

                    @if (Route::has('campanhas.sons.padrao'))
                    <form method="POST" action="{{ route('campanhas.sons.padrao', $campanha) }}" class="camp-form">
                        @csrf
                        <button class="camp-btn camp-lateral-botao" title="Tiro, trovão, lâmina, grito, porta rangendo, porta batendo, explosão, vidro quebrando, uivo de lobo, vento, chuva, passos e sino"><x-icone n="presente"/> Adicionar efeitos coringa</button>
                    </form>
                    @endif

                    <form method="POST" action="{{ route('campanhas.sons.enviar', $campanha) }}" enctype="multipart/form-data" class="camp-form">
                        @csrf
                        <label>Nome do efeito
                            <input type="text" name="nome" maxlength="60" required>
                        </label>
                        <label>Arquivo (mp3, wav, ogg, m4a ou aac — até 20 MB)
                            <input type="file" name="arquivo" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*" required>
                        </label>
                        <button class="camp-btn camp-btn-primario camp-lateral-botao">Enviar som</button>
                    </form>
                </section>
            @endif

            {{-- ===== Chave de maldições (mestre) ===== --}}
            @if ($souMestre && Route::has('campanhas.chave-maldicoes'))
                <section class="camp-card">
                    <div class="camp-card-cab">
                        <h2>Chave de maldições</h2>
                    </div>
                    <form method="POST" action="{{ route('campanhas.chave-maldicoes', $campanha) }}" class="camp-form">
                        @csrf
                        <label>Chave
                            <input type="text" name="chave_maldicoes" maxlength="80" autocomplete="off"
                                   value="{{ old('chave_maldicoes', $campanha->chave_maldicoes ?? '') }}">
                        </label>
                        <p class="camp-vazio">Jogadores que digitarem esta chave no catálogo veem as maldições não catalogadas. Deixe vazio para desativar.</p>
                        <button class="camp-btn camp-btn-primario camp-lateral-botao">Salvar chave</button>
                    </form>
                </section>
            @endif

            {{-- ===== Configurações (mestre) ===== --}}
            @if ($souMestre)
                <section class="camp-card">
                    <div class="camp-card-cab">
                        <h2>Configurações</h2>
                    </div>
                    <details class="camp-detalhes">
                        <summary class="camp-btn">Editar campanha</summary>
                        <form method="POST" action="{{ route('campanhas.update', $campanha) }}" class="camp-form">
                            @csrf @method('PUT')
                            <label>Nome
                                <input type="text" name="nome" maxlength="80" required value="{{ old('nome', $campanha->nome) }}">
                            </label>
                            <label>Descrição
                                <textarea name="descricao" rows="3" maxlength="2000">{{ old('descricao', $campanha->descricao) }}</textarea>
                            </label>
                            <button class="camp-btn camp-btn-primario camp-lateral-botao">Salvar</button>
                        </form>
                    </details>
                    {{-- Imagem de fundo do banner --}}
                    @if (Route::has('campanhas.banner.enviar'))
                    <details class="camp-detalhes">
                        <summary class="camp-btn">Imagem de fundo</summary>
                        <form method="POST" action="{{ route('campanhas.banner.enviar', $campanha) }}" enctype="multipart/form-data" class="camp-form">
                            @csrf
                            <label>Imagem do topo (JPG, PNG ou WEBP, até 6 MB; ideal 1600×500)
                                <input type="file" name="banner" accept="image/png,image/jpeg,image/webp" required>
                            </label>
                            <button class="camp-btn camp-btn-primario camp-lateral-botao">Enviar imagem</button>
                        </form>
                        @if (!empty($campanha->banner_path))
                            <form method="POST" action="{{ route('campanhas.banner.remover', $campanha) }}" onsubmit="return confirm('Remover a imagem de fundo?')">
                                @csrf @method('DELETE')
                                <button class="camp-btn camp-btn-perigo camp-btn-pequeno">Remover imagem de fundo</button>
                            </form>
                        @endif
                    </details>
                    @endif
                    <form method="POST" action="{{ route('campanhas.destroy', $campanha) }}"
                          onsubmit="return confirm('Excluir a campanha? Jogadores, vínculos de fichas e sons serão apagados. Os tabletops continuam existindo.')">
                        @csrf @method('DELETE')
                        <button class="camp-btn camp-btn-perigo">Excluir campanha</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>

    {{-- ===== Miniaturas das fichas (uma por ficha aberta; o JS clona o modelo) ===== --}}
    <div id="camp-ficha-pilha" aria-live="polite"></div>

    <template id="camp-ficha-tpl">
        <dialog class="camp-ficha-modal" aria-label="Miniatura da ficha">
            <div class="camp-ficha-foto js-ficha-foto"></div>
            <div class="camp-ficha-corpo">
                <div>
                    <h3 class="js-ficha-nome"></h3>
                    <span class="camp-dica js-ficha-jogador"></span>
                </div>

                <div class="camp-stats">
                    @foreach (['pv' => 'PVs', 'aura' => 'Aura', 'sanidade' => 'Sanidade'] as $chave => $rotulo)
                        <div class="js-stat" data-stat="{{ $chave }}">
                            <div class="camp-pv-rotulo">
                                <span>{{ $rotulo }}</span>
                                <strong class="js-stat-texto">—</strong>
                            </div>
                            <div class="camp-pv-barra camp-barra-{{ $chave }}">
                                <span class="js-stat-barra"></span>
                            </div>
                            {{-- mestre ou dono: ajusta direto na miniatura (o JS mostra e grava na ficha real) --}}
                            <div class="camp-stat-editar js-stat-editar" hidden>
                                <button type="button" class="camp-btn camp-btn-pequeno js-stat-delta" data-delta="-1" aria-label="Diminuir">−</button>
                                <input type="number" class="js-stat-atual" aria-label="Atual" placeholder="atual">
                                <span class="camp-dica">/</span>
                                <input type="number" class="js-stat-max" min="0" aria-label="Máximo" placeholder="máx">
                                <button type="button" class="camp-btn camp-btn-pequeno js-stat-delta" data-delta="1" aria-label="Aumentar">+</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="camp-ficha-acoes">
                    <form method="POST" enctype="multipart/form-data" class="js-foto-form" hidden>
                        @csrf
                        <input type="file" name="foto" class="js-foto-input" accept="image/png,image/jpeg,image/webp" hidden>
                        <button type="button" class="camp-btn js-foto-escolher"><x-icone n="camera"/> Trocar foto</button>
                    </form>
                    <a href="#" class="camp-btn js-ficha-link" hidden>Abrir ficha</a>
                    <form method="dialog"><button class="camp-btn">Fechar</button></form>
                </div>
            </div>
        </dialog>
    </template>
    {{-- ===== Painel rápido do tabletop: sobe pela direita, só move as peças (sala ao vivo) ===== --}}
    <aside class="camp-tt" id="camp-tt" hidden aria-labelledby="camp-tt-titulo" data-csrf="{{ csrf_token() }}">
        <div class="camp-tt-cab">
            <div class="camp-tt-info">
                <h2 id="camp-tt-titulo">Tabletop</h2>
                <span class="camp-tt-status" id="camp-tt-status">Conectando…</span>
            </div>
            <a href="#" class="camp-btn camp-btn-primario camp-btn-pequeno" id="camp-tt-abrir" hidden>Abrir tabletop completo</a>
            <button type="button" class="camp-btn camp-btn-pequeno" id="camp-tt-fechar" aria-label="Fechar painel do tabletop">✕</button>
        </div>
        <p class="camp-tt-aviso" id="camp-tt-aviso" hidden></p>
        <div class="camp-tt-cena" id="camp-tt-cena">
            <div class="tt-grade" id="camp-tt-grade" aria-label="Mapa do tabletop">
                <img class="tt-mapa" id="camp-tt-mapa" alt="" hidden draggable="false">
                <div class="tt-linhas"></div>
                <svg class="tt-des" id="camp-tt-svg" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"></svg>
                <div class="tt-pecas" id="camp-tt-pecas"></div>
            </div>
        </div>
        <p class="camp-tt-dica">Arraste as peças para movê-las. Todos na mesa veem a mudança em cerca de 1 segundo. Para desenhar, criar peças ou mudar o mapa, abra o tabletop completo.</p>
    </aside>
</div>


{{-- Carregados aqui mesmo, para não depender de o layout ter @yield('script') --}}
<link rel="stylesheet" href="{{ asset('css/campanha-cenas.css') }}?v={{ @filemtime(public_path('css/campanha-cenas.css')) }}">
<script src="{{ asset('js/campanha-cenas.js') }}?v={{ @filemtime(public_path('js/campanha-cenas.js')) }}"></script>
<script src="{{ asset('js/campanha.js') }}?v={{ @filemtime(public_path('js/campanha.js')) }}"></script>
<script src="{{ asset('js/campanha-sync.js') }}?v={{ @filemtime(public_path('js/campanha-sync.js')) }}"></script>
@if (Route::has('campanhas.rolagens.index'))
<script src="{{ asset('js/campanha-dados.js') }}?v={{ @filemtime(public_path('js/campanha-dados.js')) }}"></script>
<script src="{{ asset('js/campanha-iniciativa.js') }}?v={{ @filemtime(public_path('js/campanha-iniciativa.js')) }}"></script>
<script src="{{ asset('js/campanha-catalogo.js') }}?v={{ @filemtime(public_path('js/campanha-catalogo.js')) }}"></script>
@endif
<script src="{{ asset('js/campanha-tabletop.js') }}?v={{ @filemtime(public_path('js/campanha-tabletop.js')) }}"></script>
<script src="{{ asset('js/campanha-recolher.js') }}?v={{ @filemtime(public_path('js/campanha-recolher.js')) }}"></script>
@endsection