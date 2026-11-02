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
        Object.keys(dados || {}).forEach(k => {
            const v = dados[k];
            if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x)); // listas vão como campo[]
            else if (v != null) fd.append(k, v);
        });
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
    // Palavras-chave das Lendas (mesmas chaves de App\Jogos\Gladio::PALAVRAS): nome e o efeito mostrado ao passar o mouse
    const PALAVRA = {
        sobrepujar:    { nome: 'Sobrepujar', desc: 'Se for bloqueada, o dano que passar do necessário para derrubar quem bloqueou vai para o jogador.' },
        impeto:        { nome: 'Pressa', desc: 'Não fica cansada ao ser invocada: pode atacar no mesmo turno em que entra na mesa.' },
        voar:          { nome: 'Voar', desc: 'Só pode ser bloqueada por Lendas com Voar ou Alcance.' },
        alcance:       { nome: 'Alcance', desc: 'Pode bloquear Lendas com Voar.' },
        toque:         { nome: 'Toque mortal', desc: 'Qualquer dano que ela causar a uma Lenda a derruba (basta 1 de dano).' },
        vinculo:       { nome: 'Vínculo com a vida', desc: 'Todo dano que ela causa cura quem a controla na mesma quantidade (até a vida máxima).' },
        iniciativa:    { nome: 'Iniciativa', desc: 'No combate, causa dano antes das Lendas sem Iniciativa. Se derrubar a outra, não apanha dela.' },
        duplo:         { nome: 'Golpe duplo', desc: 'No combate, causa dano duas vezes: junto com as de Iniciativa e de novo no dano normal.' },
        vigilancia:    { nome: 'Vigilância', desc: 'Atacar não a deixa virada: ela continua podendo bloquear no turno do adversário.' },
        defensor:      { nome: 'Defensor', desc: 'Não pode atacar.' },
        ameaca:        { nome: 'Ameaça', desc: 'Só pode ser bloqueada por duas ou mais Lendas.' },
        indestrutivel: { nome: 'Indestrutível', desc: 'Não cai por dano nem por Toque mortal: a vida não baixa de 1. Ainda pode ser sacrificada.' },
        resistencia:   { nome: 'Hexo-Imune', desc: 'Não pode ser alvo de feitiços do adversário.' },
        desafiador:    { nome: 'Desafiador', desc: 'Ao atacar, chama uma Lenda do atacado para o combate: ela é obrigada a bloqueá-la.' },
        imbloqueavel:  { nome: 'Imbloqueável', desc: 'Não pode ser bloqueada.' },
        sombra:        { nome: 'Sombra', desc: 'Só pode ser bloqueada por Lendas com Sombra, e só bloqueia Lendas com Sombra.' },
        furtividade:   { nome: 'Furtividade', desc: 'Não pode ser bloqueada por Lendas com mais poder (ataque) que ela.' },
        flanquear:     { nome: 'Flanquear', desc: 'Toda Lenda sem Flanquear que a bloquear fica com -1/-1 até o fim do turno.' },
        exaltado:      { nome: 'Exaltado', desc: 'Quando uma Lenda sua ataca sozinha, ela ganha +1/+1 até o fim do turno para cada Lenda sua com Exaltado.' },
        bravura:       { nome: 'Bravura', desc: 'Sempre que você conjurar uma carta que não seja Lenda, ela ganha +1/+1 até o fim do turno.' },
        grito:         { nome: 'Grito de guerra', desc: 'Quando ataca, as outras Lendas que atacam junto ganham +1/+0 até o fim do turno.' },
        bushido:       { nome: 'Bushido', desc: 'Quando bloqueia ou é bloqueada, ganha +1/+1 até o fim do turno.' },
        persistir:     { nome: 'Persistir', desc: 'A primeira vez que cair, volta para a mesa com -1/-1.' },
        imortal:       { nome: 'Imortal', desc: 'A primeira vez que cair, volta para a mesa com +1/+1.' },
    };
    /** Tipos de carta (Gladio::NOMES_TIPOS). */
    const TIPOS = { lenda: 'Lenda', feitico: 'Feitiço', encanto: 'Encantamento', equipo: 'Equipamento' };
    const palavras = c => (Array.isArray(c.palavras) ? c.palavras : String(c.palavras || '').split(',')).map(s => s.trim()).filter(Boolean);
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
        const equipo = c.tipo === 'equipo';
        const atk = o.atk != null ? o.atk : c.ataque;
        const vida = o.vida != null ? o.vida : c.vida;
        const ferida = o.max != null && vida < o.max ? ' jg-ferida' : '';
        // o texto da carta é só a descrição escrita pelo mestre; no feitiço, o efeito automático fica na dica ao passar o mouse
        const texto = c.descricao || '';
        const dica = lenda ? texto : [texto, textoEfeito(c)].filter(Boolean).join('\n\n');
        // palavras-chave: da carta ou, na mesa, as que a Lenda tem agora (o.pal)
        const pal = (o.pal || palavras(c)).filter(p => PALAVRA[p]);
        const arte = c.imagem
            ? `<img src="${esc(c.imagem)}" alt="" draggable="false">`
            : `<span class="jg-carta-emblema">${EMBLEMA[lenda ? 'lenda' : 'feitico']}</span>`;
        return `<div class="jg-carta jg-carta--${lenda ? 'lenda' : 'feitico'} jg-carta--t-${esc(c.tipo || 'feitico')} ${o.classe || ''}" ${o.attrs || ''} title="${esc(dica)}">` +
            `<span class="jg-carta-custo" title="Custo em energia"><b>${esc(c.custo)}</b></span>` +
            `<div class="jg-carta-arte">${arte}</div>` +
            `<div class="jg-carta-nome${tamNome(c.nome)}"><span>${esc(c.nome)}</span></div>` +
            `<div class="jg-carta-tipo">${esc(TIPOS[c.tipo] || 'Feitiço')}${c.subtipos ? ` · <span class="jg-carta-sub">${esc(c.subtipos)}</span>` : ''}</div>` +
            (pal.length ? `<div class="jg-carta-palavras">${pal.map(p =>
                `<span class="jg-palavra" tabindex="0" title="${esc(PALAVRA[p].nome + ': ' + PALAVRA[p].desc)}">${esc(PALAVRA[p].nome)}</span>`).join(' · ')}</div>` : '') +
            `<div class="jg-carta-texto">${esc(texto)}</div>` +
            (lenda ? `<div class="jg-carta-atributos">` +
                `<span class="jg-carta-atk" title="Ataque">${ESPADA}<b>${esc(atk)}</b></span>` +
                `<span class="jg-carta-vida${ferida}" title="Vida">${GOTA}<b>${esc(vida)}</b></span></div>`
                : (equipo && (+c.ataque || +c.vida) ? `<div class="jg-carta-atributos jg-carta-bonus" title="Bônus para a Lenda equipada">` +
                    `<span class="jg-carta-atk">${ESPADA}<b>+${esc(+c.ataque || 0)}</b></span><span class="jg-carta-vida">${GOTA}<b>+${esc(+c.vida || 0)}</b></span></div>` : '')) +
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

    /* Encaixe do texto: cada carta desenhada na página é medida e, enquanto a descrição não cabe, desce um nível
       (letra menor e arte mais baixa, ver data-encaixe em jogos.css). Vale para lobby, partida e prévias, sem que
       cada tela precise chamar nada. O ResizeObserver também cobre cartas que nascem escondidas (abas, janelas). */
    const NIVEIS = 5;
    function encaixar(carta) {
        const t = carta.querySelector('.jg-carta-texto');
        if (!t || !carta.clientHeight) return;
        carta.classList.remove('jg-carta--estoura');
        for (let n = 0; n < NIVEIS; n++) {
            carta.dataset.encaixe = n;
            if (t.scrollHeight <= t.clientHeight + 1) return;
        }
        carta.classList.add('jg-carta--estoura');
    }
    const medidor = window.ResizeObserver ? new ResizeObserver(lista => lista.forEach(en => encaixar(en.target))) : null;
    const cartasEm = no => (no.nodeType !== 1 ? [] : (no.matches('.jg-carta') ? [no] : Array.from(no.querySelectorAll('.jg-carta'))));
    if (medidor) {
        new MutationObserver(mudancas => mudancas.forEach(m => {
            m.addedNodes.forEach(no => cartasEm(no).forEach(c => medidor.observe(c)));
            m.removedNodes.forEach(no => cartasEm(no).forEach(c => medidor.unobserve(c)));
        })).observe(document.documentElement, { childList: true, subtree: true });
        document.querySelectorAll('.jg-carta').forEach(c => medidor.observe(c));
    }

    // Tocar (ou clicar) numa palavra-chave mostra o efeito num balão — no celular não existe "passar o mouse".
    // Fica na fase de captura para o toque não jogar nem escolher a carta.
    let balao = null;
    document.addEventListener('click', e => {
        const p = e.target.closest('.jg-palavra');
        if (balao) { balao.remove(); balao = null; }
        if (!p) return;
        e.stopPropagation();
        e.preventDefault();
        balao = document.createElement('div');
        balao.className = 'jg-balao-palavra';
        balao.textContent = p.title;
        document.body.appendChild(balao);
        const r = p.getBoundingClientRect();
        const w = balao.offsetWidth;
        balao.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2)) + 'px';
        balao.style.top = Math.max(8, r.top - balao.offsetHeight - 8) + 'px';
        const este = balao;
        setTimeout(() => { if (balao === este) { este.remove(); balao = null; } }, 4000);
    }, true);

    return { esc, post, carta, textoEfeito, diagrama, PALAVRA, TIPOS };
})();
