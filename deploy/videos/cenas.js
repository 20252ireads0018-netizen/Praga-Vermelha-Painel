/* Vídeos explicativos do Praga Vermelha: animação vertical 1080×1920 com bonecos de palito, sincronizada com a narração
   (linha.json, gerado por narrar.ps1). Ver LEIA-ME.md. */
(async function () {
    const qs = new URLSearchParams(location.search);
    const VIDEO = qs.get('v') || 'eletrico';
    const L = await (await fetch('/vid/linha.json')).json();
    const cenas = L[VIDEO];
    const GAP = 0.12, INICIO = 0.25, FIM_EXTRA = 0.8;
    let t = INICIO;
    cenas.forEach(c => { c.t0 = t; t += c.dur + GAP; });
    let TOTAL = t + FIM_EXTRA;

    const cv = document.getElementById('c'), g = cv.getContext('2d');
    const W = 1080, H = 1920, CX = W / 2, CHAO = 1250;
    await document.fonts.load('800 80px Cinzel'); await document.fonts.load('800 60px Inter'); await document.fonts.load('600 40px Inter');

    /* ---------- utilitários ---------- */
    const lerp = (a, b, k) => a + (b - a) * k;
    const clamp = (x, a = 0, b = 1) => Math.max(a, Math.min(b, x));
    const ease = k => 1 - Math.pow(1 - clamp(k), 3);
    const fase = (k, a, b) => clamp((k - a) / (b - a)); // 0→1 entre a e b
    const pop = k => { k = clamp(k); return k < .6 ? ease(k / .6) * 1.15 : lerp(1.15, 1, (k - .6) / .4); };
    const RED = '#d63c3c', BLOOD = '#a51e1e', GOLD = '#e9c27a', INK = '#f5ecec', AZUL = '#9fdcff';
    let tremor = 0;
    function txt(s, x, y, size, cor = INK, peso = 800, fonte = 'Inter', alinha = 'center') {
        g.save(); g.font = `${peso} ${size}px ${fonte}`; g.textAlign = alinha; g.textBaseline = 'middle'; g.fillStyle = cor;
        g.shadowColor = 'rgba(0,0,0,.9)'; g.shadowBlur = 20; g.fillText(s, x, y); g.restore();
    }
    /** Texto que bate na tela: entra grande e assenta. */
    function bate(s, x, y, size, k, cor = INK, fonte = 'Cinzel', ang = -.04) {
        if (k <= 0) return;
        const e = pop(k);
        g.save(); g.translate(x, y); g.rotate(ang); g.scale(e, e); g.globalAlpha = clamp(k * 4);
        txt(s, 0, 0, size, cor, 800, fonte); g.restore();
    }
    function caixa(x, y, w, h, r, fill, stroke, lw = 3) {
        g.beginPath(); g.roundRect(x, y, w, h, r);
        if (fill) { g.fillStyle = fill; g.fill(); }
        if (stroke) { g.strokeStyle = stroke; g.lineWidth = lw; g.stroke(); }
    }
    function chip(texto, x, y, k, cor = BLOOD, w = 640) {
        if (k <= 0) return;
        const s = pop(k);
        g.save(); g.translate(x, y); g.scale(s, s); g.globalAlpha = clamp(k * 4);
        caixa(-w / 2, -62, w, 124, 62, 'rgba(12,6,8,.9)', cor, 5); txt(texto, 0, 2, 56, INK, 800); g.restore();
    }
    function raio(x1, y1, x2, y2, seed, cor = '#d9f1ff', lw = 8) {
        let r = Math.abs(seed * 9973) % 233280 || 7; const rnd = () => (r = (r * 9301 + 49297) % 233280) / 233280;
        g.save(); g.strokeStyle = cor; g.lineWidth = lw; g.shadowColor = '#5cbcff'; g.shadowBlur = 36; g.lineJoin = 'round'; g.lineCap = 'round';
        g.beginPath(); g.moveTo(x1, y1); const n = 8;
        for (let i = 1; i < n; i++) { const k = i / n; g.lineTo(lerp(x1, x2, k) + (rnd() - .5) * 70, lerp(y1, y2, k) + (rnd() - .5) * 70); }
        g.lineTo(x2, y2); g.stroke(); g.lineWidth = lw / 3; g.strokeStyle = '#fff'; g.stroke(); g.restore();
    }
    function clarao(k, cor = '255,255,255') { if (k > 0 && k < 1) { g.fillStyle = `rgba(${cor},${(1 - k) * .32})`; g.fillRect(0, 0, W, H); } }

    /* ---------- boneco de palito ----------
       Ângulos medidos a partir de "para baixo"; positivo gira para a direita da tela.
       o: { pose, cor, brilho, olhos ('ok'|'x'|'fechados'), arrepiado, queda (rad, gira nos pés), alpha, olhar (±1) }
       Devolve as posições (na tela) de pés, mãos, ombros, quadril, cabeça e coração. */
    const POSES = {
        parado: { bE: -.32, bD: .32, cE: .15, cD: -.15, pE: -.2, pD: .2, jE: 0, jD: 0 },
        tocar: { bE: -.35, bD: 1.5, cE: .1, cD: 0, pE: -.25, pD: .25, jE: 0, jD: 0 },
        tocarAlto: { bE: -.35, bD: 2.3, cE: .1, cD: 0, pE: -.25, pD: .25, jE: 0, jD: 0 },
        cima: { bE: -2.6, bD: 2.6, cE: -.3, cD: .3, pE: -.28, pD: .28, jE: 0, jD: 0 },
        poder: { bE: -1.9, bD: 1.9, cE: -.6, cD: .6, pE: -.35, pD: .35, jE: .2, jD: -.2 },
        pesado: { bE: -.8, bD: .8, cE: .9, cD: -.9, pE: -.55, pD: .55, jE: .9, jD: -.9 },
        preso: { bE: -.15, bD: .15, cE: .5, cD: -.5, pE: -.08, pD: .08, jE: 0, jD: 0 },
        defesa: { bE: .9, bD: 1.9, cE: 1.4, cD: .7, pE: -.3, pD: .3, jE: .2, jD: 0 },
    };
    function misturar(a, b, k) { const r = {}; for (const c in a) r[c] = lerp(a[c], b[c], k); return r; }
    function andando(t) { const s = Math.sin(t * 9); return { bE: -.45 * s, bD: .45 * s, cE: .3, cD: -.3, pE: .45 * s, pD: -.45 * s, jE: Math.max(0, -s) * .6, jD: Math.max(0, s) * .6 }; }
    function boneco(x, y, s, o = {}) {
        const p = typeof o.pose === 'string' ? POSES[o.pose] : (o.pose || POSES.parado);
        const cor = o.cor || INK, lw = 14;
        const pt = {};
        g.save(); g.globalAlpha = o.alpha ?? 1;
        g.translate(x, y); g.rotate(o.queda || 0); g.scale(s, s);
        g.strokeStyle = cor; g.fillStyle = cor; g.lineWidth = lw; g.lineCap = 'round'; g.lineJoin = 'round';
        if (o.brilho) { g.shadowColor = o.brilho; g.shadowBlur = 40; }
        const quadril = [0, -125], ombro = [0, -235];
        const seg = (de, ang, L) => [de[0] + Math.sin(ang) * L, de[1] + Math.cos(ang) * L];
        const membro = (de, a1, a2, L1, L2) => { const m = seg(de, a1, L1), f = seg(m, a1 + a2, L2); g.beginPath(); g.moveTo(...de); g.lineTo(...m); g.lineTo(...f); g.stroke(); return f; };
        const peE = membro(quadril, p.pE, p.jE, 64, 64), peD = membro(quadril, p.pD, p.jD, 64, 64);
        g.beginPath(); g.moveTo(...quadril); g.lineTo(...ombro); g.stroke();
        const maoE = membro(ombro, p.bE, p.cE, 58, 56), maoD = membro(ombro, p.bD, p.cD, 58, 56);
        g.beginPath(); g.arc(0, -282, 44, 0, Math.PI * 2); g.lineWidth = lw; g.stroke();
        g.shadowBlur = 0;
        if (o.arrepiado) for (let i = -3; i <= 3; i++) { g.beginPath(); g.moveTo(i * 12, -322); g.lineTo(i * 22, -362 - (i % 2) * 14); g.lineWidth = 7; g.stroke(); }
        const ol = o.olhar ?? 1, olhos = o.olhos || 'ok';
        g.lineWidth = 6;
        [[-14, -288], [14, -288]].forEach(([ex, ey]) => {
            ex += ol * 10;
            if (olhos === 'x') { g.beginPath(); g.moveTo(ex - 7, ey - 7); g.lineTo(ex + 7, ey + 7); g.moveTo(ex + 7, ey - 7); g.lineTo(ex - 7, ey + 7); g.stroke(); }
            else if (olhos === 'fechados') { g.beginPath(); g.moveTo(ex - 8, ey); g.lineTo(ex + 8, ey); g.stroke(); }
            else { g.beginPath(); g.arc(ex, ey, 5.5, 0, 7); g.fill(); }
        });
        const tela = q => { const m = g.getTransform(); return [m.a * q[0] + m.c * q[1] + m.e, m.b * q[0] + m.d * q[1] + m.f]; };
        Object.assign(pt, { peE: tela(peE), peD: tela(peD), maoE: tela(maoE), maoD: tela(maoD), ombro: tela(ombro), quadril: tela(quadril),
            cabeca: tela([0, -282]), coracao: tela([-14, -205]), ombroE: tela([-20, -232]), ombroD: tela([20, -232]) });
        g.restore();
        return pt;
    }
    /** Lâmpada da ideia, acesa e pulsando. */
    function lampada(x, y, s, tt) {
        g.save(); g.translate(x, y); g.scale(s, s); g.shadowColor = GOLD; g.shadowBlur = 50 + Math.sin(tt * 8) * 20;
        g.fillStyle = '#ffe7a3'; g.beginPath(); g.arc(0, 0, 52, Math.PI * .8, Math.PI * .2); g.lineTo(20, 70); g.lineTo(-20, 70); g.closePath(); g.fill();
        g.shadowBlur = 0; g.fillStyle = '#9a8f80'; g.fillRect(-24, 72, 48, 26);
        g.strokeStyle = GOLD; g.lineWidth = 7; g.lineCap = 'round';
        for (let i = 0; i < 5; i++) { const an = -Math.PI / 2 + (i - 2) * .55; g.beginPath(); g.moveTo(Math.cos(an) * 78, Math.sin(an) * 78); g.lineTo(Math.cos(an) * 110, Math.sin(an) * 110); g.stroke(); }
        g.restore();
    }
    /** Balão de fala que estoura com o texto. */
    function balao(x, y, texto, k) {
        if (k <= 0) return;
        g.save(); g.translate(x, y); g.scale(pop(k), pop(k)); g.globalAlpha = clamp(k * 4);
        caixa(-190, -70, 380, 140, 40, '#f5ecec'); g.fillStyle = '#f5ecec'; g.beginPath(); g.moveTo(-120, 60); g.lineTo(-170, 130); g.lineTo(-60, 66); g.fill();
        g.font = '900 70px Inter'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillStyle = BLOOD; g.fillText(texto, 0, 4); g.restore();
    }
    /** Chapéu do mestre. */
    function chapeu(x, y) { g.save(); g.fillStyle = GOLD; g.beginPath(); g.moveTo(x - 90, y - 50); g.lineTo(x + 90, y - 50); g.lineTo(x + 10, y - 190); g.closePath(); g.fill(); g.restore(); }
    function chao() { g.save(); g.strokeStyle = 'rgba(233,194,122,.35)'; g.lineWidth = 4; g.beginPath(); g.moveTo(80, CHAO + 8); g.lineTo(W - 80, CHAO + 8); g.stroke(); g.restore(); }
    function caminho(pts, k, cor = AZUL, lw = 14) {
        const n = Math.max(0, clamp(k) * (pts.length - 1)); if (n <= 0) return;
        g.save(); g.strokeStyle = cor; g.shadowColor = '#5cbcff'; g.shadowBlur = 30; g.lineWidth = lw; g.lineCap = 'round'; g.lineJoin = 'round';
        g.beginPath(); g.moveTo(...pts[0]); for (let i = 1; i <= Math.floor(n); i++) g.lineTo(...pts[i]);
        if (n % 1 && Math.ceil(n) < pts.length) { const a = pts[Math.floor(n)], b = pts[Math.ceil(n)]; g.lineTo(lerp(a[0], b[0], n % 1), lerp(a[1], b[1], n % 1)); }
        g.stroke(); g.restore();
    }
    function coracao(x, y, s, cor = RED) {
        g.save(); g.translate(x, y); g.scale(s / 24, s / 24); g.translate(-12, -12); g.fillStyle = cor;
        g.fill(new Path2D('M12 21s-8-4.7-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.3-8 11-8 11Z')); g.restore();
    }
    function proibido(x, y, r) { g.save(); g.strokeStyle = RED; g.lineWidth = 9; g.beginPath(); g.arc(x, y, r, 0, 7); g.moveTo(x - r * .7, y - r * .7); g.lineTo(x + r * .7, y + r * .7); g.stroke(); g.restore(); }
    function tomada(x, y, s) {
        g.save(); g.translate(x, y); g.scale(s, s); caixa(-70, -90, 140, 180, 22, '#e8e2d6', '#9a8f80', 5);
        g.fillStyle = '#2a2420'; g.beginPath(); g.ellipse(-24, -10, 10, 18, 0, 0, 7); g.ellipse(24, -10, 10, 18, 0, 0, 7); g.fill();
        g.beginPath(); g.arc(0, 40, 9, 0, 7); g.fill(); g.restore();
    }
    function ancora(x, y, s, cor = '#cfc8bd') {
        g.save(); g.translate(x, y); g.scale(s, s); g.strokeStyle = cor; g.lineWidth = 16; g.lineCap = 'round';
        g.beginPath(); g.arc(0, -150, 26, 0, 7); g.moveTo(0, -124); g.lineTo(0, 60); g.moveTo(-60, -80); g.lineTo(60, -80);
        g.moveTo(-110, -10); g.quadraticCurveTo(-90, 70, 0, 70); g.quadraticCurveTo(90, 70, 110, -10); g.stroke(); g.restore();
    }
    function cubo(x, y, s, cor) {
        g.save(); g.translate(x, y); g.scale(s, s); g.strokeStyle = cor; g.lineWidth = 10; g.lineJoin = 'round';
        g.stroke(new Path2D('M0 -90 80 -45v90L0 90-80 45v-90Z M0 0 80 -45 M0 0-80 -45 M0 0v90')); g.restore();
    }
    function corFogo(tt, a = 1) { const f = (Math.sin(tt * 2.2) + 1) / 2; return `rgba(255,${Math.round(lerp(130, 245, f))},${Math.round(lerp(40, 235, f))},${a})`; }
    function fogo(x, y, s, tt, a = 1) {
        g.save(); g.globalAlpha = a; g.globalCompositeOperation = 'lighter';
        for (let i = 0; i < 30; i++) {
            const p = ((tt * 1.1 + i / 30) % 1), xx = x + Math.sin(i * 7.1 + tt * 3) * 70 * s * (1 - p), yy = y - p * 300 * s, r = (46 - p * 34) * s;
            const grd = g.createRadialGradient(xx, yy, 0, xx, yy, r); grd.addColorStop(0, corFogo(tt + i * .05, .6 * (1 - p))); grd.addColorStop(1, 'rgba(255,60,20,0)');
            g.fillStyle = grd; g.beginPath(); g.arc(xx, yy, r, 0, 7); g.fill();
        }
        g.restore();
    }
    function particulas(de, para, tt, n = 22, cor) {
        for (let i = 0; i < n; i++) {
            const p = ((tt * .9 + i / n) % 1);
            const x = lerp(de[0], para[0], p), y = lerp(de[1], para[1], p) - Math.sin(p * Math.PI) * 180 + (i % 4) * 6;
            g.fillStyle = cor || corFogo(tt + i * .1, .95); g.beginPath(); g.arc(x, y, 10, 0, 7); g.fill();
        }
    }
    function fundo(tt) {
        const grd = g.createRadialGradient(CX, 850, 60, CX, 850, 1300);
        grd.addColorStop(0, '#3a0b10'); grd.addColorStop(.6, '#140608'); grd.addColorStop(1, '#070304');
        g.fillStyle = grd; g.fillRect(0, 0, W, H);
        g.save(); g.translate(CX, 850); g.rotate(tt * .06); g.strokeStyle = 'rgba(214,60,60,.1)'; g.lineWidth = 3;
        [520, 470].forEach(r => { g.beginPath(); g.arc(0, 0, r, 0, 7); g.stroke(); });
        for (let k = 0; k < 2; k++) { g.beginPath(); for (let i = 0; i < 4; i++) { const a = -Math.PI / 2 + k * Math.PI + i * Math.PI * 2 / 3; g[i ? 'lineTo' : 'moveTo'](Math.cos(a) * 470, Math.sin(a) * 470); } g.stroke(); }
        g.restore();
    }
    function legenda(cena, lt) {
        const palavras = cena.fala.split(' '), feitas = Math.floor(clamp(lt / (cena.dur * .9)) * palavras.length + .999);
        g.save(); g.font = '800 66px Inter'; const linhas = []; let atual = [];
        palavras.forEach((p, i) => { if (g.measureText(atual.map(x => x.w).concat(p).join(' ')).width > 900 && atual.length) { linhas.push(atual); atual = []; } atual.push({ w: p, i }); });
        if (atual.length) linhas.push(atual);
        const y0 = 1500;
        linhas.forEach((ln, li) => {
            const larg = g.measureText(ln.map(x => x.w).join(' ')).width; let x = CX - larg / 2;
            ln.forEach(({ w, i }) => {
                const ww = g.measureText(w + ' ').width, atualP = i === feitas - 1;
                g.textBaseline = 'middle'; g.lineWidth = 14; g.strokeStyle = 'rgba(0,0,0,.85)'; g.lineJoin = 'round';
                g.strokeText(w, x, y0 + li * 84);
                g.fillStyle = i < feitas - 1 ? INK : (atualP ? GOLD : 'rgba(245,236,236,.32)');
                g.fillText(w, x, y0 + li * 84); x += ww;
            });
        });
        g.restore();
    }

    /* ---------- cenas: k = 0→1 ao longo da fala, lt = segundos ---------- */
    const CENAS = {
        eletrico: {
            titulo: 'Atração Elétrica',
            gancho(k, lt) {
                chao(); tomada(840, CHAO - 200, 1);
                const anda = fase(k, 0, .35), recua = fase(k, .42, .55), ideia = fase(k, .55, .75);
                const x = lerp(220, 600, ease(anda)) - 60 * ease(recua);
                const pose = anda < 1 ? andando(lt) : misturar(POSES.tocar, POSES.parado, ease(recua));
                const quase = anda >= 1 && recua < 1;
                const p = boneco(x, CHAO, 1.55, { pose, arrepiado: quase, brilho: quase ? '#5cbcff' : null });
                if (quase && Math.sin(lt * 40) > 0) raio(p.maoD[0] + 10, p.maoD[1], 790, CHAO - 210, Math.floor(lt * 20), '#d9f1ff', 5);
                if (ideia === 0) bate('MATA.', 700, 420, 130, fase(k, .12, .3), RED);
                if (ideia > 0) lampada(p.cabeca[0], p.cabeca[1] - 190, pop(ideia), lt);
                bate('PENSE.', CX, 330, 130, fase(k, .7, .88), GOLD);
            },
            declarar(k, lt) {
                chao();
                const fala = fase(k, .45, .6), carta = fase(k, .65, .85);
                const j = boneco(260, CHAO, 1.35, { pose: fala > 0 ? 'poder' : 'parado' });
                const m = boneco(820, CHAO, 1.35, { pose: 'parado', cor: GOLD, olhar: -1 });
                chapeu(m.cabeca[0], m.cabeca[1]);
                txt('MESTRE', 820, CHAO + 60, 34, GOLD, 700);
                if (carta < .25) balao(j.cabeca[0] + 150, j.cabeca[1] - 190, 'IMBUIR!', fala);
                bate('NÃO SE COMPRA.', CX, 380, 84, fase(k, .05, .3), RED, 'Inter', 0);
                if (carta > 0) { // a técnica aparece entre os dois: descoberta
                    g.save(); g.translate(CX, 760); g.rotate((1 - ease(carta)) * .5); g.scale(pop(carta), pop(carta));
                    caixa(-170, -230, 340, 460, 24, 'rgba(12,6,8,.95)', AZUL, 6);
                    raio(-100, -150, 100, 60, Math.floor(lt * 12), '#d9f1ff', 6);
                    txt('TÉCNICA', 0, 120, 44, AZUL); txt('DESCOBERTA', 0, 175, 40, INK); g.restore();
                }
            },
            imbuir(k, lt) {
                chao();
                const toca = fase(k, .2, .35), sofre = toca >= 1 ? fase(k, .35, .9) : 0;
                const a = boneco(330, CHAO, 1.45, { pose: misturar(POSES.parado, POSES.tocar, toca), brilho: '#5cbcff' });
                const sacode = sofre > 0 ? Math.sin(lt * 34) * .14 : 0;
                const b = boneco(600 + sacode * 40, CHAO, 1.35, { pose: sofre > 0 ? misturar(POSES.cima, POSES.preso, Math.abs(Math.sin(lt * 20))) : 'parado', olhar: -1, arrepiado: sofre > 0, brilho: sofre > 0 ? '#5cbcff' : null, olhos: sofre > .7 ? 'x' : 'ok' });
                if (sofre > 0 && sofre < .8) { raio(a.maoD[0], a.maoD[1], b.ombro[0], b.ombro[1], Math.floor(lt * 22)); raio(b.cabeca[0], b.cabeca[1] - 50, b.cabeca[0] + 120, b.cabeca[1] - 200, Math.floor(lt * 22) + 3, '#d9f1ff', 5); tremor = 10; }
                chip('IMBUIR', CX, 380, fase(k, 0, .2), AZUL, 420);
                bate('ELE SOFRE.', CX, 560, 100, fase(k, .55, .75), INK);
            },
            conduzir(k, lt) {
                chao(); tomada(110, CHAO - 120, .7);
                const p = boneco(330, CHAO, 1.45, { pose: { ...POSES.poder, bE: -.9, bD: 1.57, cE: .2, cD: 0 }, brilho: '#5cbcff' });
                const corre = fase(k, .15, .55), dispara = fase(k, .55, .7), caem = fase(k, .68, .9);
                caminho([[150, CHAO - 120], [p.peE[0], p.peE[1]], [p.quadril[0] - 45, p.quadril[1]], [p.ombro[0] - 45, p.ombro[1] + 10], [p.ombro[0] + 20, p.ombro[1]], p.maoD], corre, AZUL, 14);
                [580, 730].forEach((x, i) => boneco(x, CHAO, 1, { pose: caem > 0 ? 'cima' : 'defesa', olhar: -1, arrepiado: dispara > 0, olhos: caem > .6 ? 'x' : 'ok', queda: 1.2 * ease(fase(caem, i * .2, .8 + i * .2)) }));
                if (dispara > 0 && caem < .5) { raio(p.maoD[0], p.maoD[1], 1060, p.maoD[1] + 10, Math.floor(lt * 24), '#d9f1ff', 12); tremor = 12; }
                clarao(fase(k, .55, .62));
                chip('CONDUZIR', CX, 380, fase(k, 0, .2), AZUL, 480);
                bate('EM LINHA!', CX, 560, 110, fase(k, .62, .8), AZUL, 'Inter', .05);
            },
            caminho(k, lt) {
                chao();
                const p = boneco(CX, CHAO, 2.0, { pose: { ...POSES.poder, bE: -1.45, bD: 1.45, cE: 0, cD: 0 } });
                // o "corpo" em volta do palito: os órgãos ficam dentro; a corrente corre pela borda
                const tx = p.ombro[0], topo = p.ombro[1] - 10, base = p.quadril[1] + 10, larg = 95;
                g.save(); g.fillStyle = 'rgba(214,60,60,.12)'; g.strokeStyle = 'rgba(255,154,154,.5)'; g.lineWidth = 4; g.setLineDash([14, 10]);
                g.beginPath(); g.roundRect(tx - larg, topo, larg * 2, base - topo, 60); g.fill(); g.stroke(); g.restore();
                coracao(p.coracao[0], p.coracao[1] + 20, 80); proibido(p.coracao[0], p.coracao[1] + 20, 66);
                txt('ÓRGÃOS', tx, base - 50, 34, 'rgba(255,154,154,.85)');
                caminho([p.peE, [p.quadril[0] - larg, base], [tx - larg, topo + 40], [tx - larg + 30, topo], [tx + larg, topo], p.maoD], fase(k, .1, .85), AZUL, 18);
                bate('PÉ → MÃO OPOSTA', CX, 300, 76, fase(k, .45, .65), AZUL, 'Inter', 0);
            },
            erro(k, lt) {
                chao();
                const cai = fase(k, .55, .75);
                const p = boneco(lerp(CX, 250, ease(cai)), CHAO, 1.9, { pose: cai > 0 ? 'parado' : misturar(POSES.poder, POSES.cima, Math.abs(Math.sin(lt * 26))), olhos: cai > 0 ? 'x' : 'ok', arrepiado: true, queda: Math.PI / 2 * ease(cai), brilho: cai > 0 ? null : '#ff6b6b' });
                if (cai === 0 && k > .1) { raio(p.peE[0], p.peE[1], p.coracao[0], p.coracao[1], Math.floor(lt * 18), '#ffd0d0', 10); tremor = 14; }
                coracao(p.coracao[0], p.coracao[1], 70 * (1 + Math.sin(lt * 20) * .15 * (1 - cai)));
                clarao(fase(k, .1, .2), '255,80,80');
                bate('CORAÇÃO.', CX, 320, 120, fase(k, .5, .7), RED);
            },
            combo(k, lt) {
                // cada palavra da declaração entra quando é dita
                [['IMBUIR', .22, AZUL], ['FOCALIZAR', .42, GOLD], ['REVERTER', .62, '#ff9a9a'], ['NA CABEÇA', .8, INK]].forEach(([s, t, cor], i) => {
                    chip(s, CX, 470 + i * 170, fase(k, t, t + .1), cor, 600);
                    if (i) bate('+', CX - 330, 385 + i * 170, 70, fase(k, t, t + .1), GOLD, 'Inter', 0);
                });
                bate('DECLARE:', CX, 300, 90, fase(k, 0, .15), GOLD, 'Cinzel', 0);
                if (k > .9) lampada(860, 300, 1, lt);
            },
            cerebro(k, lt) {
                chao();
                const toca = fase(k, .1, .25), apaga = fase(k, .55, .75);
                const a = boneco(330, CHAO, 1.4, { pose: misturar(POSES.parado, POSES.tocarAlto, toca), brilho: '#5cbcff' });
                const b = boneco(560, CHAO, 1.3, { pose: 'parado', olhar: -1, olhos: apaga > 0 ? 'fechados' : 'ok', queda: Math.PI / 2 * ease(apaga), arrepiado: toca >= 1 && apaga === 0 });
                // reverso: a eletricidade sai da cabeça dele, não entra
                if (toca >= 1 && apaga === 0) { for (let i = 0; i < 4; i++) raio(b.cabeca[0], b.cabeca[1], b.cabeca[0] + (i - 1.5) * 150, b.cabeca[1] - 240, Math.floor(lt * 22) + i + 4, '#ffd0ff', 6); tremor = 10; }
                if (apaga > .5) for (let i = 0; i < 3; i++) txt('z', b.cabeca[0] + 40 + i * 40, b.cabeca[1] - 80 - i * 50 - ((lt * 60) % 30), 60 + i * 16, AZUL);
                bate('APAGOU.', CX, 480, 120, fase(k, .7, .85), INK);
            },
            fim(k, lt) {
                if (Math.sin(lt * 9) > .3) raio(220, 260, 860, 640, Math.floor(lt * 8));
                bate('?', CX, 600, 260, fase(k, 0, .25), GOLD, 'Cinzel', 0);
                bate('QUAL LÓGICA?', CX, 840, 96, fase(k, .2, .5), INK);
                bate('PRAGA VERMELHA', CX, 990, 84, fase(k, .55, .85), RED);
            },
        },
        fisiomancia: {
            titulo: 'Fisiomancia',
            ancora(k, lt) {
                chao();
                const queima = fase(k, .15, .55), vira = fase(k, .5, .8);
                if (queima < 1) { ancora(800, CHAO - 120, 1.1 * (1 - queima * .7)); fogo(800, CHAO - 40, 1.1, lt, clamp(queima * 3)); }
                const p = boneco(330, CHAO, 1.5, { pose: misturar(POSES.tocar, POSES.pesado, ease(vira)), cor: vira > 0 ? `rgb(${Math.round(lerp(245, 160, vira))},${Math.round(lerp(236, 160, vira))},${Math.round(lerp(236, 170, vira))})` : INK, brilho: queima > 0 && vira < 1 ? '#ff9a3a' : null });
                if (queima > 0 && queima < 1) particulas([800, CHAO - 150], p.ombro, lt);
                if (vira > .6) { g.save(); g.strokeStyle = 'rgba(233,194,122,.7)'; g.lineWidth = 6; g.beginPath(); for (let i = 0; i < 5; i++) { g.moveTo(330 + (i - 2) * 30, CHAO + 8); g.lineTo(330 + (i - 2) * 90, CHAO + 70 + (i % 2) * 30); } g.stroke(); g.restore(); tremor = vira < .8 ? 12 : 0; }
                bate('VIROU UMA ÂNCORA.', CX, 400, 84, fase(k, .65, .85), GOLD, 'Cinzel');
            },
            nome(k) {
                bate('IMBUIR', CX, 700, 150, fase(k, 0, .3));
                bate('PROPRIEDADES', CX, 860, 110, fase(k, .15, .45), GOLD);
                bate('fisiomancia', CX, 1010, 64, fase(k, .45, .7), RED, 'Inter', 0);
            },
            fogo(k, lt) {
                chao();
                cubo(280, CHAO - 120, 1.1, '#cfc8bd'); fogo(280, CHAO - 30, 1.2, lt);
                const p = boneco(780, CHAO, 1.5, { pose: 'cima', olhar: -1, brilho: corFogo(lt, 1) });
                particulas([300, CHAO - 180], p.ombro, lt, 26);
                bate('LARANJA → BRANCO', CX, 380, 70, fase(k, .3, .5), corFogo(lt, 1), 'Inter', 0);
            },
            metal(k, lt) {
                chao();
                const golpe = fase(k, .3, .5);
                const p = boneco(430, CHAO, 1.6, { pose: 'parado', cor: '#b9c2cc', brilho: '#dfe8f2' });
                const ang = lerp(-1.3, .2, ease(golpe));
                g.save(); g.translate(800, CHAO - 300); g.rotate(ang); g.strokeStyle = '#e6e6e6'; g.lineWidth = 12; g.lineCap = 'round'; g.beginPath(); g.moveTo(0, 0); g.lineTo(-300, 0); g.stroke(); g.strokeStyle = GOLD; g.beginPath(); g.moveTo(10, -30); g.lineTo(10, 30); g.stroke(); g.restore();
                if (golpe >= 1) { for (let i = 0; i < 8; i++) { const a = i / 8 * Math.PI * 2 + lt; g.strokeStyle = '#ffe9a8'; g.lineWidth = 6; g.beginPath(); g.moveTo(p.ombroD[0] + 20, p.ombroD[1]); g.lineTo(p.ombroD[0] + 20 + Math.cos(a) * 70, p.ombroD[1] + Math.sin(a) * 70); g.stroke(); } tremor = fase(k, .5, .6) < 1 ? 10 : 0; }
                bate('TINK!', 760, 520, 120, fase(k, .5, .65), '#dfe8f2', 'Inter', .1);
                chip('DURO 5', CX, 330, fase(k, 0, .2), 'rgba(185,194,204,.8)', 420);
            },
            borracha(k, lt) {
                chao();
                const p = boneco(CX, CHAO, 1.7, { pose: 'defesa', cor: '#f2a65a' });
                const r = fase(k, .2, .6);
                if (r > 0 && r < 1) { raio(CX + 40, 220, p.cabeca[0], p.cabeca[1] - 50, Math.floor(lt * 20)); raio(p.cabeca[0] - 30, p.cabeca[1] - 50, 120, 400, Math.floor(lt * 20) + 2, '#ffe0a0', 6); }
                bate('IMUNE!', CX, 420, 140, fase(k, .55, .75), '#f2a65a');
            },
            ar(k, lt) {
                chao();
                const some = ease(fase(k, .15, .65));
                boneco(CX, CHAO, 1.7, { pose: 'parado', alpha: lerp(1, .08, some), cor: '#bfe3ff' });
                g.save(); g.strokeStyle = 'rgba(191,227,255,.6)'; g.lineWidth = 6; g.lineCap = 'round';
                for (let i = 0; i < 5; i++) { const y = 700 + i * 110, x = ((lt * 500 + i * 230) % 1400) - 200; g.beginPath(); g.moveTo(x, y); g.lineTo(x + 220, y); g.stroke(); }
                g.restore();
                bate('INVISÍVEL', CX, 420, 120, fase(k, .5, .7), '#bfe3ff');
            },
            correntes(k, lt) {
                chao();
                const luta = Math.sin(lt * 18) * .12 * clamp(k * 3);
                const p = boneco(CX, CHAO, 1.7, { pose: { ...POSES.preso, bE: POSES.preso.bE + luta, bD: POSES.preso.bD - luta } });
                g.save(); g.strokeStyle = '#d9d4cc'; g.lineWidth = 8;
                for (let i = 0; i < 9; i++) { const y = p.ombro[1] + 20 + i * 34; const x = CX + Math.sin(i * 1.3) * 20; g.beginPath(); g.ellipse(x, y, 70, 20, (i % 2 ? .3 : -.3), 0, 7); g.stroke(); }
                g.restore();
                chip('RESILIÊNCIA?', CX, 380, fase(k, .3, .5), BLOOD, 620);
                bate('FALHOU: PERDE A AÇÃO', CX, 540, 64, fase(k, .65, .85), RED, 'Inter', 0);
            },
            fim(k, lt) {
                fogo(CX, 760, 1.3, lt, .9);
                chip('8 AURA · NÍVEL 3', CX, 900, fase(k, 0, .3), BLOOD, 700);
                bate('PRAGA VERMELHA', CX, 1080, 92, fase(k, .4, .8), RED);
            },
        },
        cavaleiro: {
            titulo: 'O Cavaleiro Negro',
            capa: ['lanca', .5],
            pergunta(k, lt) {
                infinito(CX, 760, 260, fase(k, 0, .45), lt);
                bate('NÃO MORRE.', CX, 1040, 96, fase(k, .12, .32), INK);
                bate('NÃO DESCANSA.', CX, 1170, 96, fase(k, .62, .82), RED);
            },
            forte(k, lt) {
                noite(lt); chao();
                muralha(lt, 0); // o muro fica atrás: os soldados estão dentro do forte
                fogo(560, CHAO - 10, .55, lt, clamp(k * 4));
                [[330, 0], [470, 1.7], [700, .9]].forEach(([x, fs], i) => {
                    const pula = Math.abs(Math.sin(lt * 5 + fs)) * 24;
                    boneco(x, CHAO - pula, 1.05, { pose: misturar(POSES.cima, POSES.parado, (Math.sin(lt * 5 + fs) + 1) / 2), olhar: i === 2 ? -1 : 1 });
                });
                bate('ACABOU A GUERRA?', CX, 420, 88, fase(k, .62, .82), INK);
            },
            muro(k, lt) {
                noite(lt); chao();
                const explode = fase(k, .12, .3), entra = fase(k, .35, .75);
                muralha(lt, explode);
                if (explode > 0 && explode < 1) { clarao(explode, '255,200,150'); tremor = 16; }
                if (explode > 0) pedras(330, CHAO - 180, explode, lt);
                if (entra > 0) krauzher(lerp(170, 420, ease(entra)), CHAO, 1.45, { pose: entra < 1 ? andando(lt) : 'parado', alpha: clamp(entra * 3) });
                bate('NÃO ACABOU.', 700, 440, 110, fase(k, .05, .25), GOLD, 'Inter', .06);
                bate('UM HOMEM SÓ.', CX, 600, 92, fase(k, .62, .82), '#eef4ff');
            },
            lanca(k, lt) {
                noite(lt); chao();
                const k1 = krauzher(380, CHAO, 1.5, { pose: 'parado', olhar: 1 });
                const estoca = fase(k, .05, .3), amassa = fase(k, .3, .45), quebra = fase(k, .62, .8);
                const s = boneco(lerp(900, 760, ease(estoca)), CHAO, 1.35, { pose: { ...POSES.tocar, bD: -1.5, cD: 0 }, olhar: -1, arrepiado: amassa > 0, olhos: quebra > .5 ? 'x' : 'ok' });
                lancaDe(s.maoE, k1.coracao, amassa, quebra, lt);
                if (amassa > 0 && amassa < 1) { faiscas(k1.coracao[0] + 30, k1.coracao[1], lt); tremor = 10; }
                bate('NO PEITO.', CX, 380, 90, fase(k, .3, .45), '#dfe8f2', 'Inter', 0);
                bate('A LANÇA QUEBROU.', CX, 520, 80, fase(k, .62, .78), RED, 'Inter', 0);
            },
            flechas(k, lt) {
                noite(lt); chao();
                const kz = krauzher(330, CHAO, 1.5, { pose: 'parado' });
                const foge = fase(k, 0, .9);
                [720, 880].forEach((x, i) => {
                    const b = boneco(x + ease(foge) * 260 * (i + 1) * .6, CHAO, 1.05, { pose: andando(lt * 1.4 + i), olhar: -1, arrepiado: true });
                    if (i === 0) balao(b.cabeca[0] - 60, b.cabeca[1] - 180, 'GENERAL!', fase(k, .02, .2));
                });
                for (let i = 0; i < 4; i++) {
                    const p = ((lt * 1.3 + i / 4) % 1), alvoY = kz.ombro[1] + 40 + i * 50;
                    if (p < .7) seta(lerp(1040, kz.ombro[0] + 40, p / .7), lerp(alvoY - 90, alvoY, p / .7), 0);
                    else { const q = (p - .7) / .3; seta(kz.ombro[0] + 40 + q * 160, alvoY - q * 220, -2.4 - q * 2); if (q < .25) faiscas(kz.ombro[0] + 40, alvoY, lt); }
                }
                bate('CLANG!', 700, 560, 120, fase(k, .45, .6), '#dfe8f2', 'Inter', .08);
            },
            aco(k, lt) {
                noite(lt, .5); chao();
                const vira = fase(k, 0, .5);
                krauzher(CX, CHAO, 1.6, { pose: 'poder', metal: vira, brilho: vira > .2 ? '#dfe8f2' : null });
                [['PELE', .05], ['PULMÕES', .2], ['MÚSCULOS', .32], ['OSSOS', .44]].forEach(([s, t], i) => chip(s, i % 2 ? 790 : 290, 300 + Math.floor(i / 2) * 140, fase(k, t, t + .1), 'rgba(185,194,204,.8)', 380));
                bate('KRAUZHER', CX, 590, 104, fase(k, .62, .78), GOLD);
                bate('O GENERAL DE AÇO', CX, 685, 56, fase(k, .75, .9), INK, 'Inter', 0);
            },
            paz(k, lt) {
                noite(lt, .3); chao();
                casa(250, CHAO, fase(k, 0, .3));
                boneco(240, CHAO, .9, { pose: 'parado', alpha: fase(k, .1, .3) });
                boneco(330, CHAO, .55, { pose: 'cima', alpha: fase(k, .15, .35) });
                const vai = fase(k, .5, .9);
                krauzher(lerp(560, 860, ease(vai)), CHAO, 1.45, { pose: vai > 0 && vai < 1 ? andando(lt) : 'parado', olhar: -1, olhos: 'fechados', metal: 1 });
                bate('UMA FILHA EM CASA.', CX, 380, 70, fase(k, .05, .25), GOLD, 'Inter', 0);
                bate('SEM PAZ.', CX, 520, 110, fase(k, .62, .8), RED);
            },
            fim(k, lt) {
                noite(lt, .4);
                bate('PRAGA VERMELHA', CX, 700, 96, fase(k, 0, .35), RED);
                bate('O CAVALEIRO NEGRO', CX, 860, 78, fase(k, .3, .65), INK);
                bate('o livro · link na bio', CX, 1000, 48, fase(k, .6, .85), GOLD, 'Inter', 0);
            },
        },
    };

    /* ---------- peças da história "O Cavaleiro Negro" ---------- */
    /** Noite de chuva por cima do fundo vermelho. */
    function noite(tt, chuvaForca = 1) {
        g.fillStyle = 'rgba(4,6,14,.55)'; g.fillRect(0, 0, W, H);
        g.save(); g.strokeStyle = `rgba(170,190,230,${.35 * chuvaForca})`; g.lineWidth = 3;
        for (let i = 0; i < 70; i++) { const x = (i * 157 + tt * 260) % (W + 200) - 100, y = (i * 263 + tt * 1500) % (H + 200) - 100; g.beginPath(); g.moveTo(x, y); g.lineTo(x - 14, y + 46); g.stroke(); }
        g.restore();
    }
    /** Muro de pedra do forte; `rombo` (0→1) abre um buraco no meio. */
    function muralha(tt, rombo) {
        g.save();
        for (let lin = 0; lin < 6; lin++) for (let col = 0; col < 11; col++) {
            const x = 40 + col * 92 + (lin % 2) * 46, y = CHAO - 60 - lin * 52, dx = x - 330, dy = y - (CHAO - 180);
            if (rombo > 0 && Math.hypot(dx, dy) < 210) continue;
            if (x < 40 || x > W - 120) continue;
            caixa(x, y, 88, 48, 6, '#3b3534', '#24201f', 3);
        }
        g.restore();
    }
    function pedras(x, y, k, tt) {
        g.save(); g.fillStyle = '#5a504c';
        for (let i = 0; i < 16; i++) { const a = i / 16 * Math.PI * 2, d = ease(k) * (200 + (i % 5) * 60); g.fillRect(x + Math.cos(a) * d, y + Math.sin(a) * d * .6 + k * k * 300, 26, 22); }
        g.restore();
    }
    /** Krauzher: boneco cor de neve com cabelo cor de noite; `metal` 0→1 deixa a pele cor de aço. */
    function krauzher(x, y, s, o = {}) {
        const m = o.metal || 0;
        const cor = `rgb(${Math.round(lerp(244, 175, m))},${Math.round(lerp(247, 186, m))},${Math.round(lerp(251, 199, m))})`;
        const p = boneco(x, y, s, { ...o, cor, brilho: o.brilho || '#cfe2ff' });
        g.save(); g.globalAlpha = o.alpha ?? 1; g.fillStyle = '#0d0f1a';
        const [hx, hy] = p.cabeca, r = 44 * s;
        g.beginPath(); g.arc(hx, hy, r + 8 * s, Math.PI * 1.12, Math.PI * 1.88); g.lineTo(hx + r * .55, hy - r * .55); g.quadraticCurveTo(hx, hy - r * .38, hx - r * .55, hy - r * .55); g.closePath(); g.fill();
        g.restore();
        return p;
    }
    /** Lança da mão do soldado até o peito: a ponta amassa (`amassa`) e o cabo quebra (`quebra`). */
    function lancaDe(de, alvo, amassa, quebra, tt) {
        g.save(); g.lineCap = 'round';
        const meio = [lerp(de[0], alvo[0], .5), lerp(de[1], alvo[1], .5)];
        if (quebra <= 0) {
            g.strokeStyle = '#8a6a43'; g.lineWidth = 12; g.beginPath(); g.moveTo(de[0] + 60, de[1]); g.lineTo(alvo[0] + 40, alvo[1]); g.stroke();
        } else { // dois pedaços caindo
            const cai = ease(quebra);
            g.strokeStyle = '#8a6a43'; g.lineWidth = 12;
            g.save(); g.translate(meio[0], meio[1] + cai * 280); g.rotate(cai * 1.4); g.beginPath(); g.moveTo(-90, 0); g.lineTo(90, 0); g.stroke(); g.restore();
            g.save(); g.translate(alvo[0] + 90, alvo[1] + cai * 320); g.rotate(-cai * 2); g.beginPath(); g.moveTo(-70, 0); g.lineTo(60, 0); g.stroke(); g.restore();
        }
        // ponta de aço: amassa contra o peito
        const px = quebra > 0 ? alvo[0] + 40 : alvo[0] + 40, py = quebra > 0 ? alvo[1] + ease(quebra) * 340 : alvo[1];
        g.fillStyle = '#d6dbe2'; g.beginPath();
        const dobra = amassa * 26;
        g.moveTo(px, py - 16); g.lineTo(px - 46 + dobra, py - dobra * .6); g.lineTo(px - 30 + dobra * .4, py + 4 + dobra); g.lineTo(px, py + 16); g.closePath(); g.fill();
        g.restore();
    }
    function faiscas(x, y, tt) {
        g.save(); g.strokeStyle = '#ffe9a8'; g.lineWidth = 5; g.lineCap = 'round';
        for (let i = 0; i < 9; i++) { const a = i / 9 * Math.PI * 2 + tt * 7, r1 = 18, r2 = 60 + (i % 3) * 18; g.beginPath(); g.moveTo(x + Math.cos(a) * r1, y + Math.sin(a) * r1); g.lineTo(x + Math.cos(a) * r2, y + Math.sin(a) * r2); g.stroke(); }
        g.restore();
    }
    function seta(x, y, ang) {
        g.save(); g.translate(x, y); g.rotate(ang); g.strokeStyle = '#c9b48a'; g.lineWidth = 6; g.lineCap = 'round';
        g.beginPath(); g.moveTo(0, 0); g.lineTo(110, 0); g.stroke();
        g.fillStyle = '#d6dbe2'; g.beginPath(); g.moveTo(-22, 0); g.lineTo(4, -10); g.lineTo(4, 10); g.closePath(); g.fill();
        g.strokeStyle = '#d63c3c'; g.lineWidth = 4; g.beginPath(); g.moveTo(96, -12); g.lineTo(112, 0); g.lineTo(96, 12); g.stroke();
        g.restore();
    }
    function casa(x, y, k) {
        if (k <= 0) return;
        g.save(); g.globalAlpha = clamp(k * 2); g.strokeStyle = GOLD; g.lineWidth = 8; g.lineJoin = 'round'; g.shadowColor = GOLD; g.shadowBlur = 30;
        g.beginPath(); g.moveTo(x - 190, y); g.lineTo(x - 190, y - 300); g.lineTo(x, y - 450); g.lineTo(x + 190, y - 300); g.lineTo(x + 190, y); g.stroke();
        g.fillStyle = 'rgba(233,194,122,.35)'; g.fillRect(x + 60, y - 250, 80, 80);
        g.restore();
    }
    /** Símbolo do infinito desenhado aos poucos (k 0→1). */
    function infinito(x, y, r, k, tt) {
        if (k <= 0) return;
        g.save(); g.strokeStyle = GOLD; g.lineWidth = 18; g.lineCap = 'round'; g.shadowColor = GOLD; g.shadowBlur = 40;
        g.beginPath(); const n = Math.floor(200 * clamp(k));
        for (let i = 0; i <= n; i++) { const t = i / 200 * Math.PI * 2, d = 1 + Math.sin(t) ** 2; const px = x + r * Math.cos(t) / d, py = y + r * Math.sin(t) * Math.cos(t) / d; g[i ? 'lineTo' : 'moveTo'](px, py); }
        g.stroke(); g.restore();
    }

    /* ---------- desenho de um instante ---------- */
    const D = CENAS[VIDEO];
    function quadro(tt) {
        tremor = 0;
        const c = cenas.find((x, i) => tt >= x.t0 && (i === cenas.length - 1 || tt < cenas[i + 1].t0)) || cenas[0];
        const lt = Math.max(0, tt - c.t0), k = clamp(lt / c.dur);
        fundo(tt);
        g.save(); D[c.cena](k, lt); g.restore();
        if (tremor) { // impacto: a tela treme
            const img = g.getImageData(0, 0, W, H); fundo(tt);
            const tmp = quadro.tmp || (quadro.tmp = Object.assign(document.createElement('canvas'), { width: W, height: H }));
            tmp.getContext('2d').putImageData(img, 0, 0);
            g.drawImage(tmp, Math.sin(tt * 90) * tremor, Math.cos(tt * 77) * tremor);
        }
        txt('PRAGA VERMELHA RPG', CX, 110, 34, RED, 800, 'Cinzel');
        txt(D.titulo, CX, 165, 32, 'rgba(245,236,236,.6)', 600);
        if (tt < c.t0 + c.dur + .1) legenda(c, lt);
        caixa(60, 40, 960, 8, 4, 'rgba(255,255,255,.12)'); caixa(60, 40, 960 * clamp(tt / TOTAL), 8, 4, RED);
    }

    /* ---------- áudio ---------- */
    const ac = new AudioContext();
    const buffers = await Promise.all(cenas.map(async c => ac.decodeAudioData(await (await fetch('/vid/' + c.audio)).arrayBuffer())));
    // a duração real do áudio manda (o linha.json da voz neural é estimado pelo tamanho do mp3)
    t = INICIO; cenas.forEach((c, i) => { c.dur = buffers[i].duration; c.t0 = t; t += c.dur + GAP; }); TOTAL = t + FIM_EXTRA;
    function tocar(destino) {
        const t0 = ac.currentTime + .15;
        cenas.forEach((c, i) => { const s = ac.createBufferSource(); s.buffer = buffers[i]; s.connect(destino); s.start(t0 + c.t0); });
        return t0;
    }
    const st = document.getElementById('st');
    let rodando = null;
    function animar(t0) {
        const passo = () => { const tt = ac.currentTime - t0; quadro(Math.max(0, tt)); st.textContent = `${tt.toFixed(1)} / ${TOTAL.toFixed(1)} s`; if (tt < TOTAL) rodando = setTimeout(passo, 1000 / 60); };
        passo();
    }
    quadro(1.2);
    window.__quadro = quadro; window.__total = TOTAL; window.__cenas = cenas;
    document.getElementById('ver').onclick = async () => { await ac.resume(); clearTimeout(rodando); animar(tocar(ac.destination)); };

    /* ---------- exportação quadro a quadro: MP4 normal (H.264 + AAC), com duração certa e busca ---------- */
    window.__exportar = async (fps = 30, bitrate = 3000000) => {
        const SR = 48000, N = Math.ceil(TOTAL * fps);
        const muxer = new Mp4Muxer.Muxer({ target: new Mp4Muxer.ArrayBufferTarget(), fastStart: 'in-memory',
            video: { codec: 'avc', width: W, height: H, frameRate: fps }, audio: { codec: 'aac', numberOfChannels: 1, sampleRate: SR } });
        let erro = null;
        const ve = new VideoEncoder({ output: (c, m) => muxer.addVideoChunk(c, m), error: e => { erro = e; } });
        ve.configure({ codec: 'avc1.640028', width: W, height: H, bitrate, framerate: fps, avc: { format: 'avc' } });
        const ae = new AudioEncoder({ output: (c, m) => muxer.addAudioChunk(c, m), error: e => { erro = e; } });
        ae.configure({ codec: 'mp4a.40.2', sampleRate: SR, numberOfChannels: 1, bitrate: 128000 });
        const off = new OfflineAudioContext(1, Math.ceil(TOTAL * SR), SR);
        cenas.forEach((c, i) => { const src = off.createBufferSource(); src.buffer = buffers[i]; src.connect(off.destination); src.start(c.t0); });
        const pcm = (await off.startRendering()).getChannelData(0);
        for (let i = 0; i < pcm.length; i += 1024) {
            const pedaco = pcm.slice(i, Math.min(i + 1024, pcm.length));
            ae.encode(new AudioData({ format: 'f32-planar', sampleRate: SR, numberOfFrames: pedaco.length, numberOfChannels: 1, timestamp: Math.round(i / SR * 1e6), data: pedaco }));
        }
        for (let i = 0; i < N; i++) {
            quadro(i / fps);
            const vf = new VideoFrame(cv, { timestamp: Math.round(i * 1e6 / fps), duration: Math.round(1e6 / fps) });
            ve.encode(vf, { keyFrame: i % (fps * 2) === 0 });
            vf.close();
            if (ve.encodeQueueSize > 8) await new Promise(r => setTimeout(r, 5));
            if (i % 60 === 0) st.textContent = 'exportando ' + Math.round(i / N * 100) + '%';
            if (erro) throw erro;
        }
        await ve.flush(); await ae.flush();
        muxer.finalize();
        const buf = muxer.target.buffer;
        await fetch('/salvar?nome=' + VIDEO + '.mp4', { method: 'POST', body: buf });
        const cc = (D.capa && cenas.find(c => c.cena === D.capa[0])) || cenas[0];
        quadro(cc.t0 + cc.dur * (D.capa ? D.capa[1] : .8)); // capa: o gancho no auge (ou a cena escolhida em D.capa)
        await fetch('/salvar?nome=' + VIDEO + '.jpg', { method: 'POST', body: await new Promise(r => cv.toBlob(r, 'image/jpeg', .86)) });
        st.textContent = 'pronto: ' + (buf.byteLength / 1e6).toFixed(1) + ' MB';
        return buf.byteLength;
    };
    document.getElementById('gravar').onclick = () => window.__exportar();
})();
