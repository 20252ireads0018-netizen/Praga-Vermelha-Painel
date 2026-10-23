@extends('layout')

@section('conteudo')
@include('auth._estilo')

<div class="container">
    <div class="card ficha-card auth-card">

        <div class="ficha-header">
            <span class="selo">△</span>
            <h1>Entrar</h1>
            <p class="ficha-subtitulo">Acesse suas fichas</p>
        </div>

        <hr>

        @if(session('status'))
            <p class="auth-status">{{ session('status') }}</p>
        @endif

        <form action="{{ route('login.attempt') }}" method="POST">
            @csrf

            <div class="campo">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       placeholder="voce@exemplo.com" autocomplete="email" required autofocus>
                @error('email')
                    <p class="auth-erro">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="password">Senha</label>
                <input id="password" name="password" type="password"
                       placeholder="Sua senha" autocomplete="current-password" required>
                @error('password')
                    <p class="auth-erro">{{ $message }}</p>
                @enderror
            </div>

            <label class="auth-lembrar">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                Manter conectado
            </label>

            <button class="button" type="submit">Entrar</button>
        </form>

        <p class="auth-rodape">
            Ainda não tem conta? <a href="{{ route('register') }}">Criar conta</a>
        </p>
    </div>
</div>
@endsection