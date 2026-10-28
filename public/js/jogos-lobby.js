/**
 * Jogos da campanha: lobby (Gladio e Pela Dama).
 * Desafios, partidas, Dinheiro, criador de cartas (mestre), criador de peças e exército (cada jogador).
 * A lista de partidas e o Dinheiro são consultados a cada 3 s.
 */
(function () {
    'use strict';

    const raiz = document.getElementById('jg-lobby');
    const dadosEl = document.getElementById('jg-dados');
    if (!raiz || !dadosEl) return;

    const D = JSON.parse(dadosEl.textContent);
    const { esc, post } = window.JG;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const ls = {
        get(k) { try { return localStorage.getItem(k); } catch (_) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (_) { /* ignora */ } },
    };
    const urlPartida = (id, acao) => `${D.url.base}/partidas/${id}${acao ? '/' + acao : ''}`;

    let lobby = D.lobby;
    let cartas = D.cartas;
    let pecas = D.pecas;

    function msg(t, erro) {
        const el = $('#jg-msg');
        el.textContent = t || '';
        el.hidden = !t;
        el.classList.toggle('jg-msg--erro', !!erro);
        if (t && !erro) setTimeout(() => { if (el.textContent === t) el.hidden = true; }, 4000);
    }
    const falhou = e => msg(e.message, true);

    /* ---------- abas ---------- */
    function aba(nome) {
        $$('.jg-aba').forEach(b => { const on = b.dataset.aba === nome; b.classList.toggle('ativo', on); b.setAttribute('aria-selected', on); });
        $$('.jg-painel').forEach(p => { p.hidden = p.dataset.painel !== nome; });
        ls.set('jg-aba', nome);
    }
    $$('.jg-aba').forEach(b => b.addEventListener('click', () => aba(b.dataset.aba)));
    aba(ls.get('jg-aba') === 'pela_dama' ? 'pela_dama' : 'gladio');

    /* ---------- oponentes ---------- */
    const outros = D.membros.filter(m => m.id !== D.eu);
    $$('.jg-oponente').forEach(sel => {
        sel.innerHTML = '<option value="">Qualquer pessoa da campanha</option>' +
            outros.map(m => `<option value="${m.id}">${esc(m.nome)}${m.mestre ? ' (mestre)' : ''}</option>`).join('');
    });

    /* ---------- partidas e Dinheiro ---------- */
    function rotulo(p) {
        if (p.status === 'aberta') return p.convidado ? `Aguardando ${esc(p.convidado)}` : 'Aberto a qualquer um';
        if (p.status === 'andamento') return 'Em andamento';
        if (p.status === 'fim') return p.vencedor ? `Venceu: ${esc(p.vencedor)}` : 'Empate';
        return 'Cancelada';
    }
    function renderPartidas() {
        $$('[data-lista]').forEach(box => {
            const lista = lobby.partidas.filter(p => p.jogo === box.dataset.lista);
            box.innerHTML = lista.length ? lista.map(p => {
                const nomes = esc(p.j1) + (p.j2 ? ' × ' + esc(p.j2) : '');
                const aposta = p.jogo === 'gladio' ? (p.aposta ? ` · aposta ${p.aposta}` : ' · amistoso') : '';
                let botoes = '';
                if (p.aceitar) botoes += `<button type="button" class="camp-btn camp-btn-primario camp-btn-pequeno" data-aceitar="${p.id}" data-jogo="${p.jogo}">Aceitar</button>`;
                if (p.status === 'andamento' || p.status === 'fim')
                    botoes += `<a class="camp-btn camp-btn-pequeno${p.meu && p.status === 'andamento' ? ' camp-btn-primario' : ''}" href="${esc(p.url)}">${p.meu && p.status === 'andamento' ? 'Jogar' : (p.status === 'fim' ? 'Ver' : 'Assistir')}</a>`;
                if (p.cancelar) botoes += `<button type="button" class="camp-btn camp-btn-pequeno" data-cancelar="${p.id}" data-andamento="${p.status === 'andamento' ? 1 : 0}">Cancelar</button>`;
                return `<div class="jg-partida jg-st-${p.status}"><div class="jg-partida-info"><strong>${nomes}</strong>` +
                    `<span class="camp-dica">${rotulo(p)}${aposta}</span></div><div class="jg-partida-botoes">${botoes}</div></div>`;
            }).join('') : '<p class="camp-vazio">Nenhuma partida por enquanto.</p>';
        });
        $('#jg-meu-dinheiro').textContent = Number(lobby.carteiras[D.eu] || 0).toLocaleString('pt-BR');
        renderCarteiras();
    }
    function renderCarteiras() {
        const box = $('#jg-carteiras');
        if (box.contains(document.activeElement)) return; // não atrapalha quem está digitando
        box.innerHTML = D.membros.map(m => {
            const v = Number(lobby.carteiras[m.id] || 0);
            return `<div class="jg-carteira"><span>${esc(m.nome)}${m.mestre ? ' <small>(mestre)</small>' : ''}</span>` +
                (D.souMestre
                    ? `<input type="number" min="0" value="${v}" data-dinheiro="${m.id}" aria-label="Dinheiro de ${esc(m.nome)}"><button type="button" class="camp-btn camp-btn-pequeno" data-salvar-dinheiro="${m.id}">Salvar</button>`
                    : `<strong>${v.toLocaleString('pt-BR')}</strong>`) +
                `</div>`;
        }).join('');
    }

    async function atualizarLobby() {
        try {
            const r = await fetch(D.url.lobby, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (r.ok) { lobby = await r.json(); renderPartidas(); }
        } catch (_) { /* tenta de novo */ }
    }
    setInterval(() => { if (!document.hidden) atualizarLobby(); }, 3000);

    raiz.addEventListener('click', async e => {
        const b = e.target.closest('button');
        if (!b) return;
        try {
            if (b.dataset.aceitar) {
                b.disabled = true;
                const dados = b.dataset.jogo === 'pela_dama' ? { exercito: JSON.stringify(exercito()) } : {};
                const r = await post(urlPartida(b.dataset.aceitar, 'aceitar'), dados, D.csrf);
                location.href = r.url;
            } else if (b.dataset.cancelar) {
                const aviso = b.dataset.andamento === '1' ? 'Cancelar a partida em andamento? As apostas voltam para os dois.' : 'Cancelar este desafio?';
                if (!confirm(aviso)) return;
                lobby = await post(urlPartida(b.dataset.cancelar, 'cancelar'), {}, D.csrf);
                renderPartidas();
            } else if (b.dataset.salvarDinheiro) {
                const id = b.dataset.salvarDinheiro;
                const input = $(`[data-dinheiro="${id}"]`);
                lobby = await post(D.url.dinheiro, { user_id: id, dinheiro: input.value || 0 }, D.csrf);
                b.blur();
                renderPartidas();
                msg('Dinheiro atualizado.');
            }
        } catch (err) { b.disabled = false; falhou(err); }
    });

    $$('.jg-desafio').forEach(f => f.addEventListener('submit', async e => {
        e.preventDefault();
        const dados = { jogo: f.dataset.jogo, convidado_id: f.convidado_id.value || null };
        if (f.dataset.jogo === 'gladio') dados.aposta = f.aposta.value || 0;
        else {
            const ex = exercito();
            if (ex.filter(t => t === 'rei').length !== 1) return msg('Seu exército precisa de exatamente um Rei.', true);
            dados.exercito = JSON.stringify(ex);
        }
        try {
            lobby = await post(D.url.partidas, dados, D.csrf);
            renderPartidas();
            msg('Desafio lançado! A partida começa quando alguém aceitar.');
        } catch (err) { falhou(err); }
    }));

    /* ---------- cartas (Gladio) ---------- */
    function renderCartas() {
        $('#jg-cont-cartas').textContent = cartas.length;
        $('#jg-cartas').innerHTML = cartas.length ? cartas.map(c =>
            `<div class="jg-carta-item">${window.JG.carta(c)}` +
            (D.souMestre ? `<div class="jg-carta-acoes"><button type="button" class="camp-btn camp-btn-pequeno" data-editar-carta="${c.id}">Editar</button>` +
                `<button type="button" class="camp-btn camp-btn-pequeno" data-excluir-carta="${c.id}">Excluir</button></div>` : '') +
            `</div>`).join('') : `<p class="camp-vazio">${D.souMestre ? 'Crie a primeira carta do Gladio.' : 'O mestre ainda não criou cartas.'}</p>`;
    }

    const fc = $('#jg-criador-carta');
    if (fc) {
        let imagem = null;      // data URL nova
        let semImagem = false;  // pediu para tirar a imagem atual
        let imagemAtual = null;
        const lerCarta = () => ({
            nome: fc.nome.value.trim() || 'Nova carta', tipo: fc.tipo.value, custo: +fc.custo.value || 0,
            ataque: +fc.ataque.value || 0, vida: +fc.vida.value || 0, efeito: fc.efeito.value, valor: +fc.valor.value || 0,
            descricao: fc.descricao.value, imagem: semImagem ? null : (imagem || imagemAtual),
        });
        const previa = () => {
            const lenda = fc.tipo.value === 'lenda';
            $$('.jg-so-lenda', fc).forEach(el => { el.hidden = !lenda; });
            $$('.jg-so-feitico', fc).forEach(el => { el.hidden = lenda; });
            $('#jg-previa-carta').innerHTML = window.JG.carta(lerCarta());
        };
        const abrir = c => {
            fc.reset();
            imagem = null; semImagem = false; imagemAtual = c ? c.imagem : null;
            fc.registro.value = c ? c.id : '';
            if (c) ['nome', 'tipo', 'custo', 'ataque', 'vida', 'valor', 'descricao'].forEach(k => { fc[k].value = c[k] == null ? '' : c[k]; });
            if (c && c.efeito) fc.efeito.value = c.efeito;
            fc.hidden = false;
            previa();
            fc.nome.focus();
        };
        $('#jg-nova-carta').addEventListener('click', () => abrir(null));
        fc.addEventListener('input', previa);
        fc.addEventListener('change', async e => {
            if (e.target.name !== 'arquivo' || !e.target.files[0]) return;
            try { imagem = await reduzir(e.target.files[0], 360); semImagem = false; previa(); } catch (err) { falhou(err); }
            e.target.value = '';
        });
        fc.addEventListener('click', e => {
            const a = e.target.closest('[data-acao]');
            if (!a) return;
            if (a.dataset.acao === 'cancelar') fc.hidden = true;
            if (a.dataset.acao === 'tirar-img') { imagem = null; semImagem = true; previa(); }
        });
        fc.addEventListener('submit', async e => {
            e.preventDefault();
            const c = lerCarta();
            const dados = { id: fc.registro.value || null, nome: fc.nome.value.trim(), tipo: c.tipo, custo: c.custo, ataque: c.ataque, vida: c.vida,
                            efeito: c.tipo === 'feitico' ? c.efeito : null, valor: c.valor, descricao: c.descricao };
            if (imagem) dados.imagem = imagem;
            if (semImagem) dados.sem_imagem = 1;
            try {
                const r = await post(D.url.cartas, dados, D.csrf);
                const i = cartas.findIndex(x => x.id === r.carta.id);
                if (i >= 0) cartas[i] = r.carta; else cartas.push(r.carta);
                fc.hidden = true;
                renderCartas();
                msg('Carta salva.');
            } catch (err) { falhou(err); }
        });
        $('#jg-cartas').addEventListener('click', async e => {
            const ed = e.target.closest('[data-editar-carta]');
            const ex = e.target.closest('[data-excluir-carta]');
            if (ed) { abrir(cartas.find(c => c.id === +ed.dataset.editarCarta)); fc.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
            if (ex) {
                const c = cartas.find(x => x.id === +ex.dataset.excluirCarta);
                if (!c || !confirm(`Excluir a carta "${c.nome}"? Partidas em andamento não mudam.`)) return;
                try { await post(`${D.url.cartas}/${c.id}`, {}, D.csrf, 'DELETE'); cartas = cartas.filter(x => x !== c); renderCartas(); } catch (err) { falhou(err); }
            }
        });
    }

    /** Reduz a imagem para no máximo "max" px e devolve um data URL JPEG. */
    function reduzir(arq, max) {
        return new Promise((ok, erro) => {
            if (!/^image\//.test(arq.type)) return erro(new Error('Escolha um arquivo de imagem.'));
            const img = new Image();
            img.onload = () => {
                const k = Math.min(1, max / Math.max(img.width, img.height));
                const cv = document.createElement('canvas');
                cv.width = Math.round(img.width * k); cv.height = Math.round(img.height * k);
                cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
                URL.revokeObjectURL(img.src);
                ok(cv.toDataURL('image/jpeg', 0.82));
            };
            img.onerror = () => erro(new Error('Não deu para ler essa imagem.'));
            img.src = URL.createObjectURL(arq);
        });
    }

    /* ---------- exército (Pela Dama) ---------- */
    const chaveEx = 'jg-exercito-' + D.campanha;
    const tipos = () => {
        const t = {};
        Object.keys(D.padrao).forEach(k => { t[k] = D.padrao[k]; });
        pecas.forEach(p => { t[p.tipo] = { nome: p.nome, simbolo: p.simbolo, regras: p.regras }; });
        return t;
    };
    function exercito() {
        let ex = null;
        try { ex = JSON.parse(ls.get(chaveEx) || 'null'); } catch (_) { ex = null; }
        const t = tipos();
        if (!Array.isArray(ex) || ex.length !== 16) ex = D.exercito.slice();
        return ex.map((id, i) => (t[id] ? id : D.exercito[i])); // peça apagada volta à padrão
    }
    function renderExercito() {
        const t = tipos();
        const ex = exercito();
        const opcoes = sel => Object.keys(t).map(id => `<option value="${esc(id)}"${id === sel ? ' selected' : ''}>${esc(t[id].simbolo)} ${esc(t[id].nome)}</option>`).join('');
        const cols = 'abcdefgh';
        $('#jg-exercito').innerHTML = [0, 8].map(ini => `<div class="jg-ex-linha"><span class="jg-ex-rot">${ini ? 'Fila 2' : 'Fila 1'}</span>` +
            ex.slice(ini, ini + 8).map((id, i) => `<label class="jg-ex-casa"><small>${cols[i]}${ini ? 2 : 1}</small>` +
                `<span class="jg-ex-simb">${esc(t[id].simbolo)}</span><select data-casa="${ini + i}">${opcoes(id)}</select></label>`).join('') +
            '</div>').reverse().join('');
        const reis = ex.filter(id => id === 'rei').length;
        $('#jg-exercito-aviso').textContent = reis === 1 ? '' : 'Atenção: o exército precisa de exatamente um Rei.';
    }
    $('#jg-exercito').addEventListener('change', e => {
        const s = e.target.closest('select[data-casa]');
        if (!s) return;
        const ex = exercito();
        ex[+s.dataset.casa] = s.value;
        ls.set(chaveEx, JSON.stringify(ex));
        renderExercito();
    });
    $('#jg-exercito-padrao').addEventListener('click', () => { ls.set(chaveEx, JSON.stringify(D.exercito)); renderExercito(); });

    /* ---------- peças (Pela Dama) ---------- */
    function renderPecas() {
        $('#jg-cont-pecas').textContent = pecas.length;
        $('#jg-pecas').innerHTML = pecas.length ? pecas.map(p =>
            `<div class="jg-peca-item"><span class="jg-peca-simb">${esc(p.simbolo)}</span>` +
            `<div class="jg-peca-info"><strong>${esc(p.nome)}</strong>${window.JG.diagrama(p, 9)}</div>` +
            `<div class="jg-carta-acoes"><button type="button" class="camp-btn camp-btn-pequeno" data-editar-peca="${p.id}">Editar</button>` +
            `<button type="button" class="camp-btn camp-btn-pequeno" data-excluir-peca="${p.id}">Excluir</button></div></div>`).join('')
            : '<p class="camp-vazio">Você ainda não criou peças. As peças padrão do xadrez estão sempre disponíveis.</p>';
        renderExercito();
    }

    const fp = $('#jg-criador-peca');
    const N = 7; // a grade vai de -7 a +7
    const DIRS = [[0, 1, '↑'], [1, 1, '↗'], [1, 0, '→'], [1, -1, '↘'], [0, -1, '↓'], [-1, -1, '↙'], [-1, 0, '←'], [-1, 1, '↖']];
    const CICLO = ['', 'ambos', 'mover', 'capturar'];
    let saltos = {}; // "dx,dy" => modo
    let raios = {};  // "dx,dy" => { m, alc }

    function regrasDoCriador() {
        const out = [];
        Object.keys(raios).forEach(k => { const [dx, dy] = k.split(',').map(Number); out.push({ dx, dy, d: true, alc: raios[k].alc, m: raios[k].m }); });
        Object.keys(saltos).forEach(k => {
            if (raios[k]) return; // a direção já desliza
            const [dx, dy] = k.split(',').map(Number);
            out.push({ dx, dy, d: false, alc: 1, m: saltos[k] });
        });
        return out;
    }
    function renderCriador() {
        const regras = regrasDoCriador();
        // a grade mostra os saltos (clicáveis) e, mais clarinho, as casas alcançadas deslizando
        const alcance = {};
        regras.filter(r => r.d).forEach(r => { for (let k = 1; k <= r.alc; k++) alcance[(r.dx * k) + ',' + (r.dy * k)] = r.m; });
        let h = '';
        for (let dy = N; dy >= -N; dy--) {
            for (let dx = -N; dx <= N; dx++) {
                const k = dx + ',' + dy;
                if (dx === 0 && dy === 0) { h += `<span class="jg-centro">${esc(fp.simbolo.value || '?')}</span>`; continue; }
                const m = saltos[k] || '';
                const r = alcance[k];
                h += `<button type="button" data-k="${k}" class="${m ? 'jg-m-' + m : ''}${!m && r ? ' jg-raio jg-m-' + r : ''}" aria-label="Casa ${dx}, ${dy}"></button>`;
            }
        }
        $('#jg-grade-mov').innerHTML = h;
        $('#jg-raios').innerHTML = DIRS.map(([dx, dy, seta]) => {
            const k = dx + ',' + dy;
            const r = raios[k];
            return `<div class="jg-raio-linha"><span class="jg-seta">${seta}</span>` +
                `<select data-raio="${k}" aria-label="Deslizar ${seta}">` +
                CICLO.map(m => `<option value="${m}"${(r ? r.m : '') === m ? ' selected' : ''}>${({ '': 'Não desliza', ambos: 'Move e captura', mover: 'Só move', capturar: 'Só captura' })[m]}</option>`).join('') +
                `</select><label>até <input type="number" min="1" max="7" value="${r ? r.alc : 7}" data-alc="${k}"${r ? '' : ' disabled'}> casas</label></div>`;
        }).join('');
    }
    function abrirPeca(p) {
        fp.reset();
        saltos = {}; raios = {};
        fp.registro.value = p ? p.id : '';
        if (p) {
            fp.nome.value = p.nome; fp.simbolo.value = p.simbolo;
            p.regras.forEach(r => { if (r.d) raios[r.dx + ',' + r.dy] = { m: r.m, alc: r.alc }; else saltos[r.dx + ',' + r.dy] = r.m; });
        }
        $('#jg-espelhar').checked = true;
        fp.hidden = false;
        renderCriador();
        fp.nome.focus();
    }
    $('#jg-nova-peca').addEventListener('click', () => abrirPeca(null));
    $('#jg-grade-mov').addEventListener('click', e => {
        const b = e.target.closest('button[data-k]');
        if (!b) return;
        const [dx, dy] = b.dataset.k.split(',').map(Number);
        const novo = CICLO[(CICLO.indexOf(saltos[b.dataset.k] || '') + 1) % CICLO.length];
        const aplicar = k => { if (novo) saltos[k] = novo; else delete saltos[k]; };
        aplicar(b.dataset.k);
        if ($('#jg-espelhar').checked && dx !== 0) aplicar((-dx) + ',' + dy);
        renderCriador();
    });
    $('#jg-raios').addEventListener('change', e => {
        const s = e.target.closest('[data-raio]');
        const a = e.target.closest('[data-alc]');
        if (s) {
            const k = s.dataset.raio;
            if (s.value) raios[k] = { m: s.value, alc: raios[k] ? raios[k].alc : 7 }; else delete raios[k];
            if ($('#jg-espelhar').checked) {
                const [dx, dy] = k.split(',').map(Number);
                const esp = (-dx) + ',' + dy;
                if (dx !== 0) { if (s.value) raios[esp] = { m: s.value, alc: raios[k].alc }; else delete raios[esp]; }
            }
        }
        if (a && raios[a.dataset.alc]) {
            const alc = Math.min(7, Math.max(1, +a.value || 1));
            raios[a.dataset.alc].alc = alc;
            const [dx, dy] = a.dataset.alc.split(',').map(Number);
            if ($('#jg-espelhar').checked && dx !== 0 && raios[(-dx) + ',' + dy]) raios[(-dx) + ',' + dy].alc = alc;
        }
        renderCriador();
    });
    fp.simbolo.addEventListener('input', () => { const c = $('#jg-grade-mov .jg-centro'); if (c) c.textContent = fp.simbolo.value || '?'; });
    fp.addEventListener('click', e => {
        const a = e.target.closest('[data-acao]');
        if (!a) return;
        if (a.dataset.acao === 'cancelar') fp.hidden = true;
        if (a.dataset.acao === 'limpar') { saltos = {}; raios = {}; renderCriador(); }
    });
    fp.addEventListener('submit', async e => {
        e.preventDefault();
        const regras = regrasDoCriador();
        if (!regras.length) return msg('Marque pelo menos um movimento para a peça.', true);
        try {
            const r = await post(D.url.pecas, { id: fp.registro.value || null, nome: fp.nome.value.trim(), simbolo: fp.simbolo.value.trim(), regras: JSON.stringify(regras) }, D.csrf);
            const i = pecas.findIndex(p => p.id === r.peca.id);
            if (i >= 0) pecas[i] = r.peca; else pecas.push(r.peca);
            fp.hidden = true;
            renderPecas();
            msg('Peça salva. Escale-a no seu exército.');
        } catch (err) { falhou(err); }
    });
    $('#jg-pecas').addEventListener('click', async e => {
        const ed = e.target.closest('[data-editar-peca]');
        const ex = e.target.closest('[data-excluir-peca]');
        if (ed) { abrirPeca(pecas.find(p => p.id === +ed.dataset.editarPeca)); fp.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        if (ex) {
            const p = pecas.find(x => x.id === +ex.dataset.excluirPeca);
            if (!p || !confirm(`Excluir a peça "${p.nome}"? No exército ela volta a ser a peça padrão.`)) return;
            try { await post(`${D.url.pecas}/${p.id}`, {}, D.csrf, 'DELETE'); pecas = pecas.filter(x => x !== p); renderPecas(); } catch (err) { falhou(err); }
        }
    });

    renderPartidas();
    renderCartas();
    renderPecas();
})();
