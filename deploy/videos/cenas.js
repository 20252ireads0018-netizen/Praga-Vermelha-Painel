/* Vídeos explicativos do Praga Vermelha: animação vertical 1080×1920 sincronizada com a narração (linha.json). */
(async function () {
    const qs = new URLSearchParams(location.search);
    const VIDEO = qs.get('v') || 'eletrico';
    const L = await (await fetch('/vid/linha.json')).json();
    const cenas = L[VIDEO];
    const GAP = 0.35, INICIO = 0.6, FIM_EXTRA = 1.4;
    let t = INICIO;
    cenas.forEach(c => { c.t0 = t; t += c.dur + GAP; });
    const TOTAL = t + FIM_EXTRA;

    const cv = document.getElementById('c'), g = cv.getContext('2d');
    const W = 1080, H = 1920, CX = W / 2;
    await document.fonts.load('800 80px Cinzel'); await document.fonts.load('800 60px Inter'); await document.fonts.load('600 40px Inter');

    /* ---------- utilitários ---------- */
    const lerp = (a, b, k) => a + (b - a) * k;
    const clamp = (x, a = 0, b = 1) => Math.max(a, Math.min(b, x));
    const ease = k => 1 - Math.pow(1 - clamp(k), 3);
    const pop = k => { k = clamp(k); return k < .7 ? ease(k / .7) * 1.08 : lerp(1.08, 1, (k - .7) / .3); };
    const RED = '#d63c3c', BLOOD = '#a51e1e', GOLD = '#e9c27a', INK = '#f5ecec', DIM = 'rgba(245,236,236,.65)';
    function txt(s, x, y, size, cor = INK, peso = 800, fonte = 'Inter', alinha = 'center', sombra = true) {
        g.save(); g.font = `${peso} ${size}px ${fonte}`; g.textAlign = alinha; g.textBaseline = 'middle'; g.fillStyle = cor;
        if (sombra) { g.shadowColor = 'rgba(0,0,0,.85)'; g.shadowBlur = 18; }
        g.fillText(s, x, y); g.restore();
    }
    function caixa(x, y, w, h, r, fill, stroke, lw = 3) {
        g.beginPath(); g.roundRect(x, y, w, h, r);
        if (fill) { g.fillStyle = fill; g.fill(); }
        if (stroke) { g.strokeStyle = stroke; g.lineWidth = lw; g.stroke(); }
    }
    function icone(d, x, y, s, cor = INK, lw = 1.8, fill = false) { // caminhos 24×24 (mesmo traço dos ícones do site)
        g.save(); g.translate(x - s / 2, y - s / 2); g.scale(s / 24, s / 24);
        g.lineWidth = lw; g.strokeStyle = cor; g.fillStyle = cor; g.lineCap = 'round'; g.lineJoin = 'round';
        [].concat(d).forEach(p => { const P = new Path2D(p); fill ? g.fill(P) : g.stroke(P); }); g.restore();
    }
    const I = {
        raio: 'M13 2.5 4.5 14h7l-1 7.5L19 10h-7Z',
        coracao: 'M12 20s-8-4.7-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6.3-8 11-8 11Z',
        escudo: ['M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6Z', 'M12 7v10M8 11h8'],
        dado: ['M12 2.5 20.5 7.5v9L12 21.5 3.5 16.5v-9Z', 'M12 2.5 7.5 10h9ZM3.5 7.5 7.5 10 12 17l4.5-7 4-2.5M7.5 10l-4 6.5M16.5 10l4 6.5M12 17v4.5'],
        tomada: ['M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z', 'M9 9v3M15 9v3M9.5 16h5'],
        fio: ['M3 18c4 0 4-12 9-12s5 12 9 12', 'M3 18h-1M22 18h-1'],
        luva: ['M7 21v-6L4 11c-1-1 0-3 2-2l2 2V5a1.5 1.5 0 0 1 3 0v5V3.5a1.5 1.5 0 0 1 3 0V10V5a1.5 1.5 0 0 1 3 0v7c0 5-2 9-6 9Z'],
        bota: ['M8 3h5v10l6 3c2 1 2 3 2 5H4V3Z', 'M4 17h17'],
        cerebro: ['M12 4a3 3 0 0 0-5 1 3 3 0 0 0-3 4 3 3 0 0 0 0 5 3 3 0 0 0 3 4 3 3 0 0 0 5 1Z', 'M12 4a3 3 0 0 1 5 1 3 3 0 0 1 3 4 3 3 0 0 1 0 5 3 3 0 0 1-3 4 3 3 0 0 1-5 1Z', 'M12 4v16'],
        ancora: ['M12 7v14M5 13a7 7 0 0 0 14 0M3 13h4M17 13h4', 'M12 2.5a2.25 2.25 0 1 1 0 4.5 2.25 2.25 0 0 1 0-4.5Z', 'M9 10h6'],
        corrente: ['M9 7H7a5 5 0 0 0 0 10h2', 'M15 7h2a5 5 0 0 1 0 10h-2', 'M8 12h8'],
        vento: ['M3 8h11a3 3 0 1 0-3-3', 'M3 12h16a3 3 0 1 1-3 3', 'M3 16h8'],
        bloco: ['M12 2 21 7v10l-9 5-9-5V7Z', 'M12 12 21 7M12 12 3 7M12 12v10'],
        olho: ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z', 'M12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z'],
        alvo: ['M12 4a8 8 0 1 1 0 16 8 8 0 0 1 0-16Z', 'M12 8.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7Z', 'M12 1.5v4M12 18.5v4M1.5 12h4M18.5 12h4'],
        chama: ['M12 22c4 0 7-3 7-7 0-5-5-7-5-12-3 2-4 5-4 7-1-1-2-2-2-4-2 2-3 5-3 8 0 4 3 8 7 8Z'],
    };
    function raioZig(x1, y1, x2, y2, seed, cor = '#bfe3ff', lw = 6) { // um relâmpago irregular
        let r = seed; const rnd = () => (r = (r * 9301 + 49297) % 233280) / 233280;
        g.save(); g.strokeStyle = cor; g.lineWidth = lw; g.shadowColor = '#7cc7ff'; g.shadowBlur = 30; g.lineJoin = 'round';
        g.beginPath(); g.moveTo(x1, y1); const n = 9;
        for (let i = 1; i < n; i++) { const k = i / n; g.lineTo(lerp(x1, x2, k) + (rnd() - .5) * 90, lerp(y1, y2, k) + (rnd() - .5) * 40); }
        g.lineTo(x2, y2); g.stroke(); g.lineWidth = lw / 3; g.strokeStyle = '#fff'; g.stroke(); g.restore();
    }
    function chip(texto, x, y, k, cor = BLOOD, w = 760) {
        const s = pop(k); if (s <= 0) return;
        g.save(); g.translate(x, y); g.scale(s, s); g.globalAlpha = clamp(k * 3);
        caixa(-w / 2, -58, w, 116, 58, 'rgba(12,6,8,.85)', cor, 4);
        txt(texto, 0, 2, 50, INK, 800); g.restore();
    }
    function cartao(x, y, w, h, k, titulo, icn, linhas, corIcn = GOLD) {
        h = 260 + linhas.length * 150; y = 300 + h / 2 + 40; // começa logo abaixo do título e nunca desce até a legenda
        const s = pop(k); if (s <= 0) return;
        g.save(); g.translate(x, y); g.scale(s, s); g.globalAlpha = clamp(k * 2.5);
        caixa(-w / 2, -h / 2, w, h, 36, 'rgba(20,8,10,.92)', 'rgba(233,194,122,.55)', 4);
        icone(icn, -w / 2 + 110, -h / 2 + 110, 120, corIcn, 1.6);
        txt(titulo, -w / 2 + 210, -h / 2 + 110, 70, GOLD, 800, 'Cinzel', 'left');
        linhas.forEach((l, i) => {
            const kk = clamp((k - .25 - i * .18) * 3); if (!kk) return;
            g.save(); g.globalAlpha = kk;
            if (l[0]) { caixa(-w / 2 + 40, -h / 2 + 230 + i * 150, 260, 90, 45, BLOOD, null); txt(l[0], -w / 2 + 170, -h / 2 + 276 + i * 150, 38, '#fff', 800); }
            txt(l[1], -w / 2 + (l[0] ? 325 : 60), -h / 2 + 276 + i * 150, 42, INK, 600, 'Inter', 'left');
            g.restore();
        });
        g.restore();
    }
    function corpo(x, y, s, brilho = 0) { // silhueta humana estilizada (membros arredondados)
        const pele = `rgb(${Math.round(52 + brilho * 150)},${Math.round(24 + brilho * 90)},${Math.round(30 + brilho * 90)})`;
        g.save(); g.translate(x, y); g.scale(s, s); g.lineCap = 'round'; g.lineJoin = 'round';
        const membro = (pts, w) => {
            g.beginPath(); g.moveTo(...pts[0]); pts.slice(1).forEach(p => g.lineTo(...p));
            g.strokeStyle = 'rgba(233,194,122,.55)'; g.lineWidth = w + 7; g.stroke(); g.strokeStyle = pele; g.lineWidth = w; g.stroke();
        };
        membro([[-38, 60], [-52, 225], [-60, 380]], 50); membro([[38, 60], [52, 225], [60, 380]], 50);
        g.beginPath(); g.roundRect(-88, -205, 176, 290, 46); g.fillStyle = pele; g.fill(); g.strokeStyle = 'rgba(233,194,122,.55)'; g.lineWidth = 4; g.stroke();
        membro([[-82, -175], [-122, -40], [-150, 85]], 40); membro([[82, -175], [122, -40], [150, 85]], 40);
        g.beginPath(); g.arc(0, -275, 60, 0, Math.PI * 2); g.fillStyle = pele; g.fill(); g.strokeStyle = 'rgba(233,194,122,.55)'; g.lineWidth = 4; g.stroke();
        g.restore();
        const P = (a, b) => [x + a * s, y + b * s];
        return { peE: P(-60, 395), quadrilE: P(-90, 60), ladoE: P(-96, -120), ombroE: P(-82, -212), pescoco: P(0, -214), ombroD: P(82, -212),
            bracoD: P(128, -40), maoD: P(152, 100), maoE: P(-152, 100), coracao: P(-22, -110), cabeca: P(0, -275) };
    }
    function sigiloFundo(tt) {
        const grd = g.createRadialGradient(CX, H * .42, 50, CX, H * .42, 1300);
        grd.addColorStop(0, '#3a0b10'); grd.addColorStop(.55, '#140608'); grd.addColorStop(1, '#070304');
        g.fillStyle = grd; g.fillRect(0, 0, W, H);
        g.save(); g.translate(CX, H * .42); g.rotate(tt * .05); g.strokeStyle = 'rgba(214,60,60,.12)'; g.lineWidth = 3;
        [520, 470, 300].forEach(r => { g.beginPath(); g.arc(0, 0, r, 0, Math.PI * 2); g.stroke(); });
        for (let k = 0; k < 2; k++) { g.beginPath(); for (let i = 0; i < 4; i++) { const a = -Math.PI / 2 + k * Math.PI + i * Math.PI * 2 / 3; g[i ? 'lineTo' : 'moveTo'](Math.cos(a) * 470, Math.sin(a) * 470); } g.stroke(); }
        g.restore();
        for (let i = 0; i < 40; i++) { // brasas subindo
            const sx = (i * 263) % W, sp = 40 + (i * 37) % 60, y = H - ((tt * sp + i * 211) % (H + 200));
            g.fillStyle = `rgba(255,${120 + (i * 13) % 100},80,${.25 + (i % 5) * .08})`; g.beginPath(); g.arc(sx + Math.sin(tt + i) * 20, y, 2 + (i % 3), 0, 7); g.fill();
        }
    }
    function legenda(cena, lt) { // a fala, palavra por palavra
        const palavras = cena.fala.split(' '), feitas = Math.floor(clamp(lt / (cena.dur * .92)) * palavras.length + .999);
        g.save(); g.font = '800 54px Inter'; const linhas = []; let atual = [];
        palavras.forEach((p, i) => { const tent = atual.concat(p).map(x => x.w).join(' '); if (g.measureText(atual.map(x => x.w).concat(p).join(' ')).width > 920 && atual.length) { linhas.push(atual); atual = []; } atual.push({ w: p, i }); });
        if (atual.length) linhas.push(atual);
        const y0 = 1470;
        caixa(60, y0 - 70, 960, linhas.length * 76 + 70, 30, 'rgba(0,0,0,.55)', 'rgba(214,60,60,.35)', 2);
        linhas.forEach((ln, li) => {
            const larg = g.measureText(ln.map(x => x.w).join(' ')).width; let x = CX - larg / 2;
            ln.forEach(({ w, i }) => {
                const ww = g.measureText(w + ' ').width;
                g.fillStyle = i < feitas - 1 ? INK : (i === feitas - 1 ? GOLD : 'rgba(245,236,236,.35)');
                g.textBaseline = 'middle'; g.fillText(w, x, y0 + li * 76); x += ww;
            });
        });
        g.restore();
    }
    function cabecalho(titulo, k) {
        txt('PRAGA VERMELHA RPG', CX, 120, 36, RED, 800, 'Cinzel');
        g.save(); g.globalAlpha = clamp(k * 3); txt(titulo, CX, 185, 34, DIM, 600); g.restore();
    }

    /* ---------- cenas ---------- */
    const CENAS = {
        eletrico: {
            titulo: 'Atração Elétrica · Imbuir Maldição',
            abertura(k, lt) {
                for (let i = 0; i < 3; i++) if (Math.sin(lt * 9 + i * 2) > .2) raioZig(200 + i * 340, 280, 360 + i * 180, 760, Math.floor(lt * 8) + i * 7);
                icone(I.tomada, CX, 980, 300 * pop(k * 1.4), GOLD, 1.4);
                txt('ATRAÇÃO', CX, 1240, 110 * pop(k), INK, 800, 'Cinzel'); txt('ELÉTRICA', CX, 1360, 110 * pop(k - .1), RED, 800, 'Cinzel');
            },
            tecnica(k, lt) {
                g.save(); g.globalAlpha = clamp(k * 3); icone(I.raio, CX, 760, 340, GOLD, 1.5); g.restore();
                txt('IMBUIR', CX, 1080, 120 * pop(k), INK, 800, 'Cinzel'); txt('MALDIÇÃO', CX, 1210, 120 * pop(k - .1), RED, 800, 'Cinzel');
                chip('maldição comum · ameaça 0+', CX, 1360, k - .3, 'rgba(233,194,122,.6)');
            },
            custo(k) {
                chip('15 DE AURA', CX, 640, k * 1.6); chip('NÍVEL DE MALDIÇÃO 1', CX, 840, k * 1.6 - .5); chip('UMA FONTE ELÉTRICA', CX, 1040, k * 1.6 - 1);
                icone(I.tomada, CX, 1250, 160 * pop(k * 1.6 - 1.2), GOLD);
            },
            processo(k, lt) {
                chip('AÇÃO COMPLETA', CX, 560, k * 2);
                const rodar = clamp((k - .25) * 2); g.save(); g.translate(CX, 900); g.rotate(rodar < 1 ? lt * 6 : 0); icone(I.dado, 0, 0, 380 * pop(k * 2 - .3), GOLD, 1.3); g.restore();
                txt(rodar < 1 ? String(1 + Math.floor((lt * 23) % 30)) : 'D30', CX, 905, 90, INK, 800);
                chip('TESTE DE MALDIÇÃO (D30)', CX, 1260, k * 2 - 1);
            },
            caminho(k, lt) {
                const c = corpo(CX, 840, 1.15, .1);
                icone(I.coracao, c.coracao[0], c.coracao[1], 90, 'rgba(255,90,90,.95)', 1.8);
                g.save(); g.strokeStyle = RED; g.lineWidth = 9; g.beginPath(); g.arc(c.coracao[0], c.coracao[1], 62, 0, 7); g.moveTo(c.coracao[0] - 44, c.coracao[1] - 44); g.lineTo(c.coracao[0] + 44, c.coracao[1] + 44); g.stroke(); g.restore();
                // caminho pela pele: pé esquerdo → perna → lado do corpo → ombros → braço direito → mão
                const pts = [c.peE, c.quadrilE, c.ladoE, c.ombroE, c.pescoco, c.ombroD, c.bracoD, c.maoD];
                const pr = clamp(k * 1.3); g.save(); g.strokeStyle = '#9fdcff'; g.shadowColor = '#7cc7ff'; g.shadowBlur = 30; g.lineWidth = 12; g.lineCap = 'round';
                g.beginPath(); const n = Math.max(1, pr * (pts.length - 1)); g.moveTo(...pts[0]);
                for (let i = 1; i <= Math.floor(n); i++) g.lineTo(...pts[i]);
                if (n % 1 && Math.ceil(n) < pts.length) { const a = pts[Math.floor(n)], b = pts[Math.ceil(n)]; g.lineTo(lerp(a[0], b[0], n % 1), lerp(a[1], b[1], n % 1)); }
                g.stroke(); g.restore();
                chip('PÉ  →  MÃO OPOSTA', CX, 300, k * 2, 'rgba(124,199,255,.75)', 760);
            },
            erro(k, lt) {
                const c = corpo(CX, 840, 1.15, .15 + Math.max(0, Math.sin(lt * 20)) * .6 * clamp(k * 3));
                raioZig(c.peE[0], c.peE[1], c.coracao[0], c.coracao[1], Math.floor(lt * 10), '#ffd0d0', 9);
                raioZig(c.coracao[0], c.coracao[1], c.maoD[0], c.maoD[1], Math.floor(lt * 10) + 3, '#ffd0d0', 7);
                icone(I.coracao, c.coracao[0], c.coracao[1], 120 * (1 + Math.sin(lt * 12) * .1), RED, 2.2, true);
                txt('MORTE', CX, 330, 130 * pop(k - .3), RED, 800, 'Cinzel');
            },
            protecao(k) {
                [[I.fio, 'FIO DE METAL'], [I.luva, 'LUVAS'], [I.bota, 'ISOLANTES']].forEach((it, i) => {
                    const kk = k * 1.8 - i * .45, x = 210 + i * 330;
                    g.save(); g.globalAlpha = clamp(kk * 3); caixa(x - 140, 700, 280, 300, 30, 'rgba(20,8,10,.9)', 'rgba(233,194,122,.5)'); icone(it[0], x, 810, 150 * pop(kk), GOLD, 1.5); txt(it[1], x, 940, 34, INK, 800); g.restore();
                });
                icone(I.escudo, CX, 1200, 200 * pop(k * 2 - 1), '#9fdcff', 1.5);
            },
            efeito(k, lt) {
                txt('TENSÃO DA FONTE', CX, 290, 40, DIM, 800);
                for (let i = 0; i < 5; i++) { const on = clamp(k * 2 - i * .2); caixa(CX - 260 + i * 110, 330, 90, 90, 16, on > .5 ? '#ffd76a' : 'rgba(255,255,255,.08)', GOLD); txt(String(i + 1), CX - 215 + i * 110, 376, 40, on > .5 ? '#2a1500' : DIM, 800); }
                const a = corpo(300, 860, .8), b = corpo(780, 860, .8, Math.max(0, Math.sin(lt * 18)) * .7 * clamp(k * 2 - .3));
                if (k > .2) raioZig(a.maoD[0], a.maoD[1], b.maoE[0], b.maoE[1], Math.floor(lt * 12), '#bfe3ff', 8);
                chip('DANO: SUA AMEAÇA E A TENSÃO', CX, 1290, k * 2 - .8, BLOOD, 900);
            },
            aprim1(k, lt) {
                chip('+7 DE AURA', CX, 560, k * 2);
                const n = 1 + Math.min(3, Math.floor(clamp(k * 1.3) * 4)); txt(`+${n * 5} DE DANO`, CX, 900, 150 * pop(k * 2 - .4), GOLD, 800, 'Cinzel');
                chip('ACUMULATIVO', CX, 1200, k * 2 - 1, 'rgba(233,194,122,.6)', 560);
            },
            aprim2(k, lt) {
                chip('+10 DE AURA', CX, 520, k * 2);
                const c = corpo(220, 860, .75);
                const pr = clamp(k * 1.6 - .3); if (pr > 0) { g.save(); g.strokeStyle = '#bfe3ff'; g.shadowColor = '#7cc7ff'; g.shadowBlur = 40; g.lineWidth = 22; g.lineCap = 'round'; g.beginPath(); g.moveTo(c.maoD[0], c.maoD[1]); g.lineTo(lerp(c.maoD[0], 1020, pr), c.maoD[1]); g.stroke(); g.restore(); }
                txt('EM LINHA', CX + 140, 760, 90 * pop(k * 2 - .6), INK, 800, 'Cinzel');
                chip('ALCANCE = ALMA × 3', CX, 1290, k * 2 - 1);
            },
            aprim3(k, lt) {
                chip('+60 DE AURA', CX, 480, k * 2);
                icone(I.cerebro, CX, 800, 330 * pop(k * 2 - .3), '#ffb0b0', 1.5);
                if (k > .3) for (let i = 0; i < 4; i++) raioZig(CX, 800, CX + Math.cos(i * 1.6 + lt) * 440, 800 + Math.sin(i * 1.6 + lt) * 240, Math.floor(lt * 10) + i, '#bfe3ff', 5);
                chip('INCONSCIENTE', CX, 1140, k * 2 - .9, BLOOD, 620); chip('HEXO-FRACO · DANO CEREBRAL', CX, 1285, k * 2 - 1.2, BLOOD, 860);
            },
            fim(k, lt) {
                if (Math.sin(lt * 7) > .4) raioZig(200, 300, 880, 760, Math.floor(lt * 6));
                txt('ATRAÇÃO ELÉTRICA', CX, 900, 92 * pop(k * 2), INK, 800, 'Cinzel');
                txt('PRAGA VERMELHA', CX, 1060, 110 * pop(k * 2 - .3), RED, 800, 'Cinzel');
            },
        },
        fisiomancia: {
            titulo: 'Fisiomancia · Imbuir Propriedades',
            abertura(k, lt) {
                fogo(CX, 900, 1.3, lt, k);
                icone(I.ancora, CX, 860, 300 * pop(k * 1.5), '#d9d4cc', 1.6);
                txt('IMBUIR', CX, 1250, 110 * pop(k - .2), INK, 800, 'Cinzel'); txt('PROPRIEDADES', CX, 1370, 96 * pop(k - .3), GOLD, 800, 'Cinzel');
            },
            fisio(k) {
                txt('FISIOMANCIA', CX, 470, 100 * pop(k * 2), RED, 800, 'Cinzel');
                [[I.bloco, 'METAL', 'Duro'], [I.chama, 'FOGO', 'Quente'], [I.vento, 'AR', 'Leve']].forEach((it, i) => {
                    const kk = k * 1.6 - .3 - i * .3, y = 700 + i * 250;
                    g.save(); g.globalAlpha = clamp(kk * 3); caixa(140, y - 100, 800, 200, 30, 'rgba(20,8,10,.9)', 'rgba(233,194,122,.5)');
                    icone(it[0], 260, y, 130 * pop(kk), GOLD); txt(it[1], 380, y - 30, 56, INK, 800, 'Cinzel', 'left'); txt('→ ' + it[2], 380, y + 40, 46, GOLD, 800, 'Inter', 'left'); g.restore();
                });
            },
            custo(k) { chip('8 DE AURA', CX, 700, k * 1.6); chip('FISIOMANCIA NÍVEL 3', CX, 920, k * 1.6 - .6); },
            processo(k, lt) {
                chip('AÇÃO PRINCIPAL', CX, 520, k * 2);
                const rodar = clamp((k - .2) * 2); g.save(); g.translate(CX, 830); g.rotate(rodar < 1 ? lt * 6 : 0); icone(I.dado, 0, 0, 330 * pop(k * 2 - .3), GOLD, 1.3); g.restore();
                txt(rodar < 1 ? String(1 + Math.floor((lt * 23) % 30)) : 'D30', CX, 835, 80, INK, 800);
                chip('MALDIÇÃO (D30)', CX, 1130, k * 2 - .9); chip('ou contra a RESILIÊNCIA do alvo', CX, 1270, k * 2 - 1.3, 'rgba(233,194,122,.6)', 900);
            },
            fogo(k, lt) {
                icone(I.bloco, 280, 880, 220, '#d9d4cc', 1.6); fogo(280, 900, 1, lt, k);
                corpo(800, 900, .8, .3 + Math.sin(lt * 4) * .2);
                for (let i = 0; i < 26; i++) { // propriedades indo para o alvo
                    const p = ((lt * .7 + i / 26) % 1); const x = lerp(330, 760, p), y = 880 - Math.sin(p * Math.PI) * 220 + (i % 5) * 8;
                    g.fillStyle = corFogo(lt + i * .1, .9); g.beginPath(); g.arc(x, y, 9, 0, 7); g.fill();
                }
                txt('LARANJA → BRANCO', CX, 560, 44, DIM, 800); chip('ATÉ O FIM DA CENA', CX, 1290, k * 2 - .8);
            },
            metal(k) { cartao(CX, 960, 920, 900, k * 1.4, 'METAL', I.bloco, [['DURO 5', '+4 armadura natural/ameaça'], ['COND. 1', 'dano elétrico dobrado'], ['PESO 4', 'desvantagem em destreza']]); },
            borracha(k) { cartao(CX, 960, 920, 760, k * 1.4, 'BORRACHA', I.bloco, [['', 'imune a dano elétrico'], ['MALEÁV. 4', '-12/ameaça em destreza']], '#f2a65a'); },
            ar(k) { cartao(CX, 960, 920, 760, k * 1.4, 'AR', I.vento, [['INVIS. 10', '+10/ameaça em furtividade'], ['PROP. 3', 'arremessa o alvo']], '#bfe3ff'); },
            ancora(k) { cartao(CX, 960, 920, 900, k * 1.4, 'ÂNCORA', I.ancora, [['PESADO 25', 'muito, muito pesado'], ['DURO 15', 'armadura de sobra'], ['FATOR 15', 'resiliência para se mover']], '#d9d4cc'); },
            correntes(k) { cartao(CX, 960, 920, 760, k * 1.4, 'CORRENTES', I.corrente, [['RESTR. 10', 'resiliência a cada ação'], ['', 'se falhar, perde a ação']], '#d9d4cc'); },
            regras(k) {
                [['Objetos são consumidos', I.chama], ['Seres vivos queimam e ficam', I.olho], ['Um Imbuir por alvo', I.alvo]].forEach((r, i) => {
                    const kk = k * 1.6 - i * .4, y = 640 + i * 260;
                    g.save(); g.globalAlpha = clamp(kk * 3); caixa(110, y - 100, 860, 200, 30, 'rgba(20,8,10,.9)', 'rgba(214,60,60,.5)');
                    icone(r[1], 230, y, 110 * pop(kk), GOLD); txt(r[0], 320, y, 44, INK, 800, 'Inter', 'left'); g.restore();
                });
            },
            aprim(k) { chip('+4 AURA · +3 m de alcance', CX, 680, k * 1.8, BLOOD, 880); chip('+6 AURA · +1 alvo', CX, 880, k * 1.8 - .5, BLOOD, 880); chip('+6 AURA · forma inversa', CX, 1080, k * 1.8 - 1, BLOOD, 880); },
            fim(k, lt) {
                fogo(CX, 900, 1.4, lt, k);
                txt('FISIOMANCIA', CX, 1180, 100 * pop(k * 2), GOLD, 800, 'Cinzel');
                txt('PRAGA VERMELHA', CX, 1320, 110 * pop(k * 2 - .3), RED, 800, 'Cinzel');
            },
        },
    };
    function corFogo(tt, a = 1) { // o fogo do imbuir: do laranja ao branco
        const f = (Math.sin(tt * 1.5) + 1) / 2;
        return `rgba(${255},${Math.round(lerp(140, 245, f))},${Math.round(lerp(40, 235, f))},${a})`;
    }
    function fogo(x, y, s, tt, k) {
        g.save(); g.globalAlpha = clamp(k * 3); g.globalCompositeOperation = 'lighter';
        for (let i = 0; i < 34; i++) {
            const p = ((tt * .9 + i / 34) % 1), xx = x + Math.sin(i * 7.1 + tt * 3) * 90 * s * (1 - p), yy = y + 120 * s - p * 360 * s, r = (40 - p * 30) * s;
            const grd = g.createRadialGradient(xx, yy, 0, xx, yy, r); grd.addColorStop(0, corFogo(tt + i * .05, .55 * (1 - p))); grd.addColorStop(1, 'rgba(255,60,20,0)');
            g.fillStyle = grd; g.beginPath(); g.arc(xx, yy, r, 0, 7); g.fill();
        }
        g.restore();
    }

    /* ---------- desenho de um instante ---------- */
    const D = CENAS[VIDEO];
    function quadro(tt) {
        sigiloFundo(tt);
        const c = cenas.find((x, i) => tt >= x.t0 && (i === cenas.length - 1 || tt < cenas[i + 1].t0)) || cenas[0];
        const lt = Math.max(0, tt - c.t0), k = clamp(lt / Math.min(1.6, c.dur));
        cabecalho(D.titulo, tt);
        g.save(); const sai = tt > c.t0 + c.dur + GAP * .2 && c !== cenas[cenas.length - 1]; g.globalAlpha = sai ? .6 : 1;
        D[c.cena](k, lt); g.restore();
        if (tt < c.t0 + c.dur + .2) legenda(c, lt);
        // barra de progresso no alto
        caixa(60, 40, 960, 8, 4, 'rgba(255,255,255,.12)'); caixa(60, 40, 960 * clamp(tt / TOTAL), 8, 4, RED);
        if (tt > TOTAL - FIM_EXTRA) { g.fillStyle = `rgba(0,0,0,${clamp((tt - (TOTAL - FIM_EXTRA)) / FIM_EXTRA) * .6})`; g.fillRect(0, 0, W, H); }
    }

    /* ---------- áudio ---------- */
    const ac = new AudioContext();
    const buffers = await Promise.all(cenas.map(async c => ac.decodeAudioData(await (await fetch('/vid/' + c.audio)).arrayBuffer())));
    function tocar(destino) {
        const t0 = ac.currentTime + .15;
        cenas.forEach((c, i) => { const s = ac.createBufferSource(); s.buffer = buffers[i]; s.connect(destino); s.start(t0 + c.t0); });
        return t0;
    }
    const st = document.getElementById('st');
    let rodando = null;
    function animar(t0, aoFim) {
        const passo = () => {
            const tt = ac.currentTime - t0;
            quadro(Math.max(0, tt));
            st.textContent = `${tt.toFixed(1)} / ${TOTAL.toFixed(1)} s`;
            if (tt < TOTAL) rodando = setTimeout(passo, 1000 / 60); else aoFim && aoFim();
        };
        passo();
    }
    quadro(4);
    window.__quadro = quadro; window.__total = TOTAL;
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
        // áudio: as falas nas mesmas posições da animação
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
        quadro(cenas[0].t0 + 1.4);
        await fetch('/salvar?nome=' + VIDEO + '.jpg', { method: 'POST', body: await new Promise(r => cv.toBlob(r, 'image/jpeg', .86)) });
        st.textContent = 'pronto: ' + (buf.byteLength / 1e6).toFixed(1) + ' MB';
        return buf.byteLength;
    };
    document.getElementById('gravar').onclick = async () => {
        await ac.resume(); clearTimeout(rodando);
        const dest = ac.createMediaStreamDestination();
        const fluxo = new MediaStream([...cv.captureStream(30).getVideoTracks(), ...dest.stream.getAudioTracks()]);
        const rec = new MediaRecorder(fluxo, { mimeType: 'video/mp4;codecs=avc1.640028,mp4a.40.2', videoBitsPerSecond: 6000000, audioBitsPerSecond: 128000 });
        const partes = []; rec.ondataavailable = e => e.data.size && partes.push(e.data);
        rec.onstop = async () => {
            const blob = new Blob(partes, { type: 'video/mp4' });
            st.textContent = 'enviando ' + (blob.size / 1e6).toFixed(1) + ' MB…';
            await fetch('/salvar?nome=' + VIDEO + '.mp4', { method: 'POST', body: blob });
            quadro(cenas[0].t0 + 1.4); // capa: a primeira cena já formada
            const capa = await new Promise(r => cv.toBlob(r, 'image/jpeg', .86));
            await fetch('/salvar?nome=' + VIDEO + '.jpg', { method: 'POST', body: capa });
            st.textContent = 'pronto: ' + (blob.size / 1e6).toFixed(1) + ' MB'; window.__pronto = blob.size;
        };
        rec.start(1000);
        animar(tocar(dest), () => setTimeout(() => rec.stop(), 300));
    };
})();
