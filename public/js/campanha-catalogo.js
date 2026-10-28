/* Disputa de catálogo — formulário, placar ao vivo (polling 4 s) e detalhamento. */
(function () {
    'use strict';

    const raiz = document.getElementById('camp-catalogo');
    if (!raiz) return;
    const app = document.getElementById('camp-app');
    const csrf = app ? app.dataset.csrf : '';

    const urlIndex = raiz.dataset.urlIndex;
    const urlStore = raiz.dataset.urlStore;
    const $ = (s) => raiz.querySelector(s);

    const form = $('.js-cat-form');
    const selMald = $('.js-cat-maldicao');
    const txt = $('.js-cat-texto');
    const palavras = $('.js-cat-palavras');
    const erro = $('.js-cat-erro');
    const btn = $('.js-cat-enviar');
    const semAcesso = $('.js-cat-sem-acesso');
    const resultado = $('.js-cat-resultado');
    const lista = $('.js-cat-lista');
    const vazio = $('.js-cat-vazio');

    const ROTULOS = [
        ['significado', 'Significado'],
        ['termos_chave', 'Termos-chave'],
        ['ordem', 'Ordem das ideias'],
        ['estrutura', 'Estrutura'],
        ['concisao', 'Concisão'],
        ['raridade', 'Raridade'],
        ['orgao', 'Órgão'],
        ['apelido', 'Apelido'],
    ];
    // Envios feitos antes da revisão do comparador usam estes critérios
    const ROTULOS_ANTIGOS = [
        ['vocabulario', 'Vocabulário'],
        ['termos_chave', 'Termos-chave'],
        ['tamanho', 'Tamanho'],
        ['raridade', 'Raridade'],
        ['orgao', 'Órgão'],
    ];
    const NOMES_PENALIDADES = {
        redundancia: 'redundância',
        prolixidade: 'texto longo demais',
        desvio: 'fuga do sentido original',
        lista_palavras: 'lista de palavras sem frases',
    };

    let itens = [];
    let assinatura = '';
    const abertos = new Set();
    let espera = 0;          // segundos restantes
    let fimEspera = 0;       // timestamp ms
    let tempoEspera = null;
    let enviando = false;
    let timerPoll = null;
    let buscando = false;

    function el(tag, classe, texto) {
        const e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texto != null) e.textContent = texto;
        return e;
    }

    function cor(p) {
        if (p < 34) return 'baixo';
        if (p < 67) return 'medio';
        return 'alto';
    }

    function pct(v) {
        v = Number(v);
        if (!isFinite(v)) v = 0;
        return Math.max(0, Math.min(100, Math.round(v)));
    }

    function barra(valor, extra) {
        const p = pct(valor);
        const b = el('span', 'cat-barra ' + (extra || ''));
        const i = el('span', 'cat-barra-fill cat-' + cor(p));
        i.style.width = p + '%';
        b.appendChild(i);
        return b;
    }

    function detalhes(d) {
        const w = el('div', 'cat-detalhes');
        d = d || {};
        const rotulos = ('significado' in d || !('vocabulario' in d)) ? ROTULOS : ROTULOS_ANTIGOS;
        rotulos.forEach(function (r) {
            const linha = el('div', 'cat-det-linha');
            linha.appendChild(el('span', 'cat-det-rotulo', r[1]));
            linha.appendChild(barra(d[r[0]]));
            linha.appendChild(el('span', 'cat-det-valor', pct(d[r[0]]) + '%'));
            w.appendChild(linha);
        });
        // Penalidades aplicadas (multiplicadores abaixo de 1), ex.: "−20% por redundância"
        const pen = d.penalidades || {};
        const textos = Object.keys(NOMES_PENALIDADES)
            .filter(function (k) { return Number(pen[k]) > 0 && Number(pen[k]) < 0.995; })
            .map(function (k) { return '−' + Math.round((1 - Number(pen[k])) * 100) + '% por ' + NOMES_PENALIDADES[k]; });
        if (textos.length) w.appendChild(el('p', 'cat-det-penalidades', 'Penalidades: ' + textos.join(' · ')));
        return w;
    }

    function haQuanto(iso) {
        const t = Date.parse(iso);
        if (!isFinite(t)) return '';
        const s = Math.max(0, Math.floor((Date.now() - t) / 1000));
        if (s < 45) return 'agora há pouco';
        const m = Math.round(s / 60);
        if (m < 60) return 'há ' + (m < 1 ? 1 : m) + ' min';
        const h = Math.floor(m / 60);
        if (h < 24) return 'há ' + h + ' h';
        return 'há ' + Math.floor(h / 24) + ' d';
    }

    function contarPalavras(t) {
        const m = String(t || '').trim().match(/\S+/g);
        return m ? m.length : 0;
    }

    /* ---------- Resultado do próprio envio ---------- */

    function mostrarResultado(item) {
        resultado.hidden = false;
        resultado.textContent = '';
        const p = pct(item.pontuacao);
        const topo = el('div', 'cat-res-topo');
        topo.appendChild(el('span', 'cat-res-pct cat-txt-' + cor(p), p + '%'));
        const info = el('div', 'cat-res-info');
        info.appendChild(el('strong', '', 'Seu catálogo: ' + (item.maldicao || '')));
        info.appendChild(el('span', 'camp-dica', 'Pontuação final do envio'));
        topo.appendChild(info);
        resultado.appendChild(topo);
        resultado.appendChild(detalhes(item.detalhes));
    }

    /* ---------- Placar ---------- */

    function melhoresPorMaldicao(lst) {
        const mapa = {};
        lst.forEach(function (it) {
            const a = mapa[it.maldicao];
            if (a === undefined || pct(it.pontuacao) > pct(a.pontuacao)) mapa[it.maldicao] = it;
        });
        return mapa;
    }

    function desenhar() {
        lista.textContent = '';
        vazio.hidden = itens.length > 0;
        const melhores = melhoresPorMaldicao(itens);

        itens.forEach(function (it) {
            const p = pct(it.pontuacao);
            const aberto = abertos.has(it.id);
            const coroa = melhores[it.maldicao] === it;

            const cartao = el('div', 'cat-item' + (aberto ? ' aberto' : '') + (it.meu ? ' meu' : ''));
            const cab = el('button', 'cat-item-cab');
            cab.type = 'button';
            cab.setAttribute('aria-expanded', aberto ? 'true' : 'false');

            const t1 = el('span', 'cat-item-linha1');
            if (coroa) {
                const c = el('span', 'cat-coroa', '👑');
                c.title = 'Melhor resultado desta maldição';
                t1.appendChild(c);
            }
            t1.appendChild(el('span', 'cat-jogador', it.jogador + (it.meu ? ' (você)' : '')));
            t1.appendChild(el('span', 'cat-maldicao', it.maldicao));
            t1.appendChild(el('span', 'cat-quando', haQuanto(it.criado_em)));
            cab.appendChild(t1);

            const t2 = el('span', 'cat-item-linha2');
            t2.appendChild(barra(p));
            t2.appendChild(el('span', 'cat-pct cat-txt-' + cor(p), p + '%'));
            cab.appendChild(t2);

            cab.addEventListener('click', function () {
                if (abertos.has(it.id)) abertos.delete(it.id); else abertos.add(it.id);
                desenhar();
            });
            cartao.appendChild(cab);

            if (aberto) {
                const corpo = el('div', 'cat-item-corpo');
                corpo.appendChild(detalhes(it.detalhes));
                if (it.texto) {
                    corpo.appendChild(el('div', 'cat-texto', it.texto));
                }
                cartao.appendChild(corpo);
            }
            lista.appendChild(cartao);
        });
    }

    function assin(lst) {
        return lst.map(function (i) {
            return i.id + ':' + i.pontuacao + ':' + (i.texto ? 1 : 0);
        }).join('|');
    }

    function atualizarTempos() {
        const q = lista.querySelectorAll('.cat-quando');
        itens.forEach(function (it, i) {
            if (q[i]) q[i].textContent = haQuanto(it.criado_em);
        });
    }

    /* ---------- Disponíveis ---------- */

    function atualizarDisponiveis(disp) {
        if (!Array.isArray(disp)) return;
        const atual = selMald.value;
        const chave = disp.map(function (d) { return d.slug; }).join(',');
        const antes = Array.prototype.map.call(selMald.options, function (o) { return o.value; }).join(',');
        if (chave !== antes) {
            selMald.textContent = '';
            disp.forEach(function (d) {
                const o = document.createElement('option');
                o.value = d.slug;
                o.textContent = d.nome;
                selMald.appendChild(o);
            });
            if (disp.some(function (d) { return d.slug === atual; })) selMald.value = atual;
        }
        form.hidden = disp.length === 0;
        semAcesso.hidden = disp.length > 0;
    }

    /* ---------- Espera ---------- */

    function formatarEspera(s) {
        const m = Math.floor(s / 60);
        const r = s % 60;
        return m + ':' + (r < 10 ? '0' : '') + r;
    }

    function atualizarBotao() {
        if (enviando) {
            btn.disabled = true;
            btn.textContent = 'Enviando…';
            return;
        }
        const resta = Math.max(0, Math.ceil((fimEspera - Date.now()) / 1000));
        if (resta > 0) {
            btn.disabled = true;
            btn.textContent = 'Aguarde ' + formatarEspera(resta);
        } else {
            btn.disabled = false;
            btn.textContent = 'Enviar catálogo';
            if (tempoEspera) { clearInterval(tempoEspera); tempoEspera = null; }
        }
    }

    function iniciarEspera(seg) {
        seg = Number(seg) || 0;
        if (seg <= 0) { fimEspera = 0; atualizarBotao(); return; }
        fimEspera = Date.now() + seg * 1000;
        atualizarBotao();
        if (!tempoEspera) tempoEspera = setInterval(atualizarBotao, 1000);
    }

    /* ---------- Rede ---------- */

    function buscar() {
        if (buscando) return Promise.resolve();
        buscando = true;
        return fetch(urlIndex, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                const nova = Array.isArray(d.itens) ? d.itens : [];
                const a = assin(nova);
                if (a !== assinatura) {
                    assinatura = a;
                    itens = nova;
                    desenhar();
                }
                atualizarDisponiveis(d.disponiveis);
                if (Number(d.espera) > 0 && fimEspera - Date.now() < 1000) iniciarEspera(d.espera);
            })
            .catch(function () { /* tenta de novo no próximo ciclo */ })
            .then(function () { buscando = false; });
    }

    function mostrarErro(msg) {
        erro.textContent = msg;
        erro.hidden = !msg;
    }

    function enviar(ev) {
        ev.preventDefault();
        if (enviando || btn.disabled) return;
        mostrarErro('');
        const texto = txt.value.trim();
        if (!selMald.value) { mostrarErro('Escolha uma maldição.'); return; }
        if (texto.length < 30) { mostrarErro('O catálogo precisa ter pelo menos 30 caracteres.'); return; }

        enviando = true;
        atualizarBotao();

        const corpo = new FormData();
        corpo.append('maldicao', selMald.value);
        corpo.append('texto', texto);

        fetch(urlStore, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: corpo,
        })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) { return { status: r.status, d: d }; });
            })
            .then(function (x) {
                enviando = false;
                if (x.status === 201 || x.status === 200) {
                    if (x.d.item) mostrarResultado(x.d.item);
                    txt.value = '';
                    contar();
                    iniciarEspera(x.d.espera != null ? x.d.espera : 30);
                    assinatura = '';
                    buscar();
                    return;
                }
                if (x.status === 429) {
                    mostrarErro(x.d.erro || 'Aguarde um pouco antes de enviar outro catálogo.');
                    iniciarEspera(x.d.espera || 0);
                    return;
                }
                if (x.status === 422) {
                    const e = x.d.errors || {};
                    const msgs = [];
                    Object.keys(e).forEach(function (k) { (e[k] || []).forEach(function (m) { msgs.push(m); }); });
                    mostrarErro(msgs.length ? msgs.join(' ') : (x.d.message || 'Dados inválidos.'));
                    atualizarBotao();
                    return;
                }
                if (x.status === 419) {
                    mostrarErro('Sua sessão expirou. Recarregue a página e tente de novo.');
                } else {
                    mostrarErro(x.d.erro || x.d.message || 'Não foi possível enviar o catálogo.');
                }
                atualizarBotao();
            })
            .catch(function () {
                enviando = false;
                mostrarErro('Falha de conexão. Tente novamente.');
                atualizarBotao();
            });
    }

    function contar() {
        const n = contarPalavras(txt.value);
        palavras.textContent = n + (n === 1 ? ' palavra' : ' palavras');
    }

    /* ---------- Polling ---------- */

    function parar() {
        if (timerPoll) { clearInterval(timerPoll); timerPoll = null; }
    }
    function iniciar() {
        parar();
        timerPoll = setInterval(function () {
            buscar();
            atualizarTempos();
        }, 4000);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            buscar();
            iniciar();
        } else {
            parar();
        }
    });

    form.addEventListener('submit', enviar);
    txt.addEventListener('input', contar);
    contar();
    buscar();
    if (document.visibilityState !== 'hidden') iniciar();
})();
