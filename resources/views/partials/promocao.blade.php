{{-- Faixa da promoção relâmpago do livro (App\Support\Promocao), com a contagem regressiva; some sozinha no fim --}}
@php $promoFaixa = \App\Support\Promocao::atual(); @endphp
@if ($promoFaixa && !\App\Support\Plano::comprouLivro(auth()->user()))
    <a href="{{ route('planos.index') }}" class="pv-promo" data-promo-faixa>
        <span class="pv-promo__raio" aria-hidden="true">⚡</span>
        <span><strong>Promoção relâmpago:</strong> livro do sistema com acesso vitalício de
            <s>{{ \App\Support\CompraLivro::precoTexto(\App\Support\CompraLivro::precoCheio()) }}</s>
            por <strong>{{ \App\Support\CompraLivro::precoTexto() }}</strong></span>
        <span class="pv-promo__tempo">termina em <b data-promo-fim="{{ $promoFaixa['fim'] }}">…</b></span>
    </a>
@endif
@once
<style>
    .pv-promo { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 4px 12px; padding: 9px 16px; text-align: center;
        background: linear-gradient(90deg, #5c0b0f, #9b2226 50%, #5c0b0f); background-size: 200% 100%; animation: pv-promo-brilho 4s linear infinite;
        color: #fff; text-decoration: none; font-size: .92rem; border-bottom: 1px solid rgba(255, 255, 255, .15); }
    .pv-promo:hover { filter: brightness(1.12); }
    .pv-promo s { opacity: .7; }
    .pv-promo__raio { animation: pv-promo-raio 1.2s ease-in-out infinite; }
    .pv-promo__tempo { padding: 1px 10px; border-radius: 999px; background: rgba(0, 0, 0, .3); font-variant-numeric: tabular-nums; }
    .pl-preco-cheio { opacity: .6; margin-right: 4px; }
    .pl-promo-selo { display: inline-block; margin: 4px 0 8px; padding: 2px 10px; border-radius: 999px; background: #9b2226; color: #fff; font-size: .82rem; }
    @keyframes pv-promo-brilho { to { background-position: -200% 0; } }
    @keyframes pv-promo-raio { 50% { transform: scale(1.3); opacity: .7; } }
    @media (prefers-reduced-motion: reduce) { .pv-promo, .pv-promo__raio { animation: none; } }
</style>
<script>
/* contagem regressiva de toda marca data-promo-fim (segundos do servidor); no fim, a página recarrega com o preço cheio */
(function () {
    var difer = {{ time() }} - Math.floor(Date.now() / 1000);
    function tique() {
        var agora = Math.floor(Date.now() / 1000) + difer, acabou = false;
        document.querySelectorAll('[data-promo-fim]').forEach(function (el) {
            var r = +el.dataset.promoFim - agora;
            if (r <= 0) { acabou = true; el.textContent = '0:00'; return; }
            var h = Math.floor(r / 3600), m = Math.floor(r % 3600 / 60), s = r % 60;
            el.textContent = (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
        });
        if (acabou && !tique.feito) { tique.feito = true; setTimeout(function () { location.reload(); }, 1500); }
    }
    if (document.querySelector('[data-promo-fim]')) { tique(); setInterval(tique, 1000); }
    else document.addEventListener('DOMContentLoaded', function () { if (document.querySelector('[data-promo-fim]')) { tique(); setInterval(tique, 1000); } });
})();
</script>
@endonce
