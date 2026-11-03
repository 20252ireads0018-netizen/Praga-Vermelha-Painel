@extends('layout')

@section('title', 'Reclame aqui — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Painel Praga Vermelha</span>
            <h1 class="pv-section__title">Reclame aqui</h1>
            <p class="pv-section__desc">Achou um erro, uma carta que não funciona ou tem uma ideia? Registre aqui: todo mundo vê a lista e a resposta da equipe.</p>
        </div>

        @if (session('ok'))
            <div class="pl-alerta pl-alerta--ok">{{ session('ok') }}</div>
        @endif

        @php
            $indice = $numeros['total'] ? round($numeros['resolvidas'] * 100 / $numeros['total']) : null;
            $respostas = $numeros['total'] ? round($numeros['respondidas'] * 100 / $numeros['total']) : null;
        @endphp
        <div class="ra-numeros">
            <div><strong>{{ $numeros['total'] }}</strong><span>reclamações</span></div>
            <div><strong>{{ $respostas === null ? '—' : $respostas . '%' }}</strong><span>respondidas</span></div>
            <div><strong>{{ $indice === null ? '—' : $indice . '%' }}</strong><span>índice de solução</span></div>
            <div><strong>{{ $numeros['abertas'] }}</strong><span>aguardando resposta</span></div>
        </div>

        <div class="ra-grade">
            <div class="ra-col-form">
                @auth
                    @if ($pronto)
                        <form method="POST" action="{{ route('reclamacoes.store') }}" class="ra-form">
                            @csrf
                            <h2>Nova reclamação</h2>
                            <label>Assunto
                                <select name="categoria" required>
                                    @foreach ($categorias as $k => $nome)
                                        <option value="{{ $k }}" @selected(old('categoria') === $k)>{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Título
                                <input type="text" name="titulo" maxlength="120" required value="{{ old('titulo') }}" placeholder="Ex.: Fisiomancia animal não afeta as fichas">
                            </label>
                            <label>O que aconteceu?
                                <textarea name="texto" rows="6" maxlength="4000" required placeholder="Conte o que você fez, o que esperava e o que aconteceu.">{{ old('texto') }}</textarea>
                            </label>
                            <label>Página ou carta (opcional)
                                <input type="text" name="pagina" maxlength="255" value="{{ old('pagina') }}" placeholder="Link da página, nome da carta, da ficha…">
                            </label>
                            @if ($errors->any())
                                <div class="pl-erro">{{ $errors->first() }}</div>
                            @endif
                            <button type="submit" class="pv-btn pv-btn--primary">Registrar reclamação</button>
                        </form>
                    @else
                        <div class="pl-alerta pl-alerta--limite">O Reclame aqui ainda está sendo preparado. Tente novamente mais tarde.</div>
                    @endif
                @else
                    <div class="ra-form">
                        <h2>Nova reclamação</h2>
                        <p>Entre na sua conta para registrar uma reclamação ou sugestão.</p>
                        <a href="{{ route('login') }}" class="pv-btn pv-btn--primary">Entrar</a>
                    </div>
                @endauth
            </div>

            <div class="ra-col-lista">
                <nav class="ra-filtros">
                    <a href="{{ route('reclamacoes.index') }}" class="{{ !$filtro && !$minhas ? 'is-ativo' : '' }}">Todas</a>
                    @foreach ($status as $k => $nome)
                        <a href="{{ route('reclamacoes.index', ['status' => $k]) }}" class="{{ $filtro === $k ? 'is-ativo' : '' }}">{{ $nome }}</a>
                    @endforeach
                    @auth
                        <a href="{{ route('reclamacoes.index', ['minhas' => 1]) }}" class="{{ $minhas ? 'is-ativo' : '' }}">Minhas</a>
                    @endauth
                </nav>

                @forelse ($lista as $r)
                    <article class="ra-item ra-item--{{ $r->status }}" id="r{{ $r->id }}">
                        <header>
                            <span class="ra-status ra-status--{{ $r->status }}">{{ $status[$r->status] ?? $r->status }}</span>
                            <span class="ra-cat">{{ $categorias[$r->categoria] ?? $r->categoria }}</span>
                            <span class="ra-data">#{{ $r->id }} · {{ $r->user->name ?? 'Conta apagada' }} · {{ $r->created_at?->format('d/m/Y H:i') }}</span>
                        </header>
                        <h3>{{ $r->titulo }}</h3>
                        <p class="ra-texto">{{ $r->texto }}</p>
                        @if ($r->pagina)
                            <p class="ra-pagina">Onde: {{ $r->pagina }}</p>
                        @endif
                        @if ($r->resposta)
                            <div class="ra-resposta">
                                <strong>Resposta da equipe</strong>{{ $r->respondida_em ? ' · ' . $r->respondida_em->format('d/m/Y H:i') : '' }}
                                <p>{{ $r->resposta }}</p>
                            </div>
                        @endif
                        @if ($admin)
                            <details class="ra-admin">
                                <summary>Responder</summary>
                                <form method="POST" action="{{ route('reclamacoes.responder', $r) }}">
                                    @csrf
                                    <select name="status">
                                        @foreach ($status as $k => $nome)
                                            <option value="{{ $k }}" @selected($r->status === $k)>{{ $nome }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="resposta" rows="3" maxlength="4000" placeholder="Resposta pública">{{ $r->resposta }}</textarea>
                                    <button type="submit" class="pv-btn pv-btn--primary">Salvar</button>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="pl-atual__uso">Nenhuma reclamação {{ $filtro || $minhas ? 'com esse filtro' : 'ainda' }}.</p>
                @endforelse

                @if ($lista instanceof \Illuminate\Pagination\LengthAwarePaginator && $lista->hasPages())
                    <nav class="ra-paginas">
                        @if ($lista->previousPageUrl())<a href="{{ $lista->previousPageUrl() }}">← Anteriores</a>@endif
                        <span>Página {{ $lista->currentPage() }} de {{ $lista->lastPage() }}</span>
                        @if ($lista->nextPageUrl())<a href="{{ $lista->nextPageUrl() }}">Mais antigas →</a>@endif
                    </nav>
                @endif
            </div>
        </div>
    </div>
</section>

<style>
    .ra-numeros { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
    .ra-numeros div { padding: 14px 16px; background: var(--pv-surface); border: 1px solid var(--pv-border); border-radius: var(--pv-radius); }
    .ra-numeros strong { display: block; font-family: 'Cinzel', serif; font-size: 1.6rem; color: var(--pv-gold); }
    .ra-numeros span { color: var(--pv-muted); font-size: .9rem; }
    .ra-grade { display: grid; grid-template-columns: minmax(260px, 360px) 1fr; gap: 24px; align-items: start; }
    .ra-form { display: flex; flex-direction: column; gap: 12px; padding: 18px; background: var(--pv-surface); border: 1px solid var(--pv-gold); border-radius: var(--pv-radius); position: sticky; top: 90px; }
    .ra-form h2 { font-family: 'Cinzel', serif; font-size: 1.15rem; margin: 0; }
    .ra-form label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; }
    .ra-form input, .ra-form select, .ra-form textarea, .ra-admin select, .ra-admin textarea {
        width: 100%; box-sizing: border-box; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--pv-border); background: var(--pv-bg); color: var(--pv-text); font: inherit; font-weight: 400; }
    .ra-filtros { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .ra-filtros a { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--pv-border); color: var(--pv-muted); text-decoration: none; font-size: .9rem; }
    .ra-filtros a.is-ativo, .ra-filtros a:hover { border-color: var(--pv-gold); color: var(--pv-gold); }
    .ra-item { text-align: left; padding: 16px 18px; margin-bottom: 12px; background: var(--pv-surface); border: 1px solid var(--pv-border); border-radius: var(--pv-radius); }
    .ra-item header { display: flex; flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: flex-start; position: static; padding: 0; margin: 0; background: none; border: 0; box-shadow: none; gap: 6px 10px; font-size: .85rem; }
    .ra-col-lista, .ra-form { text-align: left; }
    .ra-item h3 { margin: 8px 0 6px; font-size: 1.05rem; }
    .ra-texto { margin: 0; white-space: pre-line; overflow-wrap: anywhere; }
    .ra-pagina, .ra-data { color: var(--pv-muted); overflow-wrap: anywhere; }
    .ra-pagina { margin: 8px 0 0; font-size: .9rem; }
    .ra-cat { padding: 2px 8px; border-radius: 999px; background: var(--pv-bg-alt, rgba(255,255,255,.05)); }
    .ra-status { padding: 2px 10px; border-radius: 999px; border: 1px solid var(--pv-border); font-weight: 600; }
    .ra-status--aberta { border-color: var(--pv-gold); color: var(--pv-gold); }
    .ra-status--analise { border-color: #4a7bb7; color: #9cc2ee; }
    .ra-status--resolvida { border-color: #2f6b3a; color: #8fd19e; }
    .ra-status--recusada { border-color: var(--pv-red-dark); color: #e88; }
    .ra-resposta { margin-top: 12px; padding: 10px 14px; border-left: 3px solid var(--pv-gold); background: rgba(201, 164, 92, .08); border-radius: 0; }
    .ra-resposta p { margin: 6px 0 0; white-space: pre-line; overflow-wrap: anywhere; }
    .ra-admin { margin-top: 10px; }
    .ra-admin summary { cursor: pointer; color: var(--pv-gold); }
    .ra-admin form { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
    .ra-admin .pv-btn { align-self: flex-start; }
    .ra-paginas { display: flex; justify-content: space-between; align-items: center; gap: 10px; color: var(--pv-muted); }
    .ra-paginas a { color: var(--pv-gold); }
    @media (max-width: 760px) { .ra-grade { grid-template-columns: 1fr; } .ra-form { position: static; } .ra-numeros { grid-template-columns: repeat(2, 1fr); } }
</style>
@endsection
