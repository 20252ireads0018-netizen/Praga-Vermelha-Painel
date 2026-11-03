@extends('layout')

@section('title', 'Pagar com Pix — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Livro do sistema · plano vitalício</span>
            <h1 class="pv-section__title">Pagar com Pix</h1>
            <p class="pv-section__desc">{{ $preco }}, pagamento único. Depois de conferido, sua conta fica sem anúncios e sem limites para sempre.</p>
        </div>

        @if (session('ok'))
            <div class="pl-alerta pl-alerta--ok">{{ session('ok') }}</div>
        @endif
        @if ($recusado)
            <div class="pl-alerta pl-alerta--limite">Não encontramos o pagamento do seu pedido anterior. Se você já pagou, gere o Pix abaixo só se for pagar de novo; senão, fale com a gente pelo e-mail do site.</div>
        @endif

        <div class="px-pagar">
            <div class="px-qr-caixa">
                <div id="pix-qr" class="px-qr" data-texto="{{ $copiaECola }}" role="img" aria-label="QR Code do Pix de {{ $preco }}"></div>
                <div class="px-valor">{{ $preco }}</div>
                <small class="pl-atual__uso">Pedido <strong class="px-codigo">{{ $pedido->codigo }}</strong></small>
            </div>

            <div class="px-passos">
                <ol>
                    <li>Abra o app do seu banco e escolha <strong>Pix → Ler QR Code</strong> ou <strong>Pix copia e cola</strong>.</li>
                    <li>Confira o valor (<strong>{{ $preco }}</strong>) e o recebedor (<strong>{{ config('pix.nome') }}</strong>) e pague.</li>
                    <li>Volte aqui e clique em <strong>Já paguei</strong>. Conferimos o pagamento e ativamos o plano na sua conta.</li>
                </ol>

                <label class="px-rotulo" for="pix-copia">Pix copia e cola</label>
                <div class="px-copia">
                    <textarea id="pix-copia" readonly rows="3">{{ $copiaECola }}</textarea>
                    <button type="button" class="pv-btn pv-btn--outline" id="pix-copiar">Copiar código</button>
                </div>

                @if ($pedido->status === 'informado')
                    <div class="pl-alerta pl-alerta--ok px-informado">
                        Aviso de pagamento recebido em {{ $pedido->informado_em?->format('d/m/Y \à\s H:i') }}{{ $pedido->pagador ? ' (pago por ' . $pedido->pagador . ')' : '' }}.
                        Estamos conferindo; quando for aprovado, o plano vitalício aparece em <a href="{{ route('planos.index') }}">Planos</a>.
                    </div>
                @else
                    <form method="POST" action="{{ route('livro.pix.paguei') }}" class="px-paguei">
                        @csrf
                        <label class="px-rotulo" for="pix-pagador">Nome de quem pagou (como aparece no Pix) <span class="pl-atual__uso">opcional, ajuda a achar seu pagamento</span></label>
                        <input id="pix-pagador" name="pagador" type="text" maxlength="80" value="{{ old('pagador', auth()->user()->name) }}">
                        @error('pagador') <span class="pl-erro">{{ $message }}</span> @enderror
                        <button type="submit" class="pv-btn pv-btn--primary">Já paguei</button>
                    </form>
                @endif
                <a href="{{ route('planos.index') }}" class="px-voltar">Voltar para os planos</a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
(function () {
    const caixa = document.getElementById('pix-qr');
    try {
        const qr = qrcode(0, 'M');
        qr.addData(caixa.dataset.texto);
        qr.make();
        caixa.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 3, scalable: true });
    } catch (e) {
        caixa.textContent = 'Não deu para desenhar o QR Code. Use o Pix copia e cola ao lado.';
    }
    const botao = document.getElementById('pix-copiar'), campo = document.getElementById('pix-copia');
    botao.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(campo.value); }
        catch (e) { campo.select(); document.execCommand('copy'); }
        botao.textContent = 'Copiado!';
        setTimeout(() => { botao.textContent = 'Copiar código'; }, 2000);
    });
})();
</script>
@endsection
