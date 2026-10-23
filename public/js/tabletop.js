/* Praga Vermelha · Tabletop (mesa virtual)
   Mapa com grade, peças arrastáveis, seleção em caixa, desenho (pincel, borracha e formas),
   imagem de fundo e "Minhas peças". Sem dependências: o estado inteiro fica em E e é salvo
   no servidor (autosave) como JSON. */
(function () {
    'use strict';

    const cfgEl = document.getElementById('tt-cfg');
    if (!cfgEl) return;
    const CFG = JSON.parse(cfgEl.textContent);

    /* ---------- constantes e utilitários ---------- */
    const COR_OK = /^#[0-9a-fA-F]{6}$/;
    const ID_OK = /^[A-Za-z0-9._-]{1,60}$/;
    const FORMAS = ['circulo', 'quadrado', 'losango'];
    const FORMA_NOME = { circulo: 'Círculo', quadrado: 'Quadrado', losango: 'Losango' };
    const ITENS = ['pincel', 'circulo', 'quadrado', 'cone', 'triangulo'];
    const PRONTAS = [
        { id: 'heroi', nome: 'Herói', forma: 'circulo', cor: '#38bdf8' },
        { id: 'aliado', nome: 'Aliado', forma: 'circulo', cor: '#4ade80' },
        { id: 'inimigo', nome: 'Inimigo', forma: 'circulo', cor: '#f87171' },
        { id: 'objeto', nome: 'Objeto', forma: 'losango', cor: '#fbbf24' },
        { id: 'obstaculo', nome: 'Obstáculo', forma: 'quadrado', cor: '#64748b' },
    ];
    const REF = 40;            // unidades do desenho por casa (o SVG escala com o tamanho da casa)
    const HIST = 60;           // passos de desfazer
    const MAX_PECAS = 200, MAX_DESENHO = 600, MAX_MODELOS = 60;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const limitar = (v, a, b) => Math.min(Math.max(v, a), b);
    const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
    const r1 = n => Math.round(n * 10) / 10;
    const uid = () => Math.random().toString(36).slice(2, 9) + Date.now().toString(36).slice(-4);
    const txt = v => (v == null ? '' : String(v));
    const esc = s => txt(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const imgOk = s => typeof s === 'string' && /^data:image\/(png|jpe?g|webp|gif);base64,/.test(s);
    const copia = o => JSON.parse(JSON.stringify(o));
    const inicial = p => Array.from(txt(p.nome).trim()).slice(0, 2).join('').toUpperCase();

    /* ---------- estado ---------- */
    function normalizar(e) {
        e = e && typeof e === 'object' && !Array.isArray(e) ? e : {};
        e.cols = limitar(Math.round(num(e.cols)) || 16, 4, 60);
        e.rows = limitar(Math.round(num(e.rows)) || 12, 4, 60);
        e.cel = limitar(Math.round(num(e.cel)) || 40, 20, 80);
        e.grade = e.grade !== false;
        e.nomes = e.nomes !== false;
        e.mapa = imgOk(e.mapa) ? e.mapa : '';
        const im = e.imgs && typeof e.imgs === 'object' && !Array.isArray(e.imgs) ? e.imgs : {};
        e.imgs = {};
        Object.keys(im).forEach(k => { if (ID_OK.test(k) && imgOk(im[k])) e.imgs[k] = im[k]; });
        e.pecas = (Array.isArray(e.pecas) ? e.pecas : []).filter(p => p && typeof p === 'object').slice(0, MAX_PECAS);
        e.pecas.forEach(p => {
            if (!ID_OK.test(txt(p.id))) p.id = uid();
            p.nome = txt(p.nome).slice(0, 24) || 'Peça';
            p.forma = FORMAS.includes(p.forma) ? p.forma : 'circulo';
            p.cor = COR_OK.test(p.cor || '') ? p.cor : '#a78bfa';
            p.tam = limitar(Math.round(num(p.tam)) || 1, 1, 4);
            p.x = limitar(Math.round(num(p.x)), 0, Math.max(0, e.cols - p.tam));
            p.y = limitar(Math.round(num(p.y)), 0, Math.max(0, e.rows - p.tam));
            if (p.img && e.imgs[p.img]) p.iz = limitar(num(p.iz) || 1, 1, 5); else { delete p.img; delete p.iz; }
        });
        e.desenho = (Array.isArray(e.desenho) ? e.desenho : []).filter(it => it && ITENS.includes(it.t)).slice(0, MAX_DESENHO);
        e.modelos = (Array.isArray(e.modelos) ? e.modelos : []).filter(m => m && typeof m === 'object').slice(0, MAX_MODELOS);
        e.modelos.forEach(m => {
            if (!ID_OK.test(txt(m.id))) m.id = uid();
            m.nome = txt(m.nome).slice(0, 24) || 'Peça';
            m.forma = FORMAS.includes(m.forma) ? m.forma : 'circulo';
            m.cor = COR_OK.test(m.cor || '') ? m.cor : '#a78bfa';
            m.tam = limitar(Math.round(num(m.tam)) || 1, 1, 4);
            m.iz = limitar(num(m.iz) || 1, 1, 5);
            m.img = imgOk(m.img) ? m.img : '';
        });
        return e;
    }
    const E = normalizar(CFG.estado);

    /* estado só de interface (não vai para o servidor) */
    const U = {
        sels: [],
        novo: { nome: '', forma: 'circulo', cor: '#a78bfa', tam: 1, img: '' },
        des: { on: false, ferr: 'pincel', cor: '#f472b6', larg: 3, preencher: true, vivo: null, cx: null, cy: null, hist: [], refaz: [] },
    };

    const el = {
        page: $('#tt-page'), cena: $('#tt-cena'), grade: $('#tt-grade'), mapa: $('#tt-mapa'),
        svg: $('#tt-svg'), gDes: $('#tt-g-des'), gVivo: $('#tt-g-vivo'), pecas: $('#tt-pecas'), caixa: $('#tt-caixa'),
        status: $('#tt-status'), sel: $('#tt-sel'), prontas: $('#tt-prontas'), modelos: $('#tt-modelos'),
        novoPrev: $('#tt-novo-prev'), tirarImgNovo: $('#tt-tirar-img-novo'), tirarMapa: $('#tt-tirar-mapa'),
        nomeMesa: $('#tt-nome-mesa'), desfazer: $('#tt-desfazer'), refazer: $('#tt-refazer'),
    };

    /* ---------- salvar (autosave) ---------- */
    let timer = null;
    function setStatus(s) {
        const rot = { salvo: 'Salvo', pendente: 'Alterações pendentes…', salvando: 'Salvando…', erro: 'Erro ao salvar — tentando de novo' };
        el.status.dataset.estado = s;
        el.status.textContent = rot[s] || '';
    }
    function formData() {
        const fd = new FormData();
        fd.append('_token', CFG.csrf);
        fd.append('_method', 'PUT');
        fd.append('estado', JSON.stringify(E));
        fd.append('nome', CFG.nome);
        return fd;
    }
    function agendar() {
        setStatus('pendente');
        clearTimeout(timer);
        timer = setTimeout(gravar, 800);
    }
    function gravar() {
        clearTimeout(timer); timer = null;
        setStatus('salvando');
        return fetch(CFG.urlSalvar, { method: 'POST', body: formData(), headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error(r.status); setStatus(timer ? 'pendente' : 'salvo'); })
            .catch(() => { setStatus('erro'); clearTimeout(timer); timer = setTimeout(gravar, 4000); });
    }
    const urgente = () => { if (timer && navigator.sendBeacon) { try { navigator.sendBeacon(CFG.urlSalvar, formData()); } catch (_) { /* ignora */ } } };
    document.addEventListener('visibilitychange', () => { if (document.hidden) urgente(); });
    window.addEventListener('pagehide', urgente);

    /* ---------- peças: helpers ---------- */
    const achar = id => E.pecas.find(p => p.id === id);
    const selecionadas = () => E.pecas.filter(p => U.sels.includes(p.id));
    const noPeca = id => el.pecas.querySelector(`[data-id="${CSS.escape(id)}"]`);
    function valor(campo) {
        const l = selecionadas();
        if (!l.length) return '';
        const ler = p => campo === 'tam' ? p.tam : campo === 'forma' ? p.forma : p.cor;
        const v = ler(l[0]);
        return l.every(p => ler(p) === v) ? v : '';
    }
    function livre(t) {
        const ocupado = (x, y) => E.pecas.some(p => x < p.x + p.tam && x + t > p.x && y < p.y + p.tam && y + t > p.y);
        for (let y = 0; y + t <= E.rows; y++) for (let x = 0; x + t <= E.cols; x++) if (!ocupado(x, y)) return [x, y];
        return [0, 0];
    }
    function limitarPecas() {
        E.pecas.forEach(p => {
            p.x = limitar(p.x, 0, Math.max(0, E.cols - p.tam));
            p.y = limitar(p.y, 0, Math.max(0, E.rows - p.tam));
        });
    }
    function limparImgs() {
        const usadas = new Set(E.pecas.map(p => p.img).filter(Boolean));
        Object.keys(E.imgs).forEach(k => { if (!usadas.has(k)) delete E.imgs[k]; });
    }
    function nomeUnico(base) {
        const n = E.pecas.filter(p => p.nome === base || p.nome.startsWith(base + ' ')).length;
        return (n > 0 ? `${base} ${n + 1}` : base).slice(0, 24);
    }
    function aviso(msg) { window.alert(msg); }

    function adicionarPeca(d, comImg) {
        if (E.pecas.length >= MAX_PECAS) return aviso(`Limite de ${MAX_PECAS} peças neste mapa.`);
        const tam = limitar(Math.round(num(d.tam)) || 1, 1, 4);
        const [x, y] = livre(tam);
        const peca = {
            id: uid(), nome: d.nomeFinal || (txt(d.nome).trim().slice(0, 24) || 'Peça'), x, y, tam,
            forma: FORMAS.includes(d.forma) ? d.forma : 'circulo',
            cor: COR_OK.test(d.cor || '') ? d.cor : '#a78bfa',
        };
        if (comImg && imgOk(comImg)) {
            const iid = 'i' + uid();
            E.imgs[iid] = comImg;
            peca.img = iid; peca.iz = limitar(num(d.iz) || 1, 1, 5);
        }
        E.pecas.push(peca);
        setSels([peca.id]);
        renderPecas(); agendar();
    }

    /* ---------- renderização ---------- */
    function renderGrade() {
        const c = E.cel;
        el.grade.style.width = E.cols * c + 'px';
        el.grade.style.height = E.rows * c + 'px';
        el.grade.style.setProperty('--c', c + 'px');
        el.grade.classList.toggle('sem-linhas', !E.grade);
        el.grade.classList.toggle('desenhando', U.des.on);
        if (E.mapa) { el.mapa.src = E.mapa; el.mapa.hidden = false; } else { el.mapa.removeAttribute('src'); el.mapa.hidden = true; }
        el.tirarMapa.hidden = !E.mapa;
        el.svg.setAttribute('viewBox', `0 0 ${E.cols * REF} ${E.rows * REF}`);
        renderDesenho();
        renderPecas();
    }
    function renderPecas() {
        const c = E.cel;
        el.pecas.innerHTML = E.pecas.map(p => {
            const src = p.img && E.imgs[p.img];
            return `<div class="tt-peca forma-${p.forma}${U.sels.includes(p.id) ? ' sel' : ''}" data-id="${esc(p.id)}" ` +
                `style="left:${p.x * c}px;top:${p.y * c}px;width:${p.tam * c}px;height:${p.tam * c}px;--pc:${p.cor};">` +
                `<span class="tt-fig">${src ? `<img src="${src}" alt="" draggable="false" style="transform:scale(${p.iz || 1})">` : `<b>${esc(inicial(p))}</b>`}</span>` +
                (E.nomes ? `<span class="tt-nome">${esc(p.nome)}</span>` : '') +
                `</div>`;
        }).join('');
    }
    function marcarSel() {
        $$('.tt-peca', el.pecas).forEach(n => n.classList.toggle('sel', U.sels.includes(n.dataset.id)));
    }
    function setSels(arr) { U.sels = arr; marcarSel(); renderSel(); }

    function renderSel() {
        const l = selecionadas();
        if (!l.length) { el.sel.innerHTML = '<p class="tt-vazio">Clique numa peça ou arraste no mapa para selecionar. Shift/Ctrl soma peças.</p>'; return; }
        const um = l.length === 1 ? l[0] : null;
        const f = valor('forma'), c = valor('cor'), t = valor('tam');
        const comImg = l.filter(p => p.img && E.imgs[p.img]);
        const opt = (v, rot, at) => `<option value="${v}"${String(at) === String(v) ? ' selected' : ''}>${rot}</option>`;
        el.sel.innerHTML =
            `<p class="tt-sel-info"><strong>${l.length}</strong> ${l.length === 1 ? 'peça selecionada' : 'peças selecionadas'}</p>` +
            (um ? `<label class="tt-campo">Nome<input type="text" maxlength="24" data-sel="nome" value="${esc(um.nome)}"></label>` : '') +
            `<div class="tt-linha2">` +
            `<label class="tt-campo">Formato<select data-sel="forma">${f ? '' : '<option value="" selected>—</option>'}${FORMAS.map(x => opt(x, FORMA_NOME[x], f)).join('')}</select></label>` +
            `<label class="tt-campo">Tamanho<select data-sel="tam">${t ? '' : '<option value="" selected>—</option>'}${[1, 2, 3, 4].map(x => opt(x, `${x}×${x}`, t)).join('')}</select></label>` +
            `</div>` +
            `<label class="tt-campo">Cor<input type="color" data-sel="cor" value="${c || '#a78bfa'}"></label>` +
            `<div class="tt-botoes">` +
            `<label class="pv-btn pv-btn--ghost tt-btn-sm">Imagem<input type="file" accept="image/*" hidden data-arq="sel"></label>` +
            (comImg.length ? `<button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="tirar-img-sel">Tirar imagem</button>` : '') +
            `</div>` +
            (comImg.length ? `<label class="tt-campo">Zoom da imagem<input type="range" min="1" max="5" step="0.1" value="${comImg[0].iz || 1}" data-sel="zoom"></label>` : '') +
            `<div class="tt-botoes">` +
            `<button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="duplicar">Duplicar</button>` +
            `<button type="button" class="pv-btn pv-btn--ghost tt-btn-sm" data-acao="salvar-sel">Salvar em Minhas peças</button>` +
            `<button type="button" class="pv-btn pv-btn--outline tt-btn-sm" data-acao="excluir-sel">Excluir</button>` +
            `</div>`;
    }

    const miniEstilo = (forma, cor) => `--pc:${COR_OK.test(cor) ? cor : '#a78bfa'};`;
    function renderProntas() {
        el.prontas.innerHTML = PRONTAS.map(p =>
            `<button type="button" class="tt-pronta" data-acao="pronta" data-id="${p.id}">` +
            `<span class="tt-mini forma-${p.forma}" style="${miniEstilo(p.forma, p.cor)}"></span>${esc(p.nome)}</button>`).join('');
    }
    function renderModelos() {
        if (!E.modelos.length) { el.modelos.innerHTML = '<p class="tt-vazio">Nenhuma peça salva. Selecione peças no mapa e use "Salvar em Minhas peças".</p>'; return; }
        el.modelos.innerHTML = E.modelos.map(m =>
            `<div class="tt-modelo"><button type="button" class="tt-modelo__usar" data-acao="usar-modelo" data-id="${esc(m.id)}" title="Colocar no mapa">` +
            `<span class="tt-mini forma-${m.forma}" style="${miniEstilo(m.forma, m.cor)}">${m.img ? `<img src="${m.img}" alt="" style="transform:scale(${m.iz || 1})">` : ''}</span>` +
            `${esc(m.nome)} <small>${m.tam}×${m.tam}</small></button>` +
            `<button type="button" class="tt-modelo__del" data-acao="del-modelo" data-id="${esc(m.id)}" aria-label="Excluir ${esc(m.nome)}">×</button></div>`).join('');
    }
    function renderNovo() {
        const n = U.novo;
        el.novoPrev.className = `tt-mini forma-${n.forma}`;
        el.novoPrev.setAttribute('style', miniEstilo(n.forma, n.cor));
        el.novoPrev.innerHTML = n.img ? `<img src="${n.img}" alt="">` : '';
        el.tirarImgNovo.hidden = !n.img;
    }
    function renderDesUI() {
        $$('[data-ferr]').forEach(b => b.classList.toggle('ativo', U.des.on && U.des.ferr === b.dataset.ferr));
        el.desfazer.disabled = !U.des.hist.length;
        el.refazer.disabled = !U.des.refaz.length;
        el.grade.classList.toggle('desenhando', U.des.on);
    }
    function syncCampos() {
        $$('[data-cfg]').forEach(i => { const k = i.dataset.cfg; if (i.type === 'checkbox') i.checked = !!E[k]; else i.value = E[k]; });
        $$('[data-novo]').forEach(i => { i.value = U.novo[i.dataset.novo]; });
        $$('[data-des]').forEach(i => { const k = i.dataset.des; if (i.type === 'checkbox') i.checked = !!U.des[k]; else i.value = U.des[k]; });
    }

    /* ---------- desenho: caminho, forma, borracha ---------- */
    function caminho(p) {
        if (p.length === 1) return `M${p[0][0]} ${p[0][1]}l0.1 0`;
        let d = `M${p[0][0]} ${p[0][1]}`;
        if (p.length === 2) return d + `L${p[1][0]} ${p[1][1]}`;
        for (let i = 1; i < p.length - 1; i++) d += `Q${p[i][0]} ${p[i][1]} ${r1((p[i][0] + p[i + 1][0]) / 2)} ${r1((p[i][1] + p[i + 1][1]) / 2)}`;
        const u = p[p.length - 1];
        return d + `L${u[0]} ${u[1]}`;
    }
    function pontosDe(t) {
        const p = t.p || [], o = [];
        for (let i = 0; i + 1 < p.length; i += 2) o.push([p[i], p[i + 1]]);
        return o;
    }
    /** Trecho do segmento A→B que cai dentro do círculo (x, y, r), como intervalo [t0, t1]; ou null. */
    function trechoDentro(a, b, x, y, r) {
        const dx = b[0] - a[0], dy = b[1] - a[1], fx = a[0] - x, fy = a[1] - y;
        const A = dx * dx + dy * dy, B = 2 * (fx * dx + fy * dy), C = fx * fx + fy * fy - r * r;
        if (A < 1e-9) return C <= 0 ? [0, 1] : null;
        const D = B * B - 4 * A * C;
        if (D <= 0) return null;
        const q = Math.sqrt(D), i0 = Math.max(0, (-B - q) / (2 * A)), i1 = Math.min(1, (-B + q) / (2 * A));
        return i0 < i1 ? [i0, i1] : null;
    }
    /** Corta o traço onde a borracha passa. null se não tocou; senão a lista de pedaços que sobraram. */
    function cortarTraco(t, x, y, r) {
        const P = pontosDe(t);
        if (!P.length) return null;
        const R = r + (t.w || 2) / 2;
        if (P.length === 1) return Math.hypot(P[0][0] - x, P[0][1] - y) <= R ? [] : null;
        const em = (a, b, k) => [r1(a[0] + (b[0] - a[0]) * k), r1(a[1] + (b[1] - a[1]) * k)];
        const pedacos = [];
        let atual = [], tocou = false;
        for (let i = 0; i + 1 < P.length; i++) {
            const a = P[i], b = P[i + 1], iv = trechoDentro(a, b, x, y, R);
            let partes = [[0, 1]];
            if (iv) {
                tocou = true;
                partes = [];
                if (iv[0] > 1e-6) partes.push([0, iv[0]]);
                if (iv[1] < 1 - 1e-6) partes.push([iv[1], 1]);
            }
            if ((!partes.length || partes[0][0] > 0) && atual.length) { pedacos.push(atual); atual = []; }
            for (const [ini, fim] of partes) {
                const q = em(a, b, fim);
                if (ini === 0 && atual.length) atual.push(q);
                else { if (atual.length) pedacos.push(atual); atual = [em(a, b, ini), q]; }
                if (fim < 1) { pedacos.push(atual); atual = []; }
            }
        }
        if (atual.length) pedacos.push(atual);
        return tocou ? pedacos : null;
    }
    const novoPincel = (base, pts) => ({ id: uid(), t: 'pincel', c: base.c, w: base.w, d: caminho(pts), p: pts.flat() });

    function tracoSvg(t) {
        if (!COR_OK.test(t.c || '') || !/^[MLQl0-9. -]+$/.test(t.d || '')) return '';
        const w = limitar(num(t.w), 1, 24);
        return `<path d="${t.d}" fill="none" stroke="${t.c}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;
    }
    function formaPontos(f) {
        const x1 = num(f.x1), y1 = num(f.y1), x2 = num(f.x2), y2 = num(f.y2), dx = x2 - x1, dy = y2 - y1;
        if (f.t === 'circulo') {
            const r = Math.hypot(dx, dy), n = 40;
            return Array.from({ length: n }, (_, i) => { const a = i / n * Math.PI * 2; return [x1 + Math.cos(a) * r, y1 + Math.sin(a) * r]; });
        }
        if (f.t === 'quadrado') {
            const s = Math.max(Math.abs(dx), Math.abs(dy)), ex = x1 + (dx < 0 ? -s : s), ey = y1 + (dy < 0 ? -s : s);
            return [[x1, y1], [ex, y1], [ex, ey], [x1, ey]];
        }
        if (f.t === 'cone') return [[x1, y1], [x2 - dy / 2, y2 + dx / 2], [x2 + dy / 2, y2 - dx / 2]]; // vértice em A, base centrada em B
        if (f.t === 'triangulo') return [[(x1 + x2) / 2, y1], [x2, y2], [x1, y2]];
        return [];
    }
    function formaSvg(f) {
        if (!COR_OK.test(f.c || '')) return '';
        const w = limitar(num(f.w), 1, 24);
        const attrs = `${f.f ? `fill="${f.c}" fill-opacity="0.18"` : 'fill="none"'} stroke="${f.c}" stroke-width="${w}" stroke-linejoin="round"`;
        if (f.t === 'circulo') return `<circle cx="${r1(num(f.x1))}" cy="${r1(num(f.y1))}" r="${r1(Math.hypot(num(f.x2) - num(f.x1), num(f.y2) - num(f.y1)))}" ${attrs}/>`;
        const P = formaPontos(f);
        return P.length ? `<polygon points="${P.map(q => r1(q[0]) + ',' + r1(q[1])).join(' ')}" ${attrs}/>` : '';
    }
    function formaTocada(f, x, y, r) {
        const P = formaPontos(f), R = r + num(f.w) / 2;
        if (P.length < 2) return false;
        for (let i = 0; i < P.length; i++) if (trechoDentro(P[i], P[(i + 1) % P.length], x, y, R)) return true;
        if (!f.f) return false;
        let dentro = false;
        for (let i = 0, j = P.length - 1; i < P.length; j = i++) {
            const [xi, yi] = P[i], [xj, yj] = P[j];
            if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) dentro = !dentro;
        }
        return dentro;
    }
    const itemSvg = it => (it.t === 'pincel' ? tracoSvg(it) : formaSvg(it));
    const raioBorracha = () => limitar(num(U.des.larg) || 3, 1, 24) + 8;

    function renderDesenho() { el.gDes.innerHTML = E.desenho.map(itemSvg).join(''); renderVivo(); }
    function renderVivo() {
        const d = U.des;
        let h = d.vivo ? itemSvg(d.vivo) : '';
        if (d.on && d.ferr === 'borracha' && d.cx != null)
            h += `<circle cx="${d.cx}" cy="${d.cy}" r="${raioBorracha()}" fill="rgba(255,255,255,.08)" stroke="#fff" stroke-width="1" stroke-dasharray="3 3"/>`;
        el.gVivo.innerHTML = h;
    }
    function ptSvg(ev) {
        const r = el.svg.getBoundingClientRect(), W = E.cols * REF, H = E.rows * REF;
        return [r1(limitar((ev.clientX - r.left) * W / (r.width || W), 0, W)), r1(limitar((ev.clientY - r.top) * H / (r.height || H), 0, H))];
    }
    function apagarCaminho(de, ate, raio) {
        const passo = Math.max(2, raio * 0.5);
        const n = Math.max(1, Math.ceil(Math.hypot(ate[0] - de[0], ate[1] - de[1]) / passo));
        let lista = E.desenho, mudou = false;
        for (let k = 1; k <= n; k++) {
            const x = de[0] + (ate[0] - de[0]) * k / n, y = de[1] + (ate[1] - de[1]) * k / n, novos = [];
            lista.forEach(it => {
                if (it.t === 'pincel') {
                    const pedacos = cortarTraco(it, x, y, raio);
                    if (!pedacos) { novos.push(it); return; }
                    mudou = true;
                    pedacos.forEach(pts => novos.push(novoPincel(it, pts)));
                } else if (formaTocada(it, x, y, raio)) mudou = true;
                else novos.push(it);
            });
            lista = novos;
        }
        if (mudou) E.desenho = lista;
        return mudou;
    }

    function ferramenta(f) {
        const d = U.des;
        d.cx = null; d.cy = null; d.vivo = null;
        if (d.on && d.ferr === f) d.on = false;
        else { d.on = true; d.ferr = f; setSels([]); }
        renderVivo(); renderDesUI();
    }
    function desDown(e) {
        const d = U.des;
        if (!d.on || e.button > 0) return;
        e.preventDefault();
        const svg = el.svg;
        svg.setPointerCapture?.(e.pointerId);
        const ferr = d.ferr, cor = COR_OK.test(d.cor) ? d.cor : '#f472b6', larg = limitar(num(d.larg) || 3, 1, 24), raio = larg + 8;
        const antes = JSON.stringify(E.desenho);
        const gravarHist = () => { d.hist.push(antes); if (d.hist.length > HIST) d.hist.shift(); d.refaz = []; renderDesUI(); };
        const inicio = ptSvg(e), pts = [inicio];
        let anterior = inicio, gravou = false;
        const apagar = q => {
            if (apagarCaminho(anterior, q, raio)) { if (!gravou) { gravarHist(); gravou = true; } renderDesenho(); agendar(); }
            anterior = q; d.cx = q[0]; d.cy = q[1]; renderVivo();
        };
        const mover = ev => {
            const q = ptSvg(ev);
            if (ferr === 'borracha') { apagar(q); return; }
            if (ferr === 'pincel') {
                const u = pts[pts.length - 1];
                if (Math.hypot(q[0] - u[0], q[1] - u[1]) < 2.5) return;
                pts.push(q);
                d.vivo = { t: 'pincel', c: cor, w: larg, d: caminho(pts) };
            } else {
                d.vivo = { t: ferr, c: cor, w: larg, f: !!d.preencher, x1: inicio[0], y1: inicio[1], x2: q[0], y2: q[1] };
            }
            renderVivo();
        };
        const fim = () => {
            svg.removeEventListener('pointermove', mover);
            svg.removeEventListener('pointerup', fim);
            svg.removeEventListener('pointercancel', fim);
            const v = d.vivo; d.vivo = null;
            if (ferr === 'borracha') { renderVivo(); return; }
            if (E.desenho.length >= MAX_DESENHO) { renderVivo(); return aviso('Limite de itens de desenho neste mapa. Desfaça ou limpe alguns.'); }
            if (ferr === 'pincel') {
                gravarHist();
                E.desenho.push(novoPincel({ c: cor, w: larg }, pts));
            } else {
                if (!v || Math.hypot(v.x2 - v.x1, v.y2 - v.y1) < 3) { renderVivo(); return; }
                gravarHist();
                E.desenho.push({ id: uid(), ...v });
            }
            renderDesenho(); agendar();
        };
        svg.addEventListener('pointermove', mover);
        svg.addEventListener('pointerup', fim);
        svg.addEventListener('pointercancel', fim);
        if (ferr === 'borracha') apagar(inicio);
        else if (ferr === 'pincel') { d.vivo = { t: 'pincel', c: cor, w: larg, d: caminho(pts) }; renderVivo(); }
    }
    function desHover(e) {
        const d = U.des;
        if (!d.on || d.ferr !== 'borracha') return;
        const q = ptSvg(e); d.cx = q[0]; d.cy = q[1]; renderVivo();
    }
    function desDesfazer() {
        const d = U.des;
        if (!d.hist.length) return;
        d.refaz.push(JSON.stringify(E.desenho));
        E.desenho = JSON.parse(d.hist.pop());
        renderDesenho(); renderDesUI(); agendar();
    }
    function desRefazer() {
        const d = U.des;
        if (!d.refaz.length) return;
        d.hist.push(JSON.stringify(E.desenho));
        E.desenho = JSON.parse(d.refaz.pop());
        renderDesenho(); renderDesUI(); agendar();
    }
    function desLimpar() {
        if (!E.desenho.length || !confirm('Apagar todo o desenho deste mapa? As peças ficam.')) return;
        U.des.hist.push(JSON.stringify(E.desenho)); U.des.refaz = [];
        E.desenho = [];
        renderDesenho(); renderDesUI(); agendar();
    }

    /* ---------- arrastar peças (move o grupo selecionado, encaixando nas casas) ---------- */
    function pecaDown(e, id) {
        if (!achar(id)) return;
        if (e.shiftKey || e.ctrlKey || e.metaKey) { // soma ou tira da seleção, sem arrastar
            const n = U.sels.slice(), i = n.indexOf(id);
            if (i >= 0) n.splice(i, 1); else n.push(id);
            setSels(n);
            return;
        }
        if (!U.sels.includes(id)) setSels([id]);
        e.preventDefault();
        const grade = el.grade;
        grade.setPointerCapture?.(e.pointerId);
        const c = E.cel;
        const grupo = selecionadas().map(q => ({ q, x: q.x, y: q.y, t: q.tam }));
        const minDx = Math.max(...grupo.map(g => -g.x)), maxDx = Math.min(...grupo.map(g => E.cols - g.t - g.x));
        const minDy = Math.max(...grupo.map(g => -g.y)), maxDy = Math.min(...grupo.map(g => E.rows - g.t - g.y));
        const x0 = e.clientX, y0 = e.clientY;
        let moveu = false;
        const mover = ev => {
            const dx = limitar(Math.round((ev.clientX - x0) / c), minDx, maxDx);
            const dy = limitar(Math.round((ev.clientY - y0) / c), minDy, maxDy);
            grupo.forEach(g => {
                g.q.x = g.x + dx; g.q.y = g.y + dy;
                const n = noPeca(g.q.id);
                if (n) { n.style.left = g.q.x * c + 'px'; n.style.top = g.q.y * c + 'px'; }
            });
            if (dx || dy) moveu = true;
        };
        const fim = () => {
            grade.removeEventListener('pointermove', mover);
            grade.removeEventListener('pointerup', fim);
            grade.removeEventListener('pointercancel', fim);
            if (moveu) agendar();
        };
        grade.addEventListener('pointermove', mover);
        grade.addEventListener('pointerup', fim);
        grade.addEventListener('pointercancel', fim);
    }

    /* ---------- seleção em caixa: arrastar no fundo seleciona as peças que o retângulo toca ---------- */
    function caixaDown(e) {
        const aditivo = e.shiftKey || e.ctrlKey || e.metaKey;
        if (e.pointerType === 'touch') { if (!aditivo) setSels([]); return; } // no toque, arrastar continua rolando a tela
        e.preventDefault();
        const grade = el.grade, cena = el.cena;
        grade.setPointerCapture?.(e.pointerId);
        const base = aditivo ? U.sels.slice() : [];
        if (!aditivo) setSels([]);
        const c = E.cel, r0 = grade.getBoundingClientRect();
        const x0 = limitar(e.clientX - r0.left, 0, r0.width), y0 = limitar(e.clientY - r0.top, 0, r0.height);
        let cx = e.clientX, cy = e.clientY, moveu = false, quadro = 0;
        const atualizar = () => {
            const r = grade.getBoundingClientRect();
            const x1 = limitar(cx - r.left, 0, r.width), y1 = limitar(cy - r.top, 0, r.height);
            const x = Math.min(x0, x1), y = Math.min(y0, y1), w = Math.abs(x1 - x0), h = Math.abs(y1 - y0);
            if (!moveu && w < 4 && h < 4) return;
            moveu = true;
            el.caixa.hidden = false;
            el.caixa.style.cssText = `left:${x}px;top:${y}px;width:${w}px;height:${h}px;`;
            const ids = E.pecas.filter(p => {
                const t = p.tam * c, px = p.x * c, py = p.y * c;
                return px < x + w && px + t > x && py < y + h && py + t > y;
            }).map(p => p.id);
            U.sels = [...new Set([...base, ...ids])];
            marcarSel();
        };
        const rolar = () => { // perto da borda da cena ela rola sozinha
            quadro = 0;
            const k = cena.getBoundingClientRect(), borda = 32, vel = 18;
            const dx = cx < k.left + borda ? -vel : cx > k.right - borda ? vel : 0;
            const dy = cy < k.top + borda ? -vel : cy > k.bottom - borda ? vel : 0;
            if (!dx && !dy) return;
            const ax = cena.scrollLeft, ay = cena.scrollTop;
            cena.scrollLeft += dx; cena.scrollTop += dy;
            if (cena.scrollLeft !== ax || cena.scrollTop !== ay) atualizar();
            quadro = requestAnimationFrame(rolar);
        };
        const mover = ev => { cx = ev.clientX; cy = ev.clientY; atualizar(); if (!quadro) quadro = requestAnimationFrame(rolar); };
        const fim = () => {
            el.caixa.hidden = true;
            if (quadro) cancelAnimationFrame(quadro);
            grade.removeEventListener('pointermove', mover);
            grade.removeEventListener('pointerup', fim);
            grade.removeEventListener('pointercancel', fim);
            renderSel();
        };
        grade.addEventListener('pointermove', mover);
        grade.addEventListener('pointerup', fim);
        grade.addEventListener('pointercancel', fim);
    }

    el.grade.addEventListener('pointerdown', e => {
        if (e.button > 0) return;
        el.grade.focus({ preventScroll: true });
        if (U.des.on) return; // o SVG trata o desenho
        const n = e.target.closest('.tt-peca');
        if (n) pecaDown(e, n.dataset.id); else caixaDown(e);
    });
    el.svg.addEventListener('pointerdown', desDown);
    el.svg.addEventListener('pointermove', desHover);
    el.svg.addEventListener('pointerleave', () => { U.des.cx = null; U.des.cy = null; renderVivo(); });

    /* ---------- teclado ---------- */
    el.grade.addEventListener('keydown', e => {
        if (e.key === 'Escape') { setSels([]); return; }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? desRefazer() : desDesfazer(); return; }
        const grupo = selecionadas();
        if (!grupo.length) return;
        const m = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
        if (m) {
            e.preventDefault();
            const dx = limitar(m[0], Math.max(...grupo.map(q => -q.x)), Math.min(...grupo.map(q => E.cols - q.tam - q.x)));
            const dy = limitar(m[1], Math.max(...grupo.map(q => -q.y)), Math.min(...grupo.map(q => E.rows - q.tam - q.y)));
            grupo.forEach(q => { q.x += dx; q.y += dy; });
            renderPecas(); agendar();
            return;
        }
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); excluirSel(); }
    });

    /* ---------- imagens ---------- */
    function reduzir(arq, max, q, tipo) {
        return new Promise((ok, falha) => {
            const fr = new FileReader();
            fr.onerror = () => falha(new Error('leitura'));
            fr.onload = () => {
                const im = new Image();
                im.onerror = () => falha(new Error('imagem'));
                im.onload = () => {
                    const k = Math.min(1, max / Math.max(im.width, im.height));
                    const c = document.createElement('canvas');
                    c.width = Math.max(1, Math.round(im.width * k)); c.height = Math.max(1, Math.round(im.height * k));
                    const cx = c.getContext('2d');
                    if (tipo === 'image/jpeg') { cx.fillStyle = '#fff'; cx.fillRect(0, 0, c.width, c.height); }
                    cx.drawImage(im, 0, 0, c.width, c.height);
                    ok(c.toDataURL(tipo, q));
                };
                im.src = fr.result;
            };
            fr.readAsDataURL(arq);
        });
    }
    async function lerArquivo(tipo, arq) {
        if (!arq || !arq.type.startsWith('image/')) return;
        try {
            if (tipo === 'mapa') {
                E.mapa = await reduzir(arq, 1400, 0.75, 'image/jpeg');
                renderGrade(); agendar();
            } else if (tipo === 'novo') {
                U.novo.img = await reduzir(arq, 256, 0.8, 'image/webp');
                renderNovo();
            } else if (tipo === 'sel') {
                const alvos = selecionadas();
                if (!alvos.length) return;
                const src = await reduzir(arq, 256, 0.8, 'image/webp'), iid = 'i' + uid();
                E.imgs[iid] = src;
                alvos.forEach(p => { p.img = iid; p.iz = 1; });
                limparImgs(); renderPecas(); renderSel(); agendar();
            }
        } catch (_) { aviso('Não consegui ler essa imagem.'); }
    }

    /* ---------- "Minhas peças" ---------- */
    function gravarModelo(d, src) {
        const nome = txt(d.nome).trim().slice(0, 24) || 'Peça';
        let m = E.modelos.find(x => x.nome.toLowerCase() === nome.toLowerCase());
        if (!m && E.modelos.length >= MAX_MODELOS) { aviso(`Limite de ${MAX_MODELOS} peças salvas. Exclua alguma para salvar outra.`); return false; }
        const dados = {
            id: m ? m.id : uid(), nome,
            forma: FORMAS.includes(d.forma) ? d.forma : 'circulo',
            cor: COR_OK.test(d.cor || '') ? d.cor : '#a78bfa',
            tam: limitar(Math.round(num(d.tam)) || 1, 1, 4),
            iz: limitar(num(d.iz) || 1, 1, 5),
            img: imgOk(src) ? src : '',
        };
        if (m) Object.assign(m, dados); else E.modelos.push(dados);
        return true;
    }

    /* ---------- ações (botões) ---------- */
    function excluirSel() {
        if (!U.sels.length) return;
        E.pecas = E.pecas.filter(p => !U.sels.includes(p.id));
        U.sels = [];
        limparImgs(); renderPecas(); renderSel(); agendar();
    }
    const ACOES = {
        pronta(b) {
            const d = PRONTAS.find(p => p.id === b.dataset.id);
            if (d) adicionarPeca({ ...d, nomeFinal: nomeUnico(d.nome), tam: 1 });
        },
        'add-novo'() {
            const n = U.novo;
            adicionarPeca({ nome: n.nome, forma: n.forma, cor: n.cor, tam: n.tam }, n.img);
            n.nome = ''; n.img = ''; syncCampos(); renderNovo();
        },
        'salvar-modelo-novo'() {
            const n = U.novo;
            if (!txt(n.nome).trim()) return aviso('Dê um nome à peça para salvar o modelo.');
            if (gravarModelo(n, n.img)) { renderModelos(); agendar(); }
        },
        'tirar-img-novo'() { U.novo.img = ''; renderNovo(); },
        'tirar-mapa'() { E.mapa = ''; renderGrade(); agendar(); },
        duplicar() {
            const orig = selecionadas();
            if (!orig.length) return;
            if (E.pecas.length + orig.length > MAX_PECAS) return aviso(`Limite de ${MAX_PECAS} peças neste mapa.`);
            const novas = [];
            orig.forEach(p => {
                const [x, y] = livre(p.tam);
                const n = { ...copia(p), id: uid(), x, y };
                E.pecas.push(n); novas.push(n.id);
            });
            setSels(novas); renderPecas(); agendar();
        },
        'excluir-sel': excluirSel,
        'tirar-img-sel'() {
            selecionadas().forEach(p => { delete p.img; delete p.iz; });
            limparImgs(); renderPecas(); renderSel(); agendar();
        },
        'salvar-sel'() {
            const sel = selecionadas();
            let n = 0;
            for (const p of sel) {
                if (!gravarModelo(p, p.img ? E.imgs[p.img] : '')) break;
                n++;
            }
            if (n) { renderModelos(); agendar(); }
        },
        'usar-modelo'(b) {
            const m = E.modelos.find(x => x.id === b.dataset.id);
            if (m) adicionarPeca({ ...m, nomeFinal: nomeUnico(m.nome) }, m.img);
        },
        'del-modelo'(b) {
            const m = E.modelos.find(x => x.id === b.dataset.id);
            if (!m || !confirm(`Excluir "${m.nome}" de Minhas peças? As peças que já estão no mapa continuam.`)) return;
            E.modelos = E.modelos.filter(x => x.id !== m.id);
            renderModelos(); agendar();
        },
        ferr(b) { ferramenta(b.dataset.ferr); },
        'des-desfazer': desDesfazer,
        'des-refazer': desRefazer,
        'des-limpar': desLimpar,
    };
    el.page.addEventListener('click', e => {
        const b = e.target.closest('[data-acao]');
        if (!b || b.disabled) return;
        const f = ACOES[b.dataset.acao];
        if (f) f(b);
    });

    /* ---------- campos (inputs) ---------- */
    function aoMudar(e) {
        const i = e.target;
        if (!(i instanceof HTMLInputElement || i instanceof HTMLSelectElement)) return;
        const d = i.dataset;

        if (d.arq) { if (e.type === 'change') { lerArquivo(d.arq, i.files && i.files[0]); i.value = ''; } return; }

        if (d.cfg) {
            const k = d.cfg;
            if (i.type === 'checkbox') E[k] = i.checked;
            else {
                const lim = { cols: [4, 60, 16], rows: [4, 60, 12], cel: [20, 80, 40] }[k];
                if (i.value === '') return;
                E[k] = limitar(Math.round(num(i.value)) || lim[2], lim[0], lim[1]);
                if (e.type === 'change') i.value = E[k];
            }
            limitarPecas(); renderGrade(); agendar();
            return;
        }

        if (d.novo) {
            U.novo[d.novo] = d.novo === 'tam' ? limitar(Math.round(num(i.value)) || 1, 1, 4) : i.value;
            renderNovo();
            return;
        }

        if (d.des) {
            const k = d.des;
            if (i.type === 'checkbox') U.des[k] = i.checked;
            else U.des[k] = k === 'larg' ? limitar(num(i.value) || 3, 1, 24) : i.value;
            renderVivo();
            return;
        }

        if (d.sel) {
            const l = selecionadas();
            if (!l.length) return;
            const k = d.sel, v = i.value;
            if (k === 'nome') l[0].nome = v.slice(0, 24) || 'Peça';
            else if (k === 'forma' && FORMAS.includes(v)) l.forEach(p => { p.forma = v; });
            else if (k === 'cor' && COR_OK.test(v)) l.forEach(p => { p.cor = v; });
            else if (k === 'tam' && v) { l.forEach(p => { p.tam = limitar(Math.round(num(v)) || 1, 1, 4); }); limitarPecas(); }
            else if (k === 'zoom') l.filter(p => p.img).forEach(p => { p.iz = limitar(num(v) || 1, 1, 5); });
            else return;
            renderPecas(); agendar();
            // forma e tamanho mudam o painel (valor "—" some); nome e cor não precisam reconstruir
            if (e.type === 'change' && (k === 'forma' || k === 'tam')) renderSel();
        }
    }
    el.page.addEventListener('input', aoMudar);
    el.page.addEventListener('change', aoMudar);

    el.nomeMesa.addEventListener('change', () => {
        const n = el.nomeMesa.value.trim().slice(0, 80);
        if (!n) { el.nomeMesa.value = CFG.nome; return; }
        CFG.nome = n; document.title = n + ' · Tabletop'; agendar();
    });

    /* ---------- início ---------- */
    renderProntas();
    renderModelos();
    syncCampos();
    renderNovo();
    renderGrade();
    renderSel();
    renderDesUI();
    setStatus('salvo');
})();