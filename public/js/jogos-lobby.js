/**
 * Jogos da campanha: lobby (Gladio e Pela Dama).
 * Desafios, partidas, Dinheiro, criador de cartas (mestre), criador de peças e exército (cada jogador).
 * A lista de partidas e o Dinheiro são consultados a cada 3 s.
 */
(function () {
    'use strict';

    const raiz = document.getElementById('jg-lobby');
    const dadosEl = document.getElementById('jg-dados');
    if (!raiz || !dadosEl) return;

    const D = JSON.parse(dadosEl.textContent);
    const { esc, post } = window.JG;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const ls = {
        get(k) { try { return localStorage.getItem(k); } catch (_) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (_) { /* ignora */ } },
    };
    const urlPartida = (id, acao) => `${D.url.base}/partidas/${id}${acao ? '/' + acao : ''}`;

    let lobby = D.lobby;
    let cartas = D.cartas;
    let pecas = D.pecas;
    let imagens = D.imagens || []; // galeria da campanha: { id, pasta, nome, url }

    function msg(t, erro) {
        const el = $('#jg-msg');
        el.textContent = t || '';
        el.hidden = !t;
        el.classList.toggle('jg-msg--erro', !!erro);
        if (t && !erro) setTimeout(() => { if (el.textContent === t) el.hidden = true; }, 4000);
    }
    const falhou = e => msg(e.message, true);

    /* ---------- abas ---------- */
    function aba(nome) {
        $$('.jg-aba').forEach(b => { const on = b.dataset.aba === nome; b.classList.toggle('ativo', on); b.setAttribute('aria-selected', on); });
        $$('.jg-painel').forEach(p => { p.hidden = p.dataset.painel !== nome; });
        ls.set('jg-aba', nome);
    }
    $$('.jg-aba').forEach(b => b.addEventListener('click', () => aba(b.dataset.aba)));
    aba(ls.get('jg-aba') === 'pela_dama' ? 'pela_dama' : 'gladio');

    /* ---------- oponentes ---------- */
    const outros = D.membros.filter(m => m.id !== D.eu);
    $$('.jg-oponente').forEach(sel => {
        sel.innerHTML = '<option value="">Qualquer pessoa da campanha</option>' +
            outros.map(m => `<option value="${m.id}">${esc(m.nome)}${m.mestre ? ' (mestre)' : ''}</option>`).join('');
    });

    /* ---------- partidas e Dinheiro ---------- */
    function rotulo(p) {
        if (p.status === 'aberta') return p.convidado ? `Aguardando ${esc(p.convidado)}` : 'Aberto a qualquer um';
        if (p.status === 'andamento') return 'Em andamento';
        if (p.status === 'fim') return p.vencedor ? `Venceu: ${esc(p.vencedor)}` : 'Empate';
        return 'Cancelada';
    }
    function renderPartidas() {
        $$('[data-lista]').forEach(box => {
            const lista = lobby.partidas.filter(p => p.jogo === box.dataset.lista);
            box.innerHTML = lista.length ? lista.map(p => {
                const nomes = esc(p.j1) + (p.j2 ? ' × ' + esc(p.j2) : '');
                const aposta = p.jogo === 'gladio' ? (p.aposta ? ` · aposta ${p.aposta}` : ' · amistoso') : '';
                let botoes = '';
                if (p.aceitar) botoes += `<button type="button" class="camp-btn camp-btn-primario camp-btn-pequeno" data-aceitar="${p.id}" data-jogo="${p.jogo}">Aceitar</button>`;
                if (p.status === 'andamento' || p.status === 'fim')
                    botoes += `<a class="camp-btn camp-btn-pequeno${p.meu && p.status === 'andamento' ? ' camp-btn-primario' : ''}" href="${esc(p.url)}">${p.meu && p.status === 'andamento' ? 'Jogar' : (p.status === 'fim' ? 'Ver' : 'Assistir')}</a>`;
                if (p.cancelar) botoes += `<button type="button" class="camp-btn camp-btn-pequeno" data-cancelar="${p.id}" data-andamento="${p.status === 'andamento' ? 1 : 0}">Cancelar</button>`;
                return `<div class="jg-partida jg-st-${p.status}"><div class="jg-partida-info"><strong>${nomes}</strong>` +
                    `<span class="camp-dica">${rotulo(p)}${aposta}</span></div><div class="jg-partida-botoes">${botoes}</div></div>`;
            }).join('') : '<p class="camp-vazio">Nenhuma partida por enquanto.</p>';
        });
        $('#jg-meu-dinheiro').textContent = Number(lobby.carteiras[D.eu] || 0).toLocaleString('pt-BR');
        renderCarteiras();
    }
    function renderCarteiras() {
        const box = $('#jg-carteiras');
        if (box.contains(document.activeElement)) return; // não atrapalha quem está digitando
        box.innerHTML = D.membros.map(m => {
            const v = Number(lobby.carteiras[m.id] || 0);
            return `<div class="jg-carteira"><span>${esc(m.nome)}${m.mestre ? ' <small>(mestre)</small>' : ''}</span>` +
                (D.souMestre
                    ? `<input type="number" min="0" value="${v}" data-dinheiro="${m.id}" aria-label="Dinheiro de ${esc(m.nome)}"><button type="button" class="camp-btn camp-btn-pequeno" data-salvar-dinheiro="${m.id}">Salvar</button>`
                    : `<strong>${v.toLocaleString('pt-BR')}</strong>`) +
                `</div>`;
        }).join('');
    }

    async function atualizarLobby() {
        try {
            const r = await fetch(D.url.lobby, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (r.ok) { lobby = await r.json(); renderPartidas(); }
        } catch (_) { /* tenta de novo */ }
    }
    setInterval(() => { if (!document.hidden) atualizarLobby(); }, 3000);

    raiz.addEventListener('click', async e => {
        const b = e.target.closest('button');
        if (!b) return;
        try {
            if (b.dataset.aceitar) {
                b.disabled = true;
                const dados = b.dataset.jogo === 'pela_dama' ? { exercito: JSON.stringify(exercito()) } : {};
                const r = await post(urlPartida(b.dataset.aceitar, 'aceitar'), dados, D.csrf);
                location.href = r.url;
            } else if (b.dataset.cancelar) {
                const aviso = b.dataset.andamento === '1' ? 'Cancelar a partida em andamento? As apostas voltam para os dois.' : 'Cancelar este desafio?';
                if (!confirm(aviso)) return;
                lobby = await post(urlPartida(b.dataset.cancelar, 'cancelar'), {}, D.csrf);
                renderPartidas();
            } else if (b.dataset.salvarDinheiro) {
                const id = b.dataset.salvarDinheiro;
                const input = $(`[data-dinheiro="${id}"]`);
                lobby = await post(D.url.dinheiro, { user_id: id, dinheiro: input.value || 0 }, D.csrf);
                b.blur();
                renderPartidas();
                msg('Dinheiro atualizado.');
            }
        } catch (err) { b.disabled = false; falhou(err); }
    });

    $$('.jg-desafio').forEach(f => f.addEventListener('submit', async e => {
        e.preventDefault();
        const dados = { jogo: f.dataset.jogo, convidado_id: f.convidado_id.value || null };
        if (f.dataset.jogo === 'gladio') dados.aposta = f.aposta.value || 0;
        else {
            const ex = exercito();
            if (ex.filter(t => t === 'rei').length !== 1) return msg('Seu exército precisa de exatamente um Rei.', true);
            dados.exercito = JSON.stringify(ex);
        }
        try {
            lobby = await post(D.url.partidas, dados, D.csrf);
            renderPartidas();
            msg('Desafio lançado! A partida começa quando alguém aceitar.');
        } catch (err) { falhou(err); }
    }));

    /* ---------- cartas (Gladio) ---------- */
    function renderCartas() {
        $('#jg-cont-cartas').textContent = cartas.length;
        $('#jg-cartas').innerHTML = cartas.length ? cartas.map(c =>
            `<div class="jg-carta-item">${window.JG.carta(c)}` +
            (D.souMestre ? `<div class="jg-carta-acoes"><button type="button" class="camp-btn camp-btn-pequeno" data-editar-carta="${c.id}">Editar</button>` +
                `<button type="button" class="camp-btn camp-btn-pequeno" data-excluir-carta="${c.id}">Excluir</button></div>` : '') +
            `</div>`).join('') : `<p class="camp-vazio">${D.souMestre ? 'Crie a primeira carta do Gladio.' : 'O mestre ainda não criou cartas.'}</p>`;
    }

    const fc = $('#jg-criador-carta');
    if (fc) {
        let imagem = null;      // data URL nova
        let semImagem = false;  // pediu para tirar a imagem atual
        let imagemAtual = null;
        const lerCarta = () => ({
            nome: fc.nome.value.trim() || 'Nova carta', tipo: fc.tipo.value, custo: +fc.custo.value || 0,
            ataque: +fc.ataque.value || 0, vida: +fc.vida.value || 0, efeito: fc.efeito.value, valor: +fc.valor.value || 0,
            descricao: fc.descricao.value, imagem: semImagem ? null : (imagem || imagemAtual),
        });
        const previa = () => {
            const lenda = fc.tipo.value === 'lenda';
            $$('.jg-so-lenda', fc).forEach(el => { el.hidden = !lenda; });
            $$('.jg-so-feitico', fc).forEach(el => { el.hidden = lenda; });
            $('#jg-previa-carta').innerHTML = window.JG.carta(lerCarta());
        };
        /* ---- enquadramento da arte: zoom e deslocamento ----
           A imagem original fica em "fonte"; o recorte (na proporção da janela de arte da carta) vira o JPEG salvo.
           aj.z = zoom (1 = a imagem cobre a janela inteira); aj.cx/aj.cy = centro do recorte, em pixels da original. */
        const ARTE_W = 480, ARTE_H = 308;  // mesma proporção da janela de arte da carta
        const ajuste = $('#jg-ajuste');
        const zoom = $('#jg-zoom');
        const caixaPrevia = $('#jg-previa-carta');
        let fonte = null;
        let aj = { z: 1, cx: 0, cy: 0 };
        const temImagem = () => !semImagem && !!(imagem || imagemAtual);
        const mostrarAjuste = () => {
            ajuste.hidden = !temImagem();
            caixaPrevia.classList.toggle('jg-ajustavel', temImagem());
        };
        // pixels da original que cabem na janela, já com o zoom
        const janela = () => {
            const iw = fonte.naturalWidth || fonte.width, ih = fonte.naturalHeight || fonte.height;
            const s = Math.max(ARTE_W / iw, ARTE_H / ih) * aj.z;
            return { iw, ih, vw: ARTE_W / s, vh: ARTE_H / s };
        };
        function recortar() {
            const { iw, ih, vw, vh } = janela();
            aj.cx = Math.min(Math.max(aj.cx, vw / 2), iw - vw / 2);
            aj.cy = Math.min(Math.max(aj.cy, vh / 2), ih - vh / 2);
            const cv = document.createElement('canvas');
            cv.width = ARTE_W; cv.height = ARTE_H;
            const ctx = cv.getContext('2d');
            ctx.fillStyle = '#140b0b';
            ctx.fillRect(0, 0, ARTE_W, ARTE_H);
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(fonte, aj.cx - vw / 2, aj.cy - vh / 2, vw, vh, 0, 0, ARTE_W, ARTE_H);
            imagem = cv.toDataURL('image/jpeg', 0.86);
            semImagem = false;
            previa();
        }
        let quadro = 0; // junta vários movimentos num desenho por quadro
        const agendarRecorte = () => { if (!quadro) quadro = requestAnimationFrame(() => { quadro = 0; recortar(); }); };
        /** Nova imagem escolhida: começa centralizada e sem zoom. */
        function usarFonte(img) {
            fonte = img;
            aj = { z: 1, cx: (img.naturalWidth || img.width) / 2, cy: (img.naturalHeight || img.height) / 2 };
            zoom.value = 1;
            recortar();
            mostrarAjuste();
        }
        // carta já salva: a imagem dela vira a fonte só quando o mestre mexer no enquadramento
        async function garantirFonte() {
            if (fonte || !imagemAtual || imagem) return !!fonte;
            const img = await abrirImagem(imagemAtual);
            fonte = img;
            aj = { z: 1, cx: img.naturalWidth / 2, cy: img.naturalHeight / 2 };
            return true;
        }

        zoom.addEventListener('input', async () => {
            if (!(await garantirFonte())) return;
            aj.z = +zoom.value;
            agendarRecorte();
        });
        caixaPrevia.addEventListener('wheel', async e => {
            if (!temImagem() || !e.target.closest('.jg-carta-arte')) return;
            e.preventDefault();
            if (!(await garantirFonte())) return;
            aj.z = Math.min(4, Math.max(1, aj.z * (e.deltaY < 0 ? 1.08 : 1 / 1.08)));
            zoom.value = aj.z;
            agendarRecorte();
        }, { passive: false });
        caixaPrevia.addEventListener('pointerdown', async e => {
            const arte = e.target.closest('.jg-carta-arte');
            if (!arte || !temImagem() || e.button > 0) return;
            e.preventDefault();
            caixaPrevia.setPointerCapture(e.pointerId);
            if (!(await garantirFonte())) return;
            const larg = arte.getBoundingClientRect().width || 1;
            const k = janela().vw / larg; // pixels da original por pixel de tela
            let x0 = e.clientX, y0 = e.clientY;
            caixaPrevia.classList.add('jg-arrastando');
            const mover = ev => {
                aj.cx -= (ev.clientX - x0) * k;
                aj.cy -= (ev.clientY - y0) * k;
                x0 = ev.clientX; y0 = ev.clientY;
                agendarRecorte();
            };
            const fim = () => {
                caixaPrevia.classList.remove('jg-arrastando');
                caixaPrevia.removeEventListener('pointermove', mover);
                caixaPrevia.removeEventListener('pointerup', fim);
                caixaPrevia.removeEventListener('pointercancel', fim);
            };
            caixaPrevia.addEventListener('pointermove', mover);
            caixaPrevia.addEventListener('pointerup', fim);
            caixaPrevia.addEventListener('pointercancel', fim);
        });

        const abrir = c => {
            fc.reset();
            imagem = null; semImagem = false; imagemAtual = c ? c.imagem : null;
            fonte = null; zoom.value = 1;
            fc.registro.value = c ? c.id : '';
            if (c) ['nome', 'tipo', 'custo', 'ataque', 'vida', 'valor', 'descricao'].forEach(k => { fc[k].value = c[k] == null ? '' : c[k]; });
            if (c && c.efeito) fc.efeito.value = c.efeito;
            fc.hidden = false;
            previa();
            mostrarAjuste();
            fc.nome.focus();
        };
        $('#jg-nova-carta').addEventListener('click', () => abrir(null));
        fc.addEventListener('input', e => { if (e.target !== zoom) previa(); });
        fc.addEventListener('change', async e => {
            if (e.target.name !== 'arquivo' || !e.target.files[0]) return;
            try { usarFonte(await abrirArquivo(e.target.files[0])); } catch (err) { falhou(err); }
            e.target.value = '';
        });
        // escolher a arte na galeria da campanha
        const escolher = $('#jg-escolher');
        const escolherPasta = $('#jg-escolher-pasta');
        const renderEscolher = () => { $('#jg-escolher-grade').innerHTML = htmlGaleria(escolherPasta.value, false); };
        escolherPasta.addEventListener('change', renderEscolher);
        fc.addEventListener('click', async e => {
            const item = e.target.closest('[data-img]');
            if (item && escolher.contains(item)) {
                const g = imagens.find(i => i.id === +item.dataset.img);
                if (!g) return;
                item.classList.add('jg-carregando');
                try {
                    const blob = await (await fetch(g.url, { credentials: 'same-origin' })).blob();
                    usarFonte(await abrirArquivo(new File([blob], g.nome + '.jpg', { type: blob.type || 'image/jpeg' })));
                    escolher.hidden = true;
                } catch (err) { falhou(err); } finally { item.classList.remove('jg-carregando'); }
                return;
            }
            const a = e.target.closest('[data-acao]');
            if (!a) return;
            if (a.dataset.acao === 'cancelar') { fc.hidden = true; escolher.hidden = true; }
            if (a.dataset.acao === 'tirar-img') { imagem = null; semImagem = true; fonte = null; previa(); mostrarAjuste(); }
            if (a.dataset.acao === 'centralizar' && await garantirFonte()) {
                aj = { z: 1, cx: (fonte.naturalWidth || fonte.width) / 2, cy: (fonte.naturalHeight || fonte.height) / 2 };
                zoom.value = 1;
                recortar();
            }
            if (a.dataset.acao === 'fechar-galeria') escolher.hidden = true;
            if (a.dataset.acao === 'galeria') {
                if (!imagens.length) return msg('A galeria está vazia. Envie imagens na "Galeria de imagens", logo abaixo.', true);
                filtroPastas(escolherPasta);
                escolher.hidden = !escolher.hidden;
                renderEscolher();
            }
        });
        fc.addEventListener('submit', async e => {
            e.preventDefault();
            const c = lerCarta();
            const dados = { id: fc.registro.value || null, nome: fc.nome.value.trim(), tipo: c.tipo, custo: c.custo, ataque: c.ataque, vida: c.vida,
                            efeito: c.tipo === 'feitico' ? c.efeito : null, valor: c.valor, descricao: c.descricao };
            if (imagem) dados.imagem = imagem;
            if (semImagem) dados.sem_imagem = 1;
            try {
                const r = await post(D.url.cartas, dados, D.csrf);
                const i = cartas.findIndex(x => x.id === r.carta.id);
                if (i >= 0) cartas[i] = r.carta; else cartas.push(r.carta);
                fc.hidden = true;
                renderCartas();
                msg('Carta salva.');
            } catch (err) { falhou(err); }
        });
        $('#jg-cartas').addEventListener('click', async e => {
            const ed = e.target.closest('[data-editar-carta]');
            const ex = e.target.closest('[data-excluir-carta]');
            if (ed) { abrir(cartas.find(c => c.id === +ed.dataset.editarCarta)); fc.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
            if (ex) {
                const c = cartas.find(x => x.id === +ex.dataset.excluirCarta);
                if (!c || !confirm(`Excluir a carta "${c.nome}"? Partidas em andamento não mudam.`)) return;
                try { await post(`${D.url.cartas}/${c.id}`, {}, D.csrf, 'DELETE'); cartas = cartas.filter(x => x !== c); renderCartas(); } catch (err) { falhou(err); }
            }
        });
    }

    /* Imagem da carta: aceita qualquer formato que o navegador abra (JPEG, PNG, GIF, WebP, AVIF, BMP, ICO, SVG…),
       mesmo quando o arquivo chega sem tipo (vale a extensão). O resultado é sempre um JPEG de até "max" px. */
    const EXT_IMG = /\.(jpe?g|jfif|pjpeg|pjp|png|apng|gif|webp|avif|bmp|dib|ico|cur|svg|heic|heif|tiff?)$/i;
    const abrirImagem = src => new Promise((ok, erro) => { const img = new Image(); img.onload = () => ok(img); img.onerror = erro; img.src = src; });

    /** Abre o arquivo como <img> já decodificada (no tamanho original). */
    async function abrirArquivo(arq) {
        const tipo = (arq.type || '').toLowerCase();
        const nome = arq.name || '';
        if (!/^image\//.test(tipo) && !EXT_IMG.test(nome)) throw new Error('Escolha um arquivo de imagem.');

        const url = URL.createObjectURL(arq);
        try {
            return await abrirImagem(url); // o Safari abre até HEIC; Chrome e Edge não abrem HEIC nem TIFF
        } catch (_) {
            throw new Error(/hei[cf]|tiff?/.test(tipo + nome.toLowerCase())
                ? 'Este navegador não abre fotos HEIC nem TIFF. Salve a imagem como JPEG ou PNG (no iPhone, o envio pelo Safari já converte).'
                : 'Este navegador não consegue abrir essa imagem. Tente JPEG, PNG ou WebP.');
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async function reduzir(arq, max) {
        const fonte = await abrirArquivo(arq);
        const w = fonte.naturalWidth || max; // SVG sem tamanho declarado
        const h = fonte.naturalHeight || max;
        const k = Math.min(1, max / Math.max(w, h));
        const cv = document.createElement('canvas');
        cv.width = Math.max(1, Math.round(w * k));
        cv.height = Math.max(1, Math.round(h * k));
        const ctx = cv.getContext('2d');
        ctx.fillStyle = '#140b0b'; // fundo da carta atrás de PNG/GIF/SVG transparentes (o JPEG não tem transparência)
        ctx.fillRect(0, 0, cv.width, cv.height);
        ctx.drawImage(fonte, 0, 0, cv.width, cv.height);
        return cv.toDataURL('image/jpeg', 0.82);
    }

    /* ---------- galeria de imagens das cartas (só o mestre vê) ---------- */
    const TODAS = '*';
    function pastas() { return [...new Set(imagens.map(i => i.pasta))].sort((a, b) => a.localeCompare(b, 'pt-BR')); }
    /** Preenche um <select> de pastas mantendo a escolha atual ("" = sem pasta). */
    function filtroPastas(sel) {
        const atual = sel.options.length ? sel.value : TODAS;
        const ps = pastas();
        sel.innerHTML = `<option value="${TODAS}">Todas as pastas (${imagens.length})</option>` +
            ps.map(p => `<option value="${esc(p)}">${esc(p || 'Sem pasta')} (${imagens.filter(i => i.pasta === p).length})</option>`).join('');
        sel.value = atual === TODAS || ps.includes(atual) ? atual : TODAS;
    }
    function htmlGaleria(pasta, gerir) {
        const lista = pasta === TODAS ? imagens : imagens.filter(i => i.pasta === pasta);
        if (!lista.length) return '<p class="camp-vazio">Nenhuma imagem aqui.</p>';
        return lista.map(i => `<div class="jg-galeria-item" data-img="${i.id}" title="${esc(i.nome)}${i.pasta ? ' · ' + esc(i.pasta) : ''}">` +
            `<img src="${esc(i.url)}" alt="${esc(i.nome)}" loading="lazy" decoding="async">` +
            (gerir ? `<button type="button" class="jg-galeria-x" data-excluir-img="${i.id}" aria-label="Excluir ${esc(i.nome)}">×</button>` : '') +
            `</div>`).join('');
    }

    const galeria = $('#jg-galeria');
    if (galeria) {
        const filtro = $('#jg-galeria-filtro');
        const progresso = $('#jg-galeria-progresso');
        const renderGaleria = () => {
            $('#jg-cont-imagens').textContent = imagens.length;
            filtroPastas(filtro);
            $('#jg-pastas').innerHTML = pastas().filter(Boolean).map(p => `<option value="${esc(p)}">`).join('');
            galeria.innerHTML = htmlGaleria(filtro.value, true);
        };
        filtro.addEventListener('change', () => { galeria.innerHTML = htmlGaleria(filtro.value, true); });

        // várias imagens de uma vez: cada uma é reduzida no navegador e vai em lotes de 8
        $('#jg-galeria-arquivos').addEventListener('change', async e => {
            const arquivos = Array.from(e.target.files);
            e.target.value = '';
            if (!arquivos.length) return;
            const pasta = $('#jg-galeria-pasta-nova').value.trim();
            let feitas = 0, falhas = 0;
            const mostrar = () => {
                progresso.hidden = false;
                progresso.textContent = `Enviando ${feitas} de ${arquivos.length}…` + (falhas ? ` (${falhas} com problema)` : '');
            };
            mostrar();
            for (let i = 0; i < arquivos.length; i += 8) {
                const fd = new FormData();
                fd.append('_token', D.csrf);
                if (pasta) fd.append('pasta', pasta);
                let n = 0;
                for (const arq of arquivos.slice(i, i + 8)) {
                    try {
                        const blob = await (await fetch(await reduzir(arq, 960))).blob();
                        const nome = arq.name.replace(/\.[^.]+$/, '');
                        fd.append(`imagens[${n}]`, blob, nome + '.jpg');
                        fd.append(`nomes[${n}]`, nome);
                        n++;
                    } catch (_) { falhas++; }
                }
                if (n) {
                    try {
                        const r = await fetch(D.url.imagens, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        const j = await r.json().catch(() => ({}));
                        if (!r.ok || !j.ok) throw new Error(j.erro || j.message || 'Erro ' + r.status + ' ao enviar.');
                        imagens = imagens.concat(j.imagens);
                        feitas += n;
                    } catch (err) { falhas += n; msg(err.message, true); }
                }
                mostrar();
                renderGaleria();
            }
            progresso.textContent = `${feitas} imagem(ns) enviada(s)` + (falhas ? `, ${falhas} com problema.` : '.');
        });

        galeria.addEventListener('click', async e => {
            const x = e.target.closest('[data-excluir-img]');
            if (!x) return;
            const g = imagens.find(i => i.id === +x.dataset.excluirImg);
            if (!g || !confirm(`Excluir "${g.nome}" da galeria? As cartas que já usam essa imagem não mudam.`)) return;
            try {
                await post(`${D.url.imagens}/${g.id}`, {}, D.csrf, 'DELETE');
                imagens = imagens.filter(i => i !== g);
                renderGaleria();
            } catch (err) { falhou(err); }
        });
        renderGaleria();
    }

    /* ---------- exército (Pela Dama) ---------- */
    const chaveEx = 'jg-exercito-' + D.campanha;
    const tipos = () => {
        const t = {};
        Object.keys(D.padrao).forEach(k => { t[k] = D.padrao[k]; });
        pecas.forEach(p => { t[p.tipo] = { nome: p.nome, simbolo: p.simbolo, regras: p.regras }; });
        return t;
    };
    function exercito() {
        let ex = null;
        try { ex = JSON.parse(ls.get(chaveEx) || 'null'); } catch (_) { ex = null; }
        const t = tipos();
        if (!Array.isArray(ex) || ex.length !== 16) ex = D.exercito.slice();
        return ex.map((id, i) => (t[id] ? id : D.exercito[i])); // peça apagada volta à padrão
    }
    function renderExercito() {
        const t = tipos();
        const ex = exercito();
        const opcoes = sel => Object.keys(t).map(id => `<option value="${esc(id)}"${id === sel ? ' selected' : ''}>${esc(t[id].simbolo)} ${esc(t[id].nome)}</option>`).join('');
        const cols = 'abcdefgh';
        $('#jg-exercito').innerHTML = [0, 8].map(ini => `<div class="jg-ex-linha"><span class="jg-ex-rot">${ini ? 'Fila 2' : 'Fila 1'}</span>` +
            ex.slice(ini, ini + 8).map((id, i) => `<label class="jg-ex-casa"><small>${cols[i]}${ini ? 2 : 1}</small>` +
                `<span class="jg-ex-simb">${esc(t[id].simbolo)}</span><select data-casa="${ini + i}">${opcoes(id)}</select></label>`).join('') +
            '</div>').reverse().join('');
        const reis = ex.filter(id => id === 'rei').length;
        $('#jg-exercito-aviso').textContent = reis === 1 ? '' : 'Atenção: o exército precisa de exatamente um Rei.';
    }
    $('#jg-exercito').addEventListener('change', e => {
        const s = e.target.closest('select[data-casa]');
        if (!s) return;
        const ex = exercito();
        ex[+s.dataset.casa] = s.value;
        ls.set(chaveEx, JSON.stringify(ex));
        renderExercito();
    });
    $('#jg-exercito-padrao').addEventListener('click', () => { ls.set(chaveEx, JSON.stringify(D.exercito)); renderExercito(); });

    /* ---------- peças (Pela Dama) ---------- */
    function renderPecas() {
        $('#jg-cont-pecas').textContent = pecas.length;
        $('#jg-pecas').innerHTML = pecas.length ? pecas.map(p =>
            `<div class="jg-peca-item"><span class="jg-peca-simb">${esc(p.simbolo)}</span>` +
            `<div class="jg-peca-info"><strong>${esc(p.nome)}</strong>${window.JG.diagrama(p, 9)}</div>` +
            `<div class="jg-carta-acoes"><button type="button" class="camp-btn camp-btn-pequeno" data-editar-peca="${p.id}">Editar</button>` +
            `<button type="button" class="camp-btn camp-btn-pequeno" data-excluir-peca="${p.id}">Excluir</button></div></div>`).join('')
            : '<p class="camp-vazio">Você ainda não criou peças. As peças padrão do xadrez estão sempre disponíveis.</p>';
        renderExercito();
    }

    const fp = $('#jg-criador-peca');
    const N = 7; // a grade vai de -7 a +7
    const DIRS = [[0, 1, '↑'], [1, 1, '↗'], [1, 0, '→'], [1, -1, '↘'], [0, -1, '↓'], [-1, -1, '↙'], [-1, 0, '←'], [-1, 1, '↖']];
    const CICLO = ['', 'ambos', 'mover', 'capturar'];
    let saltos = {}; // "dx,dy" => modo
    let raios = {};  // "dx,dy" => { m, alc }

    function regrasDoCriador() {
        const out = [];
        Object.keys(raios).forEach(k => { const [dx, dy] = k.split(',').map(Number); out.push({ dx, dy, d: true, alc: raios[k].alc, m: raios[k].m }); });
        Object.keys(saltos).forEach(k => {
            if (raios[k]) return; // a direção já desliza
            const [dx, dy] = k.split(',').map(Number);
            out.push({ dx, dy, d: false, alc: 1, m: saltos[k] });
        });
        return out;
    }
    function renderCriador() {
        const regras = regrasDoCriador();
        // a grade mostra os saltos (clicáveis) e, mais clarinho, as casas alcançadas deslizando
        const alcance = {};
        regras.filter(r => r.d).forEach(r => { for (let k = 1; k <= r.alc; k++) alcance[(r.dx * k) + ',' + (r.dy * k)] = r.m; });
        let h = '';
        for (let dy = N; dy >= -N; dy--) {
            for (let dx = -N; dx <= N; dx++) {
                const k = dx + ',' + dy;
                if (dx === 0 && dy === 0) { h += `<span class="jg-centro">${esc(fp.simbolo.value || '?')}</span>`; continue; }
                const m = saltos[k] || '';
                const r = alcance[k];
                h += `<button type="button" data-k="${k}" class="${m ? 'jg-m-' + m : ''}${!m && r ? ' jg-raio jg-m-' + r : ''}" aria-label="Casa ${dx}, ${dy}"></button>`;
            }
        }
        $('#jg-grade-mov').innerHTML = h;
        $('#jg-raios').innerHTML = DIRS.map(([dx, dy, seta]) => {
            const k = dx + ',' + dy;
            const r = raios[k];
            return `<div class="jg-raio-linha"><span class="jg-seta">${seta}</span>` +
                `<select data-raio="${k}" aria-label="Deslizar ${seta}">` +
                CICLO.map(m => `<option value="${m}"${(r ? r.m : '') === m ? ' selected' : ''}>${({ '': 'Não desliza', ambos: 'Move e captura', mover: 'Só move', capturar: 'Só captura' })[m]}</option>`).join('') +
                `</select><label>até <input type="number" min="1" max="7" value="${r ? r.alc : 7}" data-alc="${k}"${r ? '' : ' disabled'}> casas</label></div>`;
        }).join('');
    }
    function abrirPeca(p) {
        fp.reset();
        saltos = {}; raios = {};
        fp.registro.value = p ? p.id : '';
        if (p) {
            fp.nome.value = p.nome; fp.simbolo.value = p.simbolo;
            p.regras.forEach(r => { if (r.d) raios[r.dx + ',' + r.dy] = { m: r.m, alc: r.alc }; else saltos[r.dx + ',' + r.dy] = r.m; });
        }
        $('#jg-espelhar').checked = true;
        fp.hidden = false;
        renderCriador();
        fp.nome.focus();
    }
    $('#jg-nova-peca').addEventListener('click', () => abrirPeca(null));
    $('#jg-grade-mov').addEventListener('click', e => {
        const b = e.target.closest('button[data-k]');
        if (!b) return;
        const [dx, dy] = b.dataset.k.split(',').map(Number);
        const novo = CICLO[(CICLO.indexOf(saltos[b.dataset.k] || '') + 1) % CICLO.length];
        const aplicar = k => { if (novo) saltos[k] = novo; else delete saltos[k]; };
        aplicar(b.dataset.k);
        if ($('#jg-espelhar').checked && dx !== 0) aplicar((-dx) + ',' + dy);
        renderCriador();
    });
    $('#jg-raios').addEventListener('change', e => {
        const s = e.target.closest('[data-raio]');
        const a = e.target.closest('[data-alc]');
        if (s) {
            const k = s.dataset.raio;
            if (s.value) raios[k] = { m: s.value, alc: raios[k] ? raios[k].alc : 7 }; else delete raios[k];
            if ($('#jg-espelhar').checked) {
                const [dx, dy] = k.split(',').map(Number);
                const esp = (-dx) + ',' + dy;
                if (dx !== 0) { if (s.value) raios[esp] = { m: s.value, alc: raios[k].alc }; else delete raios[esp]; }
            }
        }
        if (a && raios[a.dataset.alc]) {
            const alc = Math.min(7, Math.max(1, +a.value || 1));
            raios[a.dataset.alc].alc = alc;
            const [dx, dy] = a.dataset.alc.split(',').map(Number);
            if ($('#jg-espelhar').checked && dx !== 0 && raios[(-dx) + ',' + dy]) raios[(-dx) + ',' + dy].alc = alc;
        }
        renderCriador();
    });
    fp.simbolo.addEventListener('input', () => { const c = $('#jg-grade-mov .jg-centro'); if (c) c.textContent = fp.simbolo.value || '?'; });
    fp.addEventListener('click', e => {
        const a = e.target.closest('[data-acao]');
        if (!a) return;
        if (a.dataset.acao === 'cancelar') fp.hidden = true;
        if (a.dataset.acao === 'limpar') { saltos = {}; raios = {}; renderCriador(); }
    });
    fp.addEventListener('submit', async e => {
        e.preventDefault();
        const regras = regrasDoCriador();
        if (!regras.length) return msg('Marque pelo menos um movimento para a peça.', true);
        try {
            const r = await post(D.url.pecas, { id: fp.registro.value || null, nome: fp.nome.value.trim(), simbolo: fp.simbolo.value.trim(), regras: JSON.stringify(regras) }, D.csrf);
            const i = pecas.findIndex(p => p.id === r.peca.id);
            if (i >= 0) pecas[i] = r.peca; else pecas.push(r.peca);
            fp.hidden = true;
            renderPecas();
            msg('Peça salva. Escale-a no seu exército.');
        } catch (err) { falhou(err); }
    });
    $('#jg-pecas').addEventListener('click', async e => {
        const ed = e.target.closest('[data-editar-peca]');
        const ex = e.target.closest('[data-excluir-peca]');
        if (ed) { abrirPeca(pecas.find(p => p.id === +ed.dataset.editarPeca)); fp.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        if (ex) {
            const p = pecas.find(x => x.id === +ex.dataset.excluirPeca);
            if (!p || !confirm(`Excluir a peça "${p.nome}"? No exército ela volta a ser a peça padrão.`)) return;
            try { await post(`${D.url.pecas}/${p.id}`, {}, D.csrf, 'DELETE'); pecas = pecas.filter(x => x !== p); renderPecas(); } catch (err) { falhou(err); }
        }
    });

    renderPartidas();
    renderCartas();
    renderPecas();
})();
