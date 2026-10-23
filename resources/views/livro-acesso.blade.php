@extends('layout')

@section('title', 'Livro do Sistema | Praga Vermelha')

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/leitor.css') }}">

<section class="pv-reader">
    <div class="pv-container" style="max-width:480px;padding:48px 0">
        <span class="pv-eyebrow">Praga Vermelha RPG</span>
        <h1 class="pv-reader__title">Livro do Sistema</h1>
        <p>Digite a chave para acessar o livro completo, ou continue com a versão de jogadores.</p>

        <form method="POST" action="{{ route('livro.verificar') }}" style="display:grid;gap:12px;margin-top:16px">
            @csrf
            <input type="password" name="chave" placeholder="Chave de acesso" autocomplete="off" autofocus>
            @error('chave')
                <small style="color:#e66">{{ $message }}</small>
            @enderror
            <button type="submit" class="pv-btn pv-btn--primary">Entrar com a chave</button>
        </form>

        <p style="margin-top:16px">
            <a href="{{ route('livro.players') }}" class="pv-btn pv-btn--outline">Entrar como jogador (sem chave)</a>
        </p>
    </div>
</section>
@endsection