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
    const nomeJ = j => esc(D.nomes[j] || '—');

    /* ================================================================== */
    /*  Gladio (2 a 6 jogadores)                                           */
    /*  Regras no servidor (App\Jogos\Gladio). Aqui: desenhar a mesa,      */
    /*  escolher jogadas, arrastar cartas e as janelas de ferramentas.     */
    /* ================================================================== */
    let alvo = null;          // mirando: { tipo: 'feitico', inst, precisa, gratis, efeito, valor } | { tipo: 'copiar', inst }
    let atacantes = [];       // Lendas escolhidas para o ataque
    let bloqueador = null;    // na defesa: Lenda minha escolhida, esperando o clique no atacante
    let modal = null;         // { tipo: 'baralho' | 'cemiterio' | 'ficha' | 'mao' | 'mesa' | 'feitico' | 'conjurar', inst?, lado?, gratis? }
    let ordemDeck = null;     // ordem do baralho sendo arrumada (ids, do topo para o fundo)
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
    const conjuraSozinho = c => !c.efeito && (c.hab || []).length > 0;
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

    /* ---------- estado do momento ---------- */
    function momento() {
        const e = D.estado;
        const L = e.lados;
        const ordem = e.ordem || ['a', 'b'];
        const eu = D.lado || ordem[0]; // quem assiste vê a mesa pelo primeiro lugar
        const ativo = D.status === 'andamento' && !e.fim;
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
            inimigos: ordem.filter(l => l !== eu && !L[l].fora),
        };
    }
    const tem = (x, p) => (x.pal || []).includes(p);

    /* ---------- desenho ---------- */
    function renderGladio() {
        const M = momento();
        const { e, L, ordem, eu, ativo, minhaVez, defendo, esperoDefesa, atacando, bloqueios } = M;

        // seleções que deixaram de valer
        if (!minhaVez) { atacantes = []; if (alvo) alvo = null; }
        atacantes = atacantes.filter(i => L[eu].mesa.some(m => m.i === i && m.pode));
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
                return !tem(x, 'voar') || (b && (tem(b, 'voar') || tem(b, 'alcance')));
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
                Array.from({ length: SHOTS }, (_, k) => `<i class="${k < n ? 'cheio' : ''}"></i>`).join('') + '</span>' +
                (meu && M.jogo ? `<span class="gl-shot-botoes"><button type="button" class="gl-mini" data-gl="shot" data-q="1" title="Tomar 1 shot">+ shot</button>` +
                    `<button type="button" class="gl-mini" data-gl="shot" data-q="-1" title="Desfazer 1 shot"${n ? '' : ' disabled'}>−</button></span>` : '');
        };
        const vida = lado => {
            const l = L[lado];
            const pct = Math.max(0, Math.min(100, (l.vida / (l.max || 1)) * 100));
            return `<div class="gl-vida" title="Vida${D.aposta ? ' (Dinheiro apostado)' : ''}"><span style="width:${pct}%"></span><b>${D.aposta ? '💰 ' : '❤ '}${Math.max(0, l.vida)} / ${l.max}</b></div>`;
        };
        const comandoBotao = lado => {
            const x = (L[lado].comando || [])[0];
            if (!x) return '';
            const meu = lado === D.lado;
            return `<button type="button" class="gl-zona gl-zona-cmd" data-gl="comando" data-lado="${lado}"${meu ? ` data-comando="${x.i}"` : ''} title="Comandante na zona de comando: ${esc(cartaDef(x.c).nome)} (custo ${custoCmd(L[lado])})${meu ? ' · arraste para o campo para invocar' : ''}">` +
                `👑 <span class="gl-cmd-nome">${esc(cartaDef(x.c).nome)}</span> <b>⚡${custoCmd(L[lado])}</b></button>`;
        };
        const cemiterioBotao = lado => `<button type="button" class="gl-zona" data-gl="cemiterio" data-lado="${lado}"${lado === D.lado ? ' data-zona="cemiterio"' : ''} title="Cemitério${lado === D.lado ? ' (arraste uma carta da mão para descartar)' : ''}">✝ ${L[lado].cemiterio.length}</button>`;

        // uma carta na mesa (Lenda ou feitiço no campo), com contador, anotação e o botão de ações
        const naMesa = (x, lado, feitico) => {
            const meu = lado === D.lado;
            const cls = [];
            let attrs;
            if (feitico) {
                attrs = `data-feitico="${x.i}" data-dono="${lado}"${ver(x.c, x.i, lado)}`;
            } else {
                if (meu && minhaVez && !e.atacou && x.pode && x.atk > 0 && !tem(x, 'defensor')) cls.push('gl-pode');
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
            const etiqueta = feitico ? '' : atacando.includes(x.i) ? '<span class="gl-etiqueta gl-etiqueta--ataque">atacando</span>'
                : (bloqueia ? `<span class="gl-etiqueta">bloqueia ${esc(nomeLenda(bloqueia))}</span>`
                : (x.virada ? '<span class="gl-etiqueta gl-etiqueta--cansada" title="Atacou: só volta a bloquear no próximo turno de quem a controla">virada</span>'
                : (!x.pode && meu && minhaVez ? '<span class="gl-etiqueta gl-etiqueta--cansada" title="Entrou neste turno ou já atacou">cansada</span>' : '')));
            const carta = feitico ? window.JG.carta(cartaDef(x.c), { classe: cls.join(' '), attrs })
                : window.JG.carta(defLenda(x), { atk: x.atk, vida: x.vida, max: x.max, pal: x.pal || [], classe: cls.join(' '), attrs });
            // equipamentos vinculados a esta Lenda: ficam presos a ela (e caem junto)
            const equip = feitico ? [] : (L[lado].feiticos || []).filter(f => f.em === x.i);
            return `<div class="gl-slot${feitico ? ' gl-slot--feitico' : ''}${equip.length ? ' gl-slot--equipada' : ''}" data-slot="${x.i}">` +
                (equip.length ? `<div class="gl-equipos">${equip.map(f => `<button type="button" class="gl-equipo" data-mais-feitico="${f.i}" data-dono="${lado}" title="Equipado: ${esc(cartaDef(f.c).nome)}${f.bonus && (f.bonus.atk || f.bonus.vida) ? ` (${f.bonus.atk >= 0 ? '+' : ''}${f.bonus.atk}/${f.bonus.vida >= 0 ? '+' : ''}${f.bonus.vida})` : ''}">🗡 ${esc(cartaDef(f.c).nome)}</button>`).join('')}</div>` : '') + carta +
                (!feitico && x.i === L[lado].cmd ? '<span class="gl-cmd-selo" title="Comandante">👑</span>' : '') +
                (x.cont ? `<span class="gl-contador" title="Contador">${x.cont > 0 ? '+' : ''}${x.cont}</span>` : '') +
                (x.nota ? `<span class="gl-nota" title="${esc(x.nota)}">${esc(x.nota)}</span>` : '') +
                etiqueta +
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

        // assento de um adversário (no alto da mesa)
        const assento = lado => {
            const l = L[lado];
            const mao = Array.isArray(l.mao) ? l.mao : Array.from({ length: l.mao || 0 }, () => ({ oculta: true }));
            const reveladas = mao.filter(c => c.rev);
            return `<section class="gl-assento${e.vez === lado && ativo ? ' gl-vez' : ''}${e.fase === 'defesa' && M.ataque.defensor === lado ? ' gl-atacado' : ''}${l.fora ? ' gl-fora' : ''}">` +
                `<div class="gl-assento-cab${alvoOk(lado, 'heroi') ? ' gl-alvo' : ''}" data-heroi="${lado}">` +
                    `<span class="gl-avatar">${inicialDe(lado)}</span>` +
                    `<div class="gl-assento-info"><strong>${nomeDe(lado)}</strong>${vida(lado)}</div>` +
                    `<div class="gl-assento-numeros"><span class="gl-energia" title="Energia">⚡ ${l.energia}/${l.energiaMax}</span>` +
                        `<span title="Cartas no baralho">📚 ${qtd(l.baralho)}</span>${cemiterioBotao(lado)}${comandoBotao(lado)}</div>` +
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
        if (M.esc) {
            const cartasEsc = ['baralho', 'cemiterio'].includes(M.esc.tipo);
            acoes = M.escolho
                ? `<span class="gl-dica gl-dica--escolha" title="${esc(M.esc.texto)}">${esc(M.esc.texto)}${M.esc.tipo === 'mao' ? ' (clique numa carta da sua mão)' : (cartasEsc ? '' : ' (clique no alvo destacado)')}</span>` +
                  (cartasEsc ? `<button type="button" class="camp-btn camp-btn-primario" data-gl="escolha-cartas">Escolher a carta</button>` : '') +
                  (M.esc.obrig ? '' : `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="pular" title="Não aplicar este efeito">Pular</button>`)
                : `<span class="gl-dica gl-dica--suave">Esperando ${nomeDe(M.esc.lado)}: ${esc(M.esc.texto)}</span>`;
        } else if (alvo) {
            acoes = `<span class="gl-dica">${alvo.tipo === 'copiar' ? 'Escolha a Lenda sua que vai servir de modelo' : (alvo.tipo === 'equipar' ? 'Escolha a Lenda sua que vai receber o equipamento' : 'Escolha o alvo do feitiço')}</span>` +
                `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="cancelar-alvo">Cancelar</button>`;
        } else if (defendo) {
            acoes = `<span class="gl-dica">${bloqueador != null ? 'Agora clique no atacante que ela vai bloquear' : 'Jogue Lendas da mão e escolha bloqueadores: clique numa Lenda sua e depois no atacante'}</span>` +
                `<button type="button" class="camp-btn camp-btn-primario" data-gl="defender">Confirmar defesa</button>`;
        } else if (esperoDefesa) {
            acoes = `<span class="gl-dica">Aguardando a defesa de ${nomeDe(M.ataque.defensor)}…</span>`;
        } else if (minhaVez) {
            acoes = (atacantes.length
                    ? M.inimigos.map(l => `<button type="button" class="camp-btn camp-btn-primario" data-gl="atacar" data-alvo="${l}">⚔ Atacar ${nomeDe(l)} com ${atacantes.length}</button>`).join('') +
                      `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="limpar-ataque">Limpar</button>`
                    : `<span class="gl-dica gl-dica--suave">${e.atacou ? 'Você já atacou neste turno (uma onda de ataque por turno) · ainda dá para jogar cartas' : 'Arraste cartas para o campo · escolha as Lendas da sua onda de ataque'}</span>`) +
                `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="comprar" title="Comprar uma carta do topo do baralho">Comprar</button>` +
                `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="ficha" title="Criar uma ficha (Lenda sem carta) na sua mesa">Criar ficha</button>` +
                `<button type="button" class="camp-btn camp-btn-primario" data-gl="passar" title="Vai para a fase final: efeitos de fim de turno, descarte até ${MAO_FIM} cartas e a vez passa">Fim do turno ▸</button>`;
        } else if (ativo) {
            acoes = `<span class="gl-dica gl-dica--suave">Vez de ${nomeDe(e.vez)}</span>`;
        }
        const pote = e.pote || 0;
        const centro = `<div class="gl-centro">` +
            `<div class="gl-pote" title="${D.aposta ? 'Todo Dinheiro perdido até agora: o vencedor leva o pote inteiro' : 'Pontos de vida perdidos até agora (amistoso)'}">` +
                `<span class="gl-moedas" aria-hidden="true">${'<i></i>'.repeat(Math.min(6, 1 + Math.floor(pote / Math.max(1, (e.total || 30) / 6))))}</span>` +
                `<span><small>Pote</small><b>${D.aposta ? '💰 ' : ''}${pote.toLocaleString('pt-BR')}</b></span></div>` +
            `<div class="gl-centro-acoes"><span class="gl-turno">Turno ${e.turno}</span>${fases(e, ativo)}${relogio(M)}${acoes}</div>` +
            `</div>`;

        // eu: campo, barra e a mão em leque
        const l = L[eu];
        const minhaMao = Array.isArray(l.mao) ? l.mao : null;
        const podeJogar = c => (minhaVez && c.custo <= l.energia) || (defendo && c.tipo === 'lenda' && c.custo <= l.energia);
        const maoHtml = minhaMao
            ? `<div class="gl-mao" style="--n:${minhaMao.length}">${minhaMao.map((x, k) => {
                const c = cartaDef(x.c);
                return `<div class="gl-slot gl-slot--mao${x.rev ? ' gl-revelada-minha' : ''}" style="--k:${k}">` +
                    window.JG.carta(c, { classe: (podeJogar(c) ? 'gl-jogavel' : 'gl-cara') + (alvo && alvo.inst === x.i ? ' gl-mirando' : '') +
                        (M.escolho && M.esc.tipo === 'mao' && M.esc.opcoes.includes(x.i) ? ' gl-alvo' : ''), attrs: `data-mao="${x.i}"${ver(x.c)}` }) +
                    (x.rev ? '<span class="gl-olho" title="Revelada para os outros jogadores">👁</span>' : '') +
                    (M.jogo ? `<button type="button" class="gl-mais" data-mais-mao="${x.i}" title="Ações desta carta" aria-label="Ações de ${esc(c.nome)}">⋯</button>` : '') +
                    `</div>`;
            }).join('')}</div>`
            : `<div class="gl-mao-op">${'<span class="gl-verso"></span>'.repeat(qtd(l.mao))}</div>`;
        const minhaBarra = `<div class="gl-eu-barra${e.vez === eu && ativo ? ' gl-vez' : ''}${e.fase === 'defesa' && M.ataque.defensor === eu ? ' gl-atacado' : ''}${l.fora ? ' gl-fora' : ''}${alvoOk(eu, 'heroi') ? ' gl-alvo' : ''}" data-heroi="${eu}">` +
            `<span class="gl-avatar">${inicialDe(eu)}</span>` +
            `<div class="gl-assento-info"><strong>${nomeDe(eu)}${D.lado ? ' <small>(você)</small>' : ''}</strong>${vida(eu)}</div>` +
            (M.jogo && eu === D.lado
                ? `<button type="button" class="gl-energia gl-energia--grande" data-gl="ajustes" title="Energia (sobe 1 a cada turno seu). Clique para ajustar energia, vida e mais">⚡ ${l.energia}/${l.energiaMax} <small>⚙</small></button>`
                : `<span class="gl-energia gl-energia--grande" title="Energia: sobe 1 a cada turno seu">⚡ ${l.energia}/${l.energiaMax}</span>`) +
            `<div class="gl-assento-shots">${shots(eu, eu === D.lado)}</div>` +
            (D.lado ? `<button type="button" class="gl-zona" data-gl="baralho" title="Consultar o seu baralho">📚 ${qtd(l.baralho)}</button>` : `<span>📚 ${qtd(l.baralho)}</span>`) +
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
                `<div class="gl-meu-lado">${campo(eu)}${minhaBarra}${maoHtml}</div>` +
            `</div>` +
            `<aside class="gl-log"><h3>Acontecimentos</h3><ol>${e.log.slice().reverse().map(t => `<li>${esc(t)}</li>`).join('')}</ol></aside>` +
            renderModal(M);

        // status no topo
        if (D.status === 'cancelada') status('Partida cancelada pelo mestre. As apostas voltaram.');
        else if (e.fim) {
            const ganhei = D.lado && e.fim.vencedor === D.lado;
            status(`${ganhei ? '🏆 Você venceu!' : (D.lado ? 'Você perdeu.' : '')} Vencedor: <strong>${nomeDe(e.fim.vencedor)}</strong>` +
                (D.aposta ? ` · leva o pote de ${(D.aposta * ordem.length).toLocaleString('pt-BR')} de Dinheiro` : ''));
        } else if (D.lado && L[D.lado].fora) status('Você está fora desta partida. Pode continuar assistindo.');
        else if (M.escolho) status(`<strong>Sua escolha:</strong> ${esc(M.esc.texto)}.`);
        else if (M.esc) status(`Esperando <strong>${nomeDe(M.esc.lado)}</strong> escolher: ${esc(M.esc.texto)}.`);
        else if (defendo) status(`<strong>${nomeDe(e.vez)} está atacando você!</strong> ${atacando.length} Lenda(s) avançam. Bloqueie ou jogue Lendas para se defender.`);
        else if (esperoDefesa) status(`Ataque declarado. <strong>${nomeDe(M.ataque.defensor)}</strong> está escolhendo a defesa.`);
        else if (minhaVez) status('<strong>Sua vez.</strong> Arraste cartas para o campo, ataque com Lendas prontas ou use as ferramentas.');
        else status(`Vez de <strong>${nomeDe(e.vez)}</strong>.${D.lado ? '' : ' Você está assistindo.'}`);
    }

    /* ---------- janelas: baralho, cemitério, ficha, conjurar e ações de uma carta ---------- */
    function renderModal(M) {
        if (!modal) return '';
        const { L, eu, minhaVez } = M;
        const fechar = `<button type="button" class="camp-btn camp-btn-pequeno" data-gl="fechar-modal">${modal.volta ? 'Voltar' : 'Fechar'}</button>`;
        const botaoVer = (c, inst, dono) => `<button type="button" class="camp-btn"${ver(c, inst, dono)}>🔍 Ver detalhes</button>`;
        const janela = (titulo, corpo, rodape) => `<div class="gl-modal" data-gl="fundo-modal"><div class="gl-modal-caixa" role="dialog" aria-label="${esc(titulo)}">` +
            `<div class="gl-modal-cab"><h3>${esc(titulo)}</h3>${fechar}</div><div class="gl-modal-corpo">${corpo}</div>` +
            (rodape ? `<div class="gl-modal-rodape">${rodape}</div>` : '') + `</div></div>`;
        const linhaCarta = c => `<span class="jg-deck-custo">${esc(c.custo)}</span><span class="jg-deck-nome">${esc(c.nome)} <small>${c.tipo === 'lenda' ? `Lenda ${esc(c.ataque)}/${esc(c.vida)}` : 'Feitiço'}</small></span>`;
        const marcacao = (x, rotulo) => `<div class="gl-marcacao">` +
            `<span>Contador: <b>${x.cont || 0}</b></span>` +
            `<button type="button" class="camp-btn camp-btn-pequeno" data-m="cont" data-d="1">+1</button>` +
            `<button type="button" class="camp-btn camp-btn-pequeno" data-m="cont" data-d="-1">−1</button></div>` +
            `<form class="gl-form-nota" data-gl-form="nota"><label>Anotação ${rotulo}<input type="text" name="nota" maxlength="60" value="${esc(x.nota || '')}" placeholder="Ex.: envenenada, +2/+2 até o fim do turno"></label>` +
            `<button class="camp-btn camp-btn-pequeno">Salvar anotação</button></form>`;

        if (modal.tipo === 'escolha') {
            // carta pedida por um efeito, no próprio baralho ou cemitério
            if (!M.escolho || !['baralho', 'cemiterio'].includes(M.esc.tipo)) { modal = null; return ''; }
            const zona = Array.isArray(L[eu][M.esc.tipo]) ? L[eu][M.esc.tipo] : [];
            const ops = zona.filter(x => M.esc.opcoes.includes(x.i));
            const corpo = `<p class="gl-conjurar-texto">${esc(M.esc.texto)}</p><div class="gl-grade-cartas">${ops.map(x =>
                `<div class="gl-slot">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}` +
                `<span class="gl-cem-botoes"><button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-escolher="${x.i}">Escolher</button></span></div>`).join('')}</div>`;
            return janela(M.esc.fonte, corpo, M.esc.obrig ? '' : `<button type="button" class="camp-btn" data-gl="pular">Pular</button>`);
        }

        if (modal.tipo === 'baralho') {
            const baralho = Array.isArray(L[eu].baralho) ? L[eu].baralho : [];
            const ids = baralho.map(c => c.i);
            if (!ordemDeck || ordemDeck.length !== ids.length || ordemDeck.some(i => !ids.includes(i))) ordemDeck = ids.slice();
            const porId = {};
            baralho.forEach(c => { porId[c.i] = c; });
            const mudou = ordemDeck.some((i, k) => i !== ids[k]);
            const corpo = ordemDeck.length ? `<ol class="gl-lista-baralho">${ordemDeck.map((i, k) => `<li class="jg-deck-linha">` +
                `<span class="gl-pos">${k === 0 ? 'Topo' : k + 1}</span><span class="gl-linha-ver"${ver(porId[i].c)}>${linhaCarta(cartaDef(porId[i].c))}</span>` +
                (M.jogo ? `<button type="button" class="camp-btn camp-btn-pequeno" data-buscar="${i}" title="Pôr esta carta na mão">Para a mão</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-ord="${k}" data-para="topo" title="Para o topo"${k ? '' : ' disabled'}>⤒</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-ord="${k}" data-para="-1" title="Subir"${k ? '' : ' disabled'}>↑</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-ord="${k}" data-para="1" title="Descer"${k < ordemDeck.length - 1 ? '' : ' disabled'}>↓</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-ord="${k}" data-para="fundo" title="Para o fundo"${k < ordemDeck.length - 1 ? '' : ' disabled'}>⤓</button>` : '') +
                `</li>`).join('')}</ol>` : '<p class="camp-vazio">Seu baralho acabou.</p>';
            const rodape = M.jogo
                ? `<button type="button" class="camp-btn camp-btn-primario" data-gl="salvar-ordem"${mudou ? '' : ' disabled'}>Salvar nova ordem</button>` +
                  `<button type="button" class="camp-btn" data-gl="embaralhar">Embaralhar</button>` +
                  `<button type="button" class="camp-btn" data-gl="comprar">Comprar do topo</button>`
                : '';
            return janela(`Seu baralho (${ordemDeck.length} cartas, do topo para o fundo)`, corpo, rodape);
        }

        if (modal.tipo === 'cemiterio') {
            const lado = modal.lado || eu;
            const abas = M.ordem.map(l => `<button type="button" class="jg-aba${l === lado ? ' ativo' : ''}" data-gl="aba-cemiterio" data-lado="${l}">` +
                `${l === D.lado ? 'Seu' : nomeDe(l)} · ${L[l].cemiterio.length}</button>`).join('');
            const cartasCem = L[lado].cemiterio.slice().reverse();
            const corpo = `<div class="jg-abas">${abas}</div>` + (cartasCem.length ? `<div class="gl-grade-cartas">${cartasCem.map(x =>
                `<div class="gl-slot">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}` +
                (lado === D.lado && M.jogo ? `<span class="gl-cem-botoes"><button type="button" class="camp-btn camp-btn-pequeno" data-recuperar="${x.i}">Para a mão</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-invocar-cem="${x.i}" title="Põe em jogo sem pagar energia">Invocar</button>` +
                    (x.i === L[lado].cmd ? `<button type="button" class="camp-btn camp-btn-pequeno" data-para-comando="${x.i}">👑 Para o comando</button>` : '') + '</span>' : '') +
                `</div>`).join('')}</div>` : '<p class="camp-vazio">Cemitério vazio.</p>');
            return janela('Cemitério', corpo, '<span class="camp-dica">Lendas que caem, feitiços mandados ao cemitério e cartas descartadas. O mais recente aparece primeiro.</span>');
        }

        if (modal.tipo === 'ficha') {
            const corpo = `<form class="gl-form-ficha" data-gl-form="ficha">` +
                `<label>Nome<input type="text" name="nome" maxlength="40" value="Olhinho" required></label>` +
                `<label>Ataque<input type="number" name="atk" min="0" max="9999" value="1"></label>` +
                `<label>Vida<input type="number" name="vida" min="1" max="9999" value="1"></label>` +
                `<button class="camp-btn camp-btn-primario">Criar na minha mesa</button></form>`;
            return janela('Criar ficha', corpo, '<span class="camp-dica">Para efeitos como "invoque uma ficha 1/1". Fichas não vão para o cemitério quando caem.</span>');
        }

        if (modal.tipo === 'conjurar') {
            const x = (Array.isArray(L[eu].mao) ? L[eu].mao : []).find(c => c.i === modal.inst);
            if (!x) { modal = null; return ''; }
            const c = cartaDef(x.c);
            const opcoes = [['', 'Nenhum: só conjurar (o texto é resolvido à mão)'], ['dano', 'Dano numa Lenda inimiga ou num adversário'],
                ['dano_todos', 'Dano em todas as Lendas inimigas'], ['cura', 'Cura numa Lenda sua ou em você'], ['comprar', 'Comprar cartas'],
                ['fortalecer', '+ataque numa Lenda sua'], ['energia', 'Ganhar energia neste turno']];
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(c)}<form class="gl-modal-acoes gl-form-conjurar" data-gl-form="conjurar">` +
                `<p class="gl-conjurar-texto">${esc(c.descricao || 'Este feitiço não tem descrição.')}</p>` +
                `<label>Efeito que o texto pede<select name="efeito">${opcoes.map(o => `<option value="${o[0]}">${esc(o[1])}</option>`).join('')}</select></label>` +
                `<label data-valor hidden>Valor<input type="number" name="valor" min="0" max="9999" value="1"></label>` +
                `<button class="camp-btn camp-btn-primario">Conjurar${modal.gratis ? ' sem pagar energia' : ''}</button>` +
                `<p class="camp-dica">O feitiço fica no seu campo até você mandá-lo para o cemitério. O efeito escolhido fica registrado.</p></form></div>`;
            return janela('Conjurar ' + c.nome, corpo, '');
        }

        if (modal.tipo === 'mao') {
            const x = (Array.isArray(L[eu].mao) ? L[eu].mao : []).find(c => c.i === modal.inst);
            if (!x) { modal = null; return ''; }
            const c = cartaDef(x.c);
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(c)}<div class="gl-modal-acoes">` +
                (minhaVez ? `<button type="button" class="camp-btn" data-m="gratis">Jogar sem pagar energia</button>` : '') +
                `<button type="button" class="camp-btn" data-m="invocar" title="Põe em jogo agora, sem pagar e sem efeito automático">Invocar sem custo (a qualquer momento)</button>` +
                `<button type="button" class="camp-btn" data-m="revelar">${x.rev ? 'Esconder de novo' : 'Revelar para os outros jogadores'}</button>` +
                `<button type="button" class="camp-btn" data-m="descartar">Descartar (vai ao cemitério)</button>` +
                botaoVer(x.c) +
                `<p class="camp-dica">Dica: arraste a carta para o campo para jogar, ou para o ✝ cemitério para descartar.</p></div></div>`;
            return janela(c.nome, corpo, '');
        }

        if (modal.tipo === 'mesa') {
            const dono = modal.lado;
            const x = (L[dono] ? L[dono].mesa : []).find(m => m.i === modal.inst);
            if (!x) { modal = null; return ''; }
            const minha = dono === D.lado;
            const outras = minha && L[dono].mesa.length > 1;
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(defLenda(x), { atk: x.atk, vida: x.vida, max: x.max, pal: x.pal || [] })}<div class="gl-modal-acoes">` +
                `<div class="gl-ajustes">` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-m="ajustar" data-a="1" data-v="1">+1/+1</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-m="ajustar" data-a="-1" data-v="-1">−1/−1</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-m="ajustar" data-a="1" data-v="0">+1 ataque</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-m="ajustar" data-a="-1" data-v="0">−1 ataque</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-m="ajustar" data-a="0" data-v="1">+1 vida</button>` +
                    `<button type="button" class="camp-btn camp-btn-pequeno" data-m="ajustar" data-a="0" data-v="-1">−1 vida</button>` +
                `</div>` +
                marcacao(x, 'na Lenda') +
                (minha && minhaVez && outras ? `<button type="button" class="camp-btn" data-m="copiar">Virar cópia de outra Lenda sua</button>` : '') +
                (minha && (defLenda(x).hab || []).length ? `<button type="button" class="camp-btn" data-m="ativar" title="${esc(defLenda(x).auto || '')}">⚙ Usar o texto desta carta de novo</button>` : '') +
                (minha ? `<button type="button" class="camp-btn" data-m="sacrificar">${x.i === L[dono].cmd ? 'Sacrificar (volta para a zona de comando)' : 'Sacrificar (vai ao cemitério)'}</button>` : '') +
                botaoVer(x.c, x.i, dono) +
                `<p class="camp-dica">${minha ? 'Sua Lenda.' : 'Lenda de ' + nomeDe(dono) + '.'} Para efeitos escritos nas cartas. Tudo fica registrado nos acontecimentos.</p></div></div>`;
            return janela(nomeLenda(x) + (minha ? '' : ' · ' + nomeDe(dono)).replace(/&amp;/g, '&'), corpo, '');
        }

        if (modal.tipo === 'ajustes') {
            const l = L[eu];
            const par = (rotulo, botoes) => `<div class="gl-ajuste-linha"><span>${rotulo}</span>${botoes}</div>`;
            const b = (attrs, txt, titulo) => `<button type="button" class="camp-btn camp-btn-pequeno" ${attrs} title="${titulo}">${txt}</button>`;
            const corpo = `<div class="gl-ajustes-livres">` +
                par(`Energia agora <b>${l.energia}</b>`, b('data-en="-1"', '−1', 'Gastar 1 de energia') + b('data-en="1"', '+1', 'Ganhar 1 de energia')) +
                par(`Energia máxima <b>${l.energiaMax}</b>`, b('data-en="0" data-en-max="-1"', '−1', 'Baixar a energia máxima') + b('data-en="0" data-en-max="1"', '+1', 'Subir a energia máxima') +
                    b('data-en="1" data-en-max="1"', '+1 e recarrega 1', 'Sobe a máxima e a de agora juntas')) +
                par(`Vida <b>${l.vida} / ${l.max}</b>`, b('data-vida-q="-5"', '−5', 'Perder 5 (vai para o pote)') + b('data-vida-q="-1"', '−1', 'Perder 1 (vai para o pote)') +
                    b('data-vida-q="1"', '+1', 'Recuperar 1 (até o máximo)') + b('data-vida-q="5"', '+5', 'Recuperar 5 (até o máximo)')) +
                `</div><div class="gl-ajustes-atalhos">` +
                `<button type="button" class="camp-btn" data-gl="comprar">Comprar do topo</button>` +
                `<button type="button" class="camp-btn" data-gl="baralho">Buscar no baralho</button>` +
                `<button type="button" class="camp-btn" data-gl="cemiterio" data-lado="${eu}">Cemitério</button>` +
                `<button type="button" class="camp-btn" data-gl="ficha">Criar ficha</button></div>`;
            return janela('Ajustes livres', corpo, '<span class="camp-dica">As regras das cartas variam: mexa no que precisar, a qualquer momento. Tudo fica registrado nos acontecimentos.</span>');
        }

        if (modal.tipo === 'comando') {
            const lado = modal.lado;
            const l = L[lado];
            const x = (l.comando || [])[0];
            const meu = lado === D.lado && M.jogo;
            if (!x) { modal = null; return ''; }
            const custo = custoCmd(l);
            const corpo = `<div class="gl-modal-carta">${window.JG.carta(cartaDef(x.c), { attrs: ver(x.c) })}<div class="gl-modal-acoes">` +
                `<p class="gl-conjurar-texto">👑 Comandante ${lado === D.lado ? 'seu' : 'de ' + nomeDe(lado)}. Custo agora: <b>${custo}</b> ` +
                `(${esc(cartaDef(x.c).custo)} da carta + 2 × ${l.taxa || 0} ${l.taxa === 1 ? 'vez' : 'vezes'} que já saiu da zona de comando).</p>` +
                (meu ? `<button type="button" class="camp-btn camp-btn-primario" data-gl="comandar"${M.minhaVez && custo <= l.energia ? '' : ' disabled'}>Invocar pagando ⚡${custo}</button>` +
                       `<button type="button" class="camp-btn" data-invocar-cmd="${x.i}">Invocar sem pagar (a taxa sobe igual)</button>` +
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
                (cmd ? `<p class="gl-detalhe-cmd">👑 Comandante de ${nomeDe(dono)}</p>` : '') +
                (dono && x ? `<p class="camp-dica">Em jogo · ${dono === D.lado ? 'sua' : 'de ' + nomeDe(dono)}${naMesa && x.virada ? ' · virada' : ''}</p>` : '') +
                `<div class="gl-detalhe-texto">${c.descricao ? esc(c.descricao) : '<em>Sem texto.</em>'}</div>` +
                (auto ? `<p class="gl-detalhe-auto"><b>Efeito automático:</b> ${esc(auto)}</p>` : '') +
                (c.parcial ? `<p class="camp-dica">Parte do texto não é automática: resolva com as ferramentas (⋯ nas cartas, ⚡ ajustes).</p>` : '') +
                (pal.filter(p => P[p]).length ? `<dl class="gl-detalhe-palavras">${pal.filter(p => P[p]).map(p => `<dt>${esc(P[p].nome)}</dt><dd>${esc(P[p].desc)}</dd>`).join('')}</dl>` : '') +
                (x && (x.cont || x.nota) ? `<p class="gl-detalhe-marcas">${x.cont ? `Contador: <b>${x.cont > 0 ? '+' : ''}${x.cont}</b>` : ''}${x.cont && x.nota ? ' · ' : ''}${x.nota ? 'Anotação: ' + esc(x.nota) : ''}</p>` : '') +
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
                (minha && (cartaDef(x.c).hab || []).length ? `<button type="button" class="camp-btn" data-m="ativar" title="${esc(cartaDef(x.c).auto || '')}">⚙ Usar o texto desta carta de novo</button>` : '') +
                (minha && minhaVez && L[eu].mesa.length ? `<button type="button" class="camp-btn" data-m="equipar" title="Vincula este feitiço a uma Lenda sua: ele cai junto com ela">🗡 ${x.em ? 'Trocar de Lenda' : 'Equipar numa Lenda sua'}</button>` : '') +
                (minha ? `<button type="button" class="camp-btn" data-m="encerrar">Mandar para o cemitério</button>` : '') +
                botaoVer(x.c, x.i, dono) +
                (x.em && L[dono].mesa.some(m => m.i === x.em) ? `<p class="camp-dica">🗡 Equipado em <strong>${esc(nomeLenda(L[dono].mesa.find(m => m.i === x.em)))}</strong>: vai para o cemitério quando ela sair da mesa.</p>` : '') +
                `<p class="camp-dica">O feitiço fica em jogo até ${minha ? 'você mandá-lo' : 'quem o conjurou mandá-lo'} para o cemitério.</p></div></div>`;
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
        if (!((M.minhaVez && c.custo <= M.L[M.eu].energia) || (M.defendo && c.tipo === 'lenda' && c.custo <= M.L[M.eu].energia))) {
            msg(M.defendo && c.tipo !== 'lenda' ? 'Na defesa só dá para jogar Lendas.' : (c.custo > M.L[M.eu].energia ? 'Energia insuficiente.' : 'Agora não é a sua vez.'), true);
            return;
        }
        if (c.tipo === 'feitico' && conjuraSozinho(c)) { acao({ acao: 'jogar', inst }); return; } // alvos são pedidos depois
        if (c.tipo === 'feitico' && !c.efeito) { modal = { tipo: 'conjurar', inst }; render(); return; }
        const precisa = c.tipo === 'feitico' ? PRECISA[c.efeito] : null;
        if (precisa) { alvo = { tipo: 'feitico', inst, precisa }; render(); return; }
        acao({ acao: 'jogar', inst });
    }

    function cliqueGladio(ev) {
        if (engolirClique) { engolirClique = false; return; }
        const M = momento();
        const { e, L, eu } = M;

        // ----- janelas -----
        const fundo = ev.target.closest('[data-gl="fundo-modal"]');
        if (fundo && ev.target === fundo) { modal = modal && modal.volta ? modal.volta : null; ordemDeck = null; render(); return; }
        // detalhes: botões "Ver detalhes", linhas do baralho e cartas dentro das janelas
        const verEl = ev.target.closest('button[data-ver], .gl-linha-ver[data-ver], .gl-modal .jg-carta[data-ver]');
        if (verEl) { abrirDetalhe(verEl); return; }
        const novo = ev.target.closest('[data-buscar], [data-invocar-cem], [data-para-comando], [data-en], [data-vida-q], [data-invocar-cmd]');
        if (novo) {
            const d = novo.dataset;
            if (d.buscar) acao({ acao: 'buscar', inst: +d.buscar });
            else if (d.invocarCem) acao({ acao: 'invocar', inst: +d.invocarCem, origem: 'cemiterio' });
            else if (d.paraComando) acao({ acao: 'comando', inst: +d.paraComando });
            else if (d.invocarCmd) { modal = null; acao({ acao: 'invocar', inst: +d.invocarCmd, origem: 'comando' }); }
            else if (d.en != null) acao({ acao: 'energia', energia: +d.en, energia_max: +(d.enMax || 0) });
            else if (d.vidaQ) acao({ acao: 'vida', quanto: +d.vidaQ });
            return;
        }
        const ord = ev.target.closest('[data-ord]');
        if (ord) {
            const k = +ord.dataset.ord;
            const [id] = ordemDeck.splice(k, 1);
            const p = ord.dataset.para;
            ordemDeck.splice(p === 'topo' ? 0 : p === 'fundo' ? ordemDeck.length : k + (+p), 0, id);
            render();
            return;
        }
        const escolhida = ev.target.closest('[data-escolher]');
        if (escolhida) { modal = null; acao({ acao: 'escolher', alvo: escolhida.dataset.escolher }); return; }
        const rec = ev.target.closest('[data-recuperar]');
        if (rec) { acao({ acao: 'recuperar', inst: +rec.dataset.recuperar }); return; }
        const m = ev.target.closest('[data-m]');
        if (m && modal) {
            const inst = modal.inst;
            const tipo = m.dataset.m;
            if (tipo === 'copiar') { modal = null; alvo = { tipo: 'copiar', inst }; render(); return; }
            if (tipo === 'equipar') { modal = null; alvo = { tipo: 'equipar', inst }; render(); return; }
            if (tipo === 'sacrificar' && !confirm('Sacrificar esta Lenda?')) return;
            if (tipo === 'descartar' && !confirm('Descartar esta carta?')) return;
            if (tipo === 'ajustar') { acao({ acao: 'ajustar', inst, atk: +m.dataset.a, vida: +m.dataset.v }); return; }
            if (tipo === 'cont') { acao({ acao: 'marcar', inst, cont: +m.dataset.d }); return; }
            if (tipo === 'revelar') { acao({ acao: 'revelar', inst }); return; }
            if (tipo === 'invocar') { modal = null; acao({ acao: 'invocar', inst, origem: 'mao' }); return; }
            if (tipo === 'ativar') { modal = null; acao({ acao: 'ativar', inst }); return; }
            modal = null;
            if (tipo === 'gratis') {
                const x = L[eu].mao.find(c => c.i === inst);
                const c = x && cartaDef(x.c);
                if (c && c.tipo === 'feitico' && conjuraSozinho(c)) { acao({ acao: 'jogar', inst, gratis: 1 }); return; }
                if (c && c.tipo === 'feitico' && !c.efeito) { modal = { tipo: 'conjurar', inst, gratis: true }; render(); return; }
                const precisa = c && c.tipo === 'feitico' ? PRECISA[c.efeito] : null;
                if (precisa) { alvo = { tipo: 'feitico', inst, precisa, gratis: true }; render(); return; }
                acao({ acao: 'jogar', inst, gratis: 1 });
            } else acao({ acao: tipo, inst });
            return;
        }

        const b = ev.target.closest('[data-gl]');
        if (b) {
            const g = b.dataset.gl;
            if (g === 'fechar-modal') { modal = modal && modal.volta ? modal.volta : null; ordemDeck = null; render(); }
            else if (g === 'ajustes') { modal = { tipo: 'ajustes' }; render(); }
            else if (g === 'comando') { modal = { tipo: 'comando', lado: b.dataset.lado }; render(); }
            else if (g === 'comandar') { modal = null; acao({ acao: 'comandar' }); }
            else if (g === 'baralho') { modal = { tipo: 'baralho' }; ordemDeck = null; render(); }
            else if (g === 'cemiterio' || g === 'aba-cemiterio') { modal = { tipo: 'cemiterio', lado: b.dataset.lado }; render(); }
            else if (g === 'ficha') { modal = { tipo: 'ficha', volta: modal && modal.tipo === 'ajustes' ? modal : null }; render(); const n = box.querySelector('[data-gl-form] [name=nome]'); if (n) n.select(); }
            else if (g === 'cancelar-alvo') { alvo = null; render(); }
            else if (g === 'pular') { modal = null; acao({ acao: 'escolher' }); }
            else if (g === 'escolha-cartas') { modal = { tipo: 'escolha' }; render(); }
            else if (g === 'limpar-ataque') { atacantes = []; render(); }
            else if (g === 'atacar') { const lista = atacantes.slice(); atacantes = []; acao({ acao: 'atacar', insts: lista, alvo: b.dataset.alvo }); }
            else if (g === 'defender') { bloqueador = null; acao({ acao: 'defender' }); }
            else if (g === 'passar') { alvo = null; atacantes = []; acao({ acao: 'passar' }); }
            else if (g === 'comprar') acao({ acao: 'comprar' });
            else if (g === 'apressar') acao({ acao: 'apressar' });
            else if (g === 'embaralhar') { ordemDeck = null; acao({ acao: 'embaralhar' }); }
            else if (g === 'salvar-ordem') acao({ acao: 'reordenar', ordem: ordemDeck.slice() });
            else if (g === 'shot') {
                if (+b.dataset.q > 0 && (L[eu].shots || 0) + 1 >= SHOTS && !confirm(`Esse é o ${SHOTS}º shot: você sai da partida. Tomar mesmo assim?`)) return;
                acao({ acao: 'shot', quanto: +b.dataset.q });
            }
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

        // ----- escolha pedida por um efeito: o clique no alvo destacado responde -----
        if (M.escolho) {
            const marcado = [l, h, cartaMao].find(x => x && x.classList.contains('gl-alvo'));
            if (!marcado) { abrirDetalhe(cartaVer); return; }
            const valor = marcado === l ? l.dataset.lenda : (marcado === h ? 'j:' + h.dataset.heroi : cartaMao.dataset.mao);
            acao({ acao: 'escolher', alvo: valor });
            return;
        }

        // ----- defesa: jogar Lendas da mão e escolher bloqueadores -----
        if (M.defendo) {
            if (cartaMao) { jogarDaMao(+cartaMao.dataset.mao); return; }
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
            acao({ acao: 'jogar', inst: a.inst, alvo: escolhido, gratis: a.gratis ? 1 : null, efeito: a.efeito || null, valor: a.efeito ? a.valor : null });
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
                document.body.appendChild(fantasma);
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
                    else { modal = { tipo: 'comando', lado: D.lado }; render(); msg('Fora da sua vez: invoque o comandante sem pagar pela zona de comando.'); }
                }
            } else if (zona && zona.dataset.zona === 'campo') jogarDaMao(inst);
            else if (zona && zona.dataset.zona === 'cemiterio') acao({ acao: 'descartar', inst });
        };
        document.addEventListener('pointermove', mover);
        document.addEventListener('pointerup', fim);
        document.addEventListener('pointercancel', fim);
    });

    // janela "Conjurar": o valor só aparece quando há efeito
    box.addEventListener('change', ev => {
        const sel = ev.target.closest('[data-gl-form="conjurar"] select[name=efeito]');
        if (sel) sel.form.querySelector('[data-valor]').hidden = !sel.value;
    });
    box.addEventListener('submit', ev => {
        const f = ev.target.closest('[data-gl-form]');
        if (!f) return;
        ev.preventDefault();
        const tipo = f.dataset.glForm;
        if (tipo === 'conjurar') {
            const { inst, gratis } = modal;
            const efeito = f.efeito.value || null;
            const valor = Math.max(0, +f.valor.value || 0);
            modal = null;
            const precisa = efeito ? PRECISA[efeito] : null;
            if (precisa) { alvo = { tipo: 'feitico', inst, precisa, gratis, efeito, valor }; render(); return; }
            acao({ acao: 'jogar', inst, gratis: gratis ? 1 : null, efeito, valor: efeito ? valor : null });
        } else if (tipo === 'ficha') {
            modal = null;
            acao({ acao: 'ficha', nome: f.nome.value.trim(), atk: +f.atk.value || 0, vida: +f.vida.value || 1 });
        } else if (tipo === 'nota' && modal) {
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
                    (p ? `<span class="pd-peca pd-${p.c}${CUSTOM.test(p.t) ? ' pd-custom' : ''}">${esc(t.simbolo)}</span>` : '') +
                    (j === 0 ? `<small class="pd-rot pd-rot-y">${y + 1}</small>` : '') +
                    (i === 7 ? `<small class="pd-rot pd-rot-x">${'abcdefgh'[x]}</small>` : '') +
                    '</button>';
            }
        }
        h += '</div>';

        // legenda das peças customizadas desta partida
        const custom = Object.keys(e.tipos).filter(id => CUSTOM.test(id));
        const lateral =
            `<aside class="pd-lateral">` +
                `<div class="pd-jogadores"><div class="pd-jog${e.vez === 'b' && !e.fim ? ' pd-vez' : ''}"><span class="pd-peca pd-b">♚</span> ${nomeJ('j1')}</div>` +
                `<div class="pd-jog${e.vez === 'p' && !e.fim ? ' pd-vez' : ''}"><span class="pd-peca pd-p">♚</span> ${nomeJ('j2')}</div></div>` +
                `<p class="camp-dica">Lances: ${e.lances}</p>` +
                (D.lado && D.status === 'andamento' ? `<button type="button" class="camp-btn camp-btn-pequeno" data-pd="desistir">Desistir</button>` : '') +
                (custom.length ? `<h3>Peças customizadas</h3>` + custom.map(id => `<div class="pd-legenda"><span class="pd-peca pd-b pd-custom">${esc(e.tipos[id].simbolo)}</span>` +
                    `<div><strong>${esc(e.tipos[id].nome)}</strong>${window.JG.diagrama(e.tipos[id], 8)}</div></div>`).join('') : '') +
            `</aside>`;
        box.innerHTML = `<div class="pd-area">${h}${lateral}</div>`;

        const corNome = c => (c === 'b' ? 'Brancas' : 'Pretas');
        const nomeCor = c => (c === 'b' ? nomeJ('j1') : nomeJ('j2'));
        if (D.status === 'cancelada') status('Partida cancelada pelo mestre.');
        else if (e.fim) {
            if (!e.fim.vencedor) status('Empate por afogamento: quem tinha a vez não tinha lance e não estava em xeque.');
            else status(`${D.lado === e.fim.vencedor ? '🏆 Você venceu!' : (D.lado ? 'Você perdeu.' : '')} ${corNome(e.fim.vencedor)} (<strong>${nomeCor(e.fim.vencedor)}</strong>) venceram` +
                (e.fim.motivo === 'xeque-mate' ? ' por xeque-mate.' : ' por desistência.'));
        } else {
            const xq = e.xeque ? ' <strong class="pd-aviso-xeque">Xeque!</strong>' : '';
            status(minhaVez ? `<strong>Sua vez</strong> (${corNome(D.lado)}).${xq}` : `Vez das ${corNome(e.vez)} (<strong>${nomeCor(e.vez)}</strong>).${xq}${D.lado ? '' : ' Você está assistindo.'}`);
        }
    }

    function cliquePelaDama(ev) {
        const b = ev.target.closest('[data-pd]');
        if (b && b.dataset.pd === 'desistir') {
            if (confirm('Desistir da partida?')) acao({ acao: 'desistir' });
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
    const APRESSAR_APOS = 60; // Gladio::APRESSAR_APOS: depois disso os outros podem apressar
    let difRelogio = 0;       // relógio do servidor − relógio do navegador (s)
    let ultimoApressado = null;
    const agoraServidor = () => Date.now() / 1000 + difRelogio;
    const esperandoDe = e => (e.escolha && e.escolha.lado) || (e.fase === 'defesa' && e.ataque ? e.ataque.defensor : e.vez);

    function relogio(M) {
        if (!M.ativo || !M.e.desde) return '';
        return `<span class="gl-relogio" data-relogio title="Tempo que a mesa está esperando por quem tem a vez (ou pela defesa/escolha)">⏱ <b>0:00</b></span>` +
            `<button type="button" class="camp-btn camp-btn-pequeno gl-apressar" data-gl="apressar" hidden title="Avisar quem está demorando">⏳ Apressar</button>`;
    }
    function tique() {
        if (D.jogo !== 'gladio' || !D.estado) return;
        const el = box.querySelector('[data-relogio]');
        if (!el) return;
        const e = D.estado;
        const seg = Math.max(0, Math.floor(agoraServidor() - e.desde));
        el.querySelector('b').textContent = Math.floor(seg / 60) + ':' + String(seg % 60).padStart(2, '0');
        const quem = esperandoDe(e);
        el.classList.toggle('gl-relogio--atencao', seg >= APRESSAR_APOS * 0.75 && seg < APRESSAR_APOS * 1.5);
        el.classList.toggle('gl-relogio--urgente', seg >= APRESSAR_APOS * 1.5);
        el.classList.toggle('gl-relogio--meu', quem === D.lado);
        const bt = box.querySelector('[data-gl="apressar"]');
        if (bt) bt.hidden = !(momento().jogo && quem !== D.lado && seg >= APRESSAR_APOS);
    }
    setInterval(tique, 1000);
    function avisarApressado() {
        const ap = D.estado && D.estado.apressado;
        if (!ap || ap.em === ultimoApressado) return;
        const novo = ultimoApressado !== null;
        ultimoApressado = ap.em;
        if (!novo || ap.alvo !== D.lado) return;
        msg(`⏳ ${(D.estado.nomes || {})[ap.por] || 'Alguém'} está apressando você!`, true);
        box.classList.remove('gl-apressado');
        void box.offsetWidth;
        box.classList.add('gl-apressado');
        setTimeout(() => box.classList.remove('gl-apressado'), 2600);
    }

    /* ================================================================== */
    /*  Gladio: animações (comparando o estado anterior com o novo)        */
    /* ================================================================== */
    let estadoAnterior = null;
    const reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // posição e aparência das cartas antes de redesenhar (as que sumirem viram "fantasmas" animados)
    function fotografar() {
        const f = { slot: {}, mao: {}, cem: {} };
        box.querySelectorAll('.gl-campo [data-slot]').forEach(s => { f.slot[s.dataset.slot] = { r: s.getBoundingClientRect(), html: s.outerHTML }; });
        box.querySelectorAll('.gl-mao .gl-slot--mao').forEach(s => {
            const c = s.querySelector('[data-mao]');
            if (c) f.mao[c.dataset.mao] = { r: s.getBoundingClientRect(), html: s.outerHTML };
        });
        return f;
    }
    function fantasma(foto, classe, alvoEl) {
        const g = document.createElement('div');
        g.className = 'gl-fantasma-anim ' + classe;
        g.innerHTML = foto.html;
        Object.assign(g.style, { left: foto.r.left + 'px', top: foto.r.top + 'px', width: foto.r.width + 'px', height: foto.r.height + 'px' });
        if (alvoEl) {
            const a = alvoEl.getBoundingClientRect();
            g.style.setProperty('--dx', (a.left + a.width / 2 - (foto.r.left + foto.r.width / 2)) + 'px');
            g.style.setProperty('--dy', (a.top + a.height / 2 - (foto.r.top + foto.r.height / 2)) + 'px');
        }
        document.body.appendChild(g);
        setTimeout(() => g.remove(), 1100);
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
        document.body.appendChild(s);
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
        // combate resolvido: os atacantes avançam de novo, como o golpe
        if (atA.length && !atB.length && a.vez === b.vez) atA.forEach(i => marcar(slot(i), a.vez === D.lado ? 'gl-anim-ataca' : 'gl-anim-ataca-baixo'));
    }

    /* ================================================================== */
    function render() {
        if (!D.estado) {
            status(D.status === 'cancelada' ? 'Desafio cancelado.' : 'Aguardando alguém aceitar o desafio…');
            box.innerHTML = `<p class="camp-vazio"><a href="${esc(C.url.lobby)}">Voltar aos jogos</a></p>`;
            return;
        }
        if (D.jogo !== 'gladio') { renderPelaDama(); return; }
        if (D.estado.agora) difRelogio = D.estado.agora - Date.now() / 1000;
        const foto = estadoAnterior && estadoAnterior !== D.estado ? fotografar() : null;
        renderGladio();
        if (foto) animar(estadoAnterior, D.estado, foto);
        estadoAnterior = D.estado;
        avisarApressado();
        tique();
    }
    box.addEventListener('click', ev => (D.jogo === 'gladio' ? cliqueGladio(ev) : cliquePelaDama(ev)));
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && (alvo || sel || modal || bloqueador != null)) { alvo = null; sel = null; modal = null; ordemDeck = null; bloqueador = null; render(); }
    });
    render();
    // Gladio: abre já com o tabuleiro inteiro à vista (o título da página fica acima, rolando para cima)
    if (D.jogo === 'gladio' && D.estado) {
        const t = $('#jg-status');
        if (t) window.scrollTo({ top: Math.max(0, t.getBoundingClientRect().top + window.scrollY - 76) });
    }
})();
