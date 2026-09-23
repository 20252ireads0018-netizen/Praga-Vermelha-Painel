@extends('layout')

@section('conteudo')
    <div class="container-topo">
        <div class="card">
            <h1 id="titulo">DashBoard</h1>
        <div class="menu-botoes">
            <a href="/fichas" class="button" id="Ficha">Criar Ficha</a>
            <a href="{{ route('personagens.index') }}" class="button">Fichas</a>
        </div>
        </div>
    </div>

@endsection