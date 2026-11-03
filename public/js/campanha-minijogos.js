/**
 * Minijogos da campanha: Mines, Aviator e caça-níquel, com o Dinheiro de um personagem (ou da carteira).
 * Toda sorte é do servidor (MinijogoController); aqui só se pede a jogada e se anima a resposta.
 */
(function () {
    const el = document.getElementById('mj-dados');
    if (!el) return;
    const D = JSON.parse(el.textContent);
    const $ = s => document.querySelector(s);
    const $$ = s => [...document.querySelectorAll(s)];
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const reais = n => 'R$ ' + Math.floor(+n || 0).toLocaleString('pt-BR');
    const xis = m => (+m).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '×';
    const ls = { get: k => { try { return localStorage.getItem(k); } catch (_) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (_) { /* sem armazenamento */ } } };

    /* ---------- ícones próprios (SVG) ---------- */
    const ICONES = {
        gema: '<svg viewBox="0 0 64 64"><path d="M32 4 52 22 32 60 12 22Z" fill="#f2c45c" stroke="#7a4b12" stroke-width="2"/><path d="M12 22h40M32 4 24 22l8 38 8-38Z" fill="none" stroke="#fff4c8" stroke-width="1.6" opacity=".8"/></svg>',
        kuro: '<svg viewBox="0 0 64 64"><path d="M14 6c4 10 6 14 12 16M50 6c-4 10-6 14-12 16" stroke="#ff6b6b" stroke-width="4" fill="none" stroke-linecap="round"/><path d="M32 16c13 0 20 9 20 21 0 12-9 21-20 21S12 49 12 37c0-12 7-21 20-21Z" fill="#3a0606" stroke="#d63c3c" stroke-width="2.5"/><ellipse cx="32" cy="36" rx="11" ry="7" fill="#ffdddd"/><circle cx="32" cy="36" r="5" fill="#b00"/><path d="M32 31v10" stroke="#200" stroke-width="2"/><path d="M22 50l3-4 3 4 4-4 4 4 3-4 3 4" stroke="#ffb0b0" stroke-width="1.6" fill="none"/></svg>',
        sigilo: '<svg viewBox="0 0 64 64"><circle cx="32" cy="32" r="24" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M32 10 51 43H13ZM32 54 13 21h38Z" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="32" cy="32" r="5" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>',
        corvo: '<svg viewBox="0 0 64 64"><path d="M6 34c10-2 16-12 26-14 6-1 10 1 13 4l9-2-6 6c1 6-2 12-9 16l4 10-8-6c-6 2-12 1-16-3l-6 6 1-9C9 40 7 38 6 34Z" fill="#160a0c" stroke="#d63c3c" stroke-width="2" stroke-linejoin="round"/><circle cx="44" cy="27" r="2.2" fill="#ff5050"/><path d="M18 36c6-1 12-5 16-10" stroke="#5c1a1a" stroke-width="2" fill="none"/></svg>',
        rosa: '<svg viewBox="0 0 64 64"><path d="M32 58V34" stroke="#2f6b2f" stroke-width="3"/><path d="M32 46c-6-1-10-4-12-8 6 0 10 2 12 6M32 50c5-1 9-4 11-8-6 0-9 2-11 6" fill="#2f6b2f"/><path d="M32 8c10 0 18 7 18 16 0 9-8 14-18 14S14 33 14 24c0-9 8-16 18-16Z" fill="#a51e1e" stroke="#ff7a7a" stroke-width="1.5"/><path d="M32 14c5 0 9 4 9 9-4 4-14 4-18 0 0-5 4-9 9-9Zm-4 6c2 2 6 2 8 0" fill="none" stroke="#ffb0b0" stroke-width="1.5"/></svg>',
        olho: '<svg viewBox="0 0 64 64"><path d="M4 32c8-12 18-18 28-18s20 6 28 18c-8 12-18 18-28 18S12 44 4 32Z" fill="#f5e6d0" stroke="#c9a45c" stroke-width="2.5"/><circle cx="32" cy="32" r="11" fill="#a51e1e"/><circle cx="32" cy="32" r="5" fill="#120406"/><circle cx="28" cy="28" r="2.5" fill="#fff"/></svg>',
        selo: '<svg viewBox="0 0 64 64"><circle cx="32" cy="32" r="27" fill="#2a0c10" stroke="#c9a45c" stroke-width="2.5"/><path d="M32 9 52 44H12ZM32 55 12 20h40Z" fill="none" stroke="#f2c45c" stroke-width="2"/><circle cx="32" cy="32" r="6" fill="#d63c3c"/></svg>',
        caveira: '<svg viewBox="0 0 64 64"><path d="M32 6c13 0 22 9 22 21 0 7-3 11-7 14v9H17v-9c-4-3-7-7-7-14C10 15 19 6 32 6Z" fill="#ece3d4" stroke="#7d6a52" stroke-width="2"/><ellipse cx="23" cy="29" rx="6" ry="7" fill="#1a0a0a"/><ellipse cx="41" cy="29" rx="6" ry="7" fill="#1a0a0a"/><path d="M32 36l-4 7h8Z" fill="#1a0a0a"/><path d="M22 50v8M28 50v8M34 50v8M40 50v8" stroke="#7d6a52" stroke-width="2"/></svg>',
        vassago: '<svg viewBox="0 0 64 64"><path d="M8 46 12 16l12 14 8-20 8 20 12-14 4 30Z" fill="#f2c45c" stroke="#7a4b12" stroke-width="2" stroke-linejoin="round"/><rect x="8" y="46" width="48" height="8" rx="2" fill="#c9a45c" stroke="#7a4b12" stroke-width="2"/><path d="M24 30l8 14 8-14" fill="none" stroke="#a51e1e" stroke-width="3.5" stroke-linejoin="round"/><circle cx="12" cy="15" r="3" fill="#d63c3c"/><circle cx="32" cy="9" r="3" fill="#d63c3c"/><circle cx="52" cy="15" r="3" fill="#d63c3c"/></svg>',
    };
    const ico = n => ICONES[n] || '';
    $$('.mj-ico[data-ico]').forEach(s => { s.innerHTML = ico(s.dataset.ico); });

    /* ---------- mensagens, servidor e saldo ---------- */
    function msg(texto, erro) {
        const m = $('#mj-msg');
        m.textContent = texto;
        m.hidden = !texto;
        m.classList.toggle('erro', !!erro);
        clearTimeout(msg.t);
        msg.t = setTimeout(() => { m.hidden = true; }, 4500);
    }
    async function pedir(jogo, acao, dados) {
        const r = await fetch(`${D.url}/${jogo}/${acao}`, { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': D.csrf }, body: JSON.stringify(dados || {}) });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || j.ok === false) {
            const erros = j.errors ? Object.values(j.errors).flat().join(' ') : '';
            throw new Error(j.erro || erros || j.message || 'Não foi possível jogar (erro ' + r.status + ').');
        }
        if (j.saldos) { D.saldos = j.saldos; desenharSaldo(); }
        return j;
    }
    const com = $('#mj-com');
    function desenharSaldo() {
        const atual = com.value || ls.get(D.chave + '-com') || '';
        com.innerHTML = D.saldos.fichas.map(f => `<option value="${f.id}">${esc(f.nome)} (${reais(f.dinheiro)})</option>`).join('') +
            `<option value="0">Carteira da campanha (${reais(D.saldos.carteira)})</option>`;
        if ([...com.options].some(o => o.value === atual)) com.value = atual;
        $('#mj-saldo').textContent = reais(saldo());
    }
    const saldo = () => { const f = D.saldos.fichas.find(x => String(x.id) === com.value); return f ? f.dinheiro : D.saldos.carteira; };
    com.addEventListener('change', () => { ls.set(D.chave + '-com', com.value); $('#mj-saldo').textContent = reais(saldo()); });
    const aposta = jogo => Math.max(1, Math.floor(+$(`.mj-aposta[data-jogo="${jogo}"]`).value || 0));
    const ficha = () => +com.value || null;
    $$('.mj-rapidos').forEach(box => box.addEventListener('click', e => {
        const b = e.target.closest('[data-mult]');
        if (!b) return;
        const campo = $(`.mj-aposta[data-jogo="${box.dataset.jogo}"]`);
        campo.value = b.dataset.mult === 'max' ? Math.max(1, saldo()) : Math.max(1, Math.floor((+campo.value || 1) * +b.dataset.mult));
    }));

    /* ---------- abas ---------- */
    function abrirAba(nome) {
        $$('[data-aba]').forEach(b => b.setAttribute('aria-selected', String(b.dataset.aba === nome)));
        $$('[data-painel]').forEach(p => { p.hidden = p.dataset.painel !== nome; });
        ls.set(D.chave + '-aba', nome);
    }
    $$('[data-aba]').forEach(b => b.addEventListener('click', () => abrirAba(b.dataset.aba)));

    /* ================= Mines ================= */
    let mines = null; // rodada em andamento
    const minasSel = $('#mj-minas');
    minasSel.innerHTML = Array.from({ length: 24 }, (_, i) => `<option value="${i + 1}"${i + 1 === 3 ? ' selected' : ''}>${i + 1}</option>`).join('');
    function desenharGrade(fim) {
        const abertas = new Set((fim ? fim.abertas : mines && mines.abertas) || []);
        const bombas = new Set(fim ? fim.bombas : []);
        $('#mj-grade').innerHTML = Array.from({ length: 25 }, (_, i) => {
            let cls = 'mj-casa', conteudo = ico('sigilo');
            if (abertas.has(i)) { cls += ' aberta'; conteudo = ico('gema'); }
            else if (bombas.has(i)) { cls += ' mina' + (fim && fim.casa === i ? ' explodiu' : ''); conteudo = ico('kuro'); }
            else if (fim) cls += ' apagada';
            return `<button type="button" class="${cls}" data-casa="${i}" ${!mines || abertas.has(i) ? 'disabled' : ''} aria-label="Casa ${i + 1}">${conteudo}</button>`;
        }).join('');
    }
    function desenharMines() {
        const b = $('#mj-mines-botao');
        const info = $('#mj-mines-info');
        minasSel.disabled = !!mines;
        if (mines) {
            b.textContent = mines.abertas.length ? `Sacar ${reais(mines.aposta * mines.mult)} (${xis(mines.mult)})` : 'Abra uma casa';
            b.disabled = !mines.abertas.length;
            info.innerHTML = `Aposta ${reais(mines.aposta)} · ${mines.minas} mina(s)` + (mines.proximo ? ` · próxima casa: <strong>${xis(mines.proximo)}</strong>` : '');
        } else {
            b.textContent = 'Começar';
            b.disabled = false;
        }
    }
    $('#mj-mines-botao').addEventListener('click', async e => {
        const b = e.currentTarget;
        b.disabled = true;
        try {
            if (!mines) {
                const j = await pedir('mines', 'iniciar', { aposta: aposta('mines'), ficha: ficha(), minas: +minasSel.value });
                mines = j.rodada;
                $('#mj-mines-info').textContent = '';
                desenharGrade();
            } else {
                fimMines((await pedir('mines', 'sacar')).fim);
            }
        } catch (err) { msg(err.message, true); }
        desenharMines();
    });
    $('#mj-grade').addEventListener('click', async e => {
        const c = e.target.closest('[data-casa]');
        if (!c || !mines || c.disabled) return;
        $$('#mj-grade .mj-casa').forEach(x => { x.disabled = true; });
        c.classList.add('virando');
        try {
            const j = await pedir('mines', 'abrir', { casa: +c.dataset.casa });
            if (j.fim) fimMines(j.fim); else { mines = j.rodada; desenharGrade(); }
        } catch (err) { msg(err.message, true); desenharGrade(); }
        desenharMines();
    });
    function fimMines(f) {
        mines = null;
        desenharGrade(f);
        $('#mj-mines-info').innerHTML = f.ganhou ? `<span class="mj-ganhou">Sacou ${xis(f.mult)}: +${reais(f.premio)}</span>` : '<span class="mj-perdeu">Um Kuro! A aposta foi perdida.</span>';
        if (!f.ganhou) $('#mj-grade').classList.add('tremendo'), setTimeout(() => $('#mj-grade').classList.remove('tremendo'), 600);
    }

    /* ================= Aviator ================= */
    let voo = null; // { aposta, inicio (performance.now do segundo 0), auto }
    let quadro = 0, consulta = 0;
    const multEm = seg => Math.floor(Math.exp(D.velocidade * Math.max(0, seg)) * 100 + 1e-9) / 100;
    const historico = (() => { try { return JSON.parse(ls.get(D.chave + '-quedas') || '[]'); } catch (_) { return []; } })();
    function desenharHistorico() {
        $('#mj-historico').innerHTML = historico.slice(0, 12).map(q => `<span class="${q >= 2 ? 'alta' : ''}">${xis(q)}</span>`).join('');
    }
    function desenharCeu(mult, seg, estado) {
        // curva até o ponto atual; a escala cresce com o voo para o corvo não sair da tela
        const tMax = Math.max(8, seg * 1.15), mMax = Math.max(2, mult * 1.15);
        const px = t => 10 + (t / tMax) * 370, py = m => 210 - ((m - 1) / (mMax - 1)) * 190;
        let d = `M10 210`;
        for (let i = 1; i <= 40; i++) { const t = seg * i / 40; d += ` L${px(t).toFixed(1)} ${py(multEm(t)).toFixed(1)}`; }
        $('#mj-rastro-linha').setAttribute('d', d);
        $('#mj-rastro').setAttribute('d', d + ` L${px(seg).toFixed(1)} 210 Z`);
        const corvo = $('#mj-corvo');
        corvo.style.left = (px(seg) / 400 * 100) + '%';
        corvo.style.top = (py(mult) / 220 * 100) + '%';
        const m = $('#mj-mult');
        m.textContent = xis(mult);
        $('#mj-ceu').dataset.estado = estado;
    }
    function animar() {
        if (!voo) return;
        const seg = (performance.now() - voo.inicio) / 1000;
        const mult = multEm(seg);
        desenharCeu(mult, seg, 'voando');
        $('#mj-aviator-botao').textContent = `Sacar ${reais(voo.aposta * mult)}`;
        quadro = requestAnimationFrame(animar);
        if (performance.now() - consulta > 450) { consulta = performance.now(); verificarVoo(); }
    }
    let verificando = false;
    async function verificarVoo() {
        if (verificando || !voo) return;
        verificando = true;
        try {
            const j = await pedir('aviator', 'estado');
            if (j.fim) fimVoo(j.fim);
            else if (!j.rodada) fimVoo(null);
        } catch (_) { /* tenta de novo no próximo quadro */ }
        verificando = false;
    }
    function comecarVoo(r) {
        voo = { aposta: r.aposta, inicio: performance.now() - (r.decorrido || 0) * 1000, auto: r.auto };
        $('#mj-aviator-botao').classList.add('sacar');
        $('#mj-auto').disabled = true;
        cancelAnimationFrame(quadro);
        quadro = requestAnimationFrame(animar);
    }
    function fimVoo(f) {
        if (!voo) return;
        const seg = (performance.now() - voo.inicio) / 1000;
        voo = null;
        cancelAnimationFrame(quadro);
        $('#mj-aviator-botao').classList.remove('sacar');
        $('#mj-aviator-botao').textContent = 'Voar';
        $('#mj-aviator-botao').disabled = false;
        $('#mj-auto').disabled = false;
        if (!f) return;
        historico.unshift(f.queda);
        ls.set(D.chave + '-quedas', JSON.stringify(historico.slice(0, 12)));
        desenharHistorico();
        desenharCeu(f.ganhou ? f.mult : f.queda, f.ganhou ? Math.log(f.mult) / D.velocidade : Math.max(seg, Math.log(f.queda) / D.velocidade), f.ganhou ? 'sacou' : 'caiu');
        $('#mj-mult').innerHTML = f.ganhou
            ? `${xis(f.mult)}<small>Sacou: +${reais(f.premio)} · a maldição viria em ${xis(f.queda)}</small>`
            : `${xis(f.queda)}<small>A maldição derrubou o corvo</small>`;
    }
    $('#mj-aviator-botao').addEventListener('click', async e => {
        const b = e.currentTarget;
        b.disabled = true;
        try {
            if (!voo) {
                const auto = parseFloat(String($('#mj-auto').value).replace(',', '.'));
                const j = await pedir('aviator', 'iniciar', { aposta: aposta('aviator'), ficha: ficha(), auto: auto >= 1.01 ? auto : null });
                comecarVoo(j.rodada);
            } else {
                const j = await pedir('aviator', 'sacar');
                if (j.fim) fimVoo(j.fim);
            }
        } catch (err) { msg(err.message, true); }
        b.disabled = false;
    });

    /* ================= Caça-níquel ================= */
    const SIMB = D.simbolos.map(s => s.id);
    $('#mj-tabela').innerHTML = '<thead><tr><th>Símbolo</th><th>3 iguais</th><th>2 primeiros</th></tr></thead><tbody>' +
        D.simbolos.slice().reverse().map(s => `<tr><td><span class="mj-ico pequeno">${ico(s.id)}</span>${esc(s.nome)}</td><td>${s.tres}×</td><td>${s.dois}×</td></tr>`).join('') + '</tbody>';
    const fitas = $$('#mj-rolos .mj-fita');
    const ALTURA = 5; // símbolos de "giro" antes do resultado, por rolo (cresce da esquerda para a direita)
    function preencherFita(f, final, voltas) {
        const lista = [];
        for (let i = 0; i < voltas; i++) lista.push(SIMB[Math.floor(Math.random() * SIMB.length)]);
        lista.push(final);
        f.innerHTML = lista.map(s => `<div class="mj-simb">${ico(s)}</div>`).join('');
        return lista.length;
    }
    fitas.forEach(f => preencherFita(f, SIMB[Math.floor(Math.random() * SIMB.length)], 0));
    $('#mj-slot-botao').addEventListener('click', async e => {
        const b = e.currentTarget;
        b.disabled = true;
        $('#mj-slot-resultado').textContent = '';
        $('#mj-rolos').classList.remove('ganhou');
        try {
            const j = await pedir('slot', 'girar', { aposta: aposta('slot'), ficha: ficha() });
            const f = j.fim;
            await Promise.all(fitas.map((fita, i) => new Promise(ok => {
                const n = preencherFita(fita, f.rolos[i], ALTURA * (i + 2));
                fita.style.transition = 'none';
                fita.style.transform = 'translateY(0)';
                void fita.offsetHeight;
                fita.style.transition = `transform ${1 + i * 0.45}s cubic-bezier(.2, .7, .25, 1.05)`;
                fita.style.transform = `translateY(calc(-${n - 1} * var(--mj-simb)))`;
                setTimeout(ok, 1000 + i * 450);
            })));
            fitas.forEach((fita, i) => { preencherFita(fita, f.rolos[i], 0); fita.style.transition = 'none'; fita.style.transform = 'translateY(0)'; });
            if (f.ganhou) $('#mj-rolos').classList.add('ganhou');
            $('#mj-slot-resultado').innerHTML = f.ganhou ? `<span class="mj-ganhou">${f.vezes}× · +${reais(f.premio)}</span>` : '<span class="mj-perdeu">Nada desta vez.</span>';
        } catch (err) { msg(err.message, true); }
        b.disabled = false;
    });

    /* ---------- começo: saldo, aba lembrada e rodada em andamento ---------- */
    desenharSaldo();
    desenharHistorico();
    desenharCeu(1, 0, 'parado');
    if (D.rodada && D.rodada.jogo === 'mines') { mines = D.rodada; minasSel.value = mines.minas; abrirAba('mines'); }
    else if (D.rodada && D.rodada.jogo === 'aviator') { abrirAba('aviator'); comecarVoo(D.rodada); }
    else abrirAba(ls.get(D.chave + '-aba') || 'mines');
    desenharGrade();
    desenharMines();
})();
