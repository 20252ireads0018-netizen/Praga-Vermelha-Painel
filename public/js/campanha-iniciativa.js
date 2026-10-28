/**
 * Campanhas: aba "Iniciativa" dentro do bloco do pote de dados.
 * - Abas simples Dados / Iniciativa (escolha lembrada em localStorage).
 * - Lista vem ordenada do servidor (menor resultado começa); polling a cada 3 s.
 */
(function () {
    const app = document.getElementById('camp-app');
    const box = document.getElementById('camp-dados');
    if (!app || !box) return;

    const csrf = app.dataset.csrf;
    const painelIni = box.querySelector('[data-painel="iniciativa"]');
    const painelDados = box.querySelector('[data-painel="dados"]');
    const abas = box.querySelectorAll('[data-aba-dados]');
    if (!painelIni || !painelDados || !abas.length) return;

    const url = painelIni.dataset.urlIni;
    const CHAVE = 'camp-dados-aba';
    const selFicha = painelIni.querySelector('.js-ini-ficha');
    const form = painelIni.querySelector('.js-ini-form');
    const btnRolar = painelIni.querySelector('.js-ini-rolar');
    const erroEl = painelIni.querySelector('.js-ini-erro');
    const lista = painelIni.querySelector('.js-ini-lista');
    const vazio = painelIni.querySelector('.js-ini-vazio');
    const contagem = painelIni.querySelector('.js-ini-contagem');
    const btnNova = painelIni.querySelector('.js-ini-nova');

    let assinatura = null;
    let ativa = false;
    let timer = null;
    let ocupado = false;
    let fichas = [];
    let itens = [];
    let souMestre = box.dataset.mestre === '1';

    // ---------- abas ----------
    function mostrarAba(nome) {
        ativa = nome === 'iniciativa';
        abas.forEach((b) => b.classList.toggle('ativo', b.dataset.abaDados === nome));
        painelDados.hidden = ativa;
        painelIni.hidden = !ativa;
        try { localStorage.setItem(CHAVE, nome); } catch (e) { /* ignora */ }
        if (ativa) { carregar(); iniciarPolling(); } else { pararPolling(); }
    }
    abas.forEach((b) => b.addEventListener('click', () => mostrarAba(b.dataset.abaDados)));

    // ---------- utilidades ----------
    function erro(msg) {
        erroEl.textContent = msg || '';
        erroEl.hidden = !msg;
    }
    function sinal(n) { return n < 0 ? '−' + Math.abs(n) : String(n); }
    function el(tag, cls, txt) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (txt != null) e.textContent = txt;
        return e;
    }
    function cabecalhos(extra) {
        return Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, extra || {});
    }
    async function lerJson(r) {
        try { return await r.json(); } catch (e) { return {}; }
    }

    // ---------- desenho ----------
    function jaRolou(fichaId) {
        return itens.some((i) => String(i.ficha_id) === String(fichaId));
    }

    function desenharSelect() {
        const atual = selFicha.value;
        selFicha.textContent = '';
        fichas.forEach((f) => {
            const rolou = jaRolou(f.id);
            const bonus = Number(f.bonus) || 0;
            const o = document.createElement('option');
            o.value = f.id;
            o.textContent = f.nome + ' · Iniciativa ' + (bonus >= 0 ? '+' : '−') + Math.abs(bonus) + (rolou ? ' · ✓ já rolou' : '');
            o.disabled = rolou && !souMestre;
            selFicha.appendChild(o);
        });
        if (atual && Array.from(selFicha.options).some((o) => o.value === atual && !o.disabled)) {
            selFicha.value = atual;
        } else {
            const livre = Array.from(selFicha.options).find((o) => !o.disabled);
            if (livre) selFicha.value = livre.value;
        }
        const semOpcao = !selFicha.options.length || !Array.from(selFicha.options).some((o) => !o.disabled);
        btnRolar.disabled = semOpcao || ocupado;
        selFicha.disabled = !selFicha.options.length;
    }

    function desenharLista() {
        lista.textContent = '';
        itens.forEach((it, idx) => {
            const li = el('li', 'camp-ini-item' + (idx === 0 ? ' primeiro' : '') + (it.meu ? ' meu' : ''));
            li.appendChild(el('span', 'camp-ini-pos', (it.posicao || idx + 1) + 'º'));

            const meio = el('div', 'camp-ini-meio');
            const nome = el('span', 'camp-ini-nome', it.nome);
            if (idx === 0) nome.appendChild(el('span', 'camp-ini-comeca', 'começa'));
            meio.appendChild(nome);
            if (it.jogador) meio.appendChild(el('span', 'camp-ini-jogador', it.jogador));
            const dado = Number(it.dado) || 0;
            const bonus = Number(it.bonus) || 0;
            meio.appendChild(el('span', 'camp-ini-conta', 'd100 ' + dado + ' − ' + bonus + ' = ' + sinal(Number(it.resultado))));
            li.appendChild(meio);

            li.appendChild(el('span', 'camp-ini-res', sinal(Number(it.resultado))));

            if (it.pode_remover) {
                const x = el('button', 'botao-fechar camp-ini-x', '✕');
                x.type = 'button';
                x.title = 'Remover';
                x.setAttribute('aria-label', 'Remover ' + it.nome);
                x.addEventListener('click', () => remover(it.id));
                li.appendChild(x);
            }
            lista.appendChild(li);
        });
        vazio.hidden = itens.length > 0;
        contagem.textContent = itens.length ? itens.length + (itens.length === 1 ? ' rolagem' : ' rolagens') : '';
        if (btnNova) btnNova.hidden = !itens.length;
    }

    function aplicar(d) {
        itens = Array.isArray(d.itens) ? d.itens : [];
        fichas = Array.isArray(d.fichas) ? d.fichas : [];
        if (typeof d.mestre === 'boolean') souMestre = d.mestre;
        const ass = itens.map((i) => i.id + ':' + i.resultado + ':' + (i.pode_remover ? 1 : 0)).join('|')
            + '#' + fichas.map((f) => f.id + ':' + f.bonus).join('|') + '#' + (souMestre ? 1 : 0);
        if (ass === assinatura) return;
        assinatura = ass;
        desenharLista();
        desenharSelect();
    }

    // ---------- rede ----------
    async function carregar() {
        if (ocupado) return;
        try {
            const r = await fetch(url, { headers: cabecalhos(), credentials: 'same-origin' });
            if (!r.ok) return;
            aplicar(await lerJson(r));
        } catch (e) { /* tenta no próximo ciclo */ }
    }

    function iniciarPolling() {
        pararPolling();
        timer = setInterval(() => {
            if (document.hidden || !ativa) return;
            carregar();
        }, 3000);
    }
    function pararPolling() {
        if (timer) { clearInterval(timer); timer = null; }
    }
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && ativa) carregar();
    });

    async function chamar(opts) {
        ocupado = true;
        btnRolar.disabled = true;
        erro('');
        try {
            const r = await fetch(opts.url || url, {
                method: opts.method,
                headers: cabecalhos(),
                body: opts.body,
                credentials: 'same-origin'
            });
            const d = await lerJson(r);
            if (!r.ok) {
                erro(d.erro || d.message || 'Não foi possível concluir (' + r.status + ').');
                return false;
            }
            return true;
        } catch (e) {
            erro('Falha de conexão. Tente de novo.');
            return false;
        } finally {
            ocupado = false;
            assinatura = null;
            await carregar();
            desenharSelect();
        }
    }

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        if (!selFicha.value || ocupado) return;
        const fd = new FormData();
        fd.append('ficha_id', selFicha.value);
        chamar({ method: 'POST', body: fd });
    });

    function remover(id) {
        chamar({ method: 'DELETE', url: url.replace(/\/$/, '') + '/' + encodeURIComponent(id) });
    }

    if (btnNova) {
        btnNova.addEventListener('click', () => {
            if (!confirm('Começar uma nova rodada? A lista de iniciativas será apagada para todos.')) return;
            chamar({ method: 'DELETE' });
        });
    }

    // ---------- início ----------
    let inicial = 'dados';
    try { inicial = localStorage.getItem(CHAVE) || 'dados'; } catch (e) { /* ignora */ }
    if (inicial !== 'iniciativa') inicial = 'dados';
    if (inicial === 'iniciativa') mostrarAba('iniciativa');
})();
