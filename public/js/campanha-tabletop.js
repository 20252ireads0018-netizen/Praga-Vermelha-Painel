/**
 * Campanhas: painel rápido do tabletop.
 * - Clicar num tabletop da campanha abre um painel pela direita com o mapa ao vivo (a mesma sala online do tabletop).
 * - Aqui só dá para mover as peças; o botão "Abrir tabletop completo" leva à mesa inteira.
 * - Sem sala aberta, o mestre abre uma na hora; o jogador espera o mestre abrir.
 * - Sincroniza como o tabletop: manda só as peças movidas e pergunta "mudou?" a cada ~1 s.
 */
(function () {
    'use strict';

    const gav = document.getElementById('camp-tt');
    if (!gav) return;

    const csrf = gav.dataset.csrf;
    const $ = id => document.getElementById(id);
    const el = {
        titulo: $('camp-tt-titulo'), status: $('camp-tt-status'), aviso: $('camp-tt-aviso'),
        abrir: $('camp-tt-abrir'), fechar: $('camp-tt-fechar'), cena: $('camp-tt-cena'),
        grade: $('camp-tt-grade'), mapa: $('camp-tt-mapa'), svg: $('camp-tt-svg'), pecas: $('camp-tt-pecas'),
    };

    const REF = 40; // unidades do desenho por casa (igual ao tabletop)
    const COR_OK = /^#[0-9a-fA-F]{6}$/;
    const limitar = (v, a, b) => Math.min(Math.max(v, a), b);
    const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const r1 = n => Math.round(n * 10) / 10;
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const inicial = p => Array.from(String(p.nome || '').trim()).slice(0, 2).join('').toUpperCase();
    const tf = o => `translate(${num(o.ix)}%,${num(o.iy)}%) scale(${o.iz || 1})`;
    const cid = (Math.random().toString(36).slice(2) + Date.now().toString(36)).replace(/[^a-z0-9]/g, '').slice(0, 12);

    let sessao = 0;          // sobe a cada abrir/fechar: laços de uma mesa anterior param sozinhos
    let timer = null;
    let ocupado = false;
    let M = null;            // { salaUrl, url: { estado, alterar, pesado, abrir } }
    let E = null;            // estado da sala: pecas, desenho, cols, rows, cel, grade, nomes
    let v = 0, mapaH = '';
    let imgs = {};           // id => data URL das imagens das peças
    let pend = new Map();    // peças movidas aqui e ainda não enviadas
    let arr = null;          // peça sendo arrastada: { id, x, y }
    let cel = 40;            // tamanho da casa aqui no painel (cabe na largura)

    function status(t) { el.status.textContent = t; }
    function aviso(t) { el.aviso.textContent = t || ''; el.aviso.hidden = !t; }
    const achar = id => (E ? E.pecas.find(p => p.id === id) : null);
    const noPeca = id => el.pecas.querySelector(`.tt-peca[data-id="${CSS.escape(id)}"]`);

    /* ---------- desenho (só leitura) ---------- */
    function formaPontos(f) {
        const x1 = num(f.x1), y1 = num(f.y1), x2 = num(f.x2), y2 = num(f.y2), dx = x2 - x1, dy = y2 - y1;
        if (f.t === 'quadrado') {
            const s = Math.max(Math.abs(dx), Math.abs(dy)), ex = x1 + (dx < 0 ? -s : s), ey = y1 + (dy < 0 ? -s : s);
            return [[x1, y1], [ex, y1], [ex, ey], [x1, ey]];
        }
        if (f.t === 'cone') return [[x1, y1], [x2 - dy / 2, y2 + dx / 2], [x2 + dy / 2, y2 - dx / 2]];
        if (f.t === 'triangulo') return [[(x1 + x2) / 2, y1], [x2, y2], [x1, y2]];
        return [];
    }
    function itemSvg(it) {
        if (!COR_OK.test(it.c || '')) return '';
        const w = limitar(num(it.w), 1, 24);
        if (it.t === 'pincel') {
            if (!/^[MLQl0-9. -]+$/.test(it.d || '')) return '';
            return `<path d="${it.d}" fill="none" stroke="${it.c}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;
        }
        const attrs = `${it.f ? `fill="${it.c}" fill-opacity="0.18"` : 'fill="none"'} stroke="${it.c}" stroke-width="${w}" stroke-linejoin="round"`;
        if (it.t === 'circulo') return `<circle cx="${r1(num(it.x1))}" cy="${r1(num(it.y1))}" r="${r1(Math.hypot(num(it.x2) - num(it.x1), num(it.y2) - num(it.y1)))}" ${attrs}/>`;
        const P = formaPontos(it);
        return P.length ? `<polygon points="${P.map(q => r1(q[0]) + ',' + r1(q[1])).join(' ')}" ${attrs}/>` : '';
    }

    /* ---------- renderização ---------- */
    function medir() {
        if (!E) return;
        const larg = el.cena.clientWidth - 28; // padding da cena
        cel = limitar(Math.floor(larg / E.cols), 12, E.cel || 40);
    }
    function render() {
        if (!E) return;
        medir();
        el.grade.style.width = E.cols * cel + 'px';
        el.grade.style.height = E.rows * cel + 'px';
        el.grade.style.setProperty('--c', cel + 'px');
        el.grade.classList.toggle('sem-linhas', !E.grade);
        el.svg.setAttribute('viewBox', `0 0 ${E.cols * REF} ${E.rows * REF}`);
        el.svg.innerHTML = (E.desenho || []).map(itemSvg).join('');
        renderPecas();
    }
    function renderPecas() {
        const c = cel;
        el.pecas.innerHTML = E.pecas.map(p => {
            const src = p.img && imgs[p.img];
            const cor = COR_OK.test(p.cor || '') ? p.cor : '#a78bfa';
            return `<div class="tt-peca forma-${esc(p.forma)}${arr && arr.id === p.id ? ' sel' : ''}" data-id="${esc(p.id)}" ` +
                `style="left:${p.x * c}px;top:${p.y * c}px;width:${p.tam * c}px;height:${p.tam * c}px;--pc:${cor};">` +
                `<span class="tt-fig">${src ? `<img src="${src}" alt="" draggable="false" style="transform:${tf(p)}">` : `<b>${esc(inicial(p))}</b>`}</span>` +
                (E.nomes ? `<span class="tt-nome">${esc(p.nome)}</span>` : '') +
                `</div>`;
        }).join('');
    }
    function mostrarMapa(src) {
        if (src) { el.mapa.src = src; el.mapa.hidden = false; } else { el.mapa.removeAttribute('src'); el.mapa.hidden = true; }
    }

    /* ---------- sincronização com a sala ---------- */
    async function pesado(q) {
        const r = await fetch(`${M.url.pesado}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        if (!r.ok) throw new Error('pesado ' + r.status);
        return r.json();
    }
    async function buscarPesado(s, r) {
        try {
            if (r.mapaH !== undefined && r.mapaH !== mapaH) {
                const h = r.mapaH;
                if (!h) { mapaH = ''; mostrarMapa(''); }
                else { const j = await pesado('mapa=1'); if (s !== sessao) return; mapaH = h; mostrarMapa(j.mapa || ''); }
            }
            const faltam = [...new Set(E.pecas.map(p => p.img).filter(id => id && !imgs[id]))];
            for (let i = 0; i < faltam.length; i += 10) {
                const j = await pesado('imgs=' + encodeURIComponent(faltam.slice(i, i + 10).join(',')));
                if (s !== sessao) return;
                Object.assign(imgs, j.imgs || {});
                if (!arr) renderPecas();
            }
        } catch (_) { /* tenta de novo na próxima novidade */ }
    }
    function mostrarOnline(on) {
        if (!Array.isArray(on)) return;
        status(`Ao vivo · ${on.length} online`);
        el.status.title = on.map(x => x.n + (x.d ? ' (dono da sala)' : '')).join(', ');
    }
    function aplicar(s, r) {
        if (s !== sessao) return;
        if (typeof r.v === 'number') v = r.v;
        mostrarOnline(r.on);
        if (!r.estado) return;
        E = r.estado;
        E.pecas = Array.isArray(E.pecas) ? E.pecas : [];
        // o que mexi aqui e ainda não chegou ao servidor continua onde eu deixei
        E.pecas.forEach(p => {
            const meu = pend.get(p.id);
            if (meu) { p.x = meu.x; p.y = meu.y; }
            if (arr && arr.id === p.id) { p.x = arr.x; p.y = arr.y; }
        });
        render();
        buscarPesado(s, r);
    }
    async function enviar(s) {
        const lote = Array.from(pend.values());
        pend = new Map();
        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('patch', JSON.stringify({ pecas: { up: lote, del: [] } }));
        fd.append('c', cid);
        let r;
        try {
            r = await fetch(M.url.alterar, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        } catch (e) { lote.forEach(p => { if (!pend.has(p.id)) pend.set(p.id, p); }); throw e; }
        if (r.status === 404) return encerrada(s);
        if (!r.ok) { lote.forEach(p => { if (!pend.has(p.id)) pend.set(p.id, p); }); throw new Error('alterar ' + r.status); }
        aplicar(s, await r.json());
    }
    async function consultar(s) {
        const r = await fetch(`${M.url.estado}?v=${v}&c=${cid}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        if (r.status === 404) return encerrada(s);
        if (!r.ok) throw new Error('estado ' + r.status);
        aplicar(s, await r.json());
    }
    async function ciclo(s) {
        if (s !== sessao || !M || !M.url) return;
        ocupado = true;
        try {
            if (pend.size) await enviar(s); else await consultar(s);
        } catch (_) {
            if (s === sessao) status('Sem conexão… tentando de novo');
        }
        ocupado = false;
        if (s === sessao && M && M.url) {
            clearTimeout(timer);
            timer = setTimeout(() => ciclo(s), pend.size ? 150 : (document.hidden ? 4000 : 1000));
        }
    }
    function agendarEnvio() {
        if (ocupado) return; // o ciclo em andamento já marca o próximo para logo
        clearTimeout(timer);
        const s = sessao;
        timer = setTimeout(() => ciclo(s), 150); // no máximo ~7 envios por segundo, mesmo arrastando
    }
    function encerrada(s) {
        if (s !== sessao) return;
        M.url = null;
        clearTimeout(timer);
        status('Sala encerrada');
        aviso('A sala ao vivo desta mesa foi encerrada. Feche e abra o painel de novo para reconectar.');
        el.abrir.hidden = true;
    }

    async function conectar(s) {
        try {
            const r = await fetch(M.salaUrl, {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin',
            });
            const j = await r.json().catch(() => ({}));
            if (s !== sessao) return;
            if (!r.ok || !j.ok) throw new Error(j.message || 'sala ' + r.status);
            if (!j.codigo) {
                status('Aguardando o mestre');
                aviso('O mestre ainda não abriu este tabletop ao vivo. O painel conecta sozinho assim que ele abrir.');
                timer = setTimeout(() => conectar(s), 4000);
                return;
            }
            aviso('');
            M.url = j.url;
            el.abrir.href = j.url.abrir;
            el.abrir.hidden = false;
            v = 0;
            ciclo(s);
        } catch (_) {
            if (s !== sessao) return;
            status('Não foi possível conectar');
            timer = setTimeout(() => conectar(s), 5000);
        }
    }

    /* ---------- abrir / fechar o painel ---------- */
    function descarregar() { // manda o que ficou pendente antes de fechar
        if (!pend.size || !M || !M.url || !navigator.sendBeacon) return;
        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('patch', JSON.stringify({ pecas: { up: Array.from(pend.values()), del: [] } }));
        fd.append('c', cid);
        try { navigator.sendBeacon(M.url.alterar, fd); } catch (_) { /* ignora */ }
        pend = new Map();
    }
    function limpar() {
        descarregar();
        sessao++;
        clearTimeout(timer);
        ocupado = false;
        M = null; E = null; v = 0; mapaH = ''; imgs = {}; arr = null;
        el.pecas.innerHTML = ''; el.svg.innerHTML = ''; mostrarMapa('');
        el.grade.style.width = el.grade.style.height = '0px';
    }
    function abrir(btn) {
        limpar();
        const s = sessao;
        M = { salaUrl: btn.dataset.salaUrl };
        el.titulo.textContent = btn.dataset.nome || 'Tabletop';
        el.abrir.hidden = true;
        aviso('');
        status('Conectando…');
        gav.hidden = false;
        requestAnimationFrame(() => requestAnimationFrame(() => gav.classList.add('aberta')));
        conectar(s);
    }
    function fechar() {
        if (gav.hidden) return;
        limpar();
        gav.classList.remove('aberta');
        setTimeout(() => { if (!gav.classList.contains('aberta')) gav.hidden = true; }, 260);
    }

    document.addEventListener('click', e => {
        const b = e.target.closest('.js-tt-rapido');
        if (b) { e.preventDefault(); abrir(b); }
    });
    el.fechar.addEventListener('click', fechar);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !gav.hidden && !document.querySelector('dialog[open]')) fechar();
    });
    el.abrir.addEventListener('click', descarregar);
    window.addEventListener('pagehide', descarregar);
    if (window.ResizeObserver) new ResizeObserver(() => { if (E && !arr) render(); }).observe(el.cena);

    /* ---------- arrastar peças (encaixa nas casas) ---------- */
    el.pecas.addEventListener('pointerdown', e => {
        const n = e.target.closest('.tt-peca');
        if (!n || !E || !M || !M.url || e.button > 0) return;
        const q = achar(n.dataset.id);
        if (!q) return;
        e.preventDefault();
        el.grade.setPointerCapture?.(e.pointerId);
        const c = cel, x0 = e.clientX, y0 = e.clientY, bx = q.x, by = q.y;
        arr = { id: q.id, x: q.x, y: q.y };
        n.classList.add('sel');
        const mover = ev => {
            const p = achar(arr.id);
            if (!p) return;
            const nx = limitar(bx + Math.round((ev.clientX - x0) / c), 0, E.cols - p.tam);
            const ny = limitar(by + Math.round((ev.clientY - y0) / c), 0, E.rows - p.tam);
            if (nx === arr.x && ny === arr.y) return;
            arr.x = p.x = nx; arr.y = p.y = ny;
            const no = noPeca(p.id);
            if (no) { no.style.left = nx * cel + 'px'; no.style.top = ny * cel + 'px'; }
            pend.set(p.id, Object.assign({}, p));
            agendarEnvio(); // os outros veem a peça andando
        };
        const fim = () => {
            el.grade.removeEventListener('pointermove', mover);
            el.grade.removeEventListener('pointerup', fim);
            el.grade.removeEventListener('pointercancel', fim);
            arr = null;
            if (E) renderPecas();
        };
        el.grade.addEventListener('pointermove', mover);
        el.grade.addEventListener('pointerup', fim);
        el.grade.addEventListener('pointercancel', fim);
    });
})();
