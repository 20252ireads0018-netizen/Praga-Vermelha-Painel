@extends('layout')

@section('title', 'Privacidade e cookies — Praga Vermelha')

@section('conteudo')

<section class="pv-section">
    <div class="pv-container pv-privacidade">
        <div class="pv-section__head">
            <span class="pv-eyebrow">Praga Vermelha RPG</span>
            <h1 class="pv-section__title">Privacidade e cookies</h1>
            <p class="pv-section__desc">Como o painel do Praga Vermelha RPG trata os seus dados, de acordo com a Lei Geral de Proteção de Dados (Lei 13.709/2018). Atualizada em 04/11/2026.</p>
        </div>

        <h2>Quem cuida dos dados</h2>
        <p>O painel Praga Vermelha RPG ({{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'praga-vermelha.websight.com.br' }}) é mantido por João Victor Barreto Pimentel, responsável pelo tratamento dos dados descritos aqui.</p>

        <h2>Quais dados guardamos e para quê</h2>
        <ul>
            <li><strong>Conta:</strong> nome, e-mail e senha (guardada só como código irreversível), para você entrar e recuperar o acesso.</li>
            <li><strong>O que você cria:</strong> fichas, campanhas, Kuros, mesas, mensagens de chat, rolagens, imagens e sons enviados, para o painel funcionar.</li>
            <li><strong>Compra do livro:</strong> o código do pedido Pix, o valor, o nome do pagador que você informar e a data, para conferir o pagamento e liberar o plano vitalício. Não guardamos dados de cartão nem de conta bancária.</li>
            <li><strong>Segurança:</strong> endereço IP e registros técnicos de acesso, por tempo limitado, para impedir abusos e investigar erros.</li>
            <li><strong>Cópias de segurança:</strong> o banco de dados é copiado uma vez por dia; as cópias ficam fora da parte pública do site e as mais antigas são apagadas sozinhas.</li>
        </ul>
        <p>Não vendemos nem repassamos os seus dados. Eles só são compartilhados com os serviços que fazem o site funcionar (hospedagem e, no plano grátis, o Google AdSense, descrito abaixo) ou quando a lei exigir.</p>

        <h2>Cookies</h2>
        <ul>
            <li><strong>Essenciais (sempre ligados):</strong> a sessão do login, o token de proteção dos formulários (XSRF-TOKEN), o "lembrar de mim" e a sua escolha sobre cookies (pv_cookies). Sem eles, não dá para entrar na conta.</li>
            <li><strong>Preferências no navegador:</strong> algumas telas guardam no seu próprio navegador escolhas como avisos de vez, filtros e janelas abertas. Isso não sai do seu computador.</li>
            <li><strong>Anúncios (só no plano grátis):</strong> o Google AdSense mostra anúncios a quem está no plano grátis. Se você clicar em "Aceitar", o Google pode usar cookies para personalizar os anúncios pelo seu interesse. Em "Só os essenciais", os anúncios continuam, mas sem personalização. Veja <a href="https://policies.google.com/technologies/ads?hl=pt-BR" target="_blank" rel="noopener">como o Google usa os dados nos anúncios</a> e ajuste em <a href="https://adssettings.google.com" target="_blank" rel="noopener">Minha Central de Anúncios</a>.</li>
        </ul>
        <p>Sua escolha atual: <strong id="pv-cookies-atual">—</strong>.
            <button type="button" class="pv-btn pv-btn--outline" onclick="window.pvCookies && window.pvCookies.refazer(); document.getElementById('pv-cookies-atual').textContent = 'ainda não respondida';">Mudar minha escolha</button></p>

        <h2>Seus direitos</h2>
        <p>Você pode pedir para ver, corrigir, levar ou apagar os seus dados, e retirar a permissão dos cookies de anúncios a qualquer momento (botão acima). Para pedidos sobre os seus dados, use o <a href="{{ route('reclamacoes.index') }}">Reclame aqui</a> do painel, com a sua conta. Respondemos em até 15 dias.</p>

        <h2>Crianças</h2>
        <p>O painel não é feito para menores de 13 anos. Quem tem entre 13 e 18 anos deve usá-lo com a autorização dos responsáveis.</p>
    </div>
</section>
<style>
    .pv-privacidade { max-width: 820px; }
    .pv-privacidade h2 { margin: 26px 0 8px; font-size: 1.2rem; }
    .pv-privacidade p, .pv-privacidade li { line-height: 1.6; }
    .pv-privacidade ul { padding-left: 20px; display: flex; flex-direction: column; gap: 6px; }
    .pv-privacidade a { color: #f87171; }
    .pv-privacidade .pv-btn { margin-left: 8px; padding: 6px 14px; font-size: .85rem; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var c = window.pvCookies ? window.pvCookies.escolha() : '';
        document.getElementById('pv-cookies-atual').textContent = c === 'aceito' ? 'anúncios personalizados aceitos' : (c === 'essenciais' ? 'só os essenciais' : 'ainda não respondida');
    });
</script>
@endsection
