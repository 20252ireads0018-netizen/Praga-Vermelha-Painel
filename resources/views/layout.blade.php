<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Praga Vermelha')</title>

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cards.css') }}">
    <link rel="stylesheet" href="{{ asset('css/Ficha.css') }}">
</head>
<body>

    <header>
        <h1>Praga Vermelha RPG</h1>

        <nav>
            <div class="container">
                <div class="menu-links">
                    <h3><a href="/">Dashboard</a></h3>
                    <h3><a href="/fichas">Criar Ficha</a></h3>
                    <h3><a href="{{ route('personagens.index') }}">Fichas</a></h3>
                </div>
            </div>
        </nav>
        <hr>
    </header>

    <main>
        @yield('conteudo')

        <script src="{{ asset('js/script.js') }}"></script>
        @yield('script')
    </main>

    <hr>
    <footer>
        <p>&copy; {{ date('Y') }} João Victor Barreto Pimentel</p>
    </footer>

</body>
</html>