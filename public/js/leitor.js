(function () {
    'use strict';

    var app = document.getElementById('leitor');
    if (!app) return;

    var statusEl = app.querySelector('#leitor-status');

    function erroFatal(msg) {
        if (statusEl) {
            statusEl.hidden = false;
            statusEl.textContent = msg;
        }
        if (window.console) console.error('[leitor] ' + msg);
    }

    if (!window.pdfjsLib) {
        erroFatal('Não foi possível carregar a biblioteca PDF.js (verifique sua conexão ou o bloqueio de CDN).');
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc = app.dataset.worker;

    /* ------------------------------------------------------------
       SUMÁRIO
       { g: 'Nome' }                 → cabeçalho de grupo (não clicável)
       { t: título, a: início, b: fim } → seção (b = null: até o fim)
       Os números são páginas físicas do PDF.
       Para editar, basta mexer nesta lista.
       (Nos livros de história, o sumário vem do Blade e substitui esta lista.)
       ------------------------------------------------------------ */
    var SUMARIO = [
        { g: 'Regras do sistema' },
        { t: 'Introdução ao sistema, testes e acertos',                      a: 2,   b: 2 },
        { t: 'Atributos básicos, experiência e progressão mundana',          a: 2,   b: 5 },
        { t: 'Pontos de vida, sanidade, fobias e aura',                      a: 6,   b: 9 },
        { t: 'Habilidades: distribuição, limites e usos',                    a: 9,   b: 19 },
        { t: 'Profissões, ramos e multifunções',                             a: 19,  b: 52 },
        { t: 'Talentos de profissão e talentos gerais',                      a: 20,  b: 69 },
        { t: 'Experiência, treinamento, negócios e regras complementares',   a: 70,  b: 93 },
        { t: 'Condições e regras rápidas',                                   a: 90,  b: 96 },
        { t: 'Regras rápidas de combate e regras de porte físico',           a: 97,  b: 101 },
        { t: 'Criação de personagem e ficha',                                a: 102, b: 106 },

        { g: 'Sobrenatural e maldições' },
        { t: 'Manifestações sobrenaturais, ascensão e conceitos iniciais',   a: 107, b: 109 },
        { t: 'Regras gerais de maldições e técnicas',                        a: 109, b: 116 },
        { t: 'Técnicas de maldição e seus aprimoramentos',                   a: 116, b: 336 },
        { t: 'Lista de maldições e ligações de transformação',               a: 337, b: 340 },
        { t: 'Transformações, requisitos e limitações',                      a: 340, b: 357 },
        { t: 'Talentos e regras avançadas de Hexomancia',                    a: 358, b: 367 },
        { t: 'Tensão espiritual e fenômenos sobrenaturais',                  a: 368, b: 373 },

        { g: 'Fisiomancia' },
        { t: 'Fisiomancia e manipulação de propriedades naturais',           a: 374, b: 404 },
        { t: 'Técnicas básicas e técnicas de Fisiomancia',                   a: 404, b: 416 },
        { t: 'Talentos, aprimoramentos e condução de propriedades',          a: 416, b: 421 },

        { g: 'Itens, equipamentos e objetos amaldiçoados' },
        { t: 'Sistema de armazenamento e regras gerais de itens',            a: 422, b: 423 },
        { t: 'Armas de longo alcance',                                       a: 423, b: 428 },
        { t: 'Armas corpo a corpo',                                          a: 429, b: 436 },
        { t: 'Proteções, compartimentos e outros itens',                     a: 436, b: 442 },
        { t: 'Modificações mundanas e aprimoramentos de equipamentos',       a: 443, b: 447 },
        { t: 'Pontos de vida e durabilidade de armas, construções e superfícies', a: 448, b: 450 },
        { t: 'Modificações amaldiçoadas e modificações por entidade',        a: 451, b: 458 },
        { t: 'Minérios e materiais',                                         a: 458, b: 460 },
        { t: 'Regras para amaldiçoar itens e lista de modificações amaldiçoadas', a: 461, b: 470 },
        { t: 'Objetos amaldiçoados e exemplos de itens',                     a: 471, b: 486 },

        { g: 'Cenário e organização AMA' },
        { t: 'AMA e legislação sobrenatural',                                a: 487, b: 489 },
        { t: 'Lojas, compras, preços e recursos do cenário',                 a: 489, b: 496 },
        { t: 'Patentes, progressão e hierarquia da AMA',                     a: 496, b: 501 },
        { t: 'Guia acadêmico, matérias e talentos de grade',                 a: 502, b: 506 },

        { g: 'Material de consulta' },
        { t: 'Questões e exercícios sobre Hexomancia e Fisiomancia',         a: 507, b: 511 },
        { t: 'Kuros e criaturas oriundas do profano: conceitos e ficha base', a: 512, b: 526 },
        { t: 'Propriedades Kuroticas e lista de Kuros',                      a: 527, b: 541 }
    ];

    // Livros de história: o sumário vem do Blade (JSON) e substitui o do Livro do sistema
    var dadosSum = document.getElementById('leitor-sumario-dados');
    if (dadosSum) {
        try { SUMARIO = JSON.parse(dadosSum.textContent); } catch (e) {}
    }

    /* ------------------------------------------------------------ */

    var $ = function (sel) { return app.querySelector(sel); };

    var mainEl    = $('.pv-reader__main');
    var stage     = $('#leitor-stage');
    var pagesEl   = $('#leitor-pages');
    var popEl     = $('#leitor-pop');
    var inputPage = $('#leitor-pagina');
    var totalEl   = $('#leitor-total');
    var zoomEl    = $('#leitor-zoom');
    var listSum   = $('#leitor-sumario');
    var listMarks = $('#leitor-marcadores');
    var emptyEl   = $('#leitor-vazio');
    var countEl   = $('#leitor-count');
    var btnPrev   = $('[data-act="prev"]');
    var btnNext   = $('[data-act="next"]');
    var btnMark   = $('[data-act="mark"]');

    // Verifica se o HTML tem todos os elementos necessários
    var necessarios = {
        '.pv-reader__main': mainEl, '#leitor-stage': stage, '#leitor-pages': pagesEl,
        '#leitor-pop': popEl, '#leitor-pagina': inputPage, '#leitor-total': totalEl,
        '#leitor-zoom': zoomEl, '#leitor-sumario': listSum, '#leitor-marcadores': listMarks,
        '#leitor-vazio': emptyEl, '#leitor-count': countEl
    };
    for (var chave in necessarios) {
        if (!necessarios[chave]) {
            erroFatal('Elemento ausente no HTML do leitor: ' + chave);
            return;
        }
    }

    var mobileMQ = window.matchMedia('(max-width: 820px)');

    // Cada livro guarda seus marcadores e sua página em uma chave própria
    // (data-chave no HTML; sem ela, usa "livro" — o Livro do sistema)
    var prefixo = 'pv:' + (app.dataset.chave || 'livro') + ':';

    var store = {
        get: function (k, d) {
            try {
                var v = localStorage.getItem(prefixo + k);
                return v === null ? d : JSON.parse(v);
            } catch (e) { return d; }
        },
        set: function (k, v) {
            try { localStorage.setItem(prefixo + k, JSON.stringify(v)); } catch (e) {}
        }
    };

    var pdf = null;
    var total = 0;
    var page = 1;
    var zoom = 1;
    var fit = true;
    var scale = 1;
    var base = { w: 612, h: 792 };   // tamanho da página 1 a 100%
    var sizes = [];                  // tamanho real de cada página (a 100%)
    var state = [];                  // estado de cada página (elementos, render)
    var obs = null;
    var ignorar = 0;                 // evita que a rolagem automática "corrija" a página
    var pending = null;              // seleção aguardando o clique em "Marcar trecho"

    /* ---------- utilidades ---------- */

    function mk(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text) n.textContent = text;
        return n;
    }

    function novoId() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    }

    function r4(x) { return Math.round(x * 10000) / 10000; }

    function rotulo(s) {
        if (s.b === null) return 'p. ' + s.a + ' em diante';
        if (s.a === s.b)  return 'p. ' + s.a;
        return 'p. ' + s.a + '–' + s.b;
    }

    // Seção mais específica que contém a página (maior início vence;
    // em empate, vence a que aparece depois na lista). Ignora cabeçalhos de grupo.
    function secaoDe(p) {
        var best = null;
        SUMARIO.forEach(function (s) {
            if (s.g) return;
            var fim = s.b === null ? Infinity : s.b;
            if (p >= s.a && p <= fim && (!best || s.a >= best.a)) best = s;
        });
        return best;
    }

    // Nome do marcador: o próprio trecho selecionado, encurtado
    function nomeDe(texto) {
        var t = texto.replace(/\s+/g, ' ').trim();
        if (t.length <= 48) return t;
        var corte = t.slice(0, 48);
        var sp = corte.lastIndexOf(' ');
        if (sp > 24) corte = corte.slice(0, sp);
        return corte.replace(/[\s,.;:\-–]+$/, '') + '…';
    }

    function mostrarNoPainel(btn) {
        var pn = btn.closest('.pv-reader__panel');
        if (!pn || pn.hidden) return;
        var pr = pn.getBoundingClientRect();
        var br = btn.getBoundingClientRect();
        if (br.top < pr.top) pn.scrollTop -= pr.top - br.top;
        else if (br.bottom > pr.bottom) pn.scrollTop += br.bottom - pr.bottom;
    }

    /* ---------- marcadores ---------- */
    /* Cada marca: { id, p (página), nome, texto, rects }
       rects = null  → página inteira (estrela)
       rects = [...] → trecho de texto, com retângulos em fração da página */

    var marks = store.get('marcas', null);
    if (!Array.isArray(marks)) {
        var antigos = store.get('marcadores', []);   // formato antigo: só números
        marks = (Array.isArray(antigos) ? antigos : [])
            .filter(function (p) { return typeof p === 'number'; })
            .map(function (p) {
                return { id: novoId(), p: p, nome: 'Página ' + p, texto: null, rects: null };
            });
        store.set('marcas', marks);
    }

    function achar(id) {
        for (var i = 0; i < marks.length; i++) if (marks[i].id === id) return marks[i];
        return null;
    }

    function ordemTopo(m) { return m.rects && m.rects.length ? m.rects[0].t : -1; }

    function salvarMarcas(pagina) {
        marks.sort(function (a, b) { return a.p - b.p || ordemTopo(a) - ordemTopo(b); });
        store.set('marcas', marks);
        montarMarcadores();
        if (pagina) pintar(pagina);
        atualizar();
    }

    function pintar(n) {
        var st = state[n];
        if (!st) return;
        st.marks.innerHTML = '';
        var inteira = false;

        marks.forEach(function (m) {
            if (m.p !== n) return;
            if (!m.rects) { inteira = true; return; }
            m.rects.forEach(function (r) {
                var d = mk('div', 'pv-mark');
                d.style.left   = (r.l * 100).toFixed(3) + '%';
                d.style.top    = (r.t * 100).toFixed(3) + '%';
                d.style.width  = (r.w * 100).toFixed(3) + '%';
                d.style.height = (r.h * 100).toFixed(3) + '%';
                d.dataset.id = m.id;
                st.marks.appendChild(d);
            });
        });

        st.el.classList.toggle('is-bookmarked', inteira);
    }

    function montarMarcadores() {
        listMarks.innerHTML = '';

        marks.forEach(function (m) {
            var li = mk('li');

            var go = mk('button', 'pv-reader__item');
            go.type = 'button';
            go.dataset.mark = m.id;
            if (m.texto) go.title = m.texto;
            if (m.p === page) go.classList.add('is-active');

            var info = mk('span', 'pv-reader__mark-info');
            info.appendChild(mk('span', 'pv-reader__mark-name', m.nome));
            var s = secaoDe(m.p);
            info.appendChild(mk('small', '', 'Página ' + m.p + (s ? ' · ' + s.t : '')));
            go.appendChild(info);

            var ed = mk('button', 'pv-reader__edit', '✎');
            ed.type = 'button';
            ed.dataset.rename = m.id;
            ed.title = 'Renomear marcador';

            var rm = mk('button', 'pv-reader__remove', '×');
            rm.type = 'button';
            rm.dataset.remove = m.id;
            rm.title = 'Remover marcador';

            li.appendChild(go);
            li.appendChild(ed);
            li.appendChild(rm);
            listMarks.appendChild(li);
        });

        emptyEl.hidden = marks.length > 0;
        countEl.textContent = marks.length;
    }

    function pulsarContador() {
        countEl.classList.remove('is-pulse');
        void countEl.offsetWidth;
        countEl.classList.add('is-pulse');
    }

    function alternarPagina(p) {
        p = p || page;
        var i = -1;
        marks.forEach(function (m, k) { if (m.p === p && !m.rects) i = k; });

        if (i >= 0) marks.splice(i, 1);
        else marks.push({ id: novoId(), p: p, nome: 'Página ' + p, texto: null, rects: null });

        salvarMarcas(p);
        if (i < 0) pulsarContador();
    }

    function removerMarca(id) {
        var m = achar(id);
        if (!m) return;
        marks.splice(marks.indexOf(m), 1);
        salvarMarcas(m.p);
    }

    function renomearMarca(id) {
        var m = achar(id);
        if (!m) return;
        var novo = window.prompt('Nome do marcador:', m.nome);
        if (novo === null) return;
        novo = novo.replace(/\s+/g, ' ').trim().slice(0, 80);
        if (!novo) return;
        m.nome = novo;
        salvarMarcas();
    }

    function irParaMarca(m) {
        irPara(m.p);
        if (!m.rects || !m.rects.length || !state[m.p]) return;

        var st = state[m.p];
        stage.scrollTop = posicao(m.p) + m.rects[0].t * st.el.offsetHeight - stage.clientHeight * 0.3;
        ignorar = Date.now() + 250;

        var alvos = st.marks.querySelectorAll('[data-id="' + m.id + '"]');
        Array.prototype.forEach.call(alvos, function (d) {
            d.classList.remove('is-flash');
            void d.offsetWidth;
            d.classList.add('is-flash');
            setTimeout(function () { d.classList.remove('is-flash'); }, 3500);
        });
    }

    /* ---------- sumário ---------- */

    var sumBtns = [];

    function montarSumario() {
        SUMARIO.forEach(function (s) {
            var li = mk('li');

            // Cabeçalho de grupo (estilo embutido como reserva; pode ser
            // sobrescrito em leitor.css pela classe .pv-reader__group)
            if (s.g) {
                li.className = 'pv-reader__group';
                li.textContent = s.g;
                li.style.cssText = 'padding:14px 12px 6px;font-size:.72rem;font-weight:700;' +
                                   'letter-spacing:.08em;text-transform:uppercase;opacity:.65;' +
                                   'pointer-events:none;list-style:none;';
                listSum.appendChild(li);
                return;
            }

            var b = mk('button', 'pv-reader__item');
            b.type = 'button';
            b.dataset.page = s.a;
            b.appendChild(mk('span', '', s.t));
            b.appendChild(mk('small', '', rotulo(s)));
            li.appendChild(b);
            listSum.appendChild(li);
            sumBtns.push({ btn: b, s: s });
        });
    }

    /* ---------- interface ---------- */

    function atualizar() {
        inputPage.value = page;
        if (btnPrev) btnPrev.disabled = page <= 1;
        if (btnNext) btnNext.disabled = page >= total;

        var marcada = marks.some(function (m) { return m.p === page && !m.rects; });
        if (btnMark) {
            btnMark.classList.toggle('is-on', marcada);
            btnMark.setAttribute('aria-pressed', marcada);
        }

        var atual = secaoDe(page);
        sumBtns.forEach(function (x) {
            var ativo = x.s === atual;
            x.btn.classList.toggle('is-active', ativo);
            if (ativo) mostrarNoPainel(x.btn);
        });

        Array.prototype.forEach.call(listMarks.querySelectorAll('.pv-reader__item'), function (b) {
            var m = achar(b.dataset.mark);
            b.classList.toggle('is-active', !!m && m.p === page);
        });
    }

    /* ---------- páginas (rolagem contínua) ---------- */

    function calcEscala() {
        if (fit) {
            scale = Math.max(0.4, Math.min((stage.clientWidth - 40) / base.w, 3));
        } else {
            scale = zoom;
        }
        zoomEl.textContent = Math.round(scale * 100) + '%';
    }

    function dimensionar(n) {
        var z = sizes[n] || base;
        var el = state[n].el;
        el.style.width  = (z.w * scale) + 'px';
        el.style.height = (z.h * scale) + 'px';
    }

    // Distância do topo da página até o topo da área rolável
    function posicao(n) {
        return pagesEl.offsetTop + state[n].el.offsetTop;
    }

    // Página que contém a altura y (busca binária)
    function paginaEm(y) {
        var lo = 1, hi = total;
        while (lo < hi) {
            var mid = (lo + hi + 1) >> 1;
            if (posicao(mid) <= y) lo = mid;
            else hi = mid - 1;
        }
        return lo;
    }

    function montarPaginas() {
        for (var n = 1; n <= total; n++) {
            var d = mk('div', 'pv-page');
            d.dataset.n = n;

            var camada = mk('div', 'pv-page__marks');
            var fita = mk('span', 'pv-page__ribbon');
            fita.setAttribute('aria-hidden', 'true');

            d.appendChild(camada);
            d.appendChild(fita);
            pagesEl.appendChild(d);

            state[n] = {
                el: d, marks: camada,
                canvas: null, text: null, task: null, ttask: null,
                token: 0, busy: false, done: false, scale: null
            };
        }
    }

    function limpar(n) {
        var st = state[n];
        st.token++;
        if (st.task)  { try { st.task.cancel(); }  catch (e) {} st.task = null; }
        if (st.ttask) { try { st.ttask.cancel(); } catch (e) {} st.ttask = null; }
        if (st.canvas) { st.canvas.remove(); st.canvas = null; }
        if (st.text)   { st.text.remove();   st.text = null; }
        st.el.classList.remove('is-ready');
        st.busy = false;
        st.done = false;
        st.scale = null;
    }

    function limparTudo() {
        for (var n = 1; n <= total; n++) {
            if (state[n].busy || state[n].done) limpar(n);
        }
    }

    function camadaTexto(pg, vp, s, st) {
        if (!pdfjsLib.renderTextLayer) return;

        try {
            var div = mk('div', 'textLayer');
            div.style.width  = vp.width + 'px';
            div.style.height = vp.height + 'px';
            div.style.setProperty('--scale-factor', s);
            st.el.appendChild(div);
            st.text = div;

            st.ttask = pdfjsLib.renderTextLayer({
                textContentSource: pg.streamTextContent(),
                container: div,
                viewport: vp,
                textDivs: []
            });
            st.ttask.promise.catch(function () { /* cancelado */ });
        } catch (e) {
            if (window.console) console.warn('[leitor] camada de texto falhou', e);
        }
    }

    function renderizar(n) {
        var st = state[n];
        if (st.scale === scale && (st.busy || st.done)) return;
        if (st.busy || st.done) limpar(n);

        var token = ++st.token;
        var s = scale;
        st.busy = true;
        st.scale = s;

        pdf.getPage(n).then(function (pg) {
            if (token !== st.token) return;

            var b = pg.getViewport({ scale: 1 });
            if (!sizes[n] || sizes[n].w !== b.width || sizes[n].h !== b.height) {
                sizes[n] = { w: b.width, h: b.height };
                dimensionar(n);
            }

            var vp  = pg.getViewport({ scale: s });
            var dpr = window.devicePixelRatio || 1;

            var cv = document.createElement('canvas');
            cv.width  = Math.floor(vp.width * dpr);
            cv.height = Math.floor(vp.height * dpr);
            cv.style.width  = vp.width + 'px';
            cv.style.height = vp.height + 'px';

            st.task = pg.render({
                canvasContext: cv.getContext('2d'),
                viewport: vp,
                transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null
            });

            camadaTexto(pg, vp, s, st);

            return st.task.promise.then(function () {
                if (token !== st.token) return;
                st.el.insertBefore(cv, st.el.firstChild);
                st.canvas = cv;
                st.task = null;
                st.el.classList.add('is-ready');
                st.busy = false;
                st.done = true;
            });
        }).catch(function (err) {
            if (token === st.token) st.busy = false;
            if (err && err.name !== 'RenderingCancelledException' && window.console) {
                console.warn('[leitor] erro ao renderizar página ' + n, err);
            }
        });
    }

    function reobservar() {
        if (obs) obs.disconnect();

        obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                var n = Number(e.target.dataset.n);
                if (e.isIntersecting) renderizar(n);
                else limpar(n);
            });
        }, { root: stage, rootMargin: '100% 0px' });

        for (var n = 1; n <= total; n++) obs.observe(state[n].el);
    }

    function relayout() {
        if (!total) return;

        var cur = page;
        var alt = state[cur].el.offsetHeight || 1;
        var frac = (stage.scrollTop - posicao(cur)) / alt;

        calcEscala();
        for (var n = 1; n <= total; n++) dimensionar(n);
        limparTudo();

        stage.scrollTop = posicao(cur) + frac * state[cur].el.offsetHeight;
        ignorar = Date.now() + 250;
        reobservar();
    }

    function irPara(n) {
        n = parseInt(n, 10);
        if (isNaN(n)) n = page;
        n = Math.max(1, Math.min(total || 1, n));

        page = n;
        store.set('pagina', n);

        if (total && state[n]) {
            stage.scrollTop = posicao(n) - 12;
            ignorar = Date.now() + 250;
        }
        atualizar();
    }

    function paginaAtual() {
        if (!total || Date.now() < ignorar) return;
        var n = paginaEm(stage.scrollTop + stage.clientHeight * 0.35);
        if (n !== page) {
            page = n;
            store.set('pagina', n);
            atualizar();
        }
    }

    /* ---------- zoom ---------- */

    function mudarZoom(fator) {
        if (!total) return;
        zoom = Math.max(0.4, Math.min((fit ? scale : zoom) * fator, 3));
        fit = false;
        relayout();
    }

    /* ---------- seleção de texto → marcador ---------- */

    function paginaDe(node) {
        var e = node && (node.nodeType === 1 ? node : node.parentElement);
        return e && e.closest ? e.closest('.pv-page') : null;
    }

    function contem(o, c) {
        return o.left <= c.left + 0.5 && o.right >= c.right - 0.5 &&
               o.top <= c.top + 0.5 && o.bottom >= c.bottom - 0.5;
    }

    function lerSelecao() {
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed || !sel.rangeCount) return null;

        var r = sel.getRangeAt(0);
        var pa = paginaDe(r.startContainer);
        var pb = paginaDe(r.endContainer);
        if (!pa || pa !== pb) return null;   // só dentro de uma página

        var texto = sel.toString().replace(/\s+/g, ' ').trim();
        if (!texto) return null;

        var box = pa.getBoundingClientRect();
        var lista = Array.prototype.slice.call(r.getClientRects())
            .filter(function (c) { return c.width > 1 && c.height > 1; });

        lista = lista.filter(function (c, i) {
            return !lista.some(function (o, j) {
                return j !== i && contem(o, c) && (!contem(c, o) || j < i);
            });
        });

        var rects = lista.map(function (c) {
            return {
                l: r4((c.left - box.left) / box.width),
                t: r4((c.top - box.top) / box.height),
                w: r4(c.width / box.width),
                h: r4(c.height / box.height)
            };
        });
        if (!rects.length) return null;

        return { n: Number(pa.dataset.n), texto: texto, rects: rects, rect: r.getBoundingClientRect() };
    }

    function esconderPop() {
        popEl.hidden = true;
        pending = null;
    }

    function mostrarPop() {
        var s = lerSelecao();
        if (!s) { esconderPop(); return; }

        pending = s;
        var mr = mainEl.getBoundingClientRect();
        var x = s.rect.left + s.rect.width / 2 - mr.left;
        var y = s.rect.top - mr.top - 46;
        if (y < 58) y = s.rect.bottom - mr.top + 10;
        x = Math.max(80, Math.min(x, mr.width - 80));

        popEl.style.left = x + 'px';
        popEl.style.top  = y + 'px';
        popEl.hidden = false;
    }

    function marcarSelecao() {
        if (!pending) return;
        var s = pending;

        marks.push({
            id: novoId(),
            p: s.n,
            nome: nomeDe(s.texto),
            texto: s.texto.slice(0, 300),
            rects: s.rects
        });

        var sel = window.getSelection();
        if (sel) sel.removeAllRanges();
        esconderPop();
        salvarMarcas(s.n);
        pulsarContador();
    }

    popEl.addEventListener('mousedown', function (e) { e.preventDefault(); });
    popEl.addEventListener('click', marcarSelecao);

    stage.addEventListener('mouseup', function () { setTimeout(mostrarPop, 10); });
    stage.addEventListener('touchend', function () { setTimeout(mostrarPop, 350); });

    document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed) esconderPop();
    });

    var raf = null;
    stage.addEventListener('scroll', function () {
        if (!popEl.hidden) esconderPop();
        if (raf) return;
        raf = requestAnimationFrame(function () {
            raf = null;
            paginaAtual();
        });
    }, { passive: true });

    /* ---------- eventos ---------- */

    function alternarPainel() {
        if (mobileMQ.matches) app.classList.toggle('is-side-open');
        else app.classList.toggle('is-side-hidden');
        setTimeout(function () { if (fit) relayout(); }, 300);
    }

    var acoes = {
        side:    alternarPainel,
        prev:    function () { irPara(page - 1); },
        next:    function () { irPara(page + 1); },
        zoomIn:  function () { mudarZoom(1.15); },
        zoomOut: function () { mudarZoom(1 / 1.15); },
        fit:     function () { fit = true; relayout(); },
        mark:    function () { alternarPagina(); },
        full:    function () {
            if (document.fullscreenElement) document.exitFullscreen();
            else if (app.requestFullscreen) app.requestFullscreen();
        }
    };

    function fecharPainelMobile() {
        if (mobileMQ.matches) app.classList.remove('is-side-open');
    }

    app.addEventListener('click', function (e) {
        var act = e.target.closest('[data-act]');
        if (act && acoes[act.dataset.act]) {
            acoes[act.dataset.act]();
            return;
        }

        var rm = e.target.closest('[data-remove]');
        if (rm) { removerMarca(rm.dataset.remove); return; }

        var rn = e.target.closest('[data-rename]');
        if (rn) { renomearMarca(rn.dataset.rename); return; }

        var mk1 = e.target.closest('[data-mark]');
        if (mk1) {
            var m = achar(mk1.dataset.mark);
            if (m) irParaMarca(m);
            fecharPainelMobile();
            return;
        }

        var item = e.target.closest('.pv-reader__item[data-page]');
        if (item) {
            irPara(item.dataset.page);
            fecharPainelMobile();
            return;
        }

        var tab = e.target.closest('[data-tab]');
        if (tab) {
            Array.prototype.forEach.call(app.querySelectorAll('[data-tab]'), function (t) {
                t.classList.toggle('is-active', t === tab);
            });
            Array.prototype.forEach.call(app.querySelectorAll('[data-panel]'), function (p) {
                p.hidden = p.dataset.panel !== tab.dataset.tab;
            });
        }
    });

    inputPage.addEventListener('change', function () { irPara(inputPage.value); });
    inputPage.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { irPara(inputPage.value); inputPage.blur(); }
    });

    document.addEventListener('keydown', function (e) {
        var alvo = e.target;
        if (alvo && (alvo.tagName === 'INPUT' || alvo.tagName === 'TEXTAREA')) return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        switch (e.key) {
            case 'ArrowLeft':  irPara(page - 1); break;
            case 'ArrowRight': irPara(page + 1); break;
            case 'Home':       irPara(1); break;
            case 'End':        irPara(total); break;
            case 'b':
            case 'B':          alternarPagina(); break;
            case '+':
            case '=':          mudarZoom(1.15); break;
            case '-':          mudarZoom(1 / 1.15); break;
            default: return;
        }
        e.preventDefault();
    });

    var timer = null;
    function aoRedimensionar() {
        clearTimeout(timer);
        timer = setTimeout(function () { if (fit) relayout(); }, 150);
    }
    window.addEventListener('resize', aoRedimensionar);
    document.addEventListener('fullscreenchange', aoRedimensionar);

    /* ---------- início ---------- */

    montarSumario();
    montarMarcadores();

    pdfjsLib.getDocument(app.dataset.pdf).promise.then(function (doc) {
        pdf = doc;
        total = doc.numPages;
        totalEl.textContent = '/ ' + total;
        inputPage.max = total;
        return doc.getPage(1);
    }).then(function (pg) {
        var v = pg.getViewport({ scale: 1 });
        base = { w: v.width, h: v.height };

        if (mobileMQ.matches) app.classList.remove('is-side-open');

        montarPaginas();
        calcEscala();
        for (var n = 1; n <= total; n++) { dimensionar(n); pintar(n); }

        // Esconde o "Carregando" assim que a estrutura está pronta;
        // as páginas aparecem conforme são renderizadas.
        statusEl.hidden = true;

        var salva = parseInt(store.get('pagina', 1), 10) || 1;
        page = Math.max(1, Math.min(total, salva));

        reobservar();
        irPara(page);
    }).catch(function (err) {
        var motivo = err && err.message ? ' (' + err.message + ')' : '';
        erroFatal('Não foi possível carregar o PDF' + motivo + '. Verifique se o arquivo PDF existe em public/pdf/.');
        if (window.console) console.error(err);
    });
})();