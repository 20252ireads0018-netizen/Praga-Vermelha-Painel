@extends('layout')

@section('title', 'Pagamentos Pix — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Administração</span>
            <h1 class="pv-section__title">Pagamentos Pix</h1>
            <p class="pv-section__desc">Confira no extrato da sua conta se o Pix chegou (valor, nome de quem pagou e horário) e aprove: o plano vitalício é ativado na hora. Recusar devolve a pessoa para pagar de novo.</p>
        </div>

        @if (session('ok'))
            <div class="pl-alerta pl-alerta--ok">{{ session('ok') }}</div>
        @endif

        <h2 class="px-titulo">Para conferir</h2>
        @forelse ($abertos as $p)
            <div class="px-pedido {{ $p->status === 'informado' ? 'px-pedido--informado' : '' }}">
                <div class="px-pedido__dados">
                    <strong>{{ $p->user->name ?? 'Conta apagada' }}</strong>
                    <span class="pl-atual__uso">{{ $p->user->email ?? '' }}</span>
                    <span>Pedido <strong class="px-codigo">{{ $p->codigo }}</strong> · R$ {{ number_format((float) $p->valor, 2, ',', '.') }}</span>
                    @if ($p->status === 'informado')
                        <span class="px-selo px-selo--informado">Disse que pagou {{ $p->informado_em?->format('d/m H:i') }}{{ $p->pagador ? ' · pagador: ' . $p->pagador : '' }}</span>
                    @else
                        <span class="px-selo">Gerou o Pix {{ $p->created_at?->format('d/m H:i') }} · ainda não avisou</span>
                    @endif
                </div>
                <div class="px-pedido__acoes">
                    <form method="POST" action="{{ route('admin.pix.decidir', $p->id) }}">
                        @csrf <input type="hidden" name="acao" value="aprovar">
                        <button type="submit" class="pv-btn pv-btn--primary">Aprovar</button>
                    </form>
                    <form method="POST" action="{{ route('admin.pix.decidir', $p->id) }}" onsubmit="return confirm('Recusar o pedido {{ $p->codigo }}?')">
                        @csrf <input type="hidden" name="acao" value="recusar">
                        <button type="submit" class="pv-btn pv-btn--outline">Recusar</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="pl-atual__uso">Nenhum pagamento esperando conferência.</p>
        @endforelse

        <h2 class="px-titulo">Decididos recentemente</h2>
        @forelse ($decididos as $p)
            <div class="px-pedido px-pedido--feito">
                <div class="px-pedido__dados">
                    <strong>{{ $p->user->name ?? 'Conta apagada' }}</strong>
                    <span>Pedido <span class="px-codigo">{{ $p->codigo }}</span> · R$ {{ number_format((float) $p->valor, 2, ',', '.') }}{{ $p->pagador ? ' · pagador: ' . $p->pagador : '' }}</span>
                </div>
                <span class="px-selo {{ $p->status === 'aprovado' ? 'px-selo--ok' : 'px-selo--nao' }}">{{ $p->status === 'aprovado' ? 'Aprovado' : 'Recusado' }} {{ $p->decidido_em?->format('d/m H:i') }}</span>
            </div>
        @empty
            <p class="pl-atual__uso">Nada por aqui ainda.</p>
        @endforelse
    </div>
</section>

@endsection
