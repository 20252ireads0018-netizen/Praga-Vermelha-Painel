/**
 * Miniatura do Gladio: enquanto o jogador tem uma partida em andamento, uma janelinha acompanha ele por todo o site.
 * Recolhida, mostra de quem é a vez; expandida, traz a mesa inteira (a página da partida em modo compacto, num
 * iframe) para jogar sem sair da página. Fechar esconde a partida até ela voltar a esperar por ele.
 */
(function () {
    const raiz = document.getElementById('jg-mini');
    if (!raiz) return;
    const CHAVE = 'jg-mini';
    const ler = () => { try { return JSON.parse(localStorage.getItem(CHAVE)) || {}; } catch (_) { return {}; } };
    const gravar = o => { try { localStorage.setItem(CHAVE, JSON.stringify(Object.assign(ler(), o))); } catch (_) { /* sem armazenamento */ } };
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    let atual = null;      // partida mostrada
    let tinhaVez = null;   // para reabrir quando a vez chega
    let iframe = null;

    function status(p) {
        if (p.pausa) return icone('pausa') + ' Pausada pelo mestre';
        if (p.mulligan) return 'Mão inicial: decida o mulligan';
        return p.minha ? icone('espadas') + ' Sua vez!' : `Vez de ${esc(p.espera)}`;
    }

    function render() {
        const p = atual;
        const st = ler();
        if (!p || st.fechada === p.id) { raiz.hidden = true; fecharIframe(); return; }
        const aberta = st.aberta === p.id;
        raiz.hidden = false;
        raiz.className = 'jg-mini' + (aberta ? ' jg-mini--aberta' : '') + (p.minha ? ' jg-mini--vez' : '');
        let topo = raiz.querySelector('.jg-mini-topo');
        if (!topo) {
            raiz.innerHTML = '<div class="jg-mini-topo"></div><div class="jg-mini-corpo"></div>';
            topo = raiz.querySelector('.jg-mini-topo');
        }
        const html =
            `<button type="button" class="jg-mini-info" data-mini="alternar" title="${aberta ? 'Recolher' : 'Expandir a mesa aqui'}">` +
                `<span class="jg-mini-titulo">${icone('espadas')} Gladio${p.campanha ? ' · ' + esc(p.campanha) : ''}</span>` +
                `<span class="jg-mini-status">${status(p)}</span>` +
                `<span class="jg-mini-dados">Turno ${p.turno} · ${icone('coracao')} ${Number(p.vida).toLocaleString('pt-BR')} · ${icone('carta')} ${p.mao}</span>` +
            `</button>` +
            `<span class="jg-mini-botoes">` +
                `<button type="button" data-mini="alternar" title="${aberta ? 'Recolher' : 'Expandir'}" aria-label="${aberta ? 'Recolher' : 'Expandir'}">${aberta ? '▾' : '▴'}</button>` +
                `<a href="${esc(p.url)}" title="Abrir a página da partida" aria-label="Abrir a página da partida">↗</a>` +
                `<button type="button" data-mini="fechar" title="Fechar (volta quando for a sua vez)" aria-label="Fechar">✕</button>` +
            `</span>`;
        if (topo.dataset.html !== html) { topo.dataset.html = html; topo.innerHTML = html; } // sem mudança, não redesenha (o clique não se perde)
        if (aberta) abrirIframe(p); else fecharIframe();
    }

    function abrirIframe(p) {
        const corpo = raiz.querySelector('.jg-mini-corpo');
        const src = p.url + (p.url.includes('?') ? '&' : '?') + 'mini=1';
        if (iframe && iframe.dataset.id === String(p.id)) return;
        corpo.innerHTML = '';
        iframe = document.createElement('iframe');
        iframe.dataset.id = String(p.id);
        iframe.title = 'Mesa do Gladio';
        iframe.src = src;
        corpo.appendChild(iframe);
    }
    function fecharIframe() {
        if (iframe) { iframe.remove(); iframe = null; }
    }

    raiz.addEventListener('click', ev => {
        const b = ev.target.closest('[data-mini]');
        if (!b || !atual) return;
        const st = ler();
        if (b.dataset.mini === 'alternar') gravar({ aberta: st.aberta === atual.id ? null : atual.id });
        else if (b.dataset.mini === 'fechar') gravar({ fechada: atual.id, aberta: null });
        render();
    });

    async function atualizar() {
        try {
            const r = await fetch(raiz.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!r.ok) return;
            const lista = (await r.json()).partidas || [];
            const st = ler();
            // a partida que ele expandiu continua; senão, a que espera por ele (ou a mais recente)
            atual = lista.find(p => p.id === st.aberta) || lista[0] || null;
            if (atual) {
                // fechada: volta sozinha quando a vez chega de novo
                if (st.fechada === atual.id && atual.minha && tinhaVez === false) gravar({ fechada: null });
                tinhaVez = atual.minha;
            }
            render();
        } catch (_) { /* tenta de novo */ }
    }
    atualizar();
    setInterval(() => { if (!document.hidden) atualizar(); }, 6000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) atualizar(); });
})();
