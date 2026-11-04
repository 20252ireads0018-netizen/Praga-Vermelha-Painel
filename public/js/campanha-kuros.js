/**
 * Página de Kuros da campanha: lista com filtros, editor com a ficha calculada ao vivo (KurosCalc, regras em
 * config/kuros.php), Kuros prontos do livro, entrega a jogadores e a vida/anotações de cada Kuro.
 */
(function(){
    const el = document.getElementById("kr-dados");
    if(!el) return;
    const D = JSON.parse(el.textContent);
    const CFG = D.cfg;
    const $ = s => document.querySelector(s);
    const esc = s => String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
    const cap = t => t ? t.charAt(0).toUpperCase() + t.slice(1) : t;
    const fmt = n => (n > 0 ? "+" : "") + n;
    // texto da habilidade com os valores já calculados pelo interpretador (KurosCalc.interpretar)
    const textoCalc = (partes, n) => partes.map(p => p.v == null ? esc(p.s)
        : esc(p.s) + ' <b class="kr-calc" title="Calculado para ' + n + ' escolha' + (n === 1 ? '' : 's') + '">= ' + esc(p.v) + '</b>').join("");
    // valor com o selo de cada bônus que veio de habilidade (ex.: Carisma 45 · +30 Conhecedor)
    const selos = lista => (lista || []).map(b => ' <span class="kr-selo">' + esc((b.texto || fmt(b.v)) + ' ' + b.de) + '</span>').join("");
    let kuros = D.kuros.slice();

    function msg(texto, erro){
        const m = $("#kr-msg");
        m.textContent = texto;
        m.hidden = !texto;
        m.classList.toggle("erro", !!erro);
        clearTimeout(msg.t);
        msg.t = setTimeout(() => { m.hidden = true; }, 4000);
    }
    async function enviar(url, metodo, dados){
        const r = await fetch(url, { method: metodo, credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": D.csrf },
            body: dados ? JSON.stringify(dados) : undefined });
        const j = await r.json().catch(() => ({}));
        if(!r.ok || j.ok === false) throw new Error(j.erro || j.message || ("Erro " + r.status));
        return j;
    }
    const nomeMembro = id => (D.membros.find(m => m.id === id) || {}).nome || "um jogador";

    /* ---------- lista e filtros ---------- */
    function preencherFiltros(){
        const tipos = [...new Set(kuros.map(k => +k.dados.tipo || 1))].sort((a, b) => a - b);
        const daemons = [...new Set(kuros.map(k => k.dados.daemon).filter(Boolean))].sort();
        const pastas = [...new Set(kuros.map(k => k.dados.pasta).filter(Boolean))].sort();
        const opc = (sel, itens, rot) => { const s = $(sel); if(!s) return; const v = s.value; s.innerHTML = s.options[0].outerHTML + itens.map(i => '<option value="' + esc(i) + '">' + esc(rot(i)) + '</option>').join(""); s.value = itens.map(String).includes(v) ? v : ""; };
        opc("#kr-filtro-tipo", tipos, t => "Tipo " + t);
        opc("#kr-filtro-daemon", daemons, d => d);
        opc("#kr-filtro-pasta", pastas, p => p);
    }
    function desenharLista(){
        const busca = ($("#kr-busca").value || "").toLowerCase();
        const tipo = $("#kr-filtro-tipo").value, daemon = $("#kr-filtro-daemon").value, pasta = $("#kr-filtro-pasta") ? $("#kr-filtro-pasta").value : "";
        const lista = kuros.filter(k => {
            const d = k.dados;
            const habs = Object.keys(d.habilidades || {}).map(h => (CFG.habilidades[h] || {}).nome || h).join(" ");
            return (!tipo || String(d.tipo || 1) === tipo) && (!daemon || d.daemon === daemon) && (!pasta || d.pasta === pasta)
                && (!busca || (k.nome + " " + (d.daemon || "") + " " + habs + " " + (d.pasta || "")).toLowerCase().includes(busca));
        });
        $("#kr-lista").innerHTML = lista.map(k => {
            const f = KurosCalc.calcular(k.dados, CFG);
            const pv = k.pv_atual == null ? f.pv : k.pv_atual;
            return '<article class="kr-cartao"><h3>' + esc(k.nome) + '</h3>' +
                '<span class="kr-meta">Tipo ' + f.tipo + (k.dados.daemon ? " · " + esc(k.dados.daemon) : "") + (k.dados.pasta ? " · " + esc(k.dados.pasta) : "") + ' · ' + esc(f.tamanho) + '</span>' +
                '<span class="kr-meta">PV ' + pv + '/' + f.pv + ' · Armadura ' + f.armadura + ' · ' + f.habilidades.length + ' habilidade(s)</span>' +
                (f.avisos.length && D.mestre ? '<span class="kr-req">' + f.avisos.length + ' aviso(s) na ficha</span>' : '') +
                (D.mestre ? '<label class="kr-dono">Com: <select data-dar="' + k.id + '"><option value="">só o mestre</option>' +
                    D.membros.map(m => '<option value="' + m.id + '"' + (k.dono === m.id ? " selected" : "") + '>' + esc(m.nome) + '</option>').join("") + '</select></label>' : '') +
                '<div class="kr-botoes"><button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" data-ver="' + k.id + '">Ver ficha</button>' +
                (D.mestre ? '<button type="button" class="camp-btn camp-btn-pequeno" data-editar="' + k.id + '">Editar</button>' +
                    '<button type="button" class="camp-btn camp-btn-pequeno" data-duplicar="' + k.id + '">Duplicar</button>' +
                    '<button type="button" class="camp-btn camp-btn-pequeno" data-excluir="' + k.id + '">Excluir</button>' : '') +
                '</div></article>';
        }).join("") || '<p class="camp-vazio">' + (kuros.length ? "Nenhum Kuro com esses filtros." : (D.mestre ? "Nenhum Kuro ainda. Crie um ou copie um pronto do livro." : "O mestre ainda não entregou nenhum Kuro a você.")) + '</p>';
    }
    function atualizarTudo(){ preencherFiltros(); desenharLista(); }

    /* ---------- ficha calculada (com os botões de rolar) ---------- */
    let fichaAtual = null; // { nome, f } da ficha na janela: os botões de rolar leem daqui
    let historico = [];    // rolagens desta janela, a mais nova primeiro (só quem rolou vê)
    const dadoTexto = dn => dn.dados + "d" + dn.lados + (dn.extra ? " + " + dn.extra : "") + (dn.mult > 1 ? " ×" + dn.mult : "") +
        " (" + dn.tipo + ")" + (dn.amaldicoado ? " + " + dn.amaldicoado + " amaldiçoado" : "");
    const valorTeste = (f, k) => f.atributos[k] + (f.testes[k] || []).reduce((s, t) => s + (+t.v || 0), 0);

    function fichaHtml(nome, d, f){
        fichaAtual = { nome: nome || "Kuro", f };
        const testes = k => (f.testes[k] || []).length ? '<small class="kr-testes">testes' + selos(f.testes[k]) + '</small>' : '';
        const chip = (tipo, k, rot, valor, extra) => '<button type="button" class="kr-rolar" data-rolar="' + tipo + ':' + k + '" title="Rolar d100 − ' + esc(valor) + '">' +
            '<span>' + esc(rot) + '</span><b>' + esc(valor) + '</b>' + (extra || '') + '</button>';
        const lista = (titulo, itens) => itens.length ? '<div class="kr-linha-ficha"><span>' + titulo + '</span><b>' + esc(itens.map(cap).join(", ")) + '</b></div>' : '';
        const escolhas = '<div class="kr-escolhas"><span class="' + (f.aprim.usados > f.aprim.total ? "estourou" : "") + '">Aprimoramentos ' + f.aprim.usados + '/' + f.aprim.total + '</span>' +
            '<span class="' + (f.habil.usados > f.habil.total ? "estourou" : "") + '">Habilidades ' + f.habil.usados + '/' + f.habil.total + '</span></div>';
        const texto = (rot, t) => t ? '<details class="kr-texto"><summary>' + rot + '</summary><p>' + esc(t) + '</p></details>' : '';
        return '<div class="kr-ficha"><h3>' + esc(nome || "Kuro sem nome") + '</h3>' +
            '<div class="kr-meta">Tipo ' + f.tipo + (f.circulos ? ' · ' + f.circulos + ' círculo(s)' : '') + (d.daemon ? ' · ' + esc(d.daemon) : '') + ' · ' + esc(f.tamanho) + '</div>' +
            escolhas +
            (f.avisos.length ? '<ul class="kr-avisos">' + f.avisos.map(a => '<li>' + esc(a) + '</li>').join("") + '</ul>' : '') +
            '<div class="kr-numeros"><div><span>PV</span><b>' + f.pv + '</b></div><div><span>Armadura</span><b>' + f.armadura + '</b></div>' +
            '<div><span>Deslocamento</span><b>' + f.deslocamento + (f.voo ? ' · voo ' + f.voo : '') + '</b></div></div>' +
            '<div class="kr-linha-ficha"><span>Sentidos</span><b>' + esc(f.sentidos.join(", ")) + '</b></div>' +
            lista("Resistências", f.resistencias) + lista("Fraquezas", f.fraquezas) + lista("Imunidades", f.imunidades) +

            '<div class="kr-sub">Dano <small>clique para rolar</small></div>' +
            '<div class="kr-danos">' + f.danos.map((dn, i) => '<button type="button" class="kr-rolar kr-rolar-dano" data-rolar="dano:' + i + '"><span>' + esc(dn.nome) + '</span><b>' + esc(dadoTexto(dn)) + '</b></button>').join("") + '</div>' +
            (f.critico ? '<div class="kr-linha-ficha"><span>Crítico</span><b>margem +' + f.critico.margem + ' · multiplicador +' + f.critico.mult + ' <span class="kr-selo">Lacerador</span></b></div>' : '') +

            '<div class="kr-sub">Testes <small>d100 − valor · clique para rolar</small></div>' +
            '<div class="kr-chips-rolar">' + Object.keys(f.atributos).map(k => chip("atr", k, CFG.atributos[k] || k, valorTeste(f, k), selos(f.bonus.atributos[k]) + testes(k))).join("") + '</div>' +
            '<div class="kr-chips-rolar">' + Object.keys(f.rolagens).map(k => chip("rol", k, CFG.rolagens[k] || k, fmt(f.rolagens[k]), selos(f.bonus.rolagens[k]))).join("") + '</div>' +
            '<div class="kr-rolou" aria-live="polite">' + historicoHtml() + '</div>' +

            (f.efeitos.length ? '<div class="kr-sub">Efeitos das habilidades</div>' + f.efeitos.map(e => '<div class="kr-linha-ficha"><span>' + esc(e.rot) + '</span><b>' + esc(e.valor) + ' <span class="kr-selo">' + esc(e.de) + '</span></b></div>').join("") : '') +
            '<div class="kr-sub">Habilidades sobrenaturais</div>' +
            (f.habilidades.map(h => '<details class="kr-habil-item" open><summary>' + esc(h.nome) + (h.escolhas > 1 ? ' ×' + h.escolhas : '') + '</summary><p>' + textoCalc(h.partes, h.escolhas) + '</p></details>').join("") || '<p class="kr-meta">Nenhuma.</p>') +
            texto("Descrição", d.descricao) + texto("Comportamento", d.comportamento) + texto("Surgimento", d.surgimento) +
            (D.mestre ? texto("Notas do mestre", d.notas_mestre) : '') +
            '</div>';
    }

    /* ---------- rolagens do Kuro: no navegador, só quem rola vê ---------- */
    const aleatorio = n => { const a = new Uint32Array(1); crypto.getRandomValues(a); return 1 + (a[0] % n); };
    function historicoHtml(){
        if(!historico.length) return '<p class="kr-meta">As rolagens aparecem aqui e só você vê.</p>';
        const linha = (r, i) => '<div class="kr-rolagem' + (i ? '' : ' nova') + '"><span>' + esc(r.rot) + '</span><span class="kr-conta">' + esc(r.conta) + '</span><b>' + esc(r.total) + '</b>' +
            '<button type="button" class="kr-tirar" data-limpar="' + i + '" title="Apagar esta rolagem" aria-label="Apagar esta rolagem">×</button></div>';
        return '<div class="kr-rolou-cab"><span>Rolagens (' + historico.length + ')</span><button type="button" class="camp-btn camp-btn-pequeno" data-limpar="todas">Limpar rolagens</button></div>' +
            linha(historico[0], 0) + (historico.length > 1 ? '<div class="kr-rolou-antigas">' + historico.slice(1).map((r, i) => linha(r, i + 1)).join("") + '</div>' : '');
    }
    function limparRolagens(qual){
        if(qual === "todas") historico = [];
        else historico.splice(+qual, 1);
        corpo.querySelectorAll(".kr-rolou").forEach(el => { el.innerHTML = historicoHtml(); });
    }
    function rolar(alvo){
        if(!fichaAtual) return;
        const f = fichaAtual.f;
        const [tipo, k] = alvo.split(":");
        let r;
        if(tipo === "dano"){
            const dn = f.danos[+k];
            if(!dn) return;
            const x = KurosCalc.rolarDano(dn, aleatorio);
            r = { rot: dn.nome, conta: "[" + x.dados.join(", ") + "]" + (dn.extra ? " + " + dn.extra : "") + (dn.mult > 1 ? " ×" + dn.mult : ""),
                total: x.total + " " + dn.tipo + (x.amaldicoado ? " + " + x.amaldicoado + " amaldiçoado" : "") };
        } else {
            const valor = tipo === "atr" ? valorTeste(f, k) : f.rolagens[k];
            const x = KurosCalc.rolarTeste(valor, aleatorio);
            const falha = tipo === "atr" && (f.testes[k] || []).some(t => t.texto);
            r = { rot: (tipo === "atr" ? CFG.atributos[k] : CFG.rolagens[k]) || k, conta: "d100 " + x.d100 + " − (" + fmt(valor) + ")", total: falha ? "falha automática" : String(x.total) };
        }
        historico.unshift(r);
        historico = historico.slice(0, 20);
        corpo.querySelectorAll(".kr-rolou").forEach(el => { el.innerHTML = historicoHtml(); });
    }

    /* ---------- janela ---------- */
    const janela = $("#kr-janela"), corpo = $("#kr-janela-corpo");
    function abrir(titulo, html){ historico = []; $("#kr-janela-titulo").textContent = titulo; corpo.innerHTML = html; janela.hidden = false; }
    function fechar(){ janela.hidden = true; corpo.innerHTML = ""; historico = []; fichaAtual = null; }
    // botões de rolar da ficha (na ficha e na prévia do editor), antes dos cliques de cada tela
    corpo.addEventListener("click", e => {
        const b = e.target.closest("[data-rolar], [data-limpar]");
        if(!b) return;
        e.stopPropagation();
        if(b.dataset.rolar) rolar(b.dataset.rolar); else limparRolagens(b.dataset.limpar);
    }, true);
    $("#kr-fechar").addEventListener("click", fechar);
    janela.addEventListener("click", e => { if(e.target === janela) fechar(); });
    document.addEventListener("keydown", e => { if(e.key === "Escape" && !janela.hidden) fechar(); });

    /* ---------- ver: ficha, vida atual e anotações ---------- */
    function ver(k){
        const f = KurosCalc.calcular(k.dados, CFG);
        const pv = k.pv_atual == null ? f.pv : k.pv_atual;
        abrir(k.nome, '<div class="kr-estado"><div class="kr-vida"><label>Vida atual <input type="number" id="kr-pv" value="' + pv + '"></label> / ' + f.pv +
            '<button type="button" class="camp-btn camp-btn-pequeno" data-pv="-1">−1</button><button type="button" class="camp-btn camp-btn-pequeno" data-pv="1">+1</button>' +
            '<button type="button" class="camp-btn camp-btn-pequeno" data-pv="cheia">Vida cheia</button>' +
            '<button type="button" class="camp-btn camp-btn-pequeno camp-btn-primario" id="kr-salvar-estado">Salvar vida e anotações</button></div>' +
            '<label class="kr-meta">Anotações<textarea id="kr-notas" rows="2" maxlength="5000">' + esc(k.notas) + '</textarea></label></div>' + fichaHtml(k.nome, k.dados, f));
        corpo.querySelectorAll("[data-pv]").forEach(b => b.addEventListener("click", () => {
            const i = $("#kr-pv");
            i.value = b.dataset.pv === "cheia" ? f.pv : (+i.value || 0) + (+b.dataset.pv);
        }));
        $("#kr-salvar-estado").addEventListener("click", async () => {
            try {
                const j = await enviar(D.url.base + "/" + k.id + "/estado", "POST", { pv_atual: +$("#kr-pv").value || 0, notas: $("#kr-notas").value });
                Object.assign(k, j.kuro);
                desenharLista();
                msg("Salvo.");
            } catch(e){ msg(e.message, true); }
        });
    }

    /* ---------- editor (mestre) ---------- */
    function editor(k){
        const d = JSON.parse(JSON.stringify(k.dados || {}));
        ["aprim", "atributos", "rolagens", "habilidades"].forEach(c => { if(!d[c] || Array.isArray(d[c])) d[c] = {}; });
        ["resistencias", "fraquezas", "sentidos"].forEach(c => { if(!Array.isArray(d[c])) d[c] = []; });
        d.tipo = d.tipo || 1; d.circulos = d.circulos || 0; d.tamanho = d.tamanho || 0;
        let nome = k.nome || "";
        let buscaHab = "";
        let aba = "aprim"; // aprim | habilidades | defesas | textos
        const passo = (grupo, chave, valor, min) => '<span class="kr-passo"><button type="button" data-menos="' + grupo + ':' + chave + '" aria-label="Diminuir">−</button><b>' + valor + '</b><button type="button" data-mais="' + grupo + ':' + chave + '" aria-label="Aumentar">+</button></span>';
        const cont = (rot, controle, dica) => '<div class="kr-cont"' + (dica ? ' title="' + esc(dica) + '"' : '') + '><span>' + esc(rot) + '</span>' + controle + '</div>';
        const chips = (lst, rot) => '<h4>' + rot + '</h4><div class="kr-chips">' + CFG.tipos_dano.map(t => '<button type="button" class="kr-chip' + (d[lst].includes(t) ? " ativo" : "") + '" data-lista="' + lst + ':' + t + '">' + esc(cap(t)) + '</button>').join("") + '</div>';

        function abaAprim(f){
            const vezesAtrib = Object.values(d.atributos).reduce((s, v) => s + (+v || 0), 0);
            const vezesRol = Object.values(d.rolagens).reduce((s, v) => s + (+v || 0), 0);
            return '<div class="kr-grade">' + Object.keys(CFG.aprimoramentos).map(c => cont(CFG.aprimoramentos[c].nome, passo("aprim", c, d.aprim[c] || 0), CFG.aprimoramentos[c].texto)).join("") + '</div>' +
                (d.aprim.pvs || d.aprim.armadura || d.aprim.tamanho ? '<div class="kr-grade">' +
                    (d.aprim.pvs ? '<label class="kr-escolha">Cada PVs Extra<select data-campo="modo_pvs"><option value="con"' + (d.modo_pvs !== "tipo" ? " selected" : "") + '>+ Constituição</option><option value="tipo"' + (d.modo_pvs === "tipo" ? " selected" : "") + '>+ tipo × 10</option></select></label>' : '') +
                    (d.aprim.armadura ? '<label class="kr-escolha">Cada Armadura extra<select data-campo="modo_armadura"><option value="con"' + (d.modo_armadura !== "tipo" ? " selected" : "") + '>+ 1/4 da Constituição</option><option value="tipo"' + (d.modo_armadura === "tipo" ? " selected" : "") + '>+ tipo × 5</option></select></label>' : '') +
                    (d.aprim.tamanho ? cont("Categoria de tamanho", passo("tam", "x", fmt(d.tamanho))) : '') + '</div>' : '') +
                (d.aprim.atributo ? '<h4>Aumento de atributo: +40 cada <span>' + vezesAtrib + '/' + (d.aprim.atributo || 0) + '</span></h4><div class="kr-grade kr-grade-3">' +
                    Object.keys(CFG.atributos).map(a => cont(CFG.atributos[a], passo("atributos", a, d.atributos[a] || 0))).join("") + '</div>' : '') +
                (d.aprim.rolagem ? '<h4>Rolagem poderosa: +tipo × 10 cada <span>' + vezesRol + '/' + 2 * (d.aprim.rolagem || 0) + '</span></h4><div class="kr-grade kr-grade-3">' +
                    Object.keys(CFG.rolagens).map(r => cont(CFG.rolagens[r], passo("rolagens", r, d.rolagens[r] || 0))).join("") + '</div>' : '');
        }
        function abaHabilidades(f){
            const habs = Object.keys(CFG.habilidades).filter(h => !buscaHab || (CFG.habilidades[h].nome + " " + CFG.habilidades[h].texto).toLowerCase().includes(buscaHab));
            habs.sort((a, b) => (d.habilidades[b] ? 1 : 0) - (d.habilidades[a] ? 1 : 0));
            const falta = h => (f.habilidades.find(x => x.chave === h) || {}).faltas || [];
            return '<input type="search" data-campo="busca-hab" placeholder="Pesquisar habilidade…" value="' + esc(buscaHab) + '" class="kr-busca-hab">' +
                '<div class="kr-habs">' + habs.map(h => {
                    const def = CFG.habilidades[h], n = d.habilidades[h] || 0;
                    return '<div class="kr-hab' + (n ? " tem" : "") + '"><div class="kr-hab-cab"><strong>' + esc(def.nome) + '</strong>' + passo("habilidades", h, n) + '</div>' +
                        (n && falta(h).length ? '<span class="kr-req">Falta: ' + esc(falta(h).join(", ")) + '</span>' : '') + '<p>' + textoCalc(KurosCalc.interpretar(def.texto, n || 1, f.tipo, CFG), n || 1) + '</p></div>';
                }).join("") + '</div>';
        }
        function abaDefesas(){
            return chips("resistencias", "Resistências") + chips("fraquezas", "Fraquezas") +
                '<div class="kr-linha"><label>Sentidos extras<input type="text" data-campo="sentidos" value="' + esc(d.sentidos.join(", ")) + '" placeholder="Eco-localização, Visão no escuro"></label>' +
                '<label>Tipo do ataque comum<select data-campo="tipo_dano">' + CFG.tipos_dano.map(t => '<option' + ((d.tipo_dano || "cortante") === t ? " selected" : "") + '>' + t + '</option>').join("") + '</select></label></div>';
        }
        function abaTextos(){
            return '<label>Descrição / aparência<textarea data-campo="descricao" maxlength="3000">' + esc(d.descricao || "") + '</textarea></label>' +
                '<label>Comportamento<textarea data-campo="comportamento" maxlength="2000">' + esc(d.comportamento || "") + '</textarea></label>' +
                '<label>Surgimento<textarea data-campo="surgimento" maxlength="2000">' + esc(d.surgimento || "") + '</textarea></label>' +
                '<label>Notas do mestre<textarea data-campo="notas_mestre" maxlength="3000">' + esc(d.notas_mestre || "") + '</textarea></label>';
        }

        function form(){
            const f = KurosCalc.calcular(d, CFG);
            const contagem = (u, t) => ' <span class="' + (u > t ? "estourou" : (u === t ? "cheio" : "")) + '">' + u + '/' + t + '</span>';
            const abas = [["aprim", "Aprimoramentos" + contagem(f.aprim.usados, f.aprim.total)], ["habilidades", "Habilidades" + contagem(f.habil.usados, f.habil.total)],
                ["defesas", "Defesas e ataque"], ["textos", "Textos e notas"]];
            const conteudo = { aprim: abaAprim, habilidades: abaHabilidades, defesas: abaDefesas, textos: abaTextos }[aba](f);
            return '<div class="kr-editor"><div class="kr-form">' +
                // sempre à vista: identidade do Kuro e o Salvar
                '<div class="kr-topo-form">' +
                    '<label class="kr-nome">Nome<input type="text" data-campo="nome" maxlength="80" value="' + esc(nome) + '"></label>' +
                    '<div class="kr-campo"><span>Tipo</span>' + passo("base", "tipo", d.tipo) + '</div>' +
                    '<div class="kr-campo"><span>Círculos</span>' + passo("base", "circulos", d.circulos) + '</div>' +
                    '<label>Daemon<input type="text" list="kr-daemons" data-campo="daemon" maxlength="40" value="' + esc(d.daemon || "") + '"></label>' +
                    '<label>Pasta<input type="text" data-campo="pasta" maxlength="60" value="' + esc(d.pasta || "") + '" placeholder="Ex.: Sessão 3"></label>' +
                    '<button type="button" class="camp-btn camp-btn-primario" id="kr-salvar">Salvar Kuro</button>' +
                '</div>' +
                '<datalist id="kr-daemons">' + CFG.daemons.map(x => '<option value="' + esc(x) + '">').join("") + '</datalist>' +
                '<div class="kr-abas" role="tablist">' + abas.map(([id, rot]) => '<button type="button" role="tab" data-aba="' + id + '" aria-selected="' + (aba === id) + '"' + (aba === id ? ' class="ativa"' : '') + '>' + rot + '</button>').join("") + '</div>' +
                '<div class="kr-aba" role="tabpanel">' + conteudo + '</div>' +
                '</div><div id="kr-previa">' + fichaHtml(nome, d, f) + '</div></div>';
        }
        function redesenhar(foco){
            const rolagem = corpo.querySelector(".kr-habs") ? corpo.querySelector(".kr-habs").scrollTop : 0;
            corpo.innerHTML = form();
            const habs = corpo.querySelector(".kr-habs");
            if(habs) habs.scrollTop = rolagem;
            if(foco){ const i = corpo.querySelector('[data-campo="' + foco + '"]'); if(i){ i.focus(); if(i.setSelectionRange && i.type !== "number") i.setSelectionRange(i.value.length, i.value.length); } }
        }
        function previa(){ $("#kr-previa").innerHTML = fichaHtml(nome, d, KurosCalc.calcular(d, CFG)); }

        abrir(k.id ? "Editar Kuro" : "Novo Kuro", form());
        corpo.oninput = e => {
            const c = e.target.dataset.campo;
            if(!c) return;
            const v = e.target.value;
            if(c === "nome") nome = v;
            else if(c === "busca-hab"){ buscaHab = v.toLowerCase(); redesenhar("busca-hab"); return; }
            else if(c === "sentidos") d.sentidos = v.split(",").map(s => s.trim()).filter(Boolean);
            else if(c === "tipo" || c === "circulos"){ d[c] = Math.max(c === "tipo" ? 1 : 0, Math.round(+v || 0)); redesenhar(c); return; }
            else d[c] = v;
            previa();
        };
        corpo.onchange = e => { const c = e.target.dataset.campo; if(c === "modo_pvs" || c === "modo_armadura" || c === "tipo_dano"){ d[c] = e.target.value; previa(); } };
        corpo.onclick = async e => {
            const b = e.target.closest("button");
            if(!b) return;
            const muda = (alvo, delta) => {
                const [g, chave] = alvo.split(":");
                if(g === "tam"){ d.tamanho = Math.max(-3, Math.min(3, d.tamanho + delta)); }
                else if(g === "base"){ d[chave] = chave === "tipo" ? Math.max(1, Math.min(9, d.tipo + delta)) : Math.max(0, Math.min(999, d.circulos + delta)); }
                else { d[g][chave] = Math.max(0, (d[g][chave] || 0) + delta); if(!d[g][chave]) delete d[g][chave]; }
                redesenhar();
            };
            if(b.dataset.aba){ aba = b.dataset.aba; redesenhar(); }
            else if(b.dataset.mais) muda(b.dataset.mais, 1);
            else if(b.dataset.menos) muda(b.dataset.menos, -1);
            else if(b.dataset.lista){
                const [lst, t] = b.dataset.lista.split(":");
                const outra = lst === "resistencias" ? "fraquezas" : "resistencias";
                const i = d[lst].indexOf(t);
                if(i >= 0) d[lst].splice(i, 1); else { d[lst].push(t); d[outra] = d[outra].filter(x => x !== t); }
                redesenhar();
            } else if(b.id === "kr-salvar"){
                if(!nome.trim()){ msg("Dê um nome ao Kuro.", true); return; }
                b.disabled = true;
                try {
                    const j = await enviar(D.url.salvar, "POST", { id: k.id || null, nome: nome.trim(), dados: d });
                    const i = kuros.findIndex(x => x.id === j.kuro.id);
                    if(i >= 0) kuros[i] = j.kuro; else kuros.push(j.kuro);
                    kuros.sort((a, b) => a.nome.localeCompare(b.nome));
                    atualizarTudo();
                    fechar();
                    msg("Kuro salvo.");
                } catch(err){ msg(err.message, true); b.disabled = false; }
            }
        };
    }

    /* ---------- ações da lista ---------- */
    $("#kr-lista").addEventListener("click", async e => {
        const b = e.target.closest("button");
        if(!b) return;
        const id = +(b.dataset.ver || b.dataset.editar || b.dataset.duplicar || b.dataset.excluir || 0);
        const k = kuros.find(x => x.id === id);
        if(!k) return;
        if(b.dataset.ver) ver(k);
        else if(b.dataset.editar) editor(k);
        else if(b.dataset.duplicar) editor({ id: null, nome: k.nome + " (cópia)", dados: k.dados });
        else if(b.dataset.excluir){
            if(!confirm("Excluir o Kuro " + k.nome + "?")) return;
            try { await enviar(D.url.base + "/" + k.id, "DELETE"); kuros = kuros.filter(x => x.id !== k.id); atualizarTudo(); msg("Kuro excluído."); }
            catch(err){ msg(err.message, true); }
        }
    });
    $("#kr-lista").addEventListener("change", async e => {
        const s = e.target.closest("[data-dar]");
        if(!s) return;
        const k = kuros.find(x => x.id === +s.dataset.dar);
        try {
            const j = await enviar(D.url.base + "/" + k.id + "/dar", "POST", { user_id: s.value ? +s.value : null });
            Object.assign(k, j.kuro);
            msg(k.dono ? k.nome + " agora está com " + nomeMembro(k.dono) + "." : k.nome + " voltou a ser só do mestre.");
        } catch(err){ msg(err.message, true); }
    });
    ["#kr-busca", "#kr-filtro-tipo", "#kr-filtro-daemon", "#kr-filtro-pasta"].forEach(s => { const i = $(s); if(i) i.addEventListener("input", desenharLista); });

    if(D.mestre){
        $("#kr-novo").addEventListener("click", () => editor({ id: null, nome: "", dados: { tipo: 1 } }));
        const pronto = $("#kr-pronto");
        pronto.innerHTML += CFG.prontos.map((p, i) => '<option value="' + i + '">' + esc(p.nome) + ' (T' + p.tipo + ', ' + esc(p.daemon) + ')</option>').join("");
        pronto.addEventListener("change", () => {
            const p = CFG.prontos[+pronto.value];
            pronto.value = "";
            if(p) editor({ id: null, nome: p.nome, dados: KurosCalc.doPronto(p) });
        });
    }
    atualizarTudo();
})();
