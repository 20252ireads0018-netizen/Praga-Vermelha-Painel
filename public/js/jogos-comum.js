/**
 * Jogos da campanha: utilitários usados no lobby e na partida.
 * - JG.post: manda um formulário e devolve o JSON (lança Error com a mensagem do servidor).
 * - JG.carta: desenha uma carta de Gladio.
 * - JG.diagrama: desenha o movimento de uma peça de Pela Dama numa gradezinha.
 */
window.JG = (function () {
    'use strict';

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function post(url, dados, csrf, metodo) {
        const fd = new FormData();
        fd.append('_token', csrf);
        if (metodo) fd.append('_method', metodo);
        Object.keys(dados || {}).forEach(k => { if (dados[k] != null) fd.append(k, dados[k]); });
        const r = await fetch(url, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || j.ok === false) {
            const erros = j.errors ? Object.values(j.errors).flat().join(' ') : '';
            throw new Error(j.erro || erros || j.message || 'Não foi possível concluir (erro ' + r.status + ').');
        }
        return j;
    }

    /* ---------- Gladio ---------- */
    const EFEITO = {
        dano: v => `Causa ${v} de dano numa Lenda inimiga ou no adversário.`,
        dano_todos: v => `Causa ${v} de dano em todas as Lendas inimigas.`,
        cura: v => `Cura ${v} de vida numa Lenda sua ou em você.`,
        comprar: v => `Compre ${v} carta${v == 1 ? '' : 's'}.`,
        fortalecer: v => `Uma Lenda sua ganha +${v} de ataque.`,
        energia: v => `Ganhe ${v} de energia neste turno.`,
    };
    const textoEfeito = c => (EFEITO[c.efeito] ? EFEITO[c.efeito](c.valor) : '');

    // Emblemas em traço fino (no lugar da arte quando a carta não tem imagem) e formas dos atributos
    const SVG = (corpo, vb) => `<svg viewBox="${vb || '0 0 64 64'}" aria-hidden="true" focusable="false">${corpo}</svg>`;
    const EMBLEMA = {
        lenda: SVG('<g fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round">' +
            '<path d="M13 11l4 1 27 28-3 3-27-27z"/><path d="M38 47l9-9"/><path d="M44 44l7 7"/><circle cx="52.5" cy="52.5" r="2"/>' +
            '<path d="M51 11l-4 1-27 28 3 3 27-27z"/><path d="M26 47l-9-9"/><path d="M20 44l-7 7"/><circle cx="11.5" cy="52.5" r="2"/></g>'),
        feitico: SVG('<g fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round">' +
            '<circle cx="32" cy="32" r="23"/><circle cx="32" cy="32" r="18" stroke-dasharray="1.5 3"/>' +
            '<path d="M32 13l16.5 28.5h-33z"/><path d="M32 51l-16.5-28.5h33z" opacity=".55"/><circle cx="32" cy="32" r="3.5"/></g>'),
    };
    // gládio de ponta para cima: lâmina larga (o número fica nela), guarda, empunhadura e pomo
    const ESPADA = SVG('<path d="M16 .8C20 4.5 25 8 25 12.5L23.5 25h-15L7 12.5C7 8 12 4.5 16 .8z' +
        'M2.5 25Q16 28.2 29.5 25v3.6Q16 31.8 2.5 28.6zM13.6 30.4h4.8V35h-4.8zM16 34.2a2.9 2.9 0 1 0 .001 0z"/>', '0 0 32 40');
    // nomes compridos diminuem; os muito compridos quebram em duas linhas
    const tamNome = n => { const k = String(n || '').length; return k > 18 ? ' jg-nome-2l' : (k > 11 ? ' jg-nome-longo' : ''); };
    const GOTA = SVG('<path d="M16 2c5 8 11 13.5 11 19.5A11 11 0 0 1 5 21.5C5 15.5 11 10 16 2z"/>', '0 0 32 32');

    /**
     * c: carta { nome, tipo, custo, ataque, vida, efeito, valor, descricao, imagem }.
     * o: { atk, vida, max (valores atuais na mesa), classe, attrs }
     */
    function carta(c, o) {
        o = o || {};
        const lenda = c.tipo === 'lenda';
        const atk = o.atk != null ? o.atk : c.ataque;
        const vida = o.vida != null ? o.vida : c.vida;
        const ferida = o.max != null && vida < o.max ? ' jg-ferida' : '';
        const arte = c.imagem
            ? `<img src="${esc(c.imagem)}" alt="" draggable="false">`
            : `<span class="jg-carta-emblema">${EMBLEMA[lenda ? 'lenda' : 'feitico']}</span>`;
        return `<div class="jg-carta jg-carta--${lenda ? 'lenda' : 'feitico'} ${o.classe || ''}" ${o.attrs || ''} title="${esc(c.descricao || '')}">` +
            `<span class="jg-carta-custo" title="Custo em energia"><b>${esc(c.custo)}</b></span>` +
            `<div class="jg-carta-arte">${arte}</div>` +
            `<div class="jg-carta-nome${tamNome(c.nome)}"><span>${esc(c.nome)}</span></div>` +
            `<div class="jg-carta-tipo">${lenda ? 'Lenda' : 'Feitiço'}</div>` +
            `<div class="jg-carta-texto">${esc(lenda ? (c.descricao || '') : textoEfeito(c))}</div>` +
            (lenda ? `<div class="jg-carta-atributos">` +
                `<span class="jg-carta-atk" title="Ataque">${ESPADA}<b>${esc(atk)}</b></span>` +
                `<span class="jg-carta-vida${ferida}" title="Vida">${GOTA}<b>${esc(vida)}</b></span></div>` : '') +
            `</div>`;
    }

    /* ---------- Pela Dama ---------- */
    /** Desenho do movimento. tipo: { regras, especial }. Para o Peão, mostra o movimento fixo. */
    function diagrama(tipo, tam) {
        const regras = (tipo && tipo.regras) || [];
        const marcas = {};
        let n = 2;
        regras.forEach(r => { if (!r.d) n = Math.max(n, Math.abs(r.dx), Math.abs(r.dy)); else n = Math.max(n, 3); });
        n = Math.min(n, 7);
        const marcar = (dx, dy, m, raio) => {
            if (Math.abs(dx) > n || Math.abs(dy) > n) return;
            const k = dx + ',' + dy;
            if (!marcas[k]) marcas[k] = { m, raio };
        };
        if (tipo && tipo.especial === 'peao') {
            marcar(0, 1, 'mover'); marcar(0, 2, 'mover', true); marcar(-1, 1, 'capturar'); marcar(1, 1, 'capturar');
        }
        regras.forEach(r => {
            if (!r.d) { marcar(r.dx, r.dy, r.m); return; }
            for (let k = 1; k <= r.alc; k++) marcar(r.dx * k, r.dy * k, r.m, true);
        });
        let h = `<div class="jg-diagrama" style="--n:${2 * n + 1};${tam ? '--t:' + tam + 'px' : ''}">`;
        for (let dy = n; dy >= -n; dy--) {
            for (let dx = -n; dx <= n; dx++) {
                const mk = marcas[dx + ',' + dy];
                const cls = dx === 0 && dy === 0 ? ' jg-centro' : (mk ? ` jg-m-${mk.m}${mk.raio ? ' jg-raio' : ''}` : '');
                h += `<i class="${cls.trim()}"></i>`;
            }
        }
        return h + '</div>';
    }

    return { esc, post, carta, textoEfeito, diagrama };
})();
