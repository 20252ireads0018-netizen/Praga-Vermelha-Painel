@extends('layout')

@section('conteudo')
@include('auth._estilo')

<div class="container">
    <div class="card ficha-card auth-card">

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>Criar conta</h1>
            <p class="ficha-subtitulo">Suas fichas ficam salvas nela</p>
        </div>

        <hr>

        <form action="{{ route('register.store') }}" method="POST">
            @csrf

            <div class="campo">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}"
                       placeholder="Como quer ser chamado(a)" maxlength="100" autocomplete="name" required autofocus>
                @error('name')
                    <p class="auth-erro">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       placeholder="voce@exemplo.com" autocomplete="email" required>
                @error('email')
                    <p class="auth-erro">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="password">Senha</label>
                <input id="password" name="password" type="password"
                       placeholder="Mínimo de 8 caracteres" autocomplete="new-password" required>
                @error('password')
                    <p class="auth-erro">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="password_confirmation">Confirmar senha</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       placeholder="Repita a senha" autocomplete="new-password" required>
            </div>

            <button class="button" type="submit">Criar conta</button>
        </form>

        <p class="auth-rodape">
            Já tem conta? <a href="{{ route('login') }}">Entrar</a>
        </p>
    </div>
</div>
@endsection