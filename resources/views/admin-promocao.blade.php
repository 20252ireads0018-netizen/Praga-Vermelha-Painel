@extends('layout')

@section('title', 'Promoção relâmpago — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Administração</span>
            <h1 class="pv-section__title">Promoção relâmpago</h1>
            <p class="pv-section__desc">O livro do sistema sai pela metade ou por 1/4 do preço durante os minutos que você escolher. Uma faixa com a contagem regressiva aparece no topo do site para todos, e o preço volta sozinho a {{ \App\Support\CompraLivro::precoTexto($cheio) }} quando o tempo acaba. Quem gerou o Pix durante a promoção ainda paga o preço dela por {{ intdiv(\App\Support\Promocao::TOLERANCIA, 60) }} minutos depois do fim.</p>
        </div>

        @if (session('ok'))
            <div class="pl-alerta pl-alerta--ok">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="pl-alerta">{{ $errors->first() }}</div>
        @endif

        @if ($atual)
            <div class="pl-atual">
                <div>
                    <small class="pv-eyebrow">Valendo agora</small>
                    <div class="pl-atual__nome">{{ \App\Support\CompraLivro::precoTexto() }} <s class="pr-cheio">{{ \App\Support\CompraLivro::precoTexto($cheio) }}</s></div>
                </div>
                <div class="pl-atual__uso">Termina às {{ date('H:i', $atual['fim']) }} (<span data-promo-fim="{{ $atual['fim'] }}">…</span>).</div>
                <form method="POST" action="{{ route('admin.promocao.encerrar') }}">
                    @csrf
                    <button type="submit" class="camp-btn">Encerrar agora</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.promocao.ligar') }}" class="pr-form">
            @csrf
            <fieldset>
                <legend>Desconto</legend>
                @foreach ($opcoes as $fator => $o)
                    <label class="pr-opcao"><input type="radio" name="fator" value="{{ $fator }}" @checked(old('fator', '0.5') == $fator)> {{ $o['nome'] }}: <strong>{{ \App\Support\CompraLivro::precoTexto($o['preco']) }}</strong></label>
                @endforeach
            </fieldset>
            <label class="pr-minutos">Duração (minutos)
                <input type="number" name="minutos" min="{{ \App\Support\Promocao::MINUTOS_MIN }}" max="{{ \App\Support\Promocao::MINUTOS_MAX }}" value="{{ old('minutos', 15) }}" required>
            </label>
            <div class="pr-atalhos">
                @foreach ([5, 15, 30, 60] as $m)
                    <button type="button" class="camp-btn camp-btn-pequeno" onclick="this.form.minutos.value = {{ $m }}">{{ $m }} min</button>
                @endforeach
            </div>
            <button type="submit" class="pv-btn pv-btn--primary">{{ $atual ? 'Trocar pela nova promoção' : 'Ligar a promoção' }}</button>
        </form>

        @if ($ultima && !$atual)
            <p class="pv-section__desc">Última promoção: {{ \App\Support\Promocao::FATORES[(string) $ultima['fator']] ?? '' }}, de {{ date('d/m H:i', $ultima['inicio']) }} a {{ date('H:i', $ultima['fim']) }}.</p>
        @endif
        <p class="pv-section__desc"><a href="{{ route('admin.pix') }}">Conferir os pagamentos Pix →</a></p>
    </div>
</section>
<style>
    .pr-form { display: flex; flex-direction: column; gap: 14px; max-width: 460px; margin-top: 18px; }
    .pr-form fieldset { border: 1px solid rgba(255, 255, 255, .14); border-radius: 10px; padding: 10px 14px; display: flex; flex-direction: column; gap: 8px; }
    .pr-opcao { display: flex; align-items: center; gap: 8px; cursor: pointer; }
    .pr-opcao input { width: auto; }
    .pr-minutos { display: flex; flex-direction: column; gap: 4px; }
    .pr-atalhos { display: flex; gap: 6px; flex-wrap: wrap; }
    .pr-cheio { opacity: .6; font-size: .8em; }
</style>
@endsection
