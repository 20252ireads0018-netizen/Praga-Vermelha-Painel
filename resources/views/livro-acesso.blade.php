@extends('layout')

@section('title', 'Livro do Sistema | Praga Vermelha')

@section('conteudo')
<link rel="stylesheet" href="{{ asset('css/leitor.css') }}">

<section class="pv-reader">
    <div class="pv-container" style="max-width:520px;padding:48px 0">
        <span class="pv-eyebrow">Praga Vermelha RPG</span>
        <h1 class="pv-reader__title">Livro do Sistema</h1>
        <p>Vai mestrar? Abra o livro do mestre, com tudo. Vai jogar? A versão de jogadores tem as regras sem os segredos do mestre.</p>

        @if (session('aviso'))
            <p class="pv-acesso-aviso">{{ session('aviso') }}</p>
        @endif

        @auth
            @if ($mestres->isNotEmpty() && $pediu)
                <div class="pv-acesso-pedidos">
                    <strong>Permissão dos seus mestres</strong>
                    <ul>
                        @foreach ($mestres as $m)
                            <li>{{ $m['nome'] }}:
                                @if ($m['status'] === 'aprovado') <span class="pv-acesso-ok">aprovou</span>
                                @elseif ($m['status'] === 'recusado') <span class="pv-acesso-nao">recusou</span>
                                @elseif ($m['status'] === 'pendente') <span>aguardando</span>
                                @else <span>ainda não pedido</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('livro.mestre') }}" style="margin-top:16px">
                @csrf
                <button type="submit" class="pv-btn pv-btn--primary" style="width:100%">
                    {{ $mestres->isNotEmpty() && $pediu ? 'Sou mestre: pedir de novo' : 'Sou mestre' }}
                </button>
            </form>
            @if ($mestres->isNotEmpty() && !$pediu)
                <small class="pv-acesso-dica">Você joga em campanhas de outros mestres, então eles recebem um pedido de permissão antes.</small>
            @endif
        @else
            <a href="{{ route('login') }}" class="pv-btn pv-btn--primary" style="display:block;text-align:center;margin-top:16px">Sou mestre (entre na conta)</a>
        @endauth

        <p style="margin-top:12px">
            <a href="{{ route('livro.players') }}" class="pv-btn pv-btn--outline" style="display:block;text-align:center">Sou jogador</a>
        </p>
    </div>
</section>

<style>
    .pv-acesso-aviso { padding: 10px 14px; border: 1px solid var(--pv-gold); border-radius: var(--pv-radius); background: #241d10; }
    .pv-acesso-pedidos { padding: 12px 14px; border: 1px solid var(--pv-border); border-radius: var(--pv-radius); background: var(--pv-surface); }
    .pv-acesso-pedidos ul { margin: 6px 0 0; padding-left: 18px; }
    .pv-acesso-ok { color: #8fd19e; }
    .pv-acesso-nao { color: #e88; }
    .pv-acesso-dica { display: block; margin-top: 6px; color: var(--pv-muted); }
</style>
@endsection
