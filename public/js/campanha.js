/**
 * Campanhas: sincroniza efeitos sonoros e estado da sessão.
 * - Todos (mestre e jogadores) consultam /campanhas/{id}/estado a cada 2s.
 * - Quando o som_seq muda, toca ou para o som indicado.
 * - O navegador só libera áudio após um clique, por isso o botão "Ativar som".
 * - Clique em uma ficha abre uma miniatura flutuante (nome, foto, PVs, Aura e Sanidade).
 *   Cada ficha clicada abre a sua própria miniatura, empilhada embaixo das já abertas.
 */
(function () {
    const app = document.getElementById('camp-app');
    if (!app) return;

    const estadoUrl = app.dataset.estadoUrl;
    const somUrlTpl = app.dataset.somUrl;
    const csrf = app.dataset.csrf;
    const sessaoInicial = app.dataset.sessao === '1';

    const btnAtivar = app.querySelector('.js-ativar-som');
    const volume = app.querySelector('.js-volume');
    const status = app.querySelector('.js-som-status');

    // WAV vazio usado só para "destravar" o áudio com o clique do usuário
    const SILENCIO = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAIlYAAESsAAACABAAZGF0YQAAAAA=';

    const audio = new Audio();
    audio.preload = 'auto';
    let liberado = false;
    let ultimaSeq = null; // null = ainda não sincronizou (não toca sons antigos ao abrir a página)

    function msg(texto) {
        if (status) status.textContent = texto;
    }

    if (volume) {
        audio.volume = parseFloat(volume.value);
        volume.addEventListener('input', () => { audio.volume = parseFloat(volume.value); });
    }

    if (btnAtivar) {
        btnAtivar.addEventListener('click', () => {
            audio.src = SILENCIO;
            audio.play().then(() => {
                liberado = true;
                btnAtivar.textContent = '🔊 Som ativado';
                btnAtivar.disabled = true;
                msg('Você ouvirá os efeitos do mestre.');
            }).catch(() => msg('Não foi possível ativar o som neste navegador.'));
        });
    }

    function tocar(somId) {
        if (!liberado) {
            msg('Um som foi tocado, mas o áudio não está ativado. Clique em "Ativar som".');
            return;
        }
        audio.src = somUrlTpl.replace('__ID__', encodeURIComponent(somId));
        audio.currentTime = 0;
        audio.play().then(() => msg('Tocando efeito sonoro…'))
            .catch(() => msg('Não foi possível tocar o som.'));
    }

    function parar() {
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
        msg('Som parado.');
    }

    let consultando = false;
    async function consultar() {
        if (consultando || document.hidden) return;
        consultando = true;
        try {
            const r = await fetch(estadoUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) return;
            const e = await r.json();

            // sessão iniciada/encerrada: recarrega para atualizar botões e painéis
            if (e.sessao_ativa !== sessaoInicial) {
                location.reload();
                return;
            }

            if (ultimaSeq === null) {
                ultimaSeq = e.som_seq;
            } else if (e.som_seq > ultimaSeq) {
                ultimaSeq = e.som_seq;
                if (e.som_acao === 'tocar' && e.som_id) tocar(e.som_id);
                else parar();
            }
        } catch (err) {
            /* rede instável: tenta de novo no próximo ciclo */
        } finally {
            consultando = false;
        }
    }

    setInterval(consultar, 2000);
    consultar();

    // ----- Controles do mestre -----
    async function postar(url) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!r.ok) {
            let texto = 'Não foi possível enviar o comando.';
            try { texto = (await r.json()).erro || texto; } catch (e) {}
            alert(texto);
            return;
        }
        consultar();
    }

    app.addEventListener('click', (ev) => {
        const tocarBtn = ev.target.closest('[data-som-tocar]');
        if (tocarBtn) return postar(tocarBtn.dataset.somTocar);
        const pararBtn = ev.target.closest('[data-som-parar]');
        if (pararBtn) return postar(pararBtn.dataset.somParar);
    });

    // ----- Miniaturas das fichas (empilhadas) -----
    const pilha = document.getElementById('camp-ficha-pilha');
    const tpl = document.getElementById('camp-ficha-tpl');

    if (pilha && tpl) {
        const CHAVE = 'camp-fichas-abertas:' + location.pathname;
        const num = (v) => (v === '' || v === undefined || v === null ? null : parseFloat(v));

        // lembra quais fichas estão abertas (sobrevivem ao recarregar, ex.: depois de trocar a foto)
        function salvar() {
            try {
                const ids = [...pilha.querySelectorAll('dialog')].map((p) => p.dataset.id);
                sessionStorage.setItem(CHAVE, JSON.stringify(ids));
            } catch (e) {}
        }

        function preencher(p, d) {
            const q = (s) => p.querySelector(s);
            const nome = d.nome || 'Ficha';

            // foto ou inicial
            const fotoBox = q('.js-ficha-foto');
            if (fotoBox) {
                fotoBox.textContent = '';
                if (d.foto) {
                    const img = document.createElement('img');
                    img.src = d.foto;
                    img.alt = 'Foto de ' + nome;
                    fotoBox.appendChild(img);
                } else {
                    fotoBox.textContent = nome.trim().charAt(0).toUpperCase();
                }
            }

            q('.js-ficha-nome').textContent = nome;
            q('.js-ficha-jogador').textContent = d.jogador ? 'de ' + d.jogador : '';

            // PVs, Aura e Sanidade
            p.querySelectorAll('.js-stat').forEach((box) => {
                const chave = box.dataset.stat; // pv | aura | sanidade
                const atual = num(d[chave]);
                const max = num(d[chave + 'Max']);
                const texto = box.querySelector('.js-stat-texto');
                const barra = box.querySelector('.js-stat-barra');

                if (atual === null || Number.isNaN(atual)) {
                    texto.textContent = '—';
                    barra.style.width = '0%';
                    return;
                }
                texto.textContent = max ? atual + ' / ' + max : String(atual);
                barra.style.width = (max ? Math.max(0, Math.min(100, (atual / max) * 100)) : 100) + '%';
            });

            // link para a ficha completa (só o dono recebe)
            const linkEl = q('.js-ficha-link');
            if (d.link) { linkEl.href = d.link; linkEl.hidden = false; }
            else { linkEl.hidden = true; }

            // envio de foto (só o dono)
            const formFoto = q('.js-foto-form');
            if (d.dono === '1' && d.upload) {
                formFoto.action = d.upload;
                formFoto.hidden = false;
            } else {
                formFoto.hidden = true;
                formFoto.removeAttribute('action');
            }
            q('.js-foto-input').value = '';

            // edição dos vitais (mestre ou dono): mostra os controles e preenche os campos
            const podeEditar = !!d.vitais;
            p.dataset.vitaisUrl = d.vitais || '';
            p.querySelectorAll('.js-stat').forEach((box) => {
                const ed = box.querySelector('.js-stat-editar');
                if (!ed) return;
                ed.hidden = !podeEditar;
                if (!podeEditar) return;
                const chave = box.dataset.stat;
                const inA = ed.querySelector('.js-stat-atual');
                const inM = ed.querySelector('.js-stat-max');
                if (document.activeElement !== inA) inA.value = (d[chave] === undefined || d[chave] === null) ? '' : d[chave];
                if (document.activeElement !== inM) inM.value = (d[chave + 'Max'] === undefined || d[chave + 'Max'] === null) ? '' : d[chave + 'Max'];
            });
        }

        function criarPainel(id) {
            const p = tpl.content.firstElementChild.cloneNode(true);
            p.dataset.id = id;

            // ao fechar, o painel sai da pilha e os de baixo sobem
            p.addEventListener('close', () => { p.remove(); salvar(); });

            // trocar foto: escolhe o arquivo e envia na hora
            const formFoto = p.querySelector('.js-foto-form');
            const inputFoto = p.querySelector('.js-foto-input');
            const btnFoto = p.querySelector('.js-foto-escolher');
            btnFoto.addEventListener('click', () => inputFoto.click());
            inputFoto.addEventListener('change', () => {
                if (inputFoto.files.length) formFoto.submit();
            });

            // ----- vitais: − / + e digitação direta (mestre ou dono); grava na ficha real -----
            const CAMPOS_VITAIS = {
                pv: ['vida_atual', 'vida_maxima'],
                aura: ['aura_atual', 'aura_maxima'],
                sanidade: ['sanidade_atual', 'sanidade_maxima'],
            };
            let timerVitais = null;

            function lerVitais(box) {
                const [cA, cM] = CAMPOS_VITAIS[box.dataset.stat];
                const ed = box.querySelector('.js-stat-editar');
                const a = ed.querySelector('.js-stat-atual').value;
                const m = ed.querySelector('.js-stat-max').value;
                const corpo = {};
                if (a !== '') corpo[cA] = parseInt(a, 10);
                if (m !== '') corpo[cM] = parseInt(m, 10);
                return corpo;
            }

            async function enviarVitais(box) {
                const url = p.dataset.vitaisUrl;
                if (!url) return;
                const corpo = lerVitais(box);
                if (!Object.keys(corpo).length) return;
                box.classList.add('camp-stat-salvando');
                try {
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(corpo),
                    });
                    const j = await r.json().catch(() => ({}));
                    if (!r.ok) {
                        alert(j.erro || (j.errors ? Object.values(j.errors)[0][0] : 'Não foi possível salvar os vitais.'));
                        return;
                    }
                    // o servidor devolve o estado real da ficha: atualiza o botão da lista e a miniatura
                    const b = app.querySelector('[data-ficha-abrir][data-id="' + p.dataset.id + '"]');
                    if (b) {
                        ['pv', 'aura', 'sanidade'].forEach((k) => {
                            b.dataset[k] = (j[k] === null || j[k] === undefined) ? '' : String(j[k]);
                            b.dataset[k + 'Max'] = (j[k + 'Max'] === null || j[k + 'Max'] === undefined) ? '' : String(j[k + 'Max']);
                        });
                        preencher(p, b.dataset);
                    }
                } catch (err) {
                    alert('Falha de conexão ao salvar os vitais.');
                } finally {
                    box.classList.remove('camp-stat-salvando');
                }
            }

            p.addEventListener('click', (ev) => {
                const btn = ev.target.closest('.js-stat-delta');
                if (!btn) return;
                const box = btn.closest('.js-stat');
                const inA = box.querySelector('.js-stat-atual');
                inA.value = (parseInt(inA.value, 10) || 0) + parseInt(btn.dataset.delta, 10);
                clearTimeout(timerVitais);
                enviarVitais(box);
            });
            p.addEventListener('input', (ev) => {
                if (!ev.target.classList.contains('js-stat-atual') && !ev.target.classList.contains('js-stat-max')) return;
                const box = ev.target.closest('.js-stat');
                clearTimeout(timerVitais);
                timerVitais = setTimeout(() => enviarVitais(box), 600);
            });

            pilha.appendChild(p); // entra sempre embaixo dos que já estão abertos
            return p;
        }

        function abrirFicha(d, rolar = true) {
            let p = pilha.querySelector('dialog[data-id="' + d.id + '"]');
            if (!p) p = criarPainel(d.id);

            // se algo falhar ao preencher, a miniatura abre mesmo assim
            try { preencher(p, d); } catch (err) { console.error('Miniatura da ficha:', err); }

            // show() (e não showModal()): abre sem escurecer e sem bloquear o resto da página
            if (!p.open) {
                try { p.show(); } catch (err) { p.setAttribute('open', ''); }
            }

            salvar();
            if (rolar) p.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        app.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-ficha-abrir]');
            if (b) abrirFicha(b.dataset);
        });

        // Esc fecha a miniatura de baixo (a mais recente)
        document.addEventListener('keydown', (ev) => {
            if (ev.key !== 'Escape') return;
            const abertos = pilha.querySelectorAll('dialog[open]');
            if (abertos.length) abertos[abertos.length - 1].close();
        });

        // reabre as fichas que estavam abertas antes de recarregar a página
        try {
            const ids = JSON.parse(sessionStorage.getItem(CHAVE) || '[]');
            ids.forEach((id) => {
                const b = app.querySelector('[data-ficha-abrir][data-id="' + id + '"]');
                if (b) abrirFicha(b.dataset, false);
            });
        } catch (e) {}
    }

    // ----- Efeitos sonoros: lembra se a lista está aberta ou recolhida -----
    const sonsDet = document.querySelector('.js-sons-detalhes');
    if (sonsDet) {
        const chaveSons = 'camp-sons-aberto:' + location.pathname;
        try { const v = localStorage.getItem(chaveSons); if (v !== null) sonsDet.open = v === '1'; } catch (e) {}
        sonsDet.addEventListener('toggle', () => { try { localStorage.setItem(chaveSons, sonsDet.open ? '1' : '0'); } catch (e) {} });
    }
})();