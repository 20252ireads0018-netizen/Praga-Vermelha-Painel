{{-- Cole este trecho no seu layout.blade.php (na navegação/cabeçalho): @include('auth._menu-usuario') --}}
<div class="menu-usuario" style="display:flex; align-items:center; gap:10px;">
    @auth
        <span class="menu-usuario-nome">{{ auth()->user()->name }}</span>
        <a href="{{ route('personagens.index') }}">Minhas fichas</a>
        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="button">Sair</button>
        </form>
    @else
        <a href="{{ route('login') }}">Entrar</a>
        <a href="{{ route('register') }}">Criar conta</a>
    @endauth
</div>