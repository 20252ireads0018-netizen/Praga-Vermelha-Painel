{{-- Aviso de cookies (LGPD): "Aceitar" libera os anúncios personalizados do AdSense; "Só os essenciais" pede anúncios
     não personalizados. A escolha fica no cookie pv_cookies (lido também no <head> do layout) e pode ser refeita na
     página de privacidade. Começa escondido: o script mostra só para quem ainda não respondeu. --}}
<div class="pv-cookies" id="pv-cookies" role="region" aria-label="Aviso de cookies" hidden>
    <p>Usamos cookies essenciais para o login e a segurança da sua conta. Com a sua permissão, o Google AdSense também usa cookies para personalizar os anúncios do plano grátis.
        <a href="{{ route('privacidade') }}">Saiba mais</a></p>
    <div class="pv-cookies__botoes">
        <button type="button" class="pv-btn pv-btn--outline" data-cookies="essenciais">Só os essenciais</button>
        <button type="button" class="pv-btn pv-btn--primary" data-cookies="aceito">Aceitar</button>
    </div>
</div>
<style>
    .pv-cookies { position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 2500; max-width: 760px; margin: 0 auto; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px;
        padding: 14px 16px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, .16); background: #1a0f10; color: #ececec; box-shadow: 0 12px 40px rgba(0, 0, 0, .55);
        font-size: .88rem; animation: pv-cookies-sobe .35s ease both; }
    .pv-cookies[hidden] { display: none; }
    .pv-cookies p { flex: 1 1 320px; margin: 0; line-height: 1.45; }
    .pv-cookies a { color: #f87171; }
    .pv-cookies__botoes { display: flex; gap: 8px; flex-wrap: wrap; }
    .pv-cookies .pv-btn { padding: 8px 16px; font-size: .86rem; }
    @keyframes pv-cookies-sobe { from { transform: translateY(20px); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .pv-cookies { animation: none; } }
</style>
<script>
(function () {
    var caixa = document.getElementById('pv-cookies');
    if (!caixa) return;
    var ler = function () { return (document.cookie.match(/(?:^|; )pv_cookies=([^;]*)/) || [])[1] || ''; };
    window.pvCookies = {
        escolha: ler,
        // apaga a escolha e mostra o aviso de novo (botão da página de privacidade)
        refazer: function () { document.cookie = 'pv_cookies=; Max-Age=0; Path=/; SameSite=Lax'; caixa.hidden = false; }
    };
    if (!ler()) caixa.hidden = false;
    caixa.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cookies]');
        if (!b) return;
        var v = b.dataset.cookies;
        document.cookie = 'pv_cookies=' + v + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        caixa.hidden = true;
        // anúncios que esperavam a resposta seguem agora, personalizados ou não
        if (window.adsbygoogle) {
            if (v === 'essenciais') window.adsbygoogle.requestNonPersonalizedAds = 1;
            window.adsbygoogle.pauseAdRequests = 0;
        }
    });
})();
</script>
