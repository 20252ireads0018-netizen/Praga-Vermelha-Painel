/**
 * Campanhas: sincroniza as alterações nas fichas (complementa o campanha.js).
 * - Consulta /campanhas/{id}/fichas-estado a cada 4s (pausa com a aba escondida).
 * - O servidor já filtra: o mestre recebe todas as fichas, o jogador só as próprias.
 * - Quando a "assinatura" de uma ficha muda:
 *     · atualiza os data-* do botão (a próxima abertura da miniatura já vem certa);
 *     · atualiza a miniatura se ela estiver aberta (dialog[data-id] na pilha);
 *     · destaca a linha/miniatura e mostra o selo "atualizada" (some ao clicar na ficha).
 * - Se a lista de fichas mudou (vínculo novo ou removido), mostra o aviso "Atualizar página".
 * Carregue DEPOIS do campanha.js.
 */
(function () {
    const app = document.getElementById('camp-app');
    if (!app) return;

    const url = app.dataset.fichasEstadoUrl;
    if (!url) return;

    const pilha = document.getElementById('camp-ficha-pilha');

    const INTERVALO = 4000;
    const INTERVALO_MAX = 30000;
    const CHAVES = ['pv', 'aura', 'sanidade'];

    let espera = INTERVALO;
    let timer = null;
    let consultando = false;

    const botoes = () => [...app.querySelectorAll('[data-ficha-abrir]')];
    const vazioSeNulo = (v) => (v === null || v === undefined ? '' : String(v));

    function piscar(el) {
        if (!el) return;
        el.classList.remove('camp-flash');
        void el.offsetWidth; // reinicia a animação
        el.classList.add('camp-flash');
        setTimeout(() => el.classList.remove('camp-flash'), 2600);
    }

    // Mesma regra de exibição do campanha.js (preencher)
    function pintarStat(painel, chave, atual, max) {
        const box = painel.querySelector('.js-stat[data-stat="' + chave + '"]');
        if (!box) return;
        const texto = box.querySelector('.js-stat-texto');
        const barra = box.querySelector('.js-stat-barra');

        if (atual === null || atual === undefined || Number.isNaN(atual)) {
            texto.textContent = '—';
            barra.style.width = '0%';
            return;
        }
        const temMax = max !== null && max !== undefined && max !== 0;
        texto.textContent = temMax ? atual + ' / ' + max : String(atual);
        barra.style.width = (temMax ? Math.max(0, Math.min(100, (atual / max) * 100)) : 100) + '%';
    }

    function atualizarPainelAberto(botao, ficha) {
        if (!pilha) return false;
        const painel = pilha.querySelector('dialog[data-id="' + botao.dataset.id + '"]');
        if (!painel) return false;

        const nome = ficha.nome || 'Ficha';
        const nomeEl = painel.querySelector('.js-ficha-nome');
        if (nomeEl) nomeEl.textContent = nome;

        // sem foto: a inicial acompanha o nome
        const fotoBox = painel.querySelector('.js-ficha-foto');
        if (fotoBox && !fotoBox.querySelector('img')) {
            fotoBox.textContent = nome.trim().charAt(0).toUpperCase();
        }

        CHAVES.forEach((k) => {
            const s = (ficha.stats && ficha.stats[k]) || {};
            pintarStat(painel, k, s.atual, s.max);
        });

        piscar(painel);
        return painel.open;
    }

    function aplicar(botao, ficha) {
        // 1) dados do botão (é de onde o campanha.js lê ao abrir a miniatura)
        botao.dataset.nome = ficha.nome;
        CHAVES.forEach((k) => {
            const s = (ficha.stats && ficha.stats[k]) || {};
            botao.dataset[k] = vazioSeNulo(s.atual);
            botao.dataset[k + 'Max'] = vazioSeNulo(s.max);
        });
        botao.dataset.assinatura = ficha.assinatura;

        // 2) texto da lista
        const strong = botao.querySelector('.camp-clicavel-info strong');
        if (strong) strong.textContent = ficha.nome;

        const avatar = botao.querySelector('.camp-avatar');
        if (avatar && !avatar.querySelector('img')) {
            avatar.textContent = (ficha.nome || '?').trim().charAt(0).toUpperCase();
        }

        // 3) miniatura aberta, se houver
        const estavaAberta = atualizarPainelAberto(botao, ficha);

        // 4) destaque na lista; o selo só aparece se o mestre ainda não está olhando a miniatura
        piscar(botao.closest('.camp-linha'));
        const selo = botao.querySelector('.js-ficha-atualizada');
        if (selo && !estavaAberta) selo.hidden = false;
    }

    function processar(dados) {
        const porId = {};
        (dados.fichas || []).forEach((f) => { porId[String(f.id)] = f; });

        const idsTela = botoes().map((b) => b.dataset.id).sort().join(',');
        const idsServidor = Object.keys(porId).sort().join(',');
        if (idsTela !== idsServidor) {
            const aviso = document.getElementById('camp-sync-aviso');
            if (aviso) aviso.hidden = false; // ficha vinculada/desvinculada: pede recarga
        }

        botoes().forEach((botao) => {
            const ficha = porId[botao.dataset.id];
            if (ficha && botao.dataset.assinatura !== ficha.assinatura) aplicar(botao, ficha);
        });
    }

    function agendar() {
        clearTimeout(timer);
        timer = setTimeout(consultar, espera);
    }

    async function consultar() {
        if (document.hidden || consultando) { agendar(); return; }
        consultando = true;
        try {
            const r = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            processar(await r.json());
            espera = INTERVALO;
        } catch (err) {
            espera = Math.min(espera * 2, INTERVALO_MAX); // rede instável: recua
        } finally {
            consultando = false;
            agendar();
        }
    }

    // Ao abrir a miniatura, o selo "atualizada" some.
    app.addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-ficha-abrir]');
        if (!b) return;
        const selo = b.querySelector('.js-ficha-atualizada');
        if (selo) selo.hidden = true;
    });

    const recarregar = document.getElementById('camp-sync-recarregar');
    if (recarregar) recarregar.addEventListener('click', () => location.reload());

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { espera = INTERVALO; clearTimeout(timer); consultar(); }
    });

    agendar();
})();