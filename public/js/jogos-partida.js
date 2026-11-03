/**
 * Jogos da campanha: tela da partida (Gladio ou Pela Dama).
 * O servidor guarda as regras: aqui só se desenha o estado, escolhe a jogada e manda para /acao.
 * Pergunta "mudou?" a cada ~1 s (o servidor responde só a versão quando nada mudou).
 */
(function () {
    'use strict';

    const dadosEl = document.getElementById('jg-partida-dados');
    const box = document.getElementById('jg-jogo');
    if (!dadosEl || !box) return;

    const C = JSON.parse(dadosEl.textContent);

    // Tela cheia (Gladio): só a mesa, sem a aba de acontecimentos. Sem a tela cheia do navegador (iPhone), vira
    // um modo foco que cobre a página do mesmo jeito.
    const pagina = document.getElementById('jg-partida');
    const botaoFoco = document.getElementById('jg-tela-cheia');
    if (pagina && botaoFoco) {
        const foco = ligado => {
            pagina.classList.toggle('gl-foco', ligado);
            botaoFoco.innerHTML = ligado ? icone('fechar') + ' Sair da tela cheia' : icone('tela-cheia') + ' Tela cheia';
        };
        botaoFoco.addEventListener('click', () => {
            if (!pagina.classList.contains('gl-foco')) {
                foco(true);
                if (pagina.requestFullscreen) pagina.requestFullscreen().catch(() => {});
            } else {
                foco(false);
                if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
            }
        });
        document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) foco(false); });
        document.addEventListener('keydown', ev => {
            if (ev.key === 'Escape' && pagina.classList.contains('gl-foco') && !document.fullscreenElement && !box.querySelector('.gl-modal')) foco(false);
        });
    }
    // Gladio: sair da mesa leva a partida para a miniatura do site (public/js/jogos-mini.js), que segue nas outras páginas.
    // "← Jogos da campanha" a deixa recolhida; "⇲ Miniatura" volta para a página de antes com a mesa já expandida nela.
    const idPartida = +((String(C.url.acao).match(/\/partidas\/(\d+)/) || [])[1] || 0);
    const paraMiniatura = aberta => {
        try {
            const st = JSON.parse(localStorage.getItem('jg-mini') || '{}');
            st.fechada = st.fechada === idPartida ? null : st.fechada;
            st.aberta = aberta ? idPartida : (st.aberta === idPartida ? null : st.aberta);
            localStorage.setItem('jg-mini', JSON.stringify(st));
        } catch (_) { /* sem armazenamento: a miniatura aparece recolhida */ }
    };
    const jogandoAqui = () => D.jogo === 'gladio' && D.lado && D.status === 'andamento' && !(D.estado && D.estado.fim);
    const voltar = document.querySelector('#jg-partida .camp-voltar');
    if (voltar) voltar.addEventListener('click', () => { if (jogandoAqui()) paraMiniatura(false); });
    const botaoMini = document.getElementById('jg-miniatura');
    if (botaoMini) botaoMini.addEventListener('click', () => {
        paraMiniatura(true);
        const antes = document.referrer && document.referrer.startsWith(location.origin) && !document.referrer.includes('/partidas/' + idPartida) ? document.referrer : C.url.lobby;
        location.href = antes;
    });
    // mestre: pausa ou retoma o Gladio
    const botaoPausa = document.getElementById('jg-pausar');
    if (botaoPausa) botaoPausa.addEventListener('click', () => acao({ acao: botaoPausa.dataset.acao || 'pausar' }));
    // o que flutua por cima da mesa vai para dentro da tela cheia (fora dela não aparece)
    const camada = () => document.fullscreenElement || document.body;
    const { esc, post } = window.JG;
    const $ = (s, r = document) => r.querySelector(s);
    let D = C.dados;     // { v, jogo, status, aposta, lado, nomes, vencedor, estado }
    let enviando = false;

    function msg(t, erro) {
        const el = $('#jg-msg');
        el.textContent = t || '';
        el.hidden = !t;
        el.classList.toggle('jg-msg--erro', !!erro);
        if (t) setTimeout(() => { if (el.textContent === t) el.hidden = true; }, 3500);
    }

    async function acao(dados) {
        if (enviando) return;
        enviando = true;
        try {
            D = await post(C.url.acao, dados, C.csrf);
            render();
        } catch (e) {
            msg(e.message, true);
        } finally {
            enviando = false;
        }
    }

    async function consultar() {
        if (enviando) return;
        try {
            const r = await fetch(`${C.url.estado}?v=${D.v}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!r.ok) return;
            const j = await r.json();
            if (j.estado !== undefined && !enviando) { D = j; render(); }
        } catch (_) { /* tenta de novo */ }
    }
    (function laco() {
        const fim = D.status === 'fim' || D.status === 'cancelada';
        setTimeout(async () => { await consultar(); laco(); }, fim ? 8000 : (document.hidden ? 4000 : 1000));
    })();

    function status(t) { $('#jg-status').innerHTML = t; }

    /* ---------- Como jogar: regras rápidas e palavras-chave (Gladio) ---------- */
    const guia = document.getElementById('jg-como-jogar');
    if (guia) guia.addEventListener('click', () => {
        const P = window.JG.PALAVRA;
        const caixa = document.createElement('div');
        caixa.className = 'gl-guia';
        caixa.innerHTML = `<div class="gl-guia-caixa" role="dialog" aria-modal="true" aria-label="Como jogar Gladio">
            <div class="gl-guia-cab"><h2>${icone('livro')} Como jogar Gladio</h2><button type="button" class="camp-btn camp-btn-pequeno" data-guia="fechar">${icone('fechar')} Fechar</button></div>
            <ol class="gl-guia-passos">
                <li><b>Objetivo.</b> Zerar a vida dos adversários (cada um começa com 40). Quem precisa comprar com o baralho vazio também perde.</li>
                <li><b>Começo.</b> Cada um recebe 5 cartas e decide se fica com elas ou troca (mulligan).</li>
                <li><b>Seu turno.</b> A energia máxima sobe 1 (até 10) e enche. As cartas da mão que cabem na energia <span class="gl-guia-brilha">brilham</span>: arraste para o campo ou clique nelas.</li>
                <li><b>Atacar.</b> Lendas prontas brilham no seu campo. Clique nelas (ou arraste um laço) e escolha o jogador. É uma onda de ataque por turno. Lenda que entrou agora fica cansada, a não ser que tenha Pressa.</li>
                <li><b>Defender.</b> Quando atacarem você, escolha Lendas suas de pé para bloquear. O que ninguém bloquear acerta a sua vida.</li>
                <li><b>Alvos e respostas.</b> Quando uma carta mira outra, aparece ${icone('alvo')} "mirada por" no alvo, e quem tiver uma resposta (negar, Imbuir…) pode reagir antes.</li>
                <li><b>Comandante.</b> A Lenda escolhida como comandante (${icone('coroa')}) fica no comando e pode ser invocada de lá; cada nova invocação custa mais.</li>
                <li><b>Fim do turno.</b> Você descarta até ficar com 7 cartas e a vez passa. Cada vez tem 90 segundos.</li>
            </ol>
            <h3>Palavras-chave</h3>
            <dl class="gl-guia-palavras">${Object.values(P).map(p => `<dt>${esc(p.nome)}</dt><dd>${esc(p.desc)}</dd>`).join('')}</dl>
        </div>`;
        const fechar = () => { caixa.remove(); document.removeEventListener('keydown', tecla); };
        const tecla = ev => { if (ev.key === 'Escape') fechar(); };
        caixa.addEventListener('click', ev => { if (ev.target === caixa || ev.target.closest('[data-guia="fechar"]')) fechar(); });
        document.addEventListener('keydown', tecla);
        document.body.appendChild(caixa);
    });

    /** Na sua vez, diz o próximo passo: quantas cartas cabem na energia, quantas Lendas podem atacar, ou que é hora de passar. */
    function dicaDoTurno() {
        const e = D.estado;
        if (!e || e.fim || !D.lado || e.vez !== D.lado || e.fase !== 'principal' || e.escolha || e.pilha || (e.ataque && (e.ataque.atacantes || []).length)) return;
        const jogaveis = box.querySelectorAll('.gl-mao .gl-jogavel').length;
        const prontas = box.querySelectorAll('.gl-campo .gl-pode').length;
        const partes = [];
        if (jogaveis) partes.push(`${jogaveis} carta${jogaveis > 1 ? 's' : ''} da mão cabe${jogaveis > 1 ? 'm' : ''} na sua energia (as que brilham)`);
        if (prontas) partes.push(`${prontas} Lenda${prontas > 1 ? 's' : ''} pronta${prontas > 1 ? 's' : ''} para atacar`);
        $('#jg-status').insertAdjacentHTML('beforeend', ` <span class="gl-dica-passo">${partes.length ? 'Próximo passo: ' + partes.join(' · ') + '.' : 'Nada mais para fazer agora: clique em <b>Fim do turno</b>.'}</span>`);
    }
    const nomeJ = j => esc(D.nomes[j] || '—');

    /* ================================================================== */
    /*  Gladio (2 a 6 jogadores)                                           */
    /*  Regras no servidor (App\Jogos\Gladio). Aqui: desenhar a mesa,      */
    /*  escolher jogadas, arrastar cartas e as janelas de ferramentas.     */
    /* ================================================================== */
    let alvo = null;          // mirando o alvo de um feitiço antigo sem texto: { tipo: 'feitico', inst, precisa }
    let atacantes = [];       // Lendas escolhidas para o ataque
    let bloqueador = null;    // na defesa: Lenda minha escolhida, esperando o clique no atacante
    let paraFundo = [];       // mulligan: cartas da mão escolhidas para o fundo do baralho
    let modal = null;         // { tipo: 'cemiterio' | 'mao' | 'mesa' | 'feitico' | 'comando' | 'escolha' | 'detalhe', inst?, lado? }
    let engolirClique = false; // depois de arrastar uma carta, o clique que o navegador dispara é ignorado
    const PRECISA = { dano: 'inimigo', cura: 'aliado', fortalecer: 'lenda' };
    const SHOTS = 7;
    const MAO_FIM = 7; // Gladio::MAO_FIM: cartas na mão no fim do turno
    // fases do turno (Gladio): a 'defesa' aparece como Combate
    const FASES = [['compra', 'Compra', 'Desvira, recarrega a energia, efeitos de início de turno e compra 1'],
        ['principal', 'Principal', 'Jogar cartas e declarar ataques'], ['defesa', 'Combate', 'O atacado escolhe bloqueios; depois volta para a Principal'],
        ['final', 'Final', 'Efeitos de fim de turno, descarte até ' + MAO_FIM + ' cartas e o dano nas Lendas some']];
    const fases = (e, ativo) => (ativo ? `<ol class="gl-fases" aria-label="Fases do turno">${FASES.map(([f, nome, dica]) =>
        `<li class="${e.fase === f ? 'gl-fase-atual' : ''}" title="${esc(dica)}">${nome}</li>`).join('')}</ol>` : '');
    // o texto do feitiço tem efeito automático (o motor resolve; não precisa escolher o efeito à mão)
    const conjuraSozinho = c => (c.hab || []).length > 0;
    const qtd = v => (Array.isArray(v) ? v.length : (v || 0));

    function cartaDef(id) {
        const c = Object.assign({}, D.estado.cartas[id] || { nome: '?', tipo: 'feitico', custo: 0 });
        const extra = C.imagens[id];
        if (extra) { c.imagem = extra.imagem; c.descricao = c.descricao || extra.descricao; }
        return c;
    }
    // ficha: Lenda sem carta
    const defLenda = x => (x.c != null ? cartaDef(x.c)
        : { nome: x.nome || 'Ficha', tipo: 'lenda', custo: 0, ataque: x.atk, vida: x.max, descricao: 'Ficha', palavras: '' });
    const nomeLenda = x => defLenda(x).nome;
    const nomeDe = lado => {
        const j = (D.jogadores || []).find(x => x.lado === lado);
        return esc(j ? j.nome : ((D.estado.nomes || {})[lado] || String(lado).toUpperCase()));
    };
    // atributos para abrir os detalhes de uma carta (clique ou botão direito)
    const ver = (c, inst, dono) => ` data-ver="${c == null ? '' : esc(c)}"${inst != null ? ` data-ver-inst="${inst}"` : ''}${dono ? ` data-ver-dono="${dono}"` : ''}`;
    const custoCmd = l => { const x = (l.comando || [])[0]; return x ? (+cartaDef(x.c).custo || 0) + 2 * (l.taxa || 0) : null; };
    const inicialDe = lado => Array.from(nomeDe(lado).replace(/&[^;]+;/g, '').trim())[0] || '?';
    // partida em equipes: a letra da equipe ao lado do nome (aliados não se atacam)
    const equipeDe = lado => ((D.estado && D.estado.equipes) || {})[lado] || null;
    const selo = lado => equipeDe(lado) ? ` <span class="gl-equipe gl-equipe--${equipeDe(lado)}" title="Equipe ${equipeDe(lado)}">${equipeDe(lado)}</span>` : '';
    // custo de uma carta da mão agora: o servidor manda já com os descontos das cartas em jogo
    const custoAgora = (l, x, c) => { const m = (l.extras && l.extras.custos) || {}; return m[x.i] != null ? +m[x.i] : +c.custo; };
    // habilidades de uma carta sua (na mesa ou no cemitério), escritas nela: Josael, Flavinho, Aporium, Silhueta…
    const habilidades = (x, zona) => {
        const lista = [];
        ((x.c != null && (D.estado.cartas[x.c] || {}).ativ) || []).forEach((h, k) => { if ((h.zona || 'mesa') === zona) lista.push([String(k), h.rot]); });
        if (zona === 'mesa' && x.st && x.st.silhueta) lista.push(['silhueta', 'Pagar 1 de energia: desvirar (sai da Silhueta)']);
        return lista.map(([q, rot]) => `<button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-hab="${q}" data-inst="${x.i}">${esc(rot)}</button>`).join('');
    };

    /* ---------- estado do momento ---------- */
    function momento() {
        const e = D.estado;
        const L = e.lados;
        const ordem = e.ordem || ['a', 'b'];
        const eu = D.lado || ordem[0]; // quem assiste vê a mesa pelo primeiro lugar
        const ativo = D.status === 'andamento' && !e.fim && !e.pausa; // pausada pelo mestre: ninguém age
        const jogo = !!(ativo && D.lado && !L[D.lado].fora); // posso agir (não estou fora)
        const ataque = e.ataque || { atacantes: [], bloqueios: {}, defensor: null };
        const esc = ativo ? e.escolha || null : null; // um efeito esperando alguém escolher alvo ou carta
        return {
            e, L, ordem, eu, ativo, jogo, ataque, esc,
            escolho: !!(jogo && esc && esc.lado === D.lado),
            minhaVez: jogo && !esc && e.vez === D.lado && e.fase === 'principal',
            defendo: jogo && !esc && e.fase === 'defesa' && ataque.defensor === D.lado,
            esperoDefesa: jogo && e.fase === 'defesa' && e.vez === D.lado,
            atacando: ataque.atacantes || [],
            bloqueios: ataque.bloqueios || {},
            inimigos: ordem.filter(l => l !== eu && !L[l].fora && !(equipeDe(l) && equipeDe(l) === equipeDe(eu))),
        };
    }
    const tem = (x, p) => (x.pal || []).includes(p);
    // mesmas regras de Gladio::atacar: pronta, com ataque, sem Defensor e sem nada que a impeça ("não pode mais atacar")
    const podeAtacar = x => x.pode && x.atk > 0 && !tem(x, 'defensor') && !x.preso && !(x.fl || []).some(f => f === 'silhueta' || f === 'preso_eq');

    /* ---------- desenho ---------- */
    function renderGladio() {
        const M = momento();
        const { e, L, ordem, eu, ativo, minhaVez, defendo, esperoDefesa, atacando, bloqueios } = M;

        // seleções que deixaram de valer
        if (!minhaVez) { atacantes = []; if (alvo) alvo = null; }
        atacantes = atacantes.filter(i => L[eu].mesa.some(m => m.i === i && podeAtacar(m)));
        if (!defendo) bloqueador = null;

        const alvoOk = (lado, tipo, x) => {
            if (M.escolho) { // alvo pedido por um efeito
                const op = M.esc.opcoes || [];
                if (tipo === 'lenda') return ['lenda', 'qualquer'].includes(M.esc.tipo) && op.includes(x.i);
                return ['jogador', 'qualquer'].includes(M.esc.tipo) && op.includes('j:' + lado);
            }
            if (defendo && bloqueador != null) {
                if (tipo !== 'lenda' || lado !== e.vez || !atacando.includes(x.i)) return false;
                const b = L[eu].mesa.find(m => m.i === bloqueador);
                if (!b) return false;
                // mesmas regras de Gladio::naoBloqueia: Imbloqueável, Voar, Sombra e Furtividade
                if (tem(x, 'imbloqueavel') || (tem(x, 'sombra') !== tem(b, 'sombra'))) return false;
                if (tem(x, 'furtividade') && (+b.atk || 0) > (+x.atk || 0)) return false;
                return !tem(x, 'voar') || tem(b, 'voar') || tem(b, 'alcance');
            }
            if (!alvo || L[lado].fora) return false;
            if (alvo.tipo === 'copiar') return tipo === 'lenda' && lado === eu && x.i !== alvo.inst;
            if (alvo.tipo === 'equipar') return tipo === 'lenda' && lado === eu;
            if (alvo.precisa === 'inimigo') return lado !== eu;
            if (alvo.precisa === 'aliado') return lado === eu;
            return tipo === 'lenda' && lado === eu; // fortalecer
        };

        const shots = (lado, meu) => {
            const n = L[lado].shots || 0;
            return `<span class="gl-shots" title="Shots: ${n} de ${SHOTS} (no ${SHOTS}º está fora)">` +
                Array.from({ length: SHOTS }, (_, k) => `<i class="${k < n ? 'cheio' : ''}"></i>`).join('') + '</span>';
        };
        const vida = lado => {
            const l = L[lado];
            const pct = Math.max(0, Math.min(100, (l.vida / (l.max || 1)) * 100));
            return `<div class="gl-vida" title="Pontos de vida"><span style="width:${pct}%"></span><b>${icone('coracao')} ${Math.max(0, l.vida)} / ${l.max}</b></div>`;
        };
        const comandoBotao = lado => {
            const x = (L[lado].comando || [])[0];
            if (!x) return '';
            const meu = lado === D.lado;
            return `<button type="button" class="gl-zona gl-zona-cmd" data-gl="comando" data-lado="${lado}"${meu ? ` data-comando="${x.i}"` : ''} title="Comandante na zona de comando: ${esc(cartaDef(x.c).nome)} (custo ${custoCmd(L[lado])})${meu ? ' · arraste para o campo para invocar' : ''}">` +
                `${icone('coroa')} <span class="gl-cmd-nome">${esc(cartaDef(x.c).nome)}</span> <b>${icone('raio')}${custoCmd(L[lado])}</b></button>`;
        };
        const cemiterioBotao = lado => `<button type="button" class="gl-zona" data-gl="cemiterio" data-lado="${lado}" title="Cemitério">${icone('cruz')} ${L[lado].cemiterio.length}</button>`;

        // uma carta na mesa (Lenda ou feitiço no campo), com contador, anotação e o botão de ações
        // quem mirou esta carta ou este jogador: efeitos (inclusive instantâneos que já resolveram) deste turno e do anterior,
        // e o alvo que está esperando resposta agora (pulsando)
        const miradaPor = (lista, chave) => {
            const regs = (lista || []).filter(r => r.t >= (e.turno || 0) - 1 && e.cartas[r.c]);
            const pend = e.pilha && e.pilha.campo === 'alvo:' + chave && e.pilha.fonte_c != null ? e.pilha.fonte_c : null;
            if (!regs.length && pend == null) return '';
            const nomes = regs.filter(r => r.c !== pend).map(r => cartaDef(r.c).nome);
            const titulo = (pend != null ? 'Sendo mirada agora por ' + cartaDef(pend).nome + '. ' : '') + (nomes.length ? 'Já foi alvo de: ' + nomes.join(', ') : '');
            return `<span class="gl-mirada${pend != null ? ' gl-mirada--agora' : ''}" title="${esc(titulo)}">${icone('alvo')}` +
                (pend != null ? `<b>${esc(cartaDef(pend).nome)}</b>` : '') +
                nomes.slice(-2).map(n => `<i>${esc(n)}</i>`).join('') + (nomes.length > 2 ? `<i>+${nomes.length - 2}</i>` : '') + '</span>';
        };
        const naMesa = (x, lado, feitico) => {
            const meu = lado === D.lado;
            const cls = [];
            let attrs;
            if (feitico) {
                attrs = `data-feitico="${x.i}" data-dono="${lado}"${ver(x.c, x.i, lado)}`;
            } else {
                if (meu && minhaVez && !e.atacou && podeAtacar(x)) cls.push('gl-pode');
                if (x.virada) cls.push('gl-virada');
                if (atacantes.includes(x.i)) cls.push('gl-escolhido');
                if (atacando.includes(x.i)) cls.push('gl-atacando');
                if (bloqueios[x.i] != null) cls.push('gl-bloqueando');
                if (bloqueador === x.i || (alvo && alvo.inst === x.i)) cls.push('gl-mirando');
                if (alvoOk(lado, 'lenda', x)) cls.push('gl-alvo');
                if (meu && defendo && !x.virada) cls.push('gl-defensor');
                attrs = `data-lenda="${x.i}" data-dono="${lado}"${ver(x.c, x.i, lado)}`;
            }
            const bloqueia = !feitico && bloqueios[x.i] != null ? L[e.vez].mesa.find(a => a.i === +bloqueios[x.i]) : null;
            const bloqueadoPor = !feitico && atacando.includes(x.i) ? Object.keys(bloqueios).filter(b => +bloqueios[b] === x.i)
                .map(b => (L[M.ataque.defensor] || { mesa: [] }).mesa.find(m => m.i === +b)).filter(Boolean) : [];
            const etiqueta = feitico ? '' : bloqueadoPor.length ? `<span class="gl-etiqueta gl-etiqueta--defendido" title="Já foi bloqueada">defendido por ${bloqueadoPor.map(m => esc(nomeLenda(m))).join(', ')}</span>`
                : atacando.includes(x.i) ? '<span class="gl-etiqueta gl-etiqueta--ataque">atacando</span>'
                : (bloqueia ? `<span class="gl-etiqueta">bloqueia ${esc(nomeLenda(bloqueia))}</span>`
                : (x.virada ? '<span class="gl-etiqueta gl-etiqueta--cansada" title="Atacou: só volta a bloquear no próximo turno de quem a controla">virada</span>'
                : (!x.pode && meu && minhaVez ? '<span class="gl-etiqueta gl-etiqueta--cansada" title="Entrou neste turno ou já atacou">cansada</span>' : '')));
            const carta = feitico ? window.JG.carta(cartaDef(x.c), { classe: cls.join(' '), attrs })
                : window.JG.carta(defLenda(x), { atk: x.atk, vida: x.vida, max: x.max, pal: x.pal || [], classe: cls.join(' '), attrs });
            // equipamentos vinculados a esta Lenda: ficam presos a ela (e caem junto)
            const equip = feitico ? [] : (L[lado].feiticos || []).filter(f => f.em === x.i);
            return `<div class="gl-slot${feitico ? ' gl-slot--feitico' : ''}${equip.length ? ' gl-slot--equipada' : ''}" data-slot="${x.i}">` +
                (equip.length ? `<div class="gl-equipos">${equip.map(f => `<button type="button" class="gl-equipo" data-mais-feitico="${f.i}" data-dono="${lado}" title="Equipado: ${esc(cartaDef(f.c).nome)}${f.bonus && (f.bonus.atk || f.bonus.vida) ? ` (${f.bonus.atk >= 0 ? '+' : ''}${f.bonus.atk}/${f.bonus.vida >= 0 ? '+' : ''}${f.bonus.vida})` : ''}">${icone('adaga')} ${esc(cartaDef(f.c).nome)}</button>`).join('')}</div>` : '') + carta +
                (!feitico && x.i === L[lado].cmd ? '<span class="gl-cmd-selo" title="Comandante">' + icone('coroa') + '</span>' : '') +
                (x.nota ? `<span class="gl-nota" title="${esc(x.nota)}">${esc(x.nota)}</span>` : '') +
                etiqueta + miradaPor(x.alvo_de, 'inst:' + x.i) +
                (!feitico ? (x.regen > 0 ? `<span class="gl-selo gl-selo-regen" title="Regenerar: volta de pé ao morrer (${x.regen}x)">${icone('regenerar')}${x.regen}</span>` : '') +
                    (x.st && x.st.silhueta ? '<span class="gl-selo gl-selo-silhueta" title="De cabeça para baixo: hexo-imune, indestrutível e não ataca">' + icone('invertido') + '</span>' : '') +
                    ((x.fl || []).includes('semdano') ? '<span class="gl-selo" title="Não sofre dano neste turno">' + icone('escudo') + '</span>' : '') : '') +
                (M.jogo ? `<button type="button" class="gl-mais" data-mais-${feitico ? 'feitico' : 'mesa'}="${x.i}" data-dono="${lado}" title="Ações desta carta" aria-label="Ações de ${esc(feitico ? cartaDef(x.c).nome : nomeLenda(x))}">⋯</button>` : '') +
                `</div>`;
        };
        const campo = lado => {
            const l = L[lado];
            const lendas = l.mesa.map(x => naMesa(x, lado, false)).join('');
            const feiticos = (l.feiticos || []).filter(x => !x.em || !l.mesa.some(m => m.i === x.em)).map(x => naMesa(x, lado, true)).join('');
            return `<div class="gl-campo" data-campo="${lado}"${lado === D.lado ? ' data-zona="campo"' : ''}>` +
                (lendas || feiticos ? lendas + (feiticos ? `<div class="gl-feiticos" title="Feitiços em jogo">${feiticos}</div>` : '')
                    : `<p class="gl-vazio">${lado === D.lado && M.jogo ? 'Arraste uma carta da mão para cá' : 'Sem cartas em jogo'}</p>`) + '</div>';
        };

        // playmat: a imagem que o dono escolheu cobre o lado inteiro dele (campo, barra e mão)
        const playmat = lado => { const pm = (C.playmats || {})[lado]; return pm ? { cls: ' gl-playmat', st: ` style="--playmat:url('${esc(pm)}')"` } : { cls: '', st: '' }; };
        // assento de um adversário (no alto da mesa)
        const assento = lado => {
            const l = L[lado];
            const mao = Array.isArray(l.mao) ? l.mao : Array.from({ length: l.mao || 0 }, () => ({ oculta: true }));
            const reveladas = mao.filter(c => c.rev);
            return `<section class="gl-assento${playmat(lado).cls}${e.vez === lado && ativo ? ' gl-vez' : ''}${e.fase === 'defesa' && M.ataque.defensor === lado ? ' gl-atacado' : ''}${l.fora ? ' gl-fora' : ''}"${playmat(lado).st}>` +
                `<div class="gl-assento-cab${alvoOk(lado, 'heroi') ? ' gl-alvo' : ''}" data-heroi="${lado}">` +
                    `<span class="gl-avatar">${inicialDe(lado)}</span>` +
                    `<div class="gl-assento-info"><strong>${nomeDe(lado)}${selo(lado)}</strong>${vida(lado)}${miradaPor(L[lado].alvo_de, 'lado:' + lado)}</div>` +
                    `<div class="gl-assento-numeros"><span class="gl-energia" title="Energia">${icone('raio')} ${l.energia}/${l.energiaMax}</span>` +
                        `<span title="Cartas no baralho">${icone('baralho')} ${qtd(l.baralho)}</span>${cemiterioBotao(lado)}${comandoBotao(lado)}</div>` +
                    `<div class="gl-assento-shots">${shots(lado, false)}</div>` +
                    (l.fora ? '<span class="gl-selo-fora">Fora</span>' : '') +
                `</div>` +
                `<div class="gl-mao-op" title="Cartas na mão">${mao.filter(c => !c.rev).map(() => '<span class="gl-verso"></span>').join('')}` +
                    (reveladas.length ? `<span class="gl-reveladas" title="Cartas reveladas">${reveladas.map(c => window.JG.carta(cartaDef(c.c), { classe: 'gl-revelada', attrs: ver(c.c) })).join('')}</span>` : '') +
                    `<small>${mao.length} na mão</small></div>` +
                campo(lado) +
                `</section>`;
        };

        // barra do meio: pote, vez e o que dá para fazer agora
        let acoes = '';
        const mull = e.fase === 'mulligan' && ativo ? ((e.mull || {})[D.lado] || null) : null;
        if (!(e.fase === 'mulligan')) paraFundo = [];
        if (mull && !mull.ok) {
            paraFundo = paraFundo.filter(i => (L[eu].mao || []).some(x => x.i === i));
            acoes = `<span class="gl-dica gl-dica--escolha">Mão inicial: mantenha ou faça mulligan (a mão volta ao baralho e você compra 5 de novo)` +
                (mull.n ? ` · escolha <b>${mull.n}</b> carta${mull.n > 1 ? 's' : ''} para o fundo do baralho (${paraFundo.length}/${mull.n})` : '') + '</span>' +
                `<button type="button" class="camp-btn camp-btn-primario" data-gl="manter"${paraFundo.length === mull.n ? '' : ' disabled'}>Manter a mão</button>` +
                (mull.n + 1 < 5 ? `<button type="button" class="camp-btn" data-gl="mulligan" title="Embaralha a mão de volta e compra 5; ao manter, põe ${mull.n + 1} no fundo">Mulligan</button>` : '');
        } else if (e.fase === 'mulligan' && ativo) {
            acoes = `<span class="gl-dica gl-dica--suave">${mull ? 'Você manteve a mão. ' : ''}Esperando: ${M.ordem.filter(l => !((e.mull || {})[l] || {}).ok && !L[l].fora).map(nomeDe).join(', ')}</span>`;
        } else if (M.esc) {
            const cartasEsc = ['baralho', 'cemiterio', 'palavra', 'confirmar', 'opcao', 'resposta'].includes(M.esc.tipo);
            acoes = M.escolho
                ? `<span class="gl-dica gl-dica--escolha" title="${esc(M.esc.texto)}">${esc(M.esc.texto)}${M.esc.tipo === 'mao' ? ' (clique numa carta da sua mão)' : (cartasEsc ? '' : ' (clique no alvo destacado)')}</span>` +
                  (cartasEsc ? `<button type="button" class="camp-btn camp-btn-primario" data-gl="escolha-cartas">${({ confirmar: 'Responder', palavra: 'Escolher a palavra-chave', opcao: 'Escolher', resposta: 'Responder' })[M.esc.tipo] || 'Escolher a carta'}</button>` : '') +
                  (M.esc.obrig ? '' : `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="pular" title="Não aplicar este efeito">Pular</button>`)
                : `<span class="gl-dica gl-dica--suave">Esperando ${nomeDe(M.esc.lado)}: ${esc(M.esc.texto)}</span>`;
        } else if (alvo) {
            acoes = `<span class="gl-dica">${alvo.tipo === 'copiar' ? 'Escolha a Lenda sua que vai servir de modelo' : (alvo.tipo === 'equipar' ? 'Escolha a Lenda sua que vai receber o equipamento' : 'Escolha o alvo do feitiço')}</span>` +
                `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="cancelar-alvo">Cancelar</button>`;
        } else if (defendo) {
            acoes = `<span class="gl-dica">${bloqueador != null ? 'Agora clique no atacante que ela vai bloquear' : 'Escolha bloqueadores: clique numa Lenda sua e depois no atacante'}</span>` +
                `<button type="button" class="camp-btn camp-btn-primario" data-gl="defender">Confirmar defesa</button>`;
        } else if (esperoDefesa) {
            acoes = `<span class="gl-dica">Aguardando a defesa de ${nomeDe(M.ataque.defensor)}…</span>`;
        } else if (minhaVez) {
            acoes = (atacantes.length
                    ? M.inimigos.map(l => `<button type="button" class="camp-btn camp-btn-primario" data-gl="atacar" data-alvo="${l}">${icone('espadas')} Atacar ${nomeDe(l)} com ${atacantes.length}</button>`).join('') +
                      `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="limpar-ataque">Limpar</button>`
                    : `<span class="gl-dica gl-dica--suave">${e.atacou ? 'Você já atacou neste turno (uma onda de ataque por turno) · ainda dá para jogar cartas' : 'Arraste cartas para o campo · escolha as Lendas da sua onda de ataque'}</span>`) +
                `<button type="button" class="camp-btn camp-btn-primario" data-gl="passar" title="Vai para a fase final: efeitos de fim de turno, descarte até ${MAO_FIM} cartas e a vez passa">Fim do turno ▸</button>`;
        } else if (ativo) {
            acoes = `<span class="gl-dica gl-dica--suave">Vez de ${nomeDe(e.vez)}</span>`;
        }
        const pote = e.pote || 0;
        const centro = `<div class="gl-centro">` +
            `<div class="gl-pote" title="${D.aposta ? 'Aposta de todos: o vencedor leva o pote inteiro. As moedas mostram a vida já perdida na mesa' : 'Pontos de vida perdidos até agora (amistoso)'}">` +
                `<span class="gl-moedas" aria-hidden="true">${'<i></i>'.repeat(Math.min(6, 1 + Math.floor(pote / Math.max(1, (e.total || 30) / 6))))}</span>` +
                `<span><small>Pote</small><b>${D.aposta ? icone('moeda') + ' ' + (D.aposta * ordem.length).toLocaleString('pt-BR') : icone('coracao') + ' ' + pote.toLocaleString('pt-BR')}</b></span></div>` +
            `<div class="gl-centro-acoes"><span class="gl-turno">Turno ${e.turno}</span>${fases(e, ativo)}${relogio(M)}${acoes}</div>` +
            `</div>`;

        // eu: campo, barra e a mão em leque
        const l = L[eu];
        const minhaMao = Array.isArray(l.mao) ? l.mao : null;
        const podeJogar = (c, x) => minhaVez && custoAgora(l, x, c) <= l.energia; // só no próprio turno (nem Lendas na defesa)
        const maoHtml = minhaMao
            ? `<div class="gl-mao" style="--n:${minhaMao.length}">${minhaMao.map((x, k) => {
                const c = Object.assign(cartaDef(x.c), { custo: custoAgora(l, x, cartaDef(x.c)) });
                return `<div class="gl-slot gl-slot--mao${x.rev ? ' gl-revelada-minha' : ''}" style="--k:${k}">` +
                    window.JG.carta(c, { classe: (podeJogar(c, x) ? 'gl-jogavel' : 'gl-cara') + (alvo && alvo.inst === x.i ? ' gl-mirando' : '') +
                        (M.escolho && M.esc.tipo === 'mao' && M.esc.opcoes.includes(x.i) ? ' gl-alvo' : '') +
                        (paraFundo.includes(x.i) ? ' gl-mirando' : ''), attrs: `data-mao="${x.i}"${ver(x.c)}` }) +
                    (x.rev ? '<span class="gl-olho" title="Revelada para os outros jogadores">' + icone('olho') + '</span>' : '') +
                    (M.jogo ? `<button type="button" class="gl-mais" data-mais-mao="${x.i}" title="Ações desta carta" aria-label="Ações de ${esc(c.nome)}">⋯</button>` : '') +
                    `</div>`;
            }).join('')}</div>`
            : `<div class="gl-mao-op">${'<span class="gl-verso"></span>'.repeat(qtd(l.mao))}</div>`;
        const minhaBarra = `<div class="gl-eu-barra${e.vez === eu && ativo ? ' gl-vez' : ''}${e.fase === 'defesa' && M.ataque.defensor === eu ? ' gl-atacado' : ''}${l.fora ? ' gl-fora' : ''}${alvoOk(eu, 'heroi') ? ' gl-alvo' : ''}" data-heroi="${eu}">` +
            `<span class="gl-avatar">${inicialDe(eu)}</span>` +
            `<div class="gl-assento-info"><strong>${nomeDe(eu)}${selo(eu)}${D.lado ? ' <small>(você)</small>' : ''}</strong>${vida(eu)}${miradaPor(l.alvo_de, 'lado:' + eu)}</div>` +
            `<span class="gl-energia gl-energia--grande" title="Energia: sobe 1 a cada turno seu">${icone('raio')} ${l.energia}/${l.energiaMax}</span>` +
            `<div class="gl-assento-shots">${shots(eu, eu === D.lado)}</div>` +
            `<span class="gl-zona" title="Cartas no baralho">${icone('baralho')} ${qtd(l.baralho)}</span>` +
            (l.extras && l.extras.topo ? `<button type="button" class="gl-zona"${ver(l.extras.topo)} title="Carta do topo do seu baralho (uma carta em jogo deixa você ver)">${icone('olho')} ${esc(cartaDef(l.extras.topo).nome)}</button>` : '') +
            cemiterioBotao(eu) +
            comandoBotao(eu) +
            (l.fora ? '<span class="gl-selo-fora">Fora</span>' : '') +
            (M.jogo ? `<button type="button" class="gl-mini gl-desistir" data-gl="desistir" title="Desistir da partida">Desistir</button>` : '') +
            `</div>`;

        const outros = ordem.slice(ordem.indexOf(eu) + 1).concat(ordem.slice(0, ordem.indexOf(eu)));
        box.innerHTML =
            `<div class="gl-mesa${defendo || esperoDefesa ? ' gl-em-combate' : ''}">` +
                `<div class="gl-oponentes gl-op-${outros.length}">${outros.map(assento).join('')}</div>` +
                centro +
                `<div class="gl-meu-lado${playmat(eu).cls}"${playmat(eu).st}>${campo(eu)}${minhaBarra}${maoHtml}</div>` +
            `</div>` +
            (e.pausa && D.status === 'andamento' ? `<div class="gl-pausa" role="status"><b>${icone('pausa')} Partida pausada</b><span>O mestre pausou o jogo. O relógio parou e ninguém pode agir até ele retomar.</span></div>` : '') +
            `<aside class="gl-log"><h3>Acontecimentos</h3><ol>${e.log.slice().reverse().map(t => `<li>${esc(t)}</li>`).join('')}</ol></aside>` +
            renderModal(M);

        // status no topo
        const bm = document.getElementById('jg-miniatura');
        if (bm) bm.hidden = !jogandoAqui();
        // botão do mestre: pausar / retomar
        const bp = document.getElementById('jg-pausar');
        if (bp) {
            bp.hidden = !(D.mestre && D.status === 'andamento' && !e.fim);
            bp.innerHTML = e.pausa ? icone('play') + ' Retomar' : icone('pausa') + ' Pausar';
            bp.dataset.acao = e.pausa ? 'retomar' : 'pausar';
        }
        if (D.status === 'cancelada') status('Partida cancelada pelo mestre. As apostas voltaram.');
        else if (e.pausa && !e.fim) status(icone('pausa') + ' <strong>Partida pausada pelo mestre.</strong> O relógio parou e ninguém age até ele retomar.');
        else if (e.fim) {
            const time = e.fim.equipe || null;
            const ganhei = D.lado && (time ? equipeDe(D.lado) === time : e.fim.vencedor === D.lado);
            status(`${ganhei ? icone('trofeu') + ' Você venceu!' : (D.lado ? 'Você perdeu.' : '')} ${time ? `Venceu a <strong>equipe ${time}</strong> (${ordem.filter(l => equipeDe(l) === time).map(nomeDe).join(', ')})` : `Vencedor: <strong>${nomeDe(e.fim.vencedor)}</strong>`}` +
                (e.fim.motivo === 'overdeck' ? ' (o adversário ficou sem cartas para comprar)' : '') +
                (D.aposta ? (time ? ` · a equipe divide o pote de ${(D.aposta * ordem.length).toLocaleString('pt-BR')} de Dinheiro` : ` · leva o pote de ${(D.aposta * ordem.length).toLocaleString('pt-BR')} de Dinheiro`) : ''));
        } else if (D.lado && L[D.lado].fora) status('Você está fora desta partida. Pode continuar assistindo.');
        else if (e.fase === 'mulligan') status(((e.mull || {})[D.lado] || {}).ok === false
            ? '<strong>Mão inicial.</strong> Mantenha ou faça mulligan antes de a partida começar.' : 'Esperando todos decidirem o mulligan.');
        else if (M.escolho) status(`<strong>Sua escolha:</strong> ${esc(M.esc.texto)}.`);
        else if (M.esc) status(`Esperando <strong>${nomeDe(M.esc.lado)}</strong> escolher: ${esc(M.esc.texto)}.`);
        else if (defendo) status(`<strong>${nomeDe(e.vez)} está atacando você!</strong> ${atacando.length} Lenda(s) avançam. Bloqueie ou jogue Lendas para se defender.`);
        else if (esperoDefesa) status(`Ataque declarado. <strong>${nomeDe(M.ataque.defensor)}</strong> está escolhendo a defesa.`);
        else if (minhaVez) status('<strong>Sua vez.</strong> Arraste cartas para o campo, ataque com Lendas prontas ou use as ferramentas.');
        else status(`Vez de <strong>${nomeDe(e.vez)}</strong>.${D.lado ? '' : ' Você está assistindo.'}`);
    }

    /* ---------- janelas: baralho, cemitério, conjurar e ações de uma carta ---------- */
    function renderModal(M) {
        if (!modal) return '';
        const { L, eu, minhaVez } = M;
        const fechar = `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="fechar-modal">${modal.volta ? 'Voltar' : 'Fechar'}</button>`;
        const botaoVer = (c, inst, dono) => `<button type="button" class="camp-btn"${ver(c, inst, dono)}>${icone('lupa')} Ver detalhes</button>`;
        const janela = (titulo, corpo, rodape) => `<div class="gl-modal" data-gl="fundo-modal"><div class="gl-modal-caixa" role="dialog" aria-label="${esc(titulo)}">` +
            `<div class="gl-modal-cab"><h3>${esc(titulo)}</h3>${fechar}</div><div class="gl-modal-corpo">${corpo}</div>` +
            (rodape ? `<div class="gl-modal-rodape">${rodape}</div>` : '') + `</div></div>`;
        const linhaCarta = c => `<span class="jg-deck-custo">${esc(c.custo)}</span><span class="jg-deck-nome">${esc(c.nome)} <small>${c.tipo === 'lenda' ? `Lenda ${esc(c.ataque)}/${esc(c.vida)}` : 'Feitiço'}</small></span>`;
        // só a anotação: contadores, ataque/vida e palavras-chave mudam pelo efeito das cartas
        const marcacao = (x, rotulo) => `<form class="gl-form-nota" data-gl-form="nota"><label>Anotação ${rotulo}<input type="text" name="nota" maxlength="60" value="${esc(x.nota || '')}" placeholder="Ex.: envenenada, +2/+2 até o fim do turno"></label>` +
            `<button class="camp-btn camp-btn-pequeno">Salvar anotação</button></form>`;

        if (modal.tipo === 'escolha') {
            // carta pedida por um efeito, no próprio baralho ou cemitério
            if (!M.escolho || !['baralho', 'cemiterio', 'palavra', 'confirmar', 'opcao', 'resposta'].includes(M.esc.tipo)) { modal = null; return ''; }
            if (M.esc.tipo === 'opcao') { // "ou" do texto, a letra do Zequinha, o palpite da Estátua, o que a Maria vê
                const rot = M.esc.rotulos || {};
                return janela(M.esc.fonte, `<p class="gl-conjurar-texto">${esc(M.esc.texto)}</p><div class="gl-palavras-mesa">${M.esc.opcoes.map(o =>
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-escolher="${esc(o)}">${esc(rot[o] || o)}</button>`).join('')}</div>`,
                    M.esc.obrig ? '' : `<button type="button" class="camp-btn" data-gl="pular">Pular</button>`);
            }
            if (M.esc.tipo === 'resposta') { // Vergonha de Dante / Imbuir: usar agora, fora do seu turno
                const mao = Array.isArray(L[eu].mao) ? L[eu].mao : [];
                return janela('Responder?', `<p class="gl-conjurar-texto">${esc(M.esc.texto)}</p><div class="gl-grade-cartas">${mao.filter(x => M.esc.opcoes.includes(x.i)).map(x =>
                    `<div class="gl-slot">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}<span class="gl-cem-botoes"><button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-escolher="${x.i}">Usar</button></span></div>`).join('')}</div>` +
                    // efeitos de cartas em jogo (Marechal Fonsceca, Painel de Kuros): pagar e negar
                    M.esc.opcoes.filter(o => typeof o === 'string').map(o => `<button type="button" class="camp-btn camp-btn-primario" data-escolher="${esc(o)}">${esc((M.esc.rotulos || {})[o] || o)}</button>`).join(''),
                    `<button type="button" class="camp-btn" data-gl="pular">Não usar</button>`);
            }
            if (M.esc.tipo === 'confirmar') { // "você pode…": sim ou não
                return janela(M.esc.fonte, `<p class="gl-conjurar-texto">${esc(M.esc.texto)}</p>`,
                    `<button type="button" class="camp-btn camp-btn-primario" data-escolher="sim">Sim, fazer</button><button type="button" class="camp-btn" data-gl="pular">Não</button>`);
            }
            if (M.esc.tipo === 'palavra') {
                const PAL = window.JG.PALAVRA;
                return janela(M.esc.fonte, `<p class="gl-conjurar-texto">${esc(M.esc.texto)}</p><div class="gl-palavras-mesa">${M.esc.opcoes.map(p =>
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-escolher="${p}" title="${esc((PAL[p] || {}).desc || '')}">${esc((PAL[p] || {}).nome || p)}</button>`).join('')}</div>`,
                    M.esc.obrig ? '' : `<button type="button" class="camp-btn" data-gl="pular">Pular</button>`);
            }
            const zona = Array.isArray(L[eu][M.esc.tipo]) ? L[eu][M.esc.tipo] : [];
            const ops = zona.filter(x => M.esc.opcoes.includes(x.i));
            const corpo = `<p class="gl-conjurar-texto">${esc(M.esc.texto)}</p><div class="gl-grade-cartas">${ops.map(x =>
                `<div class="gl-slot">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}` +
                `<span class="gl-cem-botoes"><button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-escolher="${x.i}">${esc(M.esc.rotulo || 'Escolher')}</button></span></div>`).join('')}</div>`;
            return janela(M.esc.fonte, corpo, M.esc.obrig ? '' : `<button type="button" class="camp-btn" data-gl="pular">Pular</button>`);
        }

        if (modal.tipo === 'cemiterio') {
            const lado = modal.lado || eu;
            const abas = M.ordem.map(l => `<button type="button" class="jg-aba${l === lado ? ' ativo' : ''}" data-gl="aba-cemiterio" data-lado="${l}">` +
                `${l === D.lado ? 'Seu' : nomeDe(l)} · ${L[l].cemiterio.length}</button>`).join('');
            const cartasCem = L[lado].cemiterio.slice().reverse();
            const meu = lado === D.lado && M.jogo;
            const formas = meu && L[lado].extras && L[lado].extras.formas_cem;
            const corpo = `<div class="jg-abas">${abas}</div>` + (cartasCem.length ? `<div class="gl-grade-cartas">${cartasCem.map(x =>
                `<div class="gl-slot">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}` +
                (meu ? `<span class="gl-cem-botoes">${habilidades(x, 'cemiterio')}${formas && ((D.estado.cartas[x.c] || {}).sub || []).includes('forma')
                    ? `<button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-cem-jogar="${x.i}" title="Cristian Fernandez: conjura do cemitério e a carta é banida">Conjurar (banir)</button>` : ''}</span>` : '') +
                `</div>`).join('')}</div>` : '<p class="camp-vazio">Cemitério vazio.</p>');
            return janela('Cemitério', corpo, '<span class="camp-dica">Lendas que caem, feitiços mandados ao cemitério e cartas descartadas. O mais recente aparece primeiro.</span>');
        }

        if (modal.tipo === 'mao') {
            const x = (Array.isArray(L[eu].mao) ? L[eu].mao : []).find(c => c.i === modal.inst);
            if (!x) { modal = null; return ''; }
            const c = cartaDef(x.c);
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(c)}<div class="gl-modal-acoes">` +
                `<button type="button" class="camp-btn" data-m="revelar">${x.rev ? 'Esconder de novo' : 'Revelar para os outros jogadores'}</button>` +
                botaoVer(x.c) +
                `<p class="camp-dica">Arraste a carta para o campo para jogar (pagando a energia, no seu turno).</p></div></div>`;
            return janela(c.nome, corpo, '');
        }

        if (modal.tipo === 'mesa') {
            const dono = modal.lado;
            const x = (L[dono] ? L[dono].mesa : []).find(m => m.i === modal.inst);
            if (!x) { modal = null; return ''; }
            const minha = dono === D.lado;
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(defLenda(x), { atk: x.atk, vida: x.vida, max: x.max, pal: x.pal || [] })}<div class="gl-modal-acoes">` +
                (minha && M.jogo ? habilidades(x, 'mesa') : '') +
                marcacao(x, 'na Lenda') +
                botaoVer(x.c, x.i, dono) +
                `<p class="camp-dica">${minha ? 'Sua Lenda.' : 'Lenda de ' + nomeDe(dono) + '.'} Tudo o que muda nela vem do efeito das cartas.</p></div></div>`;
            return janela(nomeLenda(x) + (minha ? '' : ' · ' + nomeDe(dono)).replace(/&amp;/g, '&'), corpo, '');
        }

        if (modal.tipo === 'comando') {
            const lado = modal.lado;
            const l = L[lado];
            const x = (l.comando || [])[0];
            const meu = lado === D.lado && M.jogo;
            if (!x) { modal = null; return ''; }
            const custo = custoCmd(l);
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}<div class="gl-modal-acoes">` +
                `<p class="gl-conjurar-texto">${icone('coroa')} Comandante ${lado === D.lado ? 'seu' : 'de ' + nomeDe(lado)}. Custo agora: <b>${custo}</b> ` +
                `(${esc(cartaDef(x.c).custo)} da carta + 2 × ${l.taxa || 0} ${l.taxa === 1 ? 'vez' : 'vezes'} que já saiu da zona de comando).</p>` +
                (meu ? `<button type="button" class="camp-btn camp-btn-primario" data-gl="comandar"${M.minhaVez && custo <= l.energia ? '' : ' disabled'}>Invocar pagando ${icone('raio')}${custo}</button>` +
                       `<p class="camp-dica">${M.minhaVez ? (custo <= l.energia ? 'Ou arraste o comandante para o campo.' : 'Energia insuficiente para pagar agora.') : 'Pagando, só no seu turno.'} Quando ele cair ou for sacrificado, volta para cá.</p>` : '') +
                `</div></div>`;
            return janela('Zona de comando', corpo, '');
        }

        if (modal.tipo === 'detalhe') {
            // Lenda na mesa (valores de agora) ou só a carta
            let x = null;
            let dono = modal.lado;
            if (modal.inst != null) {
                for (const lado of M.ordem) {
                    const achou = L[lado].mesa.find(m => m.i === modal.inst) || (L[lado].feiticos || []).find(m => m.i === modal.inst);
                    if (achou) { x = achou; dono = lado; break; }
                }
            }
            const naMesa = x && x.atk != null;
            const c = naMesa ? defLenda(x) : (modal.c ? cartaDef(modal.c) : null);
            if (!c) { modal = modal.volta || null; return modal ? renderModal(M) : ''; }
            const lenda = c.tipo === 'lenda';
            const pal = naMesa ? (x.pal || []) : String(c.palavras || '').split(',').map(p => p.trim()).filter(Boolean);
            const P = window.JG.PALAVRA;
            const auto = c.auto || window.JG.textoEfeito(c);
            const cmd = x && dono && x.i === L[dono].cmd;
            const grande = window.JG.carta(c, naMesa ? { atk: x.atk, vida: x.vida, max: x.max, pal } : {});
            const corpo = `<div class="gl-detalhe">${grande}<div class="gl-detalhe-info">` +
                `<p class="gl-detalhe-tipo">${lenda ? 'Lenda' : 'Feitiço'} · custo ${esc(c.custo)}` +
                (lenda ? ` · ataque ${esc(naMesa ? x.atk : c.ataque)} · vida ${esc(naMesa ? x.vida : c.vida)}${naMesa ? ` / ${esc(x.max)}` : ''}` : '') + '</p>' +
                (naMesa && (x.atk !== +c.ataque || x.max !== +c.vida) && x.c != null ? `<p class="camp-dica">Na carta: ${esc(c.ataque)}/${esc(c.vida)}.</p>` : '') +
                (cmd ? `<p class="gl-detalhe-cmd">${icone('coroa')} Comandante de ${nomeDe(dono)}</p>` : '') +
                (dono && x ? `<p class="camp-dica">Em jogo · ${dono === D.lado ? 'sua' : 'de ' + nomeDe(dono)}${naMesa && x.virada ? ' · virada' : ''}</p>` : '') +
                `<div class="gl-detalhe-texto">${c.descricao ? esc(c.descricao) : '<em>Sem texto.</em>'}</div>` +
                (auto ? `<p class="gl-detalhe-auto"><b>Efeito automático:</b> ${esc(auto)}</p>` : '') +
                (c.parcial ? `<p class="camp-dica">Parte do texto ainda não é aplicada pelo jogo.</p>` : '') +
                (pal.filter(p => P[p]).length ? `<dl class="gl-detalhe-palavras">${pal.filter(p => P[p]).map(p => `<dt>${esc(P[p].nome)}</dt><dd>${esc(P[p].desc)}</dd>`).join('')}</dl>` : '') +
                (x && x.nota ? `<p class="gl-detalhe-marcas">Anotação: ${esc(x.nota)}</p>` : '') +
                `</div></div>`;
            return janela(c.nome, corpo, '');
        }

        if (modal.tipo === 'feitico') {
            const dono = modal.lado;
            const x = ((L[dono] && L[dono].feiticos) || []).find(f => f.i === modal.inst);
            if (!x) { modal = null; return ''; }
            const minha = dono === D.lado;
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(cartaDef(x.c))}<div class="gl-modal-acoes">` +
                marcacao(x, 'no feitiço') +
                botaoVer(x.c, x.i, dono) +
                (x.em && L[dono].mesa.some(m => m.i === x.em) ? `<p class="camp-dica">${icone('adaga')} Equipado em <strong>${esc(nomeLenda(L[dono].mesa.find(m => m.i === x.em)))}</strong>: vai para o cemitério quando ela sair da mesa.</p>` : '') +
                `<p class="camp-dica">Fica em jogo até um efeito tirá-lo.</p></div></div>`;
            return janela(cartaDef(x.c).nome, corpo, '');
        }
        return '';
    }

    /* ---------- jogar uma carta da mão (clique ou arrasto) ---------- */
    function jogarDaMao(inst) {
        const M = momento();
        const x = (Array.isArray(M.L[M.eu].mao) ? M.L[M.eu].mao : []).find(c => c.i === inst);
        const c = x && cartaDef(x.c);
        if (!c) return;
        const custo = custoAgora(M.L[M.eu], x, c);
        if (c.resp) { msg('Essa carta só é usada em resposta à jogada de outro jogador.', true); return; }
        if (!(M.minhaVez && custo <= M.L[M.eu].energia)) {
            msg(M.defendo ? 'Na defesa só dá para escolher bloqueadores: cartas, só no seu turno.' : (custo > M.L[M.eu].energia ? 'Energia insuficiente.' : 'Agora não é a sua vez.'), true);
            return;
        }
        if (c.tipo !== 'lenda' && conjuraSozinho(c)) { acao({ acao: 'jogar', inst }); return; } // alvos são pedidos depois
        const precisa = c.tipo !== 'lenda' && !(c.descricao || '').trim() ? PRECISA[c.efeito] : null; // efeito fixo antigo, carta sem texto
        if (precisa) { alvo = { tipo: 'feitico', inst, precisa }; render(); return; }
        acao({ acao: 'jogar', inst });
    }

    function cliqueGladio(ev) {
        if (engolirClique) { engolirClique = false; return; }
        const M = momento();
        const { e, L, eu } = M;

        // ----- janelas -----
        const fundo = ev.target.closest('[data-gl="fundo-modal"]');
        if (fundo && ev.target === fundo) { modal = modal && modal.volta ? modal.volta : null; render(); return; }
        // detalhes: botões "Ver detalhes", linhas do baralho e cartas dentro das janelas
        const verEl = ev.target.closest('button[data-ver], .gl-linha-ver[data-ver], .gl-modal .jg-carta[data-ver]');
        if (verEl) { abrirDetalhe(verEl); return; }
        const escolhida = ev.target.closest('[data-escolher]');
        if (escolhida) { modal = null; acao({ acao: 'escolher', alvo: escolhida.dataset.escolher }); return; }
        const hab = ev.target.closest('[data-hab]');
        if (hab) { modal = null; acao({ acao: 'habilidade', inst: +hab.dataset.inst, qual: hab.dataset.hab }); return; }
        const doCem = ev.target.closest('[data-cem-jogar]');
        if (doCem) { modal = null; acao({ acao: 'jogar', inst: +doCem.dataset.cemJogar, origem: 'cemiterio' }); return; }
        const m = ev.target.closest('[data-m]');
        if (m && modal) {
            const inst = modal.inst;
            const tipo = m.dataset.m;
            if (tipo === 'revelar') acao({ acao: 'revelar', inst }); // o resto (ficha, ajustes, sacrificar…) só pelas cartas
            return;
        }

        const b = ev.target.closest('[data-gl]');
        if (b) {
            const g = b.dataset.gl;
            if (g === 'fechar-modal') { modal = modal && modal.volta ? modal.volta : null; render(); }
            else if (g === 'comando') { modal = { tipo: 'comando', lado: b.dataset.lado }; render(); }
            else if (g === 'comandar') { modal = null; acao({ acao: 'comandar' }); }
            else if (g === 'cemiterio' || g === 'aba-cemiterio') { modal = { tipo: 'cemiterio', lado: b.dataset.lado }; render(); }
            else if (g === 'cancelar-alvo') { alvo = null; render(); }
            else if (g === 'pular') { modal = null; acao({ acao: 'escolher' }); }
            else if (g === 'escolha-cartas') { modal = { tipo: 'escolha' }; render(); }
            else if (g === 'limpar-ataque') { atacantes = []; render(); }
            else if (g === 'atacar') { const lista = atacantes.slice(); atacantes = []; acao({ acao: 'atacar', insts: lista, alvo: b.dataset.alvo }); }
            else if (g === 'defender') { bloqueador = null; acao({ acao: 'defender' }); }
            else if (g === 'passar') { alvo = null; atacantes = []; acao({ acao: 'passar' }); }
            else if (g === 'manter') { const lista = paraFundo.slice(); paraFundo = []; acao({ acao: 'manter', insts: lista }); }
            else if (g === 'mulligan') { paraFundo = []; acao({ acao: 'mulligan' }); }
            else if (g === 'desistir' && confirm('Desistir da partida?' + (D.aposta ? ' Você perde a aposta.' : ''))) acao({ acao: 'desistir' });
            return;
        }
        const maisMao = ev.target.closest('[data-mais-mao]');
        if (maisMao) { modal = { tipo: 'mao', inst: +maisMao.dataset.maisMao }; render(); return; }
        const maisMesa = ev.target.closest('[data-mais-mesa]');
        if (maisMesa) { modal = { tipo: 'mesa', inst: +maisMesa.dataset.maisMesa, lado: maisMesa.dataset.dono }; render(); return; }
        const maisFeitico = ev.target.closest('[data-mais-feitico]');
        if (maisFeitico) { modal = { tipo: 'feitico', inst: +maisFeitico.dataset.maisFeitico, lado: maisFeitico.dataset.dono }; render(); return; }

        const cartaVer = ev.target.closest('.jg-carta[data-ver]');
        if (!M.jogo) { abrirDetalhe(cartaVer); return; }
        const h = ev.target.closest('[data-heroi]');
        const l = ev.target.closest('[data-lenda]');
        const cartaMao = ev.target.closest('[data-mao]');

        // ----- mulligan: o clique na mão marca as cartas que vão para o fundo -----
        if (e.fase === 'mulligan') {
            const mull = (e.mull || {})[D.lado];
            if (cartaMao && mull && !mull.ok && mull.n > 0) {
                const i = +cartaMao.dataset.mao;
                paraFundo = paraFundo.includes(i) ? paraFundo.filter(x => x !== i) : (paraFundo.length < mull.n ? paraFundo.concat(i) : paraFundo);
                render();
            } else abrirDetalhe(cartaVer);
            return;
        }
        // ----- escolha pedida por um efeito: o clique no alvo destacado responde -----
        if (M.escolho) {
            const marcado = [l, h, cartaMao].find(x => x && x.classList.contains('gl-alvo'));
            if (!marcado) { abrirDetalhe(cartaVer); return; }
            const valor = marcado === l ? l.dataset.lenda : (marcado === h ? 'j:' + h.dataset.heroi : cartaMao.dataset.mao);
            acao({ acao: 'escolher', alvo: valor });
            return;
        }

        // ----- defesa: só escolher bloqueadores (cartas da mão, só no próprio turno) -----
        if (M.defendo) {
            if (cartaMao) { abrirDetalhe(cartaVer); return; }
            if (!l) return;
            const inst = +l.dataset.lenda;
            if (l.dataset.dono === eu) {
                if (M.bloqueios[inst] != null) { bloqueador = null; acao({ acao: 'bloquear', inst }); return; } // tira o bloqueio
                const minha = L[eu].mesa.find(x => x.i === inst);
                if (minha && minha.virada) { msg('Essa Lenda está virada (atacou no seu último turno) e não pode bloquear.', true); return; }
                bloqueador = bloqueador === inst ? null : inst;
                render();
            } else if (bloqueador != null && l.classList.contains('gl-alvo')) {
                const quem = bloqueador;
                bloqueador = null;
                acao({ acao: 'bloquear', inst: quem, outro: inst });
            } else abrirDetalhe(cartaVer);
            return;
        }
        if (!M.minhaVez) { abrirDetalhe(cartaVer); return; }

        // ----- mirando: o clique escolhe o alvo -----
        if (alvo) {
            if (alvo.tipo === 'copiar' || alvo.tipo === 'equipar') {
                if (l && l.classList.contains('gl-alvo')) { const a = alvo; alvo = null; acao({ acao: a.tipo, inst: a.inst, outro: +l.dataset.lenda }); }
                return;
            }
            let escolhido = null;
            if (l && l.classList.contains('gl-alvo')) escolhido = l.dataset.lenda;
            else if (h && h.classList.contains('gl-alvo')) escolhido = h.dataset.heroi === eu ? 'eu' : 'j:' + h.dataset.heroi;
            if (escolhido == null) return;
            const a = alvo;
            alvo = null;
            acao({ acao: 'jogar', inst: a.inst, alvo: escolhido });
            return;
        }
        if (cartaMao) { if (cartaMao.classList.contains('gl-jogavel')) jogarDaMao(+cartaMao.dataset.mao); else abrirDetalhe(cartaVer); return; }
        // escolher quem ataca
        if (l && l.dataset.dono === eu && l.classList.contains('gl-pode')) {
            const inst = +l.dataset.lenda;
            atacantes = atacantes.includes(inst) ? atacantes.filter(i => i !== inst) : atacantes.concat(inst);
            render();
        } else abrirDetalhe(cartaVer);
    }

    function abrirDetalhe(el) {
        if (!el) return;
        const d = el.dataset;
        modal = { tipo: 'detalhe', c: d.ver || null, inst: d.verInst ? +d.verInst : null, lado: d.verDono || null,
                  volta: modal && modal.tipo !== 'detalhe' ? modal : (modal && modal.volta) || null };
        render();
    }
    box.addEventListener('contextmenu', ev => {
        if (D.jogo !== 'gladio') return;
        const el = ev.target.closest('.jg-carta[data-ver]');
        if (!el) return;
        ev.preventDefault();
        abrirDetalhe(el);
    });

    /* ---------- arrastar da mão: para o campo joga, para o cemitério descarta ---------- */
    /* ---------- escolher vários atacantes de uma vez: arrastar um retângulo no próprio campo ---------- */
    box.addEventListener('pointerdown', ev => {
        if (D.jogo !== 'gladio' || ev.button > 0) return;
        const M = momento();
        const campoEu = ev.target.closest(`.gl-campo[data-campo="${M.eu}"]`);
        if (!campoEu || !M.minhaVez || M.e.atacou || alvo || modal || ev.target.closest('[data-lenda], [data-feitico], button')) return;
        const x0 = ev.clientX, y0 = ev.clientY;
        let laco = null;
        const mover = e2 => {
            if (!laco) {
                if (Math.hypot(e2.clientX - x0, e2.clientY - y0) < 8) return;
                laco = document.createElement('div');
                laco.className = 'gl-laco';
                camada().appendChild(laco);
            }
            const r = { l: Math.min(x0, e2.clientX), t: Math.min(y0, e2.clientY), r: Math.max(x0, e2.clientX), b: Math.max(y0, e2.clientY) };
            Object.assign(laco.style, { left: r.l + 'px', top: r.t + 'px', width: (r.r - r.l) + 'px', height: (r.b - r.t) + 'px' });
            laco.dataset.r = JSON.stringify(r);
        };
        const fim = () => {
            document.removeEventListener('pointermove', mover);
            document.removeEventListener('pointerup', fim);
            document.removeEventListener('pointercancel', fim);
            if (!laco) return;
            const r = JSON.parse(laco.dataset.r || 'null');
            laco.remove();
            if (!r) return;
            // as Lendas prontas que o retângulo tocou entram no ataque (as que já estavam continuam)
            box.querySelectorAll(`.gl-campo[data-campo="${M.eu}"] [data-lenda].gl-pode`).forEach(el => {
                const c = el.getBoundingClientRect();
                const i = +el.dataset.lenda;
                if (c.right > r.l && c.left < r.r && c.bottom > r.t && c.top < r.b && !atacantes.includes(i)) atacantes.push(i);
            });
            engolirClique = true;
            setTimeout(() => { engolirClique = false; }, 0);
            render();
        };
        document.addEventListener('pointermove', mover);
        document.addEventListener('pointerup', fim);
        document.addEventListener('pointercancel', fim);
    });

    box.addEventListener('pointerdown', ev => {
        if (D.jogo !== 'gladio' || ev.button > 0) return;
        const carta = ev.target.closest('.gl-mao [data-mao], .gl-eu-barra [data-comando]');
        if (!carta || ev.target.closest('.gl-mais') || !momento().jogo) return;
        const doComando = carta.dataset.comando != null;
        const inst = +(doComando ? carta.dataset.comando : carta.dataset.mao);
        const x0 = ev.clientX, y0 = ev.clientY;
        let fantasma = null;
        let zona = null;
        const mover = e2 => {
            if (!fantasma) {
                if (Math.hypot(e2.clientX - x0, e2.clientY - y0) < 8) return; // ainda é um clique
                fantasma = carta.cloneNode(true);
                fantasma.classList.add('gl-fantasma');
                fantasma.style.setProperty('--cw', getComputedStyle(carta).getPropertyValue('--cw'));
                camada().appendChild(fantasma);
                carta.classList.add('gl-arrastando');
                box.classList.add('gl-arrastando-carta');
            }
            fantasma.style.left = e2.clientX + 'px';
            fantasma.style.top = e2.clientY + 'px';
            fantasma.hidden = true;
            const sob = document.elementFromPoint(e2.clientX, e2.clientY);
            fantasma.hidden = false;
            const z = sob && sob.closest('[data-zona]');
            if (zona && zona !== z) zona.classList.remove('gl-sobre');
            zona = z;
            if (zona) zona.classList.add('gl-sobre');
        };
        const fim = () => {
            document.removeEventListener('pointermove', mover);
            document.removeEventListener('pointerup', fim);
            document.removeEventListener('pointercancel', fim);
            if (!fantasma) return;
            fantasma.remove();
            carta.classList.remove('gl-arrastando');
            box.classList.remove('gl-arrastando-carta');
            if (zona) zona.classList.remove('gl-sobre');
            engolirClique = true;
            setTimeout(() => { engolirClique = false; }, 0);
            if (doComando) {
                if (zona && zona.dataset.zona === 'campo') {
                    if (momento().minhaVez) acao({ acao: 'comandar' });
                    else { msg('O comandante só entra no seu turno, pagando o custo.', true); }
                }
            } else if (zona && zona.dataset.zona === 'campo') jogarDaMao(inst);
        };
        document.addEventListener('pointermove', mover);
        document.addEventListener('pointerup', fim);
        document.addEventListener('pointercancel', fim);
    });

    box.addEventListener('submit', ev => {
        const f = ev.target.closest('[data-gl-form]');
        if (!f) return;
        ev.preventDefault();
        const tipo = f.dataset.glForm;
        if (tipo === 'nota' && modal) {
            acao({ acao: 'marcar', inst: modal.inst, nota: f.nota.value });
        }
    });

    /* ================================================================== */
    /*  Pela Dama                                                          */
    /* ================================================================== */
    let sel = null; // "x,y" da peça escolhida
    const CUSTOM = /^c\d+$/; // peças criadas pelos jogadores ("c" + id); as padrão usam nomes (cavalo, rei…)

    function renderPelaDama() {
        const e = D.estado;
        const minhaVez = D.status === 'andamento' && D.lado && e.vez === D.lado;
        const legais = (minhaVez && e.legais) || {};
        if (sel && !legais[sel]) sel = null;
        const destinos = sel ? legais[sel].map(d => d[0] + ',' + d[1]) : [];
        const pretasEmBaixo = D.lado === 'p';
        const ult = e.ultimo;
        // rei em xeque de quem tem a vez
        let reiXeque = null;
        if (e.xeque && !(e.fim && e.fim.motivo === 'desistencia')) {
            e.tab.forEach((linha, y) => linha.forEach((p, x) => {
                if (p && p.c === e.vez && (e.tipos[p.t] || {}).especial === 'rei') reiXeque = x + ',' + y;
            }));
        }

        let h = '<div class="pd-tabuleiro" role="grid">';
        for (let i = 0; i < 8; i++) {
            const y = pretasEmBaixo ? i : 7 - i;
            for (let j = 0; j < 8; j++) {
                const x = pretasEmBaixo ? 7 - j : j;
                const k = x + ',' + y;
                const p = e.tab[y][x];
                const t = p ? (e.tipos[p.t] || { nome: '?', simbolo: '?' }) : null;
                const cls = ['pd-casa', (x + y) % 2 ? 'pd-clara' : 'pd-escura'];
                if (ult && ((ult.de[0] === x && ult.de[1] === y) || (ult.para[0] === x && ult.para[1] === y))) cls.push('pd-ultimo');
                if (sel === k) cls.push('pd-sel');
                if (destinos.includes(k)) cls.push(p ? 'pd-captura' : 'pd-destino');
                if (reiXeque === k) cls.push('pd-xeque');
                if (legais[k]) cls.push('pd-movivel');
                h += `<button type="button" class="${cls.join(' ')}" data-k="${k}"` +
                    (p ? ` title="${esc(t.nome)} ${p.c === 'b' ? 'branca' : 'preta'}"` : '') + '>' +
                    (p ? window.JG.peca(t, p.c, CUSTOM.test(p.t)) : '') +
                    (j === 0 ? `<small class="pd-rot pd-rot-y">${y + 1}</small>` : '') +
                    (i === 7 ? `<small class="pd-rot pd-rot-x">${'abcdefgh'[x]}</small>` : '') +
                    '</button>';
            }
        }
        h += '</div>';

        // legenda das peças customizadas desta partida
        const custom = Object.keys(e.tipos).filter(id => CUSTOM.test(id));
        const prot = e.protecao || {};
        const jog = (c, j) => {
            const vez = e.vez === c && !e.fim && D.status === 'andamento';
            const escudo = prot[c] > 0 && !e.fim
                ? ` <span class="pd-escudo" title="Rei protegido: ninguém pode dar xeque nele até ${c === 'b' ? 'as brancas' : 'as pretas'} completarem 3 jogadas (faltam ${prot[c]})">${icone('escudo')}${prot[c]}</span>` : '';
            const venceu = e.fim && e.fim.vencedor === c;
            return `<div class="pd-jog${vez ? ' pd-vez' : ''}${venceu ? ' pd-venceu' : ''}${e.fim && e.fim.vencedor && !venceu ? ' pd-perdeu' : ''}" data-heroi="${c}">` +
                `<span class="pd-peca pd-${c}">♚</span> <span class="pd-jog-nome">${nomeJ(c === 'b' ? 'j1' : 'j2')}${D.lado === c ? ' <small>(você)</small>' : ''}</span>${escudo}` +
                (vez ? `<span class="pd-pensando">${D.lado === c ? 'sua vez' : 'pensando…'}</span>` : '') + (venceu ? ` <span class="pd-coroa">${icone('trofeu')}</span>` : '') + '</div>';
        };
        const lateral =
            `<aside class="pd-lateral">` +
                `<div class="pd-jogadores">${jog('b')}${jog('p')}</div>` +
                `<p class="camp-dica">Lances: ${e.lances}</p>` +
                (D.lado && D.status === 'andamento' ? `<button type="button" class="camp-btn camp-btn-pequeno" data-pd="desistir">Desistir</button>` : '') +
                (custom.length ? `<h3>Peças customizadas</h3>` + custom.map(id => `<div class="pd-legenda">${window.JG.peca(e.tipos[id], 'b', true)}` +
                    `<div><strong>${esc(e.tipos[id].nome)}</strong>${window.JG.diagrama(e.tipos[id], 8)}</div></div>`).join('') : '') +
            `</aside>`;
        box.innerHTML = `<div class="pd-area${minhaVez ? ' pd-minha-vez' : ''}${D.status === 'andamento' && D.lado && !minhaVez ? ' pd-espera' : ''}${e.fim ? ' pd-acabou' : ''}">${h}${lateral}</div>`;
        anunciarPelaDama(e);

        const corNome = c => (c === 'b' ? 'Brancas' : 'Pretas');
        const nomeCor = c => (c === 'b' ? nomeJ('j1') : nomeJ('j2'));
        if (D.status === 'cancelada') status('Partida cancelada pelo mestre.');
        else if (e.fim) {
            if (!e.fim.vencedor) status('Empate por afogamento: quem tinha a vez não tinha lance e não estava em xeque.');
            else status(`${D.lado === e.fim.vencedor ? icone('trofeu') + ' Você venceu!' : (D.lado ? 'Você perdeu.' : '')} ${corNome(e.fim.vencedor)} (<strong>${nomeCor(e.fim.vencedor)}</strong>) venceram` +
                (e.fim.motivo === 'xeque-mate' ? ' por xeque-mate.' : ' por desistência.'));
        } else {
            const xq = e.xeque ? ' <strong class="pd-aviso-xeque">Xeque!</strong>' : '';
            status(minhaVez ? `<strong>Sua vez</strong> (${corNome(D.lado)}).${xq}` : `Vez das ${corNome(e.vez)} (<strong>${nomeCor(e.vez)}</strong>).${xq}${D.lado ? '' : ' Você está assistindo.'}`);
        }
    }

    let pdAntes = null; // { vez, xeque, fim, lances } do último desenho: os avisos só aparecem quando algo muda
    function faixa(html, classe, ms = 1800) {
        const f = document.createElement('div');
        f.className = 'pd-faixa ' + classe;
        f.innerHTML = html;
        camada().appendChild(f);
        setTimeout(() => f.remove(), ms);
    }
    function anunciarPelaDama(e) {
        const agora = { vez: e.vez, xeque: !!e.xeque, fim: e.fim ? e.fim.motivo + ':' + e.fim.vencedor : null, lances: e.lances, status: D.status };
        const antes = pdAntes;
        pdAntes = agora;
        document.title = (D.status === 'andamento' && D.lado && e.vez === D.lado && !e.fim ? '(Sua vez) ' : '') + document.title.replace(/^\(Sua vez\) /, '');
        if (!antes) return; // abrir a página não anuncia nada
        if (agora.fim && agora.fim !== antes.fim) return fimPelaDama(e);
        if (D.status === 'cancelada' && antes.status !== 'cancelada') return faixa('Partida cancelada pelo mestre', 'pd-faixa--neutra', 2600);
        if (agora.lances === antes.lances) return;
        if (agora.xeque) faixa((D.lado === e.vez ? 'Seu Rei está em ' : '') + '<strong>Xeque!</strong>', 'pd-faixa--xeque');
        else if (D.lado && e.vez === D.lado) faixa('<strong>Sua vez</strong>', 'pd-faixa--vez', 1300);
    }
    function fimPelaDama(e) {
        const v = e.fim.vencedor;
        const motivo = { 'xeque-mate': 'por xeque-mate', desistencia: 'por desistência', afogamento: 'afogamento' }[e.fim.motivo] || '';
        const nomeV = v ? (v === 'b' ? nomeJ('j1') : nomeJ('j2')) : '';
        const perdedor = v ? (v === 'b' ? nomeJ('j2') : nomeJ('j1')) : '';
        let titulo, texto, classe;
        if (!v) { titulo = 'Empate'; texto = 'Afogamento: quem tinha a vez não tinha lance e não estava em xeque.'; classe = 'pd-fim--empate'; }
        else if (D.lado === v) { titulo = icone('trofeu') + ' Vitória!'; texto = `Você venceu ${motivo}${e.fim.motivo === 'desistencia' ? `: ${perdedor} desistiu` : ''}.`; classe = 'pd-fim--vitoria'; }
        else if (D.lado) { titulo = 'Derrota'; texto = e.fim.motivo === 'desistencia' ? 'Você desistiu da partida.' : `${nomeV} venceu ${motivo}.`; classe = 'pd-fim--derrota'; }
        else { titulo = 'Fim de partida'; texto = `${nomeV} venceu ${motivo}${e.fim.motivo === 'desistencia' ? ` (${perdedor} desistiu)` : ''}.`; classe = 'pd-fim--neutra'; }
        const f = document.createElement('div');
        f.className = 'pd-fim ' + classe;
        f.innerHTML = `<div class="pd-fim-card" role="alertdialog" aria-label="Fim de partida"><h2>${titulo}</h2><p>${texto}</p>` +
            `<div class="pd-fim-acoes"><a class="camp-btn camp-btn-primario" href="${esc(C.url.lobby)}">Voltar aos jogos</a><button type="button" class="camp-btn" data-fechar>Ver o tabuleiro</button></div></div>`;
        f.addEventListener('click', ev => { if (ev.target === f || ev.target.closest('[data-fechar]')) f.remove(); });
        camada().appendChild(f);
    }

    function cliquePelaDama(ev) {
        const b = ev.target.closest('[data-pd]');
        if (b && b.dataset.pd === 'desistir') {
            if (confirm('Desistir da partida? O adversário vence na hora.')) acao({ acao: 'desistir' });
            return;
        }
        const c = ev.target.closest('.pd-casa');
        if (!c) return;
        const e = D.estado;
        if (D.status !== 'andamento' || !D.lado || e.vez !== D.lado) return;
        const legais = e.legais || {};
        const k = c.dataset.k;
        if (sel && legais[sel] && legais[sel].some(d => d[0] + ',' + d[1] === k)) {
            const [x, y] = sel.split(',').map(Number);
            const [tx, ty] = k.split(',').map(Number);
            sel = null;
            acao({ acao: 'mover', x, y, tx, ty });
            return;
        }
        sel = legais[k] ? (sel === k ? null : k) : null;
        render();
    }

    /* ================================================================== */
    /*  Gladio: cronômetro da vez                                          */
    /* ================================================================== */
    const TEMPO_VEZ = 90;     // Gladio::TEMPO_VEZ: acabou, a mesa segue sozinha
    const TEMPO_RESPOSTA = 20; // Gladio::TEMPO_RESPOSTA: quem pode responder fora do turno decide nesse tempo
    let difRelogio = 0;       // relógio do servidor − relógio do navegador (s)
    let tempoPedido = null;   // "desde" da vez cujo fim de tempo já foi pedido ao servidor
    const agoraServidor = () => Date.now() / 1000 + difRelogio;
    const esperandoDe = e => (e.escolha && e.escolha.lado) || (e.fase === 'defesa' && e.ataque ? e.ataque.defensor : e.vez);

    function relogio(M) {
        if (!M.ativo || !M.e.desde) return '';
        return `<span class="gl-relogio" data-relogio title="Tempo que resta nesta vez (${TEMPO_VEZ} s). Acabou, o turno passa sozinho (ou a defesa/escolha segue como está)">${icone('relogio')} <b>1:30</b></span>`;
    }
    function tique() {
        if (D.jogo !== 'gladio' || !D.estado) return;
        const el = box.querySelector('[data-relogio]');
        if (!el) return;
        const e = D.estado;
        if (e.pausa) { el.classList.remove('gl-relogio--atencao', 'gl-relogio--urgente'); return; } // relógio parado
        // resposta fora do turno (Vergonha de Dante, Imbuir): janela curta, com o relógio dela
        const resp = e.escolha && e.escolha.tipo === 'resposta' && e.escolha.desde;
        const desde = resp ? e.escolha.desde : e.desde;
        const seg = Math.max(0, Math.floor(agoraServidor() - desde));
        const resta = Math.max(0, (resp ? TEMPO_RESPOSTA : TEMPO_VEZ) - seg);
        el.querySelector('b').textContent = Math.floor(resta / 60) + ':' + String(resta % 60).padStart(2, '0');
        const quem = esperandoDe(e);
        el.classList.toggle('gl-relogio--atencao', resta <= 30 && resta > 10);
        el.classList.toggle('gl-relogio--urgente', resta <= 10);
        el.classList.toggle('gl-relogio--meu', quem === D.lado);
        // tempo esgotado: quem estiver na mesa avisa o servidor (uma vez por vez), e a partida segue
        if (resta === 0 && momento().jogo && tempoPedido !== desde && !enviando) {
            tempoPedido = desde;
            acao({ acao: 'tempo' });
        }
    }
    setInterval(tique, 1000);

    /* ================================================================== */
    /*  Gladio: animações (comparando o estado anterior com o novo)        */
    /* ================================================================== */
    let estadoAnterior = null;
    const reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // posição e aparência das cartas antes de redesenhar (as que sumirem viram "fantasmas" animados)
    function fotografar() {
        const f = { slot: {}, mao: {}, cem: {} };
        // cw: largura da carta (a cópia que anima fora da mesa precisa dela, senão sai no tamanho padrão)
        const cw = s => { const c = s.querySelector('.jg-carta'); return c ? c.getBoundingClientRect().width : 0; };
        box.querySelectorAll('.gl-campo [data-slot]').forEach(s => { f.slot[s.dataset.slot] = { r: s.getBoundingClientRect(), html: s.outerHTML, cw: cw(s) }; });
        box.querySelectorAll('.gl-mao .gl-slot--mao').forEach(s => {
            const c = s.querySelector('[data-mao]');
            if (c) f.mao[c.dataset.mao] = { r: s.getBoundingClientRect(), html: s.outerHTML, cw: cw(s) };
        });
        return f;
    }
    function fantasma(foto, classe, alvoEl) {
        const g = document.createElement('div');
        g.className = 'gl-fantasma-anim ' + classe;
        g.innerHTML = foto.html;
        if (foto.cw) g.style.setProperty('--cw-fantasma', foto.cw + 'px');
        Object.assign(g.style, { left: foto.r.left + 'px', top: foto.r.top + 'px', width: foto.r.width + 'px', height: foto.r.height + 'px' });
        if (alvoEl) {
            const a = alvoEl.getBoundingClientRect();
            g.style.setProperty('--dx', (a.left + a.width / 2 - (foto.r.left + foto.r.width / 2)) + 'px');
            g.style.setProperty('--dy', (a.top + a.height / 2 - (foto.r.top + foto.r.height / 2)) + 'px');
        }
        camada().appendChild(g);
        setTimeout(() => g.remove(), 1100);
    }
    /** A carta sai do lugar, voa até o alvo, dá a pancada (o alvo treme e pisca) e volta. */
    function investida(f, alvoEl, original) {
        if (!f || !alvoEl) return;
        const a = alvoEl.getBoundingClientRect ? alvoEl.getBoundingClientRect() : alvoEl;
        const g = document.createElement('div');
        g.className = 'gl-fantasma-anim gl-investida';
        g.innerHTML = f.html;
        if (f.cw) g.style.setProperty('--cw-fantasma', f.cw + 'px');
        Object.assign(g.style, { left: f.r.left + 'px', top: f.r.top + 'px', width: f.r.width + 'px', height: f.r.height + 'px' });
        const dx = a.left + a.width / 2 - (f.r.left + f.r.width / 2), dy = a.top + a.height / 2 - (f.r.top + f.r.height / 2);
        const d = Math.hypot(dx, dy) || 1, para = Math.max(0, d - Math.min(a.height, f.r.height) * .45); // para encostado no alvo
        const px = dx / d * para, py = dy / d * para;
        if (original) original.style.visibility = 'hidden';
        camada().appendChild(g);
        const an = g.animate([
            { transform: 'translate(0,0) scale(1) rotate(0deg)' },
            { transform: `translate(${-dx / d * 18}px,${-dy / d * 18}px) scale(1.08) rotate(${dx > 0 ? -6 : 6}deg)`, offset: .2 }, // toma impulso
            { transform: `translate(${px}px,${py}px) scale(1.15) rotate(${dx > 0 ? 8 : -8}deg)`, offset: .5, easing: 'cubic-bezier(.5,0,1,.5)' },
            { transform: `translate(${px * .9}px,${py * .9}px) scale(1.05) rotate(0deg)`, offset: .62 },
            { transform: 'translate(0,0) scale(1) rotate(0deg)' },
        ], { duration: 900, easing: 'ease-in-out' });
        setTimeout(() => { // a pancada
            const alvoDom = alvoEl.getBoundingClientRect ? alvoEl : null;
            if (alvoDom) marcar(alvoDom, 'gl-anim-golpe');
            const boom = document.createElement('span');
            boom.className = 'gl-impacto';
            Object.assign(boom.style, { left: (f.r.left + f.r.width / 2 + px) + 'px', top: (f.r.top + f.r.height / 2 + py) + 'px' });
            camada().appendChild(boom);
            setTimeout(() => boom.remove(), 600);
        }, 450);
        an.onfinish = () => { g.remove(); if (original) original.style.visibility = ''; };
    }
    function marcar(el, classe) {
        if (!el) return;
        el.classList.remove(classe);
        void el.offsetWidth;
        el.classList.add(classe);
        setTimeout(() => el.classList.remove(classe), 1200);
    }
    function numeroFlutuante(el, n) {
        if (!el || !n) return;
        const r = el.getBoundingClientRect();
        const s = document.createElement('span');
        s.className = 'gl-numero-anim ' + (n < 0 ? 'gl-numero--dano' : 'gl-numero--cura');
        s.textContent = (n > 0 ? '+' : '') + n;
        Object.assign(s.style, { left: (r.left + r.width / 2) + 'px', top: (r.top + r.height / 3) + 'px' });
        camada().appendChild(s);
        setTimeout(() => s.remove(), 1300);
    }
    function animar(a, b, foto) {
        if (reduzir || !a || !b || a === b || !a.lados || !b.lados) return;
        const ids = l => new Set((l.mesa || []).map(x => x.i).concat((l.feiticos || []).map(x => x.i)));
        const slot = i => box.querySelector(`.gl-campo [data-slot="${i}"]`);
        const cem = lado => box.querySelector(`[data-gl="cemiterio"][data-lado="${lado}"]`);
        for (const lado of b.ordem || []) {
            const A = a.lados[lado], B = b.lados[lado];
            if (!A || !B) continue;
            const antesIds = ids(A), agoraIds = ids(B);
            // carta jogada (entrou no campo)
            agoraIds.forEach(i => { if (!antesIds.has(i)) marcar(slot(i), 'gl-anim-entra'); });
            // carta que saiu do campo: cai (Lenda morrendo / feitiço indo ao cemitério)
            antesIds.forEach(i => { if (!agoraIds.has(i) && foto.slot[i]) fantasma(foto.slot[i], 'gl-anim-morre', cem(lado)); });
            // dano e cura nas Lendas que ficaram
            (B.mesa || []).forEach(m => {
                const v = (A.mesa || []).find(x => x.i === m.i);
                if (v && v.vida !== m.vida) {
                    const el = slot(m.i);
                    if (m.vida < v.vida) marcar(el, 'gl-anim-dano');
                    numeroFlutuante(el, m.vida - v.vida);
                }
            });
            // vida (Dinheiro) do jogador
            if (A.vida !== B.vida) {
                const cab = box.querySelector(`[data-heroi="${lado}"] .gl-vida`);
                numeroFlutuante(cab, B.vida - A.vida);
                if (B.vida < A.vida) marcar(box.querySelector(`[data-heroi="${lado}"]`), 'gl-anim-golpe');
            }
            // compras
            const nA = Array.isArray(A.mao) ? A.mao.length : (A.mao || 0);
            const nB = Array.isArray(B.mao) ? B.mao.length : (B.mao || 0);
            if (lado === D.lado && Array.isArray(A.mao) && Array.isArray(B.mao)) {
                const tinha = new Set(A.mao.map(x => x.i));
                B.mao.forEach(x => { if (!tinha.has(x.i)) marcar(box.querySelector(`.gl-mao [data-mao="${x.i}"]`)?.closest('.gl-slot'), 'gl-anim-compra'); });
                // descarte: carta que saiu da mão e não foi para o campo voa até o cemitério
                const tem = new Set(B.mao.map(x => x.i));
                A.mao.forEach(x => { if (!tem.has(x.i) && !agoraIds.has(x.i) && foto.mao[x.i]) fantasma(foto.mao[x.i], 'gl-anim-voa', cem(lado)); });
            } else if (nB > nA) {
                const versos = box.querySelectorAll(`[data-heroi="${lado}"] ~ .gl-mao-op .gl-verso`);
                Array.from(versos).slice(-(nB - nA)).forEach(v => marcar(v, 'gl-anim-compra'));
            }
            if ((B.cemiterio || []).length > (A.cemiterio || []).length) marcar(cem(lado), 'gl-anim-pulso');
        }
        // ataque declarado e bloqueios
        const atA = (a.ataque && a.ataque.atacantes) || [], atB = (b.ataque && b.ataque.atacantes) || [];
        atB.forEach(i => { if (!atA.includes(i)) marcar(slot(i), b.vez === D.lado ? 'gl-anim-ataca' : 'gl-anim-ataca-baixo'); });
        const blA = (a.ataque && a.ataque.bloqueios) || {}, blB = (b.ataque && b.ataque.bloqueios) || {};
        Object.keys(blB).forEach(i => { if (blA[i] == null) marcar(slot(i), 'gl-anim-bloqueia'); });
        // combate resolvido: cada atacante voa até o alvo (o jogador, ou a Lenda que bloqueou), dá a pancada e volta
        if (atA.length && !atB.length && a.vez === b.vez && a.ataque) {
            const quemBloqueou = {};
            Object.entries(a.ataque.bloqueios || {}).forEach(([bl, at]) => { quemBloqueou[+at] = +bl; });
            atA.forEach((i, k) => {
                const alvoEl = quemBloqueou[i] != null ? (slot(quemBloqueou[i]) || (foto.slot[quemBloqueou[i]] && foto.slot[quemBloqueou[i]].r))
                    : box.querySelector(`[data-heroi="${a.ataque.defensor}"]`);
                setTimeout(() => investida(foto.slot[i], alvoEl, slot(i)), k * 160);
            });
        }
        // cartas conjuradas e efeitos que começaram: a carta brilha (ou aparece no meio da mesa, se já saiu do campo)
        const visto = Math.max(0, ...(a.brilho || []).map(x => x.n));
        const novos = (b.brilho || []).filter(x => x.n > visto);
        // o efeito do feitiço que acabou de ser conjurado já aparece junto com ele
        novos.filter(x => x.t !== 'mira' && !(x.t === 'efeito' && novos.some(y => y.t === 'conjurou' && y.i === x.i)))
            .slice(-6).forEach((x, k) => setTimeout(() => brilhar(x, slot(x.i)), k * 380));
        // quem o efeito mirou: uma seta da carta (ou do dono dela) até cada alvo
        novos.filter(x => x.t === 'mira').slice(-8).forEach((x, k) => setTimeout(() => {
            const de = (x.i != null && slot(x.i)) || box.querySelector(`[data-heroi="${x.lado}"]`);
            const para = x.para != null ? slot(x.para) : box.querySelector(`[data-heroi="${x.para_lado}"]`);
            seta(de, para, 'gl-seta--efeito', 1700);
        }, 250 + k * 200));
    }
    // alguém saiu da partida no 7º shot: os copinhos aparecem em fila e tombam um a um, a faixa diz quem caiu e o assento cambaleia
    function foraPorShots(a, b) {
        if (!a || !b || a === b || !a.lados || !b.lados) return;
        for (const lado of b.ordem || []) {
            const A = a.lados[lado], B = b.lados[lado];
            if (!A || !B || A.fora || !B.fora || (B.shots || 0) < SHOTS) continue;
            const eu = lado === D.lado;
            const f = document.createElement('div');
            f.className = 'gl-ko-shots' + (eu ? ' gl-ko-shots--eu' : '');
            f.setAttribute('role', 'status');
            f.innerHTML = '<div class="gl-ko-copos">' + Array.from({ length: SHOTS }, (_, k) => `<i style="--k:${k}"></i>`).join('') + '</div>' +
                `<b>${eu ? 'Você tomou' : nomeDe(lado) + ' tomou'} o ${SHOTS}º shot</b><span>e está fora da partida</span>`;
            camada().appendChild(f);
            setTimeout(() => f.remove(), 3200);
            marcar(box.querySelector(`[data-heroi="${lado}"]`), 'gl-anim-tonto');
        }
    }
    /* ---------- chat da partida (caixa + balões sobre o assento) e bolão de quem assiste ---------- */
    let chatVisto = null; // última mensagem já mostrada como balão
    function renderSocial() {
        const lista = document.getElementById('gl-chat-lista');
        if (!lista) return;
        const chat = D.chat || [];
        const embaixo = lista.scrollHeight - lista.scrollTop - lista.clientHeight < 30;
        lista.innerHTML = chat.map(m => `<li><strong>${esc(m.nome)}${m.lado ? '' : ' <small>(assistindo)</small>'}</strong> ${esc(m.texto)}</li>`).join('')
            || '<li class="gl-chat-vazio">Ninguém escreveu ainda.</li>';
        if (embaixo || chatVisto === null) lista.scrollTop = lista.scrollHeight;
        const ultima = chat.length ? chat[chat.length - 1].n : 0;
        if (chatVisto !== null) chat.filter(m => m.n > chatVisto).slice(-4).forEach(balao);
        chatVisto = ultima;
        renderBolao();
    }
    function balao(m) {
        const alvo = m.lado && box.querySelector(`[data-heroi="${m.lado}"]`);
        const b = document.createElement('div');
        b.className = 'gl-balao' + (alvo ? '' : ' gl-balao--solto');
        b.innerHTML = `<strong>${esc(m.nome)}</strong>${esc(m.texto)}`;
        if (alvo) {
            const r = alvo.getBoundingClientRect();
            Object.assign(b.style, { left: (r.left + Math.min(r.width / 2, 120)) + 'px', top: (r.top - 6) + 'px' });
        }
        camada().appendChild(b);
        setTimeout(() => b.remove(), 5000);
    }
    function renderBolao() {
        const caixa = document.getElementById('gl-bolao');
        const B = D.bolao;
        if (!caixa || !B || !Object.keys(B.alvos || {}).length) { if (caixa) caixa.hidden = true; return; }
        caixa.hidden = false;
        if (caixa.contains(document.activeElement)) return; // não atrapalha quem está digitando
        const fmtN = n => Number(n || 0).toLocaleString('pt-BR');
        caixa.innerHTML = `<h3>Bolão <small>${fmtN(B.pote)} em jogo</small></h3>` +
            `<ul class="gl-bolao-totais">${Object.entries(B.alvos).map(([k, nome]) => `<li><span>${esc(nome)}</span><b>${fmtN((B.totais || {})[k])}</b></li>`).join('')}</ul>` +
            (B.meu.length ? `<p class="gl-bolao-meu">Suas apostas: ${B.meu.map(a => esc(B.alvos[a.alvo] || a.alvo) + ' ' + fmtN(a.v)).join(', ')}</p>` : '') +
            (B.aberto
                ? `<form class="gl-bolao-form" id="gl-bolao-form"><select name="alvo" aria-label="Em quem apostar">${Object.entries(B.alvos).map(([k, nome]) => `<option value="${esc(k)}">${esc(nome)}</option>`).join('')}</select>` +
                  `<input type="number" name="valor" min="1" max="${B.saldo}" placeholder="Valor" aria-label="Valor"><button class="camp-btn camp-btn-pequeno camp-btn-primario">Apostar</button></form>` +
                  `<p class="gl-bolao-dica">Até o fim do turno ${B.ate}. Sai da carteira da campanha (você tem ${fmtN(B.saldo)}). Quem acertar divide o bolão na proporção do que apostou; sem acertos, todos recebem de volta.</p>`
                : `<p class="gl-bolao-dica">${D.lado ? 'Quem está jogando não aposta no bolão.' : (B.pago ? 'Bolão acertado.' : 'Apostas encerradas (até o turno ' + B.ate + ').')}</p>`);
    }
    document.addEventListener('submit', async ev => {
        const f = ev.target;
        if (f.id !== 'gl-chat-form' && f.id !== 'gl-bolao-form') return;
        ev.preventDefault();
        try {
            if (f.id === 'gl-chat-form') {
                const texto = f.texto.value.trim();
                if (!texto) return;
                f.texto.value = '';
                D = await post(C.url.chat, { texto }, C.csrf);
            } else {
                D = await post(C.url.bolao, { alvo: f.alvo.value, valor: f.valor.value }, C.csrf);
                msg('Aposta feita no bolão.');
            }
            document.activeElement && document.activeElement.blur && f.id === 'gl-bolao-form' && document.activeElement.blur();
            render();
        } catch (e) { msg(e.message, true); }
    });

    /* ---------- setas: de quem para quem ---------- */
    const SVGNS = 'http://www.w3.org/2000/svg';
    function centro(el) { const r = el.getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; }
    /** Seta de el1 até el2 (por cima de tudo). ms: some depois disso; sem ms, fica até ser apagada. */
    function seta(el1, el2, classe, ms, svg) {
        if (!el1 || !el2 || el1 === el2) return null;
        const [x1, y1] = centro(el1), [x2, y2] = centro(el2);
        if (!svg) {
            svg = document.createElementNS(SVGNS, 'svg');
            svg.setAttribute('class', 'gl-setas');
            camada().appendChild(svg);
            if (ms) setTimeout(() => svg.remove(), ms);
        }
        // termina um pouco antes do centro do alvo, para a ponta não sumir debaixo da carta
        const d = Math.hypot(x2 - x1, y2 - y1) || 1, recuo = Math.min(28, d / 3);
        const xf = x2 - (x2 - x1) / d * recuo, yf = y2 - (y2 - y1) / d * recuo;
        const curva = Math.min(80, d / 4); // um arco suave, para setas paralelas não se sobreporem
        const mx = (x1 + xf) / 2 - (yf - y1) / d * curva, my = (y1 + yf) / 2 + (xf - x1) / d * curva;
        const p = document.createElementNS(SVGNS, 'path');
        p.setAttribute('d', `M${x1},${y1} Q${mx},${my} ${xf},${yf}`);
        p.setAttribute('class', 'gl-seta ' + (classe || ''));
        const ponta = document.createElementNS(SVGNS, 'circle');
        ponta.setAttribute('cx', xf); ponta.setAttribute('cy', yf); ponta.setAttribute('r', 5);
        ponta.setAttribute('class', 'gl-seta-ponta ' + (classe || ''));
        svg.appendChild(p);
        svg.appendChild(ponta);
        return svg;
    }
    // combate em andamento: atacantes → jogador atacado e bloqueadores → atacante, enquanto a defesa é escolhida
    let linhasCombate = null;
    function desenharCombate() {
        if (linhasCombate) { linhasCombate.remove(); linhasCombate = null; }
        const e = D.jogo === 'gladio' && D.estado;
        const at = e && e.ataque;
        if (!at || !(at.atacantes || []).length || e.fim) return;
        const s = i => box.querySelector(`.gl-campo [data-slot="${i}"]`);
        const bloq = at.bloqueios || {};
        const bloqueados = new Set(Object.values(bloq).map(Number));
        at.atacantes.forEach(i => {
            if (!bloqueados.has(+i)) linhasCombate = seta(s(i), box.querySelector(`[data-heroi="${at.defensor}"]`), 'gl-seta--ataque', 0, linhasCombate);
        });
        Object.entries(bloq).forEach(([b, a]) => { linhasCombate = seta(s(b), s(a), 'gl-seta--bloqueio', 0, linhasCombate); });
        if (linhasCombate) linhasCombate.classList.add('gl-setas--combate');
    }
    let redesenho = null;
    const redesenhar = () => { clearTimeout(redesenho); redesenho = setTimeout(desenharCombate, 60); };
    window.addEventListener('resize', redesenhar);
    window.addEventListener('scroll', redesenhar, true);
    document.addEventListener('fullscreenchange', redesenhar);
    function rotulo(el, texto, classe) {
        const r = el.getBoundingClientRect();
        const s = document.createElement('span');
        s.className = 'gl-brilho-rotulo ' + (classe || '');
        s.textContent = texto;
        Object.assign(s.style, { left: (r.left + r.width / 2) + 'px', top: (r.top - 6) + 'px' });
        camada().appendChild(s);
        setTimeout(() => s.remove(), 1500);
    }
    function brilhar(x, el) {
        const def = x.c != null ? cartaDef(x.c) : null;
        if (x.t === 'efeito' && el) marcar(el, 'gl-anim-efeito'); // a carta em jogo brilha no lugar dela também
        if (!def) return;
        // feitiço conjurado ou efeito ativado: a carta salta rápido para o centro da tela e vai sumindo
        const g = document.createElement('div');
        g.className = 'gl-conjura-anim' + (x.t === 'efeito' ? ' gl-conjura-anim--efeito' : '');
        g.innerHTML = window.JG.carta(def) + `<span>${x.t === 'efeito' ? 'Efeito ativado' : 'Conjurou'} · ${nomeDe(x.lado)}</span>`;
        if (el) { // nasce de onde a carta está e voa para o centro
            const r = el.getBoundingClientRect();
            g.style.setProperty('--de-x', (r.left + r.width / 2 - innerWidth / 2) + 'px');
            g.style.setProperty('--de-y', (r.top + r.height / 2 - innerHeight / 2) + 'px');
        }
        camada().appendChild(g);
        setTimeout(() => g.remove(), 1500);
    }

    /* ================================================================== */
    function render() {
        if (!D.estado) {
            status(D.status === 'cancelada' ? 'Desafio cancelado.' : 'Aguardando alguém aceitar o desafio…');
            box.innerHTML = `<p class="camp-vazio"><a href="${esc(C.url.lobby)}">Voltar aos jogos</a></p>`;
            return;
        }
        if (D.jogo !== 'gladio') { renderPelaDama(); renderSocial(); return; }
        if (D.estado.agora) difRelogio = D.estado.agora - Date.now() / 1000;
        const foto = estadoAnterior && estadoAnterior !== D.estado ? fotografar() : null;
        renderGladio();
        dicaDoTurno();
        if (foto) animar(estadoAnterior, D.estado, foto);
        foraPorShots(estadoAnterior, D.estado);
        estadoAnterior = D.estado;
        desenharCombate();
        renderSocial();
        tique();
    }
    box.addEventListener('click', ev => (D.jogo === 'gladio' ? cliqueGladio(ev) : cliquePelaDama(ev)));
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && (alvo || sel || modal || bloqueador != null)) { alvo = null; sel = null; modal = null; bloqueador = null; render(); }
    });
    render();
    // Gladio: abre já com o tabuleiro inteiro à vista (o título da página fica acima, rolando para cima)
    if (D.jogo === 'gladio' && D.estado) {
        const t = $('#jg-status');
        if (t) window.scrollTo({ top: Math.max(0, t.getBoundingClientRect().top + window.scrollY - 76) });
    }
})();
