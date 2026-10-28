/**
 * Campanhas: pote de dados com rolagens públicas (JS + CSS num arquivo só).
 * - O jogador escolhe quantidade, faces e extras (+2, -1, +3-1...) e rola.
 * - A rolagem é feita no servidor e salva; todos consultam /campanhas/{id}/rolagens a cada 2,5s.
 * - Quem rola vê o resultado na hora (resposta do POST); os demais recebem pelo polling.
 * Carregue depois do campanha.js.
 */
(function () {
    const app = document.getElementById('camp-app');
    if (!app) return;

    const url = app.dataset.rolagensUrl;
    const csrf = app.dataset.csrf;
    const box = document.getElementById('camp-dados');
    if (!url || !box) return;

    // ---------- estilos (injetados uma única vez) ----------
    if (!document.getElementById('camp-dados-css')) {
        const css = document.createElement('style');
        css.id = 'camp-dados-css';
        css.textContent = `
/* Tudo na paleta da campanha (variáveis --camp-*): fundo transparente, borda clara, raio 6px, como os .camp-btn */
.camp-dados-form { display: flex; flex-direction: column; gap: .5rem; }
.camp-dados-hab { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; align-items: end; }
.camp-dados-hab[hidden] { display: none; }
.camp-dados-hab label { display: flex; flex-direction: column; gap: .2rem; font-size: .78rem; color: var(--camp-texto-suave); min-width: 0; }
.camp-dados-hab select { width: 100%; min-height: 1.9rem; padding: .25rem .4rem; font: inherit; font-size: .85rem; color: inherit; background: var(--camp-fundo-campo, transparent); border: 1px solid var(--camp-borda-forte); border-radius: 6px; }
.camp-dados-hab select option { color: #000; }
.camp-dados-hab .camp-btn { grid-column: 1 / -1; min-height: 2rem; }
.camp-dados-faces { display: flex; flex-wrap: wrap; gap: .3rem; }
.camp-dados-faces .camp-btn { padding: .2rem .5rem; min-width: 2.6rem; min-height: 1.8rem; font-size: .8rem; }
.camp-dado-ativo { background: var(--camp-acento); border-color: var(--camp-acento); color: #fff; font-weight: 700; }
.camp-dados-linha { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.camp-dados-linha label, .camp-dados-form > label { display: flex; flex-direction: column; gap: .2rem; font-size: .78rem; color: var(--camp-texto-suave); }
.camp-dados-form input {
    min-height: 1.9rem; padding: .25rem .55rem; font: inherit; font-size: .85rem; line-height: 1.2; color: inherit;
    background: transparent; border: 1px solid var(--camp-borda-forte); border-radius: 6px;
    -moz-appearance: textfield; appearance: textfield; transition: background-color .15s, border-color .15s;
}
.camp-dados-form input::placeholder { color: var(--camp-texto-suave); opacity: .7; }
.camp-dados-form input:hover { background: var(--camp-fundo-hover); }
.camp-dados-form input:focus { outline: none; border-color: var(--camp-acento); background: var(--camp-fundo-campo); }
.camp-dados-form input::-webkit-inner-spin-button,
.camp-dados-form input::-webkit-outer-spin-button { -webkit-appearance: none; display: none; margin: 0; }
.camp-dados-qtd { display: flex; gap: .3rem; align-items: stretch; }
.camp-dados-qtd input { flex: 1; min-width: 0; text-align: center; }
.camp-dados-qtd .camp-btn { padding: .2rem .55rem; min-height: 1.9rem; min-width: 2rem; }
.camp-dados-rodape { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.camp-dados-rodape .camp-btn { min-height: 2rem; }
.camp-dados-rodape .js-dados-limpar { margin-left: auto; margin-right: .4rem; min-height: 1.9rem; }
.camp-dados-previa { font-family: ui-monospace, Consolas, monospace; font-size: 1rem; font-weight: 700; }
.camp-dados-erro { color: #e5484d; font-size: .8rem; margin: 0; }
.camp-dados-lista { display: flex; flex-direction: column; gap: .4rem; max-height: 20rem; overflow-y: auto; margin-top: .6rem; padding-right: .2rem; }
/* cartão compacto: nome e fórmula em cima; dados à esquerda e total à direita na mesma linha */
.camp-rolagem { display: grid; grid-template-columns: 1fr auto; gap: .2rem .6rem; align-items: center; padding: .45rem .6rem; border: 1px solid var(--camp-borda); border-radius: 6px; background: var(--camp-fundo-card); }
.camp-rolagem-topo { grid-column: 1 / -1; display: flex; justify-content: space-between; gap: .5rem; align-items: baseline; font-size: .85rem; }
.camp-rolagem-formula { font-family: ui-monospace, Consolas, monospace; font-size: .8rem; opacity: .75; }
.camp-rolagem > .camp-dica { grid-column: 1 / -1; font-size: .78rem; }
.camp-rolagem-dados { display: flex; flex-wrap: wrap; gap: .25rem; align-items: center; }
.camp-rolagem-dado { min-width: 1.7rem; padding: .1rem .3rem; text-align: center; border: 1px solid var(--camp-borda-forte); border-radius: 6px; font-size: .85rem; font-weight: 600; font-variant-numeric: tabular-nums; }
.camp-rolagem-dado.camp-dado-max { border-color: var(--camp-ok); color: var(--camp-ok); }
.camp-rolagem-dado.camp-dado-min { border-color: var(--camp-acento-hover); color: #e5484d; }
.camp-rolagem-bonus { font-weight: 700; opacity: .9; padding: 0 .2rem; font-size: .85rem; }
.camp-rolagem-total { display: flex; align-items: baseline; gap: .35rem; justify-self: end; }
.camp-rolagem-total .camp-dica { font-size: .72rem; }
.camp-rolagem-total strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }
.camp-rolagem-pordado-btn { grid-column: 1 / -1; justify-self: start; background: none; border: 0; padding: .1rem 0; font: inherit; font-size: .74rem; color: var(--camp-texto-suave); cursor: pointer; min-height: 1.5rem; }
.camp-rolagem-pordado-btn:hover { color: inherit; }
.camp-rolagem-pordado { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .25rem; }
.camp-rolagem-pordado[hidden] { display: none; }
.camp-rolagem-pordado .camp-rolagem-dado { font-size: .8rem; }
/* abas dos potes (público + secretos) e formulário de novo pote */
.camp-potes-abas { display: flex; flex-wrap: wrap; gap: .3rem; align-items: center; margin-bottom: .55rem; }
.camp-potes-abas .js-pote-aba.ativo { background: var(--camp-acento); border-color: var(--camp-acento); color: #fff; }
.camp-pote-novo { flex-basis: 100%; }
.camp-pote-novo summary { list-style: none; display: inline-flex; cursor: pointer; }
.camp-pote-novo summary::-webkit-details-marker { display: none; }
.camp-pote-novo .camp-form { margin-top: .5rem; padding: .6rem; border: 1px dashed var(--camp-borda-forte); border-radius: 6px; }
.camp-pote-membros { display: flex; flex-wrap: wrap; gap: .35rem .8rem; font-size: .85rem; }
.camp-pote-membros .camp-dica { flex-basis: 100%; }
.camp-pote-membro { display: inline-flex; align-items: center; gap: .3rem; cursor: pointer; }
.camp-pote-excluir { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; margin: 0 0 .5rem; }
/* animação curta: os dados "giram" números aleatórios e param no valor real, um atrás do outro */
.camp-dado-rolando { animation: camp-dado-gira .14s linear infinite; opacity: .85; }
@keyframes camp-dado-gira { 0% { transform: rotate(-7deg) scale(.95); } 50% { transform: rotate(7deg) scale(1.05); } 100% { transform: rotate(-7deg) scale(.95); } }
.camp-dado-parou { animation: camp-dado-pousa .25s ease-out; }
@keyframes camp-dado-pousa { from { transform: scale(1.4); } to { transform: scale(1); } }
.camp-total-pronto { display: inline-block; animation: camp-dado-pousa .3s ease-out; }
`;
        document.head.appendChild(css);
    }

    const form = box.querySelector('.js-dados-form');
    const inQtd = box.querySelector('.js-dados-qtd');
    const inLados = box.querySelector('.js-dados-lados');
    const inExtras = box.querySelector('.js-dados-extras');
    const inRotulo = box.querySelector('.js-dados-rotulo');
    const btnRolar = box.querySelector('.js-dados-rolar');
    const btnLimpar = box.querySelector('.js-dados-limpar');
    const souMestre = box.dataset.mestre === '1';
    const erro = box.querySelector('.js-dados-erro');
    const lista = box.querySelector('.js-dados-lista');
    const vazio = box.querySelector('.js-dados-vazio');
    const previa = box.querySelector('.js-dados-previa');

    const INTERVALO = 2500;
    let consultando = false;

    // ---------- potes: público ('') e secretos (id) ----------
    // Cada pote guarda seu próprio estado; só o pote aberto é consultado no polling.
    const abas = document.querySelector('.js-potes-abas');
    const rotuloPote = document.querySelector('.js-pote-rotulo');
    const estados = {}; // por pote: { ultimoId, vistos, primeira, itens }
    function estadoDe(p) {
        return estados[p] || (estados[p] = { ultimoId: 0, vistos: new Set(), primeira: true, itens: [] });
    }
    let poteAtual = '';
    try {
        const ini = abas && abas.dataset.poteInicial;
        poteAtual = ini || localStorage.getItem('camp-pote:' + location.pathname) || '';
    } catch (e) {}
    if (!abas || !abas.querySelector('.js-pote-aba[data-pote="' + poteAtual + '"]')) poteAtual = '';

    function mostrarErro(texto) {
        erro.textContent = texto || '';
        erro.hidden = !texto;
    }

    // ---------- prévia da fórmula (ex.: 3d6+2) ----------
    function formula() {
        const qtd = parseInt(inQtd.value, 10) || 1;
        const lados = parseInt(inLados.value, 10) || 0;
        const extras = inExtras.value.replace(/[\s,;]+/g, '');
        const ex = extras && !/^[+-]/.test(extras) ? '+' + extras : extras;
        return qtd + 'd' + lados + ex;
    }
    function atualizarPrevia() {
        previa.textContent = formula();
        box.querySelectorAll('[data-lados]').forEach((b) => {
            b.classList.toggle('camp-dado-ativo', b.dataset.lados === String(inLados.value));
        });
    }

    box.querySelectorAll('[data-lados]').forEach((b) => {
        b.addEventListener('click', () => { inLados.value = b.dataset.lados; atualizarPrevia(); });
    });
    box.querySelectorAll('[data-qtd-delta]').forEach((b) => {
        b.addEventListener('click', () => {
            const n = (parseInt(inQtd.value, 10) || 1) + parseInt(b.dataset.qtdDelta, 10);
            inQtd.value = Math.max(1, Math.min(30, n));
            atualizarPrevia();
        });
    });
    [inQtd, inLados, inExtras].forEach((el) => el.addEventListener('input', atualizarPrevia));
    atualizarPrevia();

    // ---------- renderização ----------
    function el(tag, classe, texto) {
        const e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texto !== undefined) e.textContent = texto;
        return e;
    }

    const expandidas = new Set(); // ids com a visão "por dado" aberta (sobrevive a redesenhos/troca de aba)

    function cartao(r, animar) {
        const item = el('div', 'camp-rolagem');
        item.dataset.id = r.id;

        const topo = el('div', 'camp-rolagem-topo');
        topo.appendChild(el('strong', '', r.jogador));
        const ex = r.extras ? (/^[+-]/.test(r.extras) ? r.extras : '+' + r.extras) : '';
        topo.appendChild(el('span', 'camp-rolagem-formula', r.qtd + 'd' + r.lados + ex));
        item.appendChild(topo);

        if (r.rotulo) item.appendChild(el('div', 'camp-dica', r.rotulo));

        const dados = el('div', 'camp-rolagem-dados');
        const spans = [];
        (r.resultados || []).forEach((v) => {
            let c = 'camp-rolagem-dado';
            if (r.lados > 1 && v === r.lados) c += ' camp-dado-max';
            else if (v === 1) c += ' camp-dado-min';
            const s = el('span', animar ? 'camp-rolagem-dado camp-dado-rolando' : c, animar ? '?' : String(v));
            if (animar) { s.dataset.final = c; s.dataset.valor = String(v); }
            dados.appendChild(s);
            spans.push(s);
        });
        if (r.bonus) {
            dados.appendChild(el('span', 'camp-rolagem-bonus', (r.bonus > 0 ? '+' : '−') + Math.abs(r.bonus)));
        }
        item.appendChild(dados);

        const total = el('div', 'camp-rolagem-total');
        total.appendChild(el('span', 'camp-dica', 'Total'));
        const totalEl = el('strong', '', animar ? '…' : String(r.total));
        total.appendChild(totalEl);
        item.appendChild(total);

        // visão "por dado": o modificador aplicado a cada dado (o total acima não muda)
        const bonus = Number(r.bonus) || 0;
        if (bonus !== 0 && (r.resultados || []).length) {
            const aberto = expandidas.has(r.id);
            const btn = el('button', 'camp-rolagem-pordado-btn', (aberto ? '▾' : '▸') + ' por dado');
            btn.type = 'button';
            btn.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            const linha = el('div', 'camp-rolagem-pordado');
            linha.hidden = !aberto;
            const sinal = bonus > 0 ? '+' : '−';
            r.resultados.forEach((v) => {
                const aj = v + bonus;
                const s = el('span', 'camp-rolagem-dado' + (aj < 0 ? ' camp-dado-min' : ''), (aj < 0 ? '−' : '') + Math.abs(aj));
                s.title = v + ' ' + sinal + ' ' + Math.abs(bonus) + ' = ' + (aj < 0 ? '−' : '') + Math.abs(aj);
                linha.appendChild(s);
            });
            btn.addEventListener('click', () => {
                const abrir = linha.hidden;
                linha.hidden = !abrir;
                btn.textContent = (abrir ? '▾' : '▸') + ' por dado';
                btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
                if (abrir) expandidas.add(r.id); else expandidas.delete(r.id);
            });
            item.appendChild(btn);
            item.appendChild(linha);
        }

        if (animar) animarDados(spans, totalEl, r);
        return item;
    }

    // Cada dado troca de número a cada 60 ms e para no valor real, um atrás do outro; o total aparece por último.
    function animarDados(spans, totalEl, r) {
        const lados = Math.max(2, r.lados || 6);
        spans.forEach((s, i) => {
            const duracao = 550 + i * 140;
            const inicio = performance.now();
            (function tick() {
                if (performance.now() - inicio < duracao) {
                    s.textContent = String(1 + Math.floor(Math.random() * lados));
                    setTimeout(tick, 60);
                } else {
                    s.textContent = s.dataset.valor;
                    s.className = s.dataset.final + ' camp-dado-parou';
                }
            })();
        });
        const fim = 550 + Math.max(0, spans.length - 1) * 140 + 150;
        setTimeout(() => {
            totalEl.textContent = String(r.total);
            totalEl.classList.add('camp-total-pronto');
        }, fim);
    }

    function adicionar(r, destacar) {
        const chave = r.pote_id ? String(r.pote_id) : '';
        const e = estadoDe(chave);
        if (e.vistos.has(r.id)) return;
        e.vistos.add(r.id);
        if (r.id > e.ultimoId) e.ultimoId = r.id;
        e.itens.unshift(r);
        if (e.itens.length > 40) e.itens.length = 40;
        if (chave !== poteAtual) return; // fica guardado para quando a aba for aberta

        const c = cartao(r, destacar);
        if (destacar) c.classList.add('camp-flash');
        lista.prepend(c); // mais recente em cima

        while (lista.children.length > 40) lista.lastElementChild.remove();
        if (vazio) vazio.hidden = true;
    }

    // remove do pote as rolagens com id menor que o limiar (Infinity = todas)
    function podar(p, limiar) {
        const e = estadoDe(p);
        const antes = e.itens.length;
        e.itens = e.itens.filter((r) => r.id >= limiar);
        if (e.itens.length === antes) return;
        if (p === poteAtual) {
            lista.querySelectorAll('.camp-rolagem').forEach((el) => { if (Number(el.dataset.id) < limiar) el.remove(); });
            if (vazio) vazio.hidden = e.itens.length > 0;
        }
    }

    // ---------- limpar histórico (apaga no servidor; os outros recebem pelo polling) ----------
    if (btnLimpar) {
        btnLimpar.addEventListener('click', async () => {
            if (!confirm('Limpar o histórico deste pote para todos?')) return;
            btnLimpar.disabled = true;
            try {
                const r = await fetch(url + (poteAtual ? '?pote=' + encodeURIComponent(poteAtual) : ''), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) { mostrarErro(d.erro || 'Não foi possível limpar o histórico.'); return; }
                podar(poteAtual, Infinity);
            } catch (e) {
                mostrarErro('Falha de conexão. Tente de novo.');
            } finally {
                btnLimpar.disabled = false;
            }
        });
    }

    // troca de aba: re-renderiza do cache e o polling traz o que faltar
    function mostrarPote(p) {
        poteAtual = p;
        try { localStorage.setItem('camp-pote:' + location.pathname, p); } catch (e) {}
        if (abas) abas.querySelectorAll('.js-pote-aba').forEach((b) => b.classList.toggle('ativo', b.dataset.pote === p));
        document.querySelectorAll('.js-pote-excluir').forEach((f) => { f.hidden = f.dataset.pote !== p; });
        if (rotuloPote) rotuloPote.textContent = p ? 'pote secreto' : 'rolagens públicas';
        lista.innerHTML = '';
        const e = estadoDe(p);
        e.itens.forEach((r) => lista.appendChild(cartao(r, false)));
        if (vazio) vazio.hidden = e.itens.length > 0;
        // limpar: o mestre em qualquer pote; num pote secreto, também quem o criou (tem o form de excluir)
        if (btnLimpar) btnLimpar.hidden = !(souMestre || (p && document.querySelector('.js-pote-excluir[data-pote="' + p + '"]')));
        consultar();
    }
    if (abas) {
        abas.addEventListener('click', (ev) => {
            const b = ev.target.closest('.js-pote-aba');
            if (b) mostrarPote(b.dataset.pote);
        });
    }

    // ---------- consulta ----------
    async function consultar() {
        if (consultando || document.hidden) return;
        consultando = true;
        try {
            const e = estadoDe(poteAtual);
            const r = await fetch(url + '?apos=' + e.ultimoId + (poteAtual ? '&pote=' + encodeURIComponent(poteAtual) : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!r.ok) return;
            const d = await r.json();
            // o servidor informa quantas rolagens o pote tem e o menor id: o que foi limpo (manual ou a cada 20) some daqui também
            if (typeof d.total === 'number') podar(poteAtual, d.total === 0 ? Infinity : (d.desde || 0));
            (d.rolagens || []).forEach((x) => adicionar(x, !e.primeira));
            e.primeira = false;
        } catch (e) {
            /* rede instável: tenta no próximo ciclo */
        } finally {
            consultando = false;
        }
    }

    // ---------- rolar ----------
    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        mostrarErro('');
        btnRolar.disabled = true;

        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    qtd: parseInt(inQtd.value, 10),
                    lados: parseInt(inLados.value, 10),
                    extras: inExtras.value,
                    rotulo: inRotulo.value,
                    pote_id: poteAtual ? parseInt(poteAtual, 10) : null,
                }),
            });
            const d = await r.json().catch(() => ({}));

            if (!r.ok) {
                const primeiro = d.errors ? Object.values(d.errors)[0][0] : null;
                mostrarErro(d.erro || primeiro || 'Não foi possível rolar os dados.');
                return;
            }
            adicionar(d.rolagem, true);
        } catch (e) {
            mostrarErro('Falha de conexão. Tente de novo.');
        } finally {
            btnRolar.disabled = false;
        }
    });

    // ---------- rolar habilidade de uma ficha (1d100 − total, mesmo envio do botão Rolar) ----------
    (function () {
        const linha = box.querySelector('.js-dados-hab');
        const selF = box.querySelector('.js-hab-ficha');
        const selH = box.querySelector('.js-hab-habilidade');
        const btnH = box.querySelector('.js-hab-rolar');
        const fonte = document.getElementById('camp-fichas-rolagem');
        if (!linha || !selF || !selH || !btnH || !fonte) return;
        let fichas = [];
        try { fichas = JSON.parse(fonte.textContent) || []; } catch (e) {}
        fichas = (Array.isArray(fichas) ? fichas : []).filter((f) => f && Array.isArray(f.habilidades) && f.habilidades.length);
        if (!fichas.length) return;

        const chaveLS = 'camp-hab:' + location.pathname;
        let salvo = {};
        try { salvo = JSON.parse(localStorage.getItem(chaveLS) || '{}') || {}; } catch (e) {}
        const guardar = () => {
            try { localStorage.setItem(chaveLS, JSON.stringify({ ficha: selF.value, hab: selH.value })); } catch (e) {}
        };

        fichas.forEach((f) => {
            const o = document.createElement('option');
            o.value = String(f.id);
            o.textContent = f.nome;
            selF.appendChild(o);
        });
        const fichaAtual = () => fichas.find((f) => String(f.id) === selF.value) || fichas[0];
        function preencherHabs(manter) {
            selH.innerHTML = '';
            fichaAtual().habilidades.forEach((h) => {
                const o = document.createElement('option');
                o.value = h.chave;
                o.textContent = h.nome + ' (' + h.total + ')';
                selH.appendChild(o);
            });
            if (manter && Array.from(selH.options).some((o) => o.value === manter)) selH.value = manter;
        }
        if (salvo.ficha && fichas.some((f) => String(f.id) === String(salvo.ficha))) selF.value = String(salvo.ficha);
        preencherHabs(salvo.hab);
        linha.hidden = false;

        selF.addEventListener('change', () => { preencherHabs(); guardar(); });
        selH.addEventListener('change', guardar);

        btnH.addEventListener('click', () => {
            const f = fichaAtual();
            const h = f.habilidades.find((x) => x.chave === selH.value);
            if (!h) return;
            guardar();
            const total = Math.trunc(Number(h.total)) || 0;
            inQtd.value = 1;
            inLados.value = 100;
            inExtras.value = total > 0 ? '-' + total : (total < 0 ? '+' + Math.abs(total) : '');
            inRotulo.value = (h.nome + ' — ' + f.nome).slice(0, 60);
            atualizarPrevia();
            form.requestSubmit();
        });
    })();

    document.addEventListener('visibilitychange', () => { if (!document.hidden) consultar(); });

    mostrarPote(poteAtual); // já chama consultar()
    setInterval(consultar, INTERVALO);
})();