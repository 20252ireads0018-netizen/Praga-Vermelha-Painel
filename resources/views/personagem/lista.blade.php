@extends('layout')

@section('conteudo')
<div class="container">
    <div class="card ficha-card">

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>Fichas de Agentes</h1>
            <p class="ficha-subtitulo">Arquivo da Organização</p>
        </div>

        <hr style="width:100%">

        @if($personagens->isEmpty())
            <p class="lista-vazia">Nenhuma ficha foi criada ainda.</p>
        @else
            <div class="lista-fichas-grid">
                @foreach($personagens as $personagem)
                    <a href="{{ route('personagens.show', $personagem->id) }}" class="personagem-card-link">
                        <div class="personagem-card">
                            <form action="{{ route('personagens.destroy', $personagem->id)}}" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta ficha? Essa ação não pode ser desfeita.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="botao-fechar">X</button>
                            </form>
                            @if($personagem->profissao && $personagem->profissao !== 'Selecione')
                                <span class="personagem-card-tag">{{ $personagem->profissao }}</span>
                            @endif
                            <h3 class="personagem-card-nome">{{ $personagem->nome }}</h3>
                            <div class="personagem-card-info">
                                <span>{{ $personagem->raca ?? 'Raça não definida' }}</span>
                                <span>{{ $personagem->idade ?? '?' }} anos</span>
                                <span>Nível {{ $personagem->nivel_mundano ?? 1 }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endsection