/**
 * Jogos da campanha: tela da partida (Gladio ou Pela Dama).
 * O servidor guarda as regras: aqui só se desenha o estado, escolhe a jogada e manda para /acao.
 * Pergunta "mudou?" a cada ~1 s (o servidor responde só a versão quando nada mudou).
 */
(function () {
    'use strict';

    const dadosEl = document.getElementById('jg-partida-dados');
    const box = document.getElementById('jg-jogo');
    if (!dadosEl || !box) return;

    const C = JSON.parse(dadosEl.textContent);
    const { esc, post } = window.JG;
    const $ = (s, r = document) => r.querySelector(s);
    let D = C.dados;     // { v, jogo, status, aposta, lado, nomes, vencedor, estado }
    let enviando = false;

    function msg(t, erro) {
        const el = $('#jg-msg');
        el.textContent = t || '';
        el.hidden = !t;
        el.classList.toggle('jg-msg--erro', !!erro);
        if (t) setTimeout(() => { if (el.textContent === t) el.hidden = true; }, 3500);
    }

    async function acao(dados) {
        if (enviando) return;
        enviando = true;
        try {
            D = await post(C.url.acao, dados, C.csrf);
            render();
        } catch (e) {
            msg(e.message, true);
        } finally {
            enviando = false;
        }
    }

    async function consultar() {
        if (enviando) return;
        try {
            const r = await fetch(`${C.url.estado}?v=${D.v}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!r.ok) return;
            const j = await r.json();
            if (j.estado !== undefined && !enviando) { D = j; render(); }
        } catch (_) { /* tenta de novo */ }
    }
    (function laco() {
        const fim = D.status === 'fim' || D.status === 'cancelada';
        setTimeout(async () => { await consultar(); laco(); }, fim ? 8000 : (document.hidden ? 4000 : 1000));
    })();

    function status(t) { $('#jg-status').innerHTML = t; }
    const nomeJ = j => esc(D.nomes[j] || '—');

    /* ================================================================== */
    /*  Gladio                                                             */
    /* ================================================================== */
    let alvo = null; // { tipo: 'feitico' | 'ataque', inst, precisa: 'inimigo' | 'aliado' | 'lenda' }
    const PRECISA = { dano: 'inimigo', cura: 'aliado', fortalecer: 'lenda' };

    function cartaDef(id) {
        const c = Object.assign({}, D.estado.cartas[id] || { nome: '?', tipo: 'feitico', custo: 0 });
        const extra = C.imagens[id];
        if (extra) { c.imagem = extra.imagem; c.descricao = c.descricao || extra.descricao; }
        return c;
    }

    function renderGladio() {
        const e = D.estado;
        const eu = D.lado || 'a';
        const op = eu === 'a' ? 'b' : 'a';
        const nomeLado = l => (l === 'a' ? nomeJ('j1') : nomeJ('j2'));
        const minhaVez = D.status === 'andamento' && D.lado && e.vez === D.lado;
        const L = e.lados;

        // alvos válidos no modo de mirar
        const alvoOk = (lado, tipo) => {
            if (!alvo) return false;
            const pIni = alvo.tipo === 'ataque' || alvo.precisa === 'inimigo';
            if (tipo === 'heroi') return pIni ? lado === op : (alvo.precisa === 'aliado' && lado === eu);
            return pIni ? lado === op : lado === eu;
        };

        const heroi = (lado) => {
            const l = L[lado];
            const pct = Math.max(0, Math.min(100, (l.vida / (l.max || 1)) * 100));
            const mao = Array.isArray(l.mao) ? l.mao.length : l.mao;
            return `<div class="gl-heroi${e.vez === lado && !e.fim ? ' gl-vez' : ''}${alvoOk(lado, 'heroi') ? ' gl-alvo' : ''}" data-heroi="${lado}">` +
                `<div class="gl-heroi-nome">${nomeLado(lado)}${lado === D.lado ? ' <small>(você)</small>' : ''}</div>` +
                `<div class="gl-vida" title="Vida${D.aposta ? ' (Dinheiro apostado)' : ''}"><span style="width:${pct}%"></span><b>${D.aposta ? '💰 ' : '❤ '}${l.vida} / ${l.max}</b></div>` +
                `<div class="gl-heroi-info"><span title="Energia">⚡ ${l.energia} / ${l.energiaMax}</span><span title="Cartas na mão">🂠 ${mao}</span><span title="Cartas no baralho">📚 ${l.baralho}</span></div>` +
                `</div>`;
        };
        const campo = (lado) => {
            const m = L[lado].mesa;
            return `<div class="gl-campo" data-campo="${lado}">` + (m.length ? m.map(x =>
                window.JG.carta(cartaDef(x.c), {
                    atk: x.atk, vida: x.vida, max: x.max,
                    classe: (lado === D.lado && minhaVez && x.pode && x.atk > 0 ? 'gl-pode' : '') +
                        (alvo && alvo.inst === x.i && alvo.tipo === 'ataque' ? ' gl-mirando' : '') +
                        (alvoOk(lado, 'lenda') ? ' gl-alvo' : ''),
                    attrs: `data-lenda="${x.i}" data-dono="${lado}"`,
                })).join('') : '<p class="gl-vazio">Sem Lendas na mesa</p>') + '</div>';
        };
        const maoOp = Array.isArray(L[op].mao) ? L[op].mao.length : L[op].mao;
        const minhaMao = Array.isArray(L[eu].mao) ? L[eu].mao : null;

        box.innerHTML =
            `<div class="gl-tabuleiro">` +
                `<div class="gl-lado gl-lado--op">${heroi(op)}<div class="gl-mao-op">${'<span class="gl-verso"></span>'.repeat(maoOp)}</div>${campo(op)}</div>` +
                `<div class="gl-meio">` +
                    `<span class="gl-turno">Turno ${e.turno}</span>` +
                    (alvo ? `<span class="gl-dica">${alvo.tipo === 'ataque' ? 'Escolha quem atacar' : 'Escolha o alvo do feitiço'}</span><button type="button" class="camp-btn camp-btn-pequeno" data-gl="cancelar-alvo">Cancelar</button>` : '') +
                    (minhaVez ? `<button type="button" class="camp-btn camp-btn-primario" data-gl="passar">Terminar turno</button>` : '') +
                    (D.lado && D.status === 'andamento' ? `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="desistir">Desistir</button>` : '') +
                `</div>` +
                `<div class="gl-lado gl-lado--eu">${campo(eu)}${heroi(eu)}` +
                    (minhaMao ? `<div class="gl-mao">${minhaMao.map(x => {
                        const c = cartaDef(x.c);
                        const pode = minhaVez && c.custo <= L[eu].energia;
                        return window.JG.carta(c, { classe: (pode ? 'gl-jogavel' : 'gl-cara') + (alvo && alvo.inst === x.i ? ' gl-mirando' : ''), attrs: `data-mao="${x.i}"` });
                    }).join('')}</div>` : `<div class="gl-mao-op">${'<span class="gl-verso"></span>'.repeat(L[eu].mao)}</div>`) +
                `</div>` +
            `</div>` +
            `<aside class="gl-log"><h3>Acontecimentos</h3><ol>${e.log.slice().reverse().map(t => `<li>${esc(t)}</li>`).join('')}</ol></aside>`;

        // status
        if (D.status === 'cancelada') status('Partida cancelada pelo mestre. As apostas voltaram.');
        else if (e.fim) {
            const venc = e.fim.vencedor === 'a' ? nomeJ('j1') : nomeJ('j2');
            const ganhei = D.lado && e.fim.vencedor === D.lado;
            status(`${ganhei ? '🏆 Você venceu!' : (D.lado ? 'Você perdeu.' : '')} Vencedor: <strong>${venc}</strong>` +
                (D.aposta ? ` · leva ${(2 * D.aposta).toLocaleString('pt-BR')} de Dinheiro` : '') + (e.fim.motivo === 'desistencia' ? ' (desistência)' : ''));
        } else status(minhaVez ? '<strong>Sua vez.</strong> Jogue cartas da mão ou ataque com suas Lendas.' : `Vez de <strong>${nomeLado(e.vez)}</strong>.${D.lado ? '' : ' Você está assistindo.'}`);
    }

    function cliqueGladio(ev) {
        const b = ev.target.closest('[data-gl]');
        if (b) {
            if (b.dataset.gl === 'cancelar-alvo') { alvo = null; render(); }
            if (b.dataset.gl === 'passar') { alvo = null; acao({ acao: 'passar' }); }
            if (b.dataset.gl === 'desistir' && confirm('Desistir da partida?' + (D.aposta ? ' Você perde a aposta.' : ''))) acao({ acao: 'desistir' });
            return;
        }
        const e = D.estado;
        if (D.status !== 'andamento' || !D.lado || e.vez !== D.lado) return;
        const eu = D.lado;

        // com um alvo pendente: o clique escolhe o alvo
        if (alvo) {
            const h = ev.target.closest('[data-heroi]');
            const l = ev.target.closest('[data-lenda]');
            let escolhido = null;
            if (h && h.classList.contains('gl-alvo')) escolhido = h.dataset.heroi === eu ? 'eu' : 'inimigo';
            if (l && l.classList.contains('gl-alvo')) escolhido = l.dataset.lenda;
            if (escolhido == null) return;
            const a = alvo;
            alvo = null;
            acao({ acao: a.tipo === 'ataque' ? 'atacar' : 'jogar', inst: a.inst, alvo: escolhido });
            return;
        }

        const m = ev.target.closest('[data-mao]');
        if (m && m.classList.contains('gl-jogavel')) {
            const x = e.lados[eu].mao.find(c => String(c.i) === m.dataset.mao);
            const c = x && cartaDef(x.c);
            if (!c) return;
            const precisa = c.tipo === 'feitico' ? PRECISA[c.efeito] : null;
            if (precisa) { alvo = { tipo: 'feitico', inst: x.i, precisa }; render(); }
            else acao({ acao: 'jogar', inst: x.i });
            return;
        }
        const l = ev.target.closest('[data-lenda]');
        if (l && l.dataset.dono === eu && l.classList.contains('gl-pode')) {
            alvo = { tipo: 'ataque', inst: +l.dataset.lenda };
            render();
        }
    }

    /* ================================================================== */
    /*  Pela Dama                                                          */
    /* ================================================================== */
    let sel = null; // "x,y" da peça escolhida
    const CUSTOM = /^c\d+$/; // peças criadas pelos jogadores ("c" + id); as padrão usam nomes (cavalo, rei…)

    function renderPelaDama() {
        const e = D.estado;
        const minhaVez = D.status === 'andamento' && D.lado && e.vez === D.lado;
        const legais = (minhaVez && e.legais) || {};
        if (sel && !legais[sel]) sel = null;
        const destinos = sel ? legais[sel].map(d => d[0] + ',' + d[1]) : [];
        const pretasEmBaixo = D.lado === 'p';
        const ult = e.ultimo;
        // rei em xeque de quem tem a vez
        let reiXeque = null;
        if (e.xeque && !(e.fim && e.fim.motivo === 'desistencia')) {
            e.tab.forEach((linha, y) => linha.forEach((p, x) => {
                if (p && p.c === e.vez && (e.tipos[p.t] || {}).especial === 'rei') reiXeque = x + ',' + y;
            }));
        }

        let h = '<div class="pd-tabuleiro" role="grid">';
        for (let i = 0; i < 8; i++) {
            const y = pretasEmBaixo ? i : 7 - i;
            for (let j = 0; j < 8; j++) {
                const x = pretasEmBaixo ? 7 - j : j;
                const k = x + ',' + y;
                const p = e.tab[y][x];
                const t = p ? (e.tipos[p.t] || { nome: '?', simbolo: '?' }) : null;
                const cls = ['pd-casa', (x + y) % 2 ? 'pd-clara' : 'pd-escura'];
                if (ult && ((ult.de[0] === x && ult.de[1] === y) || (ult.para[0] === x && ult.para[1] === y))) cls.push('pd-ultimo');
                if (sel === k) cls.push('pd-sel');
                if (destinos.includes(k)) cls.push(p ? 'pd-captura' : 'pd-destino');
                if (reiXeque === k) cls.push('pd-xeque');
                if (legais[k]) cls.push('pd-movivel');
                h += `<button type="button" class="${cls.join(' ')}" data-k="${k}"` +
                    (p ? ` title="${esc(t.nome)} ${p.c === 'b' ? 'branca' : 'preta'}"` : '') + '>' +
                    (p ? `<span class="pd-peca pd-${p.c}${CUSTOM.test(p.t) ? ' pd-custom' : ''}">${esc(t.simbolo)}</span>` : '') +
                    (j === 0 ? `<small class="pd-rot pd-rot-y">${y + 1}</small>` : '') +
                    (i === 7 ? `<small class="pd-rot pd-rot-x">${'abcdefgh'[x]}</small>` : '') +
                    '</button>';
            }
        }
        h += '</div>';

        // legenda das peças customizadas desta partida
        const custom = Object.keys(e.tipos).filter(id => CUSTOM.test(id));
        const lateral =
            `<aside class="pd-lateral">` +
                `<div class="pd-jogadores"><div class="pd-jog${e.vez === 'b' && !e.fim ? ' pd-vez' : ''}"><span class="pd-peca pd-b">♚</span> ${nomeJ('j1')}</div>` +
                `<div class="pd-jog${e.vez === 'p' && !e.fim ? ' pd-vez' : ''}"><span class="pd-peca pd-p">♚</span> ${nomeJ('j2')}</div></div>` +
                `<p class="camp-dica">Lances: ${e.lances}</p>` +
                (D.lado && D.status === 'andamento' ? `<button type="button" class="camp-btn camp-btn-pequeno" data-pd="desistir">Desistir</button>` : '') +
                (custom.length ? `<h3>Peças customizadas</h3>` + custom.map(id => `<div class="pd-legenda"><span class="pd-peca pd-b pd-custom">${esc(e.tipos[id].simbolo)}</span>` +
                    `<div><strong>${esc(e.tipos[id].nome)}</strong>${window.JG.diagrama(e.tipos[id], 8)}</div></div>`).join('') : '') +
            `</aside>`;
        box.innerHTML = `<div class="pd-area">${h}${lateral}</div>`;

        const corNome = c => (c === 'b' ? 'Brancas' : 'Pretas');
        const nomeCor = c => (c === 'b' ? nomeJ('j1') : nomeJ('j2'));
        if (D.status === 'cancelada') status('Partida cancelada pelo mestre.');
        else if (e.fim) {
            if (!e.fim.vencedor) status('Empate por afogamento: quem tinha a vez não tinha lance e não estava em xeque.');
            else status(`${D.lado === e.fim.vencedor ? '🏆 Você venceu!' : (D.lado ? 'Você perdeu.' : '')} ${corNome(e.fim.vencedor)} (<strong>${nomeCor(e.fim.vencedor)}</strong>) venceram` +
                (e.fim.motivo === 'xeque-mate' ? ' por xeque-mate.' : ' por desistência.'));
        } else {
            const xq = e.xeque ? ' <strong class="pd-aviso-xeque">Xeque!</strong>' : '';
            status(minhaVez ? `<strong>Sua vez</strong> (${corNome(D.lado)}).${xq}` : `Vez das ${corNome(e.vez)} (<strong>${nomeCor(e.vez)}</strong>).${xq}${D.lado ? '' : ' Você está assistindo.'}`);
        }
    }

    function cliquePelaDama(ev) {
        const b = ev.target.closest('[data-pd]');
        if (b && b.dataset.pd === 'desistir') {
            if (confirm('Desistir da partida?')) acao({ acao: 'desistir' });
            return;
        }
        const c = ev.target.closest('.pd-casa');
        if (!c) return;
        const e = D.estado;
        if (D.status !== 'andamento' || !D.lado || e.vez !== D.lado) return;
        const legais = e.legais || {};
        const k = c.dataset.k;
        if (sel && legais[sel] && legais[sel].some(d => d[0] + ',' + d[1] === k)) {
            const [x, y] = sel.split(',').map(Number);
            const [tx, ty] = k.split(',').map(Number);
            sel = null;
            acao({ acao: 'mover', x, y, tx, ty });
            return;
        }
        sel = legais[k] ? (sel === k ? null : k) : null;
        render();
    }

    /* ================================================================== */
    function render() {
        if (!D.estado) {
            status(D.status === 'cancelada' ? 'Desafio cancelado.' : 'Aguardando alguém aceitar o desafio…');
            box.innerHTML = `<p class="camp-vazio"><a href="${esc(C.url.lobby)}">Voltar aos jogos</a></p>`;
            return;
        }
        if (D.jogo === 'gladio') renderGladio(); else renderPelaDama();
    }
    box.addEventListener('click', ev => (D.jogo === 'gladio' ? cliqueGladio(ev) : cliquePelaDama(ev)));
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && (alvo || sel)) { alvo = null; sel = null; render(); }
    });
    render();
})();
