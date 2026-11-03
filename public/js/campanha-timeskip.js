/**
 * Página de time skips da campanha. O mestre monta as regras (do zero ou de um modelo de config/timeskip.php), abre
 * para os jogadores e vê o que cada um escolheu; o jogador escolhe ramo, atividades e propriedades com as contas ao
 * vivo (TimeSkipCalc, as mesmas do servidor).
 */
(function () {
    const el = document.getElementById('ts-dados');
    if (!el) return;
    const D = JSON.parse(el.textContent);
    const $ = s => document.querySelector(s);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const num = n => (+n || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    const reais = n => 'R$ ' + Math.round(+n || 0).toLocaleString('pt-BR');
    const STATUS = { rascunho: 'Rascunho (só você vê)', aberto: 'Aberto para escolhas', fechado: 'Fechado (só leitura)' };
    const TIPOS = { '': 'Ganho (entra nos totais)', escolhas: 'Escolhas extras', salario: 'Salário mensal (R$)', salarios: 'Salários inteiros a mais', dinheiro: 'Dinheiro (R$)' };
    let skips = D.skips.slice();
    let respostas = D.respostas.slice();

    function msg(texto, erro) {
        const m = $('#ts-msg');
        m.textContent = texto;
        m.hidden = !texto;
        m.classList.toggle('erro', !!erro);
        clearTimeout(msg.t);
        msg.t = setTimeout(() => { m.hidden = true; }, 4000);
    }
    async function enviar(url, metodo, dados) {
        const r = await fetch(url, { method: metodo, credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': D.csrf },
            body: dados ? JSON.stringify(dados) : undefined });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || j.ok === false) throw new Error(j.erro || j.message || ('Erro ' + r.status));
        return j;
    }
    const janela = $('#ts-janela');
    const corpo = $('#ts-janela-corpo');
    function abrirJanela(titulo) { $('#ts-janela-titulo').textContent = titulo; janela.hidden = false; document.body.style.overflow = 'hidden'; }
    function fecharJanela() { janela.hidden = true; corpo.innerHTML = ''; document.body.style.overflow = ''; jogo = null; ed = null; }
    $('#ts-fechar').addEventListener('click', fecharJanela);
    janela.addEventListener('click', e => { if (e.target === janela) fecharJanela(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !janela.hidden) fecharJanela(); });

    const nomeFicha = id => id ? ((D.fichas.find(f => f.id === id) || {}).nome || 'Ficha #' + id) : 'Personagem sem ficha';
    const minhasFichas = () => D.fichas.filter(f => f.user_id === D.eu);
    const respostaDe = (skip, user, ficha) => respostas.find(r => r.skip === skip && r.user_id === user && r.ficha_id === ficha);

    /* ---------- lista ---------- */
    function desenharLista() {
        const lista = $('#ts-lista');
        if (!skips.length) {
            lista.innerHTML = '<p class="ts-meta">' + (D.mestre ? 'Nenhum time skip ainda. Crie um do zero ou comece do modelo de 10 anos.' : 'O mestre ainda não abriu nenhum time skip.') + '</p>';
            return;
        }
        lista.innerHTML = skips.map(s => {
            const c = s.config || {};
            const meus = respostas.filter(r => r.skip === s.id && r.user_id === D.eu).length;
            const todos = respostas.filter(r => r.skip === s.id).length;
            return `<article class="ts-cartao" data-id="${s.id}">
                <div class="ts-cab"><h3>${esc(s.nome)}</h3><span class="ts-status ${esc(s.status)}">${esc(STATUS[s.status] || s.status)}</span></div>
                <p class="ts-meta">${num(c.escolhas)} escolhas · ${(c.ramos || []).length} ramos · ${(c.atividades || []).length} atividades · ${(c.propriedades || []).length} propriedades</p>
                ${D.mestre ? `<p class="ts-meta">${todos} personagem(ns) com escolhas</p>` : (meus ? `<p class="ts-meta">Você já preencheu ${meus} personagem(ns).</p>` : '')}
                <div class="ts-botoes">
                    <button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-acao="jogar">${D.mestre ? 'Ver como jogador' : (s.status === 'aberto' ? 'Fazer escolhas' : 'Ver escolhas')}</button>
                    ${D.mestre ? `<button type="button" class="camp-btn camp-btn-pequeno" data-acao="editar">Editar regras</button>
                    <button type="button" class="camp-btn camp-btn-pequeno" data-acao="respostas">Escolhas dos jogadores (${todos})</button>
                    <select data-acao="status" aria-label="Situação">${Object.keys(STATUS).map(k => `<option value="${k}"${k === s.status ? ' selected' : ''}>${esc(STATUS[k])}</option>`).join('')}</select>
                    <button type="button" class="camp-btn camp-btn-pequeno" data-acao="excluir">Excluir</button>` : ''}
                </div>
            </article>`;
        }).join('');
    }
    $('#ts-lista').addEventListener('click', async e => {
        const b = e.target.closest('button[data-acao]');
        if (!b) return;
        const s = skips.find(x => x.id === +b.closest('[data-id]').dataset.id);
        if (!s) return;
        const acao = b.dataset.acao;
        if (acao === 'jogar') abrirJogo(s);
        if (acao === 'editar') abrirEditor(s);
        if (acao === 'respostas') abrirRespostas(s);
        if (acao === 'excluir' && confirm('Excluir "' + s.nome + '" e todas as escolhas feitas nele?')) {
            try {
                await enviar(D.url.base + '/' + s.id, 'DELETE');
                skips = skips.filter(x => x.id !== s.id);
                respostas = respostas.filter(r => r.skip !== s.id);
                desenharLista();
                msg('Time skip excluído.');
            } catch (err) { msg(err.message, true); }
        }
    });
    $('#ts-lista').addEventListener('change', async e => {
        const sel = e.target.closest('select[data-acao="status"]');
        if (!sel) return;
        const s = skips.find(x => x.id === +sel.closest('[data-id]').dataset.id);
        try {
            const j = await enviar(D.url.salvar, 'POST', { id: s.id, nome: s.nome, status: sel.value });
            Object.assign(s, j.skip);
            desenharLista();
            msg(STATUS[s.status] + '.');
        } catch (err) { msg(err.message, true); desenharLista(); }
    });

    /* ---------- escolhas do jogador ---------- */
    let jogo = null; // { skip, ficha, user, dados, ler }
    const vazio = () => ({ ramo: '', atividades: {}, propriedades: {}, salario: 0, dinheiro_inicial: 0, notas: '' });
    function abrirJogo(s, resposta) {
        const fichas = minhasFichas();
        const ficha = resposta ? resposta.ficha_id : (fichas[0] ? fichas[0].id : 0);
        const user = resposta ? resposta.user_id : D.eu;
        const r = resposta || respostaDe(s.id, user, ficha);
        jogo = { skip: s, ficha, user, dados: Object.assign(vazio(), r ? JSON.parse(JSON.stringify(r.dados)) : {}),
            ler: !!resposta && resposta.user_id !== D.eu || (!D.mestre && s.status !== 'aberto'), teste: D.mestre && !resposta };
        abrirJanela(s.nome + (resposta ? ' · ' + (resposta.jogador || '') : ''));
        desenharJogo();
    }
    function textoEfeito(ef, unidade) {
        const passo = ef.a_cada > 1 ? ` a cada ${ef.a_cada} ${unidade[1]}` : ` por ${unidade[0]}`;
        const rot = ef.tipo === 'salario' ? 'R$ no salário mensal' : ef.tipo === 'dinheiro' ? 'R$' : ef.tipo === 'salarios' ? 'salário(s)' : ef.tipo === 'escolhas' ? 'escolha(s)' : ef.rotulo;
        return `${ef.valor > 0 ? '+' : ''}${num(ef.valor)} ${rot}${passo}${ef.limite > 0 ? ` (até ${num(ef.limite)})` : ''}`;
    }
    function itemHtml(it, n, prop, cfg) {
        const ramoNome = it.ramo ? ((cfg.ramos || []).find(r => r.id === it.ramo) || {}).nome || it.ramo : '';
        const bloqueado = it.ramo && it.ramo !== jogo.dados.ramo;
        const tags = [];
        if (!prop && it.custo > 1) tags.push(`<span class="ts-tag">${it.custo} escolhas por vez</span>`);
        if (prop) tags.push(`<span class="ts-tag">${reais(it.preco)} por marco</span>`);
        if (it.max > 0) tags.push(`<span class="ts-tag">máx. ${it.max}</span>`);
        if (ramoNome) tags.push(`<span class="ts-tag${bloqueado ? ' req' : ''}">${esc(ramoNome)}</span>`);
        if (it.requisito) tags.push(`<span class="ts-tag req">Requisito: ${esc(it.requisito)}</span>`);
        if (!prop && it.fator > 0) tags.push(`<span class="ts-tag">salário a ${it.fator}%</span>`);
        const unidade = prop ? ['marco', 'marcos'] : ['escolha', 'vezes'];
        const ganhos = (it.efeitos || []).map(ef => textoEfeito(ef, unidade)).join(' · ');
        const podeMais = !jogo.ler && !bloqueado && (!it.max || n < it.max);
        return `<div class="ts-item${n ? ' tem' : ''}${bloqueado && !n ? ' bloqueado' : ''}">
            <div><strong>${esc(it.nome)}</strong><span class="ts-tags">${tags.join('')}</span>
                ${it.descricao ? `<p>${esc(it.descricao)}</p>` : ''}${ganhos ? `<div class="ts-ganho">${esc(ganhos)}</div>` : ''}</div>
            <div class="ts-passo" data-tipo="${prop ? 'propriedades' : 'atividades'}" data-item="${esc(it.id)}">
                <button type="button" data-passo="-1" ${jogo.ler || !n ? 'disabled' : ''} aria-label="Menos">−</button><b>${n}</b><button type="button" data-passo="1" ${podeMais ? '' : 'disabled'} aria-label="Mais">+</button>
            </div>
        </div>`;
    }
    function desenharJogo() {
        const cfg = jogo.skip.config || {};
        const d = jogo.dados;
        const fichas = minhasFichas();
        const grupos = [];
        (cfg.atividades || []).forEach(a => {
            const g = a.grupo || 'Atividades';
            let x = grupos.find(y => y.nome === g);
            if (!x) grupos.push(x = { nome: g, itens: [] });
            x.itens.push(a);
        });
        const seletorFicha = jogo.ler || jogo.teste && !fichas.length ? `<p class="ts-meta">Personagem: <strong>${esc(nomeFicha(jogo.ficha))}</strong></p>`
            : `<label class="ts-meta">Personagem <select id="ts-ficha">${fichas.map(f => `<option value="${f.id}"${f.id === jogo.ficha ? ' selected' : ''}>${esc(f.nome)}</option>`).join('')}<option value="0"${!jogo.ficha ? ' selected' : ''}>Personagem novo, sem ficha</option></select></label>`;
        corpo.innerHTML = `<div class="ts-jogo">
            <div>
                ${jogo.teste ? '<p class="camp-aviso">Você está vendo como um jogador vê. As escolhas aqui não são gravadas.</p>' : ''}
                ${!jogo.teste && jogo.ler && jogo.user === D.eu ? '<p class="camp-aviso">Este time skip está fechado: só dá para ver.</p>' : ''}
                ${seletorFicha}
                ${cfg.descricao ? `<p class="ts-texto">${esc(cfg.descricao)}</p>` : ''}
                ${cfg.parametros ? `<details><summary>Parâmetros iniciais</summary><p class="ts-texto">${esc(cfg.parametros)}</p></details>` : ''}
                ${cfg.bonus ? `<details open><summary>Bônus absolutos</summary><p class="ts-texto">${esc(cfg.bonus)}</p></details>` : ''}
                ${(cfg.ramos || []).length ? `<h4>Ramo</h4><div class="ts-ramos">
                    ${cfg.ramos.map(r => `<button type="button" class="ts-ramo${d.ramo === r.id ? ' ativo' : ''}" data-ramo="${esc(r.id)}" ${jogo.ler ? 'disabled' : ''}><strong>${esc(r.nome)}</strong><span>${esc(r.descricao)}</span></button>`).join('')}
                    <button type="button" class="ts-ramo${!d.ramo ? ' ativo' : ''}" data-ramo="" ${jogo.ler ? 'disabled' : ''}><strong>Nenhum ramo</strong><span>Só as atividades gerais.</span></button>
                </div>` : ''}
                ${grupos.map(g => `<h4>${esc(g.nome)}</h4>${g.itens.map(a => itemHtml(a, +d.atividades[a.id] || 0, false, cfg)).join('')}`).join('')}
                ${(cfg.propriedades || []).length ? `<h4>Propriedades</h4><p class="ts-meta">Bens que melhoram a vida do personagem. Quanto mais marcos, melhor (a casa, os filhos, a viagem...). Cada marco custa o preço indicado.</p>
                    ${cfg.propriedades.map(p => itemHtml(p, +d.propriedades[p.id] || 0, true, cfg)).join('')}` : ''}
            </div>
            <aside class="ts-resumo" id="ts-resumo"></aside>
        </div>`;
        desenharResumo();
    }
    function desenharResumo() {
        const cfg = jogo.skip.config || {};
        const d = jogo.dados;
        const c = TimeSkipCalc.calcular(cfg, d);
        const pct = c.escolhas ? Math.min(100, c.usadas / c.escolhas * 100) : 0;
        const totais = Object.keys(c.totais).sort((a, b) => a.localeCompare(b, 'pt-BR'));
        const caixa = $('#ts-resumo');
        const foco = document.activeElement && caixa.contains(document.activeElement) ? document.activeElement.id : null;
        if (!caixa.dataset.pronto) {
            caixa.dataset.pronto = '1';
            caixa.innerHTML = `<div id="ts-conta"></div>
                ${cfg.meses ? `<label>Salário mensal do personagem (R$)<input type="number" min="0" step="100" id="ts-salario" value="${+d.salario || 0}" ${jogo.ler ? 'disabled' : ''}></label>` : ''}
                <label>Dinheiro antes do time skip (R$)<input type="number" min="0" step="1000" id="ts-inicial" value="${+d.dinheiro_inicial || 0}" ${jogo.ler ? 'disabled' : ''}></label>
                <div id="ts-dinheiro"></div>
                <div id="ts-ganhos"></div>
                <ul class="ts-erros" id="ts-erros"></ul>
                <label>Anotações (maldição escolhida, pessoas com benefícios, NPCs...)<textarea id="ts-notas" maxlength="5000" ${jogo.ler ? 'disabled' : ''}>${esc(d.notas || '')}</textarea></label>
                ${jogo.ler || jogo.teste ? '' : '<button type="button" class="camp-btn camp-btn-primario" id="ts-gravar">Salvar escolhas</button>'}`;
        }
        $('#ts-conta').innerHTML = `<div class="ts-contador${c.usadas > c.escolhas ? ' estourou' : ''}"><b>${c.escolhas - c.usadas}</b><div>escolhas sobrando (${c.usadas} de ${c.escolhas} usadas)</div></div>
            <div class="ts-medidor"><i style="width:${pct}%"></i></div>`;
        $('#ts-dinheiro').innerHTML = `<table>
            ${cfg.meses ? `<tr><td>Salário: ${reais(c.salario_mensal)} × ${cfg.meses} meses a ${c.fator}%${c.salarios_extra ? ` + ${num(c.salarios_extra)} salário(s)` : ''}</td><td>${reais(c.renda)}</td></tr>` : ''}
            <tr><td>Gasto com propriedades</td><td>− ${reais(c.gasto)}</td></tr>
            <tr><td>Dinheiro no fim</td><td class="${c.saldo < 0 ? 'negativo' : ''}">${reais(c.saldo)}</td></tr></table>`;
        $('#ts-ganhos').innerHTML = totais.length ? `<strong>O que você ganhou</strong><table>${totais.map(k => `<tr><td>${esc(k)}</td><td>${c.totais[k] > 0 ? '+' : ''}${num(c.totais[k])}</td></tr>`).join('')}</table>` : '';
        $('#ts-erros').innerHTML = c.erros.map(x => `<li>${esc(x)}</li>`).join('');
        const g = $('#ts-gravar');
        if (g) g.disabled = c.erros.length > 0;
        if (foco && document.getElementById(foco)) document.getElementById(foco).focus();
    }
    corpo.addEventListener('click', e => {
        if (!jogo || jogo.ler) return;
        const ramo = e.target.closest('[data-ramo]');
        if (ramo) { jogo.dados.ramo = ramo.dataset.ramo; desenharJogo(); return; }
        const passo = e.target.closest('[data-passo]');
        if (passo) {
            const caixa = passo.parentElement;
            const lista = jogo.dados[caixa.dataset.tipo];
            const n = Math.max(0, (+lista[caixa.dataset.item] || 0) + +passo.dataset.passo);
            if (n) lista[caixa.dataset.item] = n; else delete lista[caixa.dataset.item];
            const rolagem = janela.scrollTop;
            desenharJogo();
            janela.scrollTop = rolagem;
            return;
        }
        if (e.target.id === 'ts-gravar') gravarJogo(e.target);
    });
    corpo.addEventListener('input', e => {
        if (!jogo) return;
        if (e.target.id === 'ts-salario') { jogo.dados.salario = Math.max(0, +e.target.value || 0); desenharResumo(); }
        if (e.target.id === 'ts-inicial') { jogo.dados.dinheiro_inicial = Math.max(0, +e.target.value || 0); desenharResumo(); }
        if (e.target.id === 'ts-notas') jogo.dados.notas = e.target.value;
    });
    corpo.addEventListener('change', e => {
        if (!jogo || e.target.id !== 'ts-ficha') return;
        jogo.ficha = +e.target.value || 0;
        const r = respostaDe(jogo.skip.id, jogo.user, jogo.ficha);
        jogo.dados = Object.assign(vazio(), r ? JSON.parse(JSON.stringify(r.dados)) : {});
        desenharJogo();
    });
    async function gravarJogo(botao) {
        botao.disabled = true;
        try {
            const j = await enviar(D.url.base + '/' + jogo.skip.id + '/resposta', 'POST', { ficha_id: jogo.ficha, dados: jogo.dados });
            respostas = respostas.filter(r => r.id !== j.resposta.id).concat([j.resposta]);
            desenharLista();
            msg('Escolhas salvas para ' + nomeFicha(jogo.ficha) + '.');
            fecharJanela();
        } catch (err) { alert(err.message); botao.disabled = false; }
    }

    /* ---------- escolhas dos jogadores (mestre) ---------- */
    function abrirRespostas(s) {
        const lista = respostas.filter(r => r.skip === s.id);
        const cfg = s.config || {};
        const ramo = id => ((cfg.ramos || []).find(r => r.id === id) || {}).nome || '—';
        abrirJanela('Escolhas · ' + s.nome);
        corpo.innerHTML = lista.length ? `<div class="ts-rolagem"><table class="ts-tabela"><thead><tr><th>Jogador</th><th>Personagem</th><th>Ramo</th><th>Escolhas</th><th>Dinheiro no fim</th><th>Ganhos</th><th></th></tr></thead><tbody>
            ${lista.map(r => {
                const c = TimeSkipCalc.calcular(cfg, r.dados);
                const ganhos = Object.keys(c.totais).map(k => `${esc(k)} ${c.totais[k] > 0 ? '+' : ''}${num(c.totais[k])}`).join(' · ');
                return `<tr><td>${esc(r.jogador)}</td><td>${esc(nomeFicha(r.ficha_id))}</td><td>${esc(ramo(r.dados.ramo))}</td><td>${c.usadas}/${c.escolhas}</td><td>${reais(c.saldo)}</td>
                    <td style="font-size:.78rem">${ganhos}${r.dados.notas ? `<div class="ts-texto">${esc(r.dados.notas)}</div>` : ''}<div class="ts-meta">${esc(r.em || '')}</div></td>
                    <td><button type="button" class="camp-btn camp-btn-pequeno" data-ver="${r.id}">Abrir</button></td></tr>`;
            }).join('')}</tbody></table></div>`
            : '<p class="ts-meta">Ninguém fez escolhas ainda.' + (s.status === 'aberto' ? '' : ' Deixe o time skip como "Aberto para escolhas" para os jogadores preencherem.') + '</p>';
        corpo.querySelectorAll('[data-ver]').forEach(b => b.addEventListener('click', () => {
            const r = respostas.find(x => x.id === +b.dataset.ver);
            abrirJogo(s, r);
        }));
    }

    /* ---------- editor de regras (mestre) ---------- */
    let ed = null; // { id, nome, status, cfg }
    let seqId = 0;
    const novoEfeito = () => ({ rotulo: '', valor: 1, a_cada: 1, limite: 0, tipo: '' });
    const configVazia = () => ({ descricao: '', escolhas: 20, meses: 0, fator: 100, parametros: '', bonus: '', bonus_efeitos: [], ramos: [], atividades: [], propriedades: [] });
    function abrirEditor(s) {
        ed = { id: s ? s.id : null, nome: s ? s.nome : 'Novo time skip', status: s ? s.status : 'rascunho',
            cfg: Object.assign(configVazia(), s ? JSON.parse(JSON.stringify(s.config || {})) : {}) };
        abrirJanela(s ? 'Regras · ' + s.nome : 'Novo time skip');
        desenharEditor();
    }
    const campo = (rot, caminho, valor, tipo, extra) => `<label>${rot}${tipo === 'area'
        ? `<textarea data-k="${caminho}" ${extra || ''}>${esc(valor)}</textarea>`
        : `<input type="${tipo || 'text'}" data-k="${caminho}" ${tipo === 'number' ? 'data-num step="any"' : ''} value="${esc(valor)}" ${extra || ''}>`}</label>`;
    const seletorRamo = (caminho, valor) => `<label>Ramo exigido<select data-k="${caminho}"><option value="">Nenhum (qualquer um)</option>${(ed.cfg.ramos || []).map(r => `<option value="${esc(r.id)}"${r.id === valor ? ' selected' : ''}>${esc(r.nome)}</option>`).join('')}</select></label>`;
    function efeitosHtml(caminho, lista, unidade) {
        return `<div class="ts-dica">Ganhos ${unidade}. "A cada" = quantas vezes até ganhar uma vez; "Limite" = teto do ganho (0 = sem teto).</div>
            ${(lista || []).map((ef, i) => `<div class="ts-ef">
                ${campo('O que ganha', `${caminho}.${i}.rotulo`, ef.rotulo, 'text', 'maxlength="80" placeholder="Ex.: Sanidade total e atual"')}
                ${campo('Valor', `${caminho}.${i}.valor`, ef.valor, 'number')}
                ${campo('A cada', `${caminho}.${i}.a_cada`, ef.a_cada, 'number', 'min="1"')}
                ${campo('Limite', `${caminho}.${i}.limite`, ef.limite, 'number', 'min="0"')}
                <label>Conta como<select data-k="${caminho}.${i}.tipo">${Object.keys(TIPOS).map(t => `<option value="${t}"${t === (ef.tipo || '') ? ' selected' : ''}>${TIPOS[t]}</option>`).join('')}</select></label>
                <button type="button" class="camp-btn camp-btn-pequeno" data-del="${caminho}.${i}" aria-label="Tirar ganho">✕</button>
            </div>`).join('')}
            <button type="button" class="camp-btn camp-btn-pequeno" data-add="${caminho}">+ Ganho</button>`;
    }
    function desenharEditor() {
        const c = ed.cfg;
        const aberto = new Set([...corpo.querySelectorAll('details[open][data-chave]')].map(x => x.dataset.chave));
        const det = (chave, titulo, html) => `<details data-chave="${chave}"${aberto.has(chave) ? ' open' : ''}><summary>${esc(titulo)}</summary>${html}</details>`;
        const rolagem = janela.scrollTop;
        corpo.innerHTML = `<div class="ts-ed">
            <div class="ts-ed-linha">
                ${campo('Nome', 'nome', ed.nome, 'text', 'maxlength="80"')}
                ${campo('Escolhas por personagem', 'cfg.escolhas', c.escolhas, 'number', 'min="0"')}
                ${campo('Meses de salário', 'cfg.meses', c.meses, 'number', 'min="0"')}
                ${campo('Salário recebido (%)', 'cfg.fator', c.fator, 'number', 'min="0" max="100"')}
            </div>
            <p class="ts-dica">O dinheiro do jogador no fim = dinheiro de antes + salário mensal × meses × % recebido + ganhos em dinheiro − propriedades. Deixe 0 meses se o time skip não paga salário.</p>
            ${campo('Descrição', 'cfg.descricao', c.descricao, 'area', 'maxlength="3000"')}
            ${campo('Parâmetros iniciais (para quem perdeu o personagem)', 'cfg.parametros', c.parametros, 'area', 'maxlength="4000"')}
            ${campo('Bônus absolutos (texto)', 'cfg.bonus', c.bonus, 'area', 'maxlength="4000"')}
            <fieldset><legend>Ganhos dos bônus absolutos</legend>${efeitosHtml('cfg.bonus_efeitos', c.bonus_efeitos, 'que todo personagem recebe')}</fieldset>

            <fieldset><legend>Ramos (${c.ramos.length})</legend>
                ${c.ramos.map((r, i) => det('r' + i, r.nome || 'Ramo sem nome', `
                    ${campo('Nome', `cfg.ramos.${i}.nome`, r.nome, 'text', 'maxlength="60"')}
                    ${campo('Descrição', `cfg.ramos.${i}.descricao`, r.descricao, 'area', 'maxlength="1500"')}
                    <button type="button" class="camp-btn camp-btn-pequeno" data-del="cfg.ramos.${i}">Tirar ramo</button>`)).join('')}
                <button type="button" class="camp-btn camp-btn-pequeno" data-add="cfg.ramos">+ Ramo</button>
            </fieldset>

            <fieldset><legend>Atividades (${c.atividades.length})</legend>
                <p class="ts-dica">Cada vez que o jogador escolhe uma atividade, ela gasta o custo em escolhas. Atividades com o mesmo grupo aparecem juntas.</p>
                ${c.atividades.map((a, i) => det('a' + i, (a.grupo ? a.grupo + ' · ' : '') + (a.nome || 'Atividade sem nome'), `
                    <div class="ts-ed-linha">
                        ${campo('Nome', `cfg.atividades.${i}.nome`, a.nome, 'text', 'maxlength="80"')}
                        ${campo('Grupo', `cfg.atividades.${i}.grupo`, a.grupo, 'text', 'maxlength="60" list="ts-grupos"')}
                        ${seletorRamo(`cfg.atividades.${i}.ramo`, a.ramo)}
                    </div>
                    <div class="ts-ed-linha">
                        ${campo('Custo (escolhas por vez)', `cfg.atividades.${i}.custo`, a.custo, 'number', 'min="1"')}
                        ${campo('Máximo de vezes (0 = sem)', `cfg.atividades.${i}.max`, a.max, 'number', 'min="0"')}
                        ${campo('Salário recebido (%) se escolher', `cfg.atividades.${i}.fator`, a.fator, 'number', 'min="0" max="100"')}
                        ${campo('Outro requisito (o mestre confere)', `cfg.atividades.${i}.requisito`, a.requisito, 'text', 'maxlength="200"')}
                    </div>
                    ${campo('Descrição', `cfg.atividades.${i}.descricao`, a.descricao, 'area', 'maxlength="2000"')}
                    ${efeitosHtml(`cfg.atividades.${i}.efeitos`, a.efeitos, 'por vez escolhida')}
                    <div><button type="button" class="camp-btn camp-btn-pequeno" data-del="cfg.atividades.${i}">Tirar atividade</button></div>`)).join('')}
                <button type="button" class="camp-btn camp-btn-pequeno" data-add="cfg.atividades">+ Atividade</button>
                <datalist id="ts-grupos">${[...new Set(c.atividades.map(a => a.grupo).filter(Boolean))].map(g => `<option value="${esc(g)}">`).join('')}</datalist>
            </fieldset>

            <fieldset><legend>Propriedades (${c.propriedades.length})</legend>
                <p class="ts-dica">Compradas por marco, com o dinheiro do personagem. Os ganhos valem por marco.</p>
                ${c.propriedades.map((p, i) => det('p' + i, p.nome || 'Propriedade sem nome', `
                    <div class="ts-ed-linha">
                        ${campo('Nome', `cfg.propriedades.${i}.nome`, p.nome, 'text', 'maxlength="80"')}
                        ${campo('Preço por marco (R$)', `cfg.propriedades.${i}.preco`, p.preco, 'number', 'min="0"')}
                        ${campo('Máximo de marcos (0 = sem)', `cfg.propriedades.${i}.max`, p.max, 'number', 'min="0"')}
                        ${seletorRamo(`cfg.propriedades.${i}.ramo`, p.ramo)}
                        ${campo('Outro requisito', `cfg.propriedades.${i}.requisito`, p.requisito, 'text', 'maxlength="200"')}
                    </div>
                    ${campo('Descrição', `cfg.propriedades.${i}.descricao`, p.descricao, 'area', 'maxlength="2000"')}
                    ${efeitosHtml(`cfg.propriedades.${i}.efeitos`, p.efeitos, 'por marco')}
                    <div><button type="button" class="camp-btn camp-btn-pequeno" data-del="cfg.propriedades.${i}">Tirar propriedade</button></div>`)).join('')}
                <button type="button" class="camp-btn camp-btn-pequeno" data-add="cfg.propriedades">+ Propriedade</button>
            </fieldset>

            <div class="ts-salvar-ed">
                <button type="button" class="camp-btn camp-btn-primario" id="ts-ed-salvar">Salvar regras</button>
                <span class="ts-dica">Quem já fez escolhas continua com elas; o que deixar de existir some das escolhas.</span>
            </div>
        </div>`;
        janela.scrollTop = rolagem;
    }
    function caminhoAte(caminho) {
        const partes = caminho.split('.');
        let alvo = ed;
        for (let i = 0; i < partes.length - 1; i++) alvo = alvo[partes[i]];
        return [alvo, partes[partes.length - 1]];
    }
    corpo.addEventListener('input', e => {
        if (!ed || !e.target.dataset.k) return;
        const [alvo, chave] = caminhoAte(e.target.dataset.k);
        alvo[chave] = e.target.hasAttribute('data-num') ? (+e.target.value || 0) : e.target.value;
        const det = /^cfg\.\w+\.\d+\.nome$/.test(e.target.dataset.k) && e.target.closest('details');
        if (det) det.querySelector('summary').textContent = e.target.value || 'Sem nome';
    });
    corpo.addEventListener('change', e => {
        if (!ed || !e.target.dataset.k || e.target.tagName !== 'SELECT') return;
        const [alvo, chave] = caminhoAte(e.target.dataset.k);
        alvo[chave] = e.target.value;
    });
    corpo.addEventListener('click', async e => {
        if (!ed) return;
        const add = e.target.closest('[data-add]');
        if (add) {
            const [alvo, chave] = caminhoAte(add.dataset.add);
            const lista = alvo[chave] = alvo[chave] || [];
            const tipo = add.dataset.add.split('.').pop();
            const id = 'n' + Date.now().toString(36) + (++seqId); // já com id: um ramo novo pode ser exigido antes de salvar
            if (tipo === 'ramos') lista.push({ id, nome: 'Novo ramo', descricao: '' });
            else if (tipo === 'atividades') lista.push({ id, grupo: (lista[lista.length - 1] || {}).grupo || 'Atividades gerais', nome: 'Nova atividade', descricao: '', ramo: '', custo: 1, max: 0, requisito: '', fator: 0, efeitos: [] });
            else if (tipo === 'propriedades') lista.push({ id, nome: 'Nova propriedade', descricao: '', preco: 0, ramo: '', max: 0, requisito: '', efeitos: [] });
            else lista.push(novoEfeito());
            desenharEditor();
            const novos = corpo.querySelectorAll(`details[data-chave^="${tipo[0]}"]`);
            if (tipo !== 'efeitos' && tipo !== 'bonus_efeitos' && novos.length) { const ult = novos[novos.length - 1]; ult.open = true; ult.scrollIntoView({ block: 'center' }); }
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            const [alvo, chave] = caminhoAte(del.dataset.del);
            alvo.splice(+chave, 1);
            desenharEditor();
            return;
        }
        if (e.target.id === 'ts-ed-salvar') {
            e.target.disabled = true;
            try {
                const j = await enviar(D.url.salvar, 'POST', { id: ed.id, nome: ed.nome, status: ed.status, config: ed.cfg });
                const i = skips.findIndex(x => x.id === j.skip.id);
                if (i >= 0) skips[i] = j.skip; else skips.unshift(j.skip);
                desenharLista();
                msg('Regras salvas.');
                fecharJanela();
            } catch (err) { alert(err.message); e.target.disabled = false; }
        }
    });

    /* ---------- criar ---------- */
    if (D.mestre) {
        $('#ts-novo').addEventListener('click', () => abrirEditor(null));
        const sel = $('#ts-modelo');
        sel.innerHTML += D.modelos.map(m => `<option value="${esc(m.id)}">${esc(m.nome)}</option>`).join('');
        sel.addEventListener('change', async () => {
            const m = D.modelos.find(x => x.id === sel.value);
            sel.value = '';
            if (!m) return;
            try {
                const j = await enviar(D.url.salvar, 'POST', { nome: m.nome, modelo: m.id });
                skips.unshift(j.skip);
                desenharLista();
                msg('"' + m.nome + '" criado como rascunho. Edite as regras à vontade e abra para os jogadores.');
                abrirEditor(j.skip);
            } catch (err) { msg(err.message, true); }
        });
    }
    desenharLista();
})();
