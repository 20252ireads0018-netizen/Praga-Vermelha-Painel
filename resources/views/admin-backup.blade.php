@extends('layout')

@section('title', 'Backups — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Administração</span>
            <h1 class="pv-section__title">Backups do banco</h1>
            <p class="pv-section__desc">Um backup é feito sozinho uma vez por dia e os {{ $manter }} mais recentes ficam guardados fora da parte pública do site. Para restaurar, importe o arquivo .sql.gz no phpMyAdmin (aba Importar): as tabelas são recriadas com os dados do dia do backup.</p>
        </div>

        @if (session('ok'))
            <div class="pl-alerta pl-alerta--ok">{{ session('ok') }}</div>
        @endif
        @error('backup')
            <div class="pl-alerta">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('admin.backup.gerar') }}" onsubmit="this.querySelector('button').disabled = true">
            @csrf
            <button type="submit" class="camp-btn">Fazer backup agora</button>
        </form>

        <h2 class="px-titulo">Guardados</h2>
        @forelse ($backups as $b)
            <div class="px-pedido">
                <div class="px-pedido__dados">
                    <strong>{{ date('d/m/Y H:i', $b['quando']) }}</strong>
                    <span class="pl-atual__uso">{{ $b['nome'] }} · {{ number_format($b['bytes'] / 1048576, 2, ',', '.') }} MB</span>
                </div>
                <a class="camp-btn" href="{{ route('admin.backup.baixar', $b['nome']) }}">Baixar</a>
            </div>
        @empty
            <p class="pv-section__desc">Nenhum backup ainda.</p>
        @endforelse
    </div>
</section>

@endsection
