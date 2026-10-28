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
            : `<span class="jg-carta-icone" aria-hidden="true">${lenda ? '⚔️' : '✨'}</span>`;
        return `<div class="jg-carta jg-carta--${lenda ? 'lenda' : 'feitico'} ${o.classe || ''}" ${o.attrs || ''} title="${esc(c.descricao || '')}">` +
            `<span class="jg-carta-custo" title="Custo em energia">${esc(c.custo)}</span>` +
            `<div class="jg-carta-arte">${arte}</div>` +
            `<div class="jg-carta-nome">${esc(c.nome)}</div>` +
            `<div class="jg-carta-tipo">${lenda ? 'Lenda' : 'Feitiço'}</div>` +
            `<div class="jg-carta-texto">${esc(lenda ? (c.descricao || '') : textoEfeito(c))}</div>` +
            (lenda ? `<span class="jg-carta-atk" title="Ataque">${esc(atk)}</span><span class="jg-carta-vida${ferida}" title="Vida">${esc(vida)}</span>` : '') +
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
