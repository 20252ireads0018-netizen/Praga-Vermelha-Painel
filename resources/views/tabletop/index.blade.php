@extends('layout')

@section('title', 'Tabletop · Praga Vermelha')

@section('conteudo')
<div class="pv-container tt-lista">

    <div class="pv-section__head tt-lista__head">
        <span class="pv-eyebrow">Mesa virtual</span>
        <h1 class="pv-section__title">Tabletop</h1>
        <p class="pv-section__desc">Crie uma mesa, monte o mapa com grade, posicione as peças e desenhe por cima. Tudo é salvo automaticamente.</p>
    </div>

    <form method="POST" action="{{ route('tabletop.store') }}" class="tt-nova-mesa">
        @csrf
        <input type="text" name="nome" maxlength="80" required placeholder="Nome da nova mesa (ex.: Taverna do Corvo)"
               value="{{ old('nome') }}">
        <button type="submit" class="pv-btn pv-btn--primary">Criar mesa</button>
    </form>
    @error('nome')
        <p class="tt-erro">{{ $message }}</p>
    @enderror

    @if($mesas->isEmpty())
        <p class="tt-vazio tt-vazio--grande">Nenhuma mesa criada ainda.</p>
    @else
        <div class="pv-grid pv-grid--3 tt-mesas">
            @foreach($mesas as $mesa)
                <div class="pv-card tt-mesa-card">
                    <div class="pv-card__icon">♜</div>
                    <h3 class="pv-card__title">{{ $mesa->nome }}</h3>
                    <p class="pv-card__text">Atualizada em {{ $mesa->updated_at->format('d/m/Y \à\s H:i') }}</p>
                    <div class="tt-mesa-card__acoes">
                        <a href="{{ route('tabletop.show', $mesa) }}" class="pv-btn pv-btn--outline">Abrir</a>
                        <form method="POST" action="{{ route('tabletop.destroy', $mesa) }}"
                              onsubmit="return confirm('Excluir a mesa &quot;{{ addslashes($mesa->nome) }}&quot;? Não dá para desfazer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pv-btn pv-btn--ghost">Excluir</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection