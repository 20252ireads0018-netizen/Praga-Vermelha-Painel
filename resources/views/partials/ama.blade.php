{{-- Estudos na AMA (config/ama.php): períodos, matérias, aulas, provas, Currículo e Talentos de Grade. Os bônus
     das matérias entram nas rolagens das habilidades (window.amaBonus). Fica dentro do #form-ficha: o campo
     estudos_ama vai junto quando a ficha é salva. --}}
@php
    $amaSalvo = $personagem->estudos_ama ?? null;
    if (is_string($amaSalvo)) {
        $amaSalvo = json_decode($amaSalvo, true);
    }
    if (!is_array($amaSalvo) || !isset($amaSalvo['periodos']) || !is_array($amaSalvo['periodos'])) {
        $amaSalvo = ['periodos' => []];
    }
@endphp
<style>
    #ama-card { overflow-x: hidden; }
    .ama-dica { font-size: .82rem; opacity: .75; margin: 4px 0 10px; }
    .ama-periodo { border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; padding: 10px; margin-bottom: 10px; background: rgba(0, 0, 0, .2); }
    .ama-periodo-cab { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    .ama-periodo-cab strong { flex: 1; }
    .ama-prova { display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; margin: 0; width: auto; }
    .ama-materia { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 4px 8px; padding: 6px 8px; border-radius: 8px; background: rgba(255, 255, 255, .04); margin-bottom: 6px; }
    .ama-materia-nome { font-weight: 600; overflow-wrap: anywhere; }
    .ama-materia-aulas { display: inline-flex; align-items: center; gap: 4px; font-size: .85rem; margin: 0; width: auto; white-space: nowrap; }
    .ama-materia-aulas input { width: 56px; text-align: center; }
    .ama-materia-info { grid-column: 1 / -1; font-size: .8rem; opacity: .8; display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; }
    .ama-materia-info select { font-size: .8rem; padding: 2px 4px; }
    .ama-aviso { color: #fca5a5; }
    .ama-x { width: 28px; height: 28px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, .15); background: none; color: inherit; cursor: pointer; }
    .ama-add { display: flex; gap: 6px; margin-top: 4px; }
    .ama-add select { flex: 1; min-width: 0; }
    .ama-talento { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; font-size: .85rem; }
    .ama-talento select { flex: 1; min-width: 160px; }
    .ama-tabela { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .ama-tabela td { padding: 3px 6px; border-bottom: 1px solid rgba(255, 255, 255, .07); }
    .ama-tabela td:last-child { text-align: right; font-weight: 700; }
    .ama-tal-desc { font-size: .82rem; opacity: .8; margin: 2px 0 8px; }
    .ama-badge { font-size: .7rem; padding: 1px 5px; border-radius: 999px; background: rgba(99, 102, 241, .5); color: #fff; margin-left: 4px; white-space: nowrap; }
    #ama-card h3 { margin: 14px 0 6px; font-size: 1rem; }
    .ama-carreira-linha { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, .6fr) minmax(0, 1fr); gap: 10px; align-items: end; margin: 6px 0; }
    .ama-carreira-linha label { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; margin: 0; width: auto; }
    .ama-carreira-linha select, .ama-carreira-linha input { width: 100%; box-sizing: border-box; }
    .ama-patente-card { border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; padding: 10px 12px; background: rgba(0, 0, 0, .2); margin: 8px 0 12px; }
    .ama-patente-card h4 { margin: 0 0 6px; }
    .ama-patente-card dl { margin: 0; display: grid; gap: 8px; }
    .ama-patente-card dt { font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; opacity: .65; }
    .ama-patente-card dd { margin: 2px 0 0; white-space: pre-line; line-height: 1.5; }
    .ama-regras { margin-top: 8px; border-top: 1px solid rgba(255, 255, 255, .1); padding-top: 8px; }
    .ama-regras summary { cursor: pointer; font-weight: 600; }
    .ama-regras h4 { margin: 12px 0 4px; font-size: .9rem; }
    .ama-regras p { margin: 0; white-space: pre-line; font-size: .88rem; line-height: 1.55; opacity: .9; }
    .ama-exercito { display: grid; grid-template-columns: minmax(0, 1fr) 110px auto; gap: 8px; align-items: end; border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; padding: 10px; margin-bottom: 10px; background: rgba(0, 0, 0, .2); }
    .ama-exercito label { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; margin: 0; width: auto; min-width: 0; }
    .ama-exercito label.largo { grid-column: 1 / -1; }
    .ama-exercito input, .ama-exercito textarea { width: 100%; box-sizing: border-box; }
    .ama-exercito textarea { min-height: 70px; resize: vertical; }
    @media (max-width: 560px) {
        .ama-carreira-linha { grid-template-columns: 1fr 1fr; }
        .ama-carreira-linha label:last-child { grid-column: 1 / -1; }
        .ama-exercito { grid-template-columns: minmax(0, 1fr) 90px; }
        .ama-exercito .ama-x { grid-column: 1 / -1; width: 100%; }
    }
</style>

<div class="card card-rolagem card-talentos" id="ama-card" style="max-height: 80%; overflow-y:auto;">
    <button type="button" class="botao-fechar" id="fechar-ama" title="Fechar (Esc)" aria-label="Fechar">✕</button>
    <div class="botoes-grid" style="text-align:center"><h1>AMA</h1></div>
    <div class="abas-painel" role="tablist">
        <button type="button" class="aba-painel ativo" data-aba="estudos">Estudos</button>
        <button type="button" class="aba-painel" data-aba="patente">Patente e salário</button>
        <button type="button" class="aba-painel" data-aba="exercitos">Exércitos</button>
    </div>

    {{-- Patente, grau e salário (config/ama.php "patentes"): escolher a patente sugere o salário dela; o jogador muda à vontade --}}
    <div class="aba-conteudo" data-aba="patente">
        <div class="ama-carreira-linha">
            <label>Patente
                <select id="ama-patente">
                    <option value="0">Sem patente</option>
                    @foreach (config('ama.patentes', []) as $n => $pt)
                        <option value="{{ $n }}">{{ $n }}. {{ $pt['nome'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Grau
                <select id="ama-grau">
                    @foreach (config('ama.graus', []) as $i => $g)
                        <option value="{{ $i + 1 }}">{{ $g }}</option>
                    @endforeach
                </select>
            </label>
            <label>Salário mensal (R$)
                <input type="number" id="ama-salario" min="0" step="0.01" placeholder="0,00">
            </label>
        </div>
        <p class="ama-dica" id="ama-salario-dica"></p>
        <div id="ama-patente-info"></div>
        <details class="ama-regras">
            <summary>Regras de patente (provas, notas de missão, investimentos e doação)</summary>
            @foreach (config('ama.patente_regras', []) as $r)
                <h4>{{ $r['titulo'] }}</h4>
                <p>{{ $r['texto'] }}</p>
            @endforeach
        </details>
    </div>

    {{-- Exércitos: nome, descrição, nível e aprimoramentos de cada um --}}
    <div class="aba-conteudo" data-aba="exercitos">
        <div id="ama-exercitos"></div>
        <p class="ama-dica" id="ama-exercitos-vazio">Nenhum exército ainda.</p>
        <button type="button" class="button" id="ama-novo-exercito">+ Novo exército</button>
    </div>
    <input type="hidden" name="carreira_ama" id="carreira-ama" value="">

    <div class="aba-conteudo ativo" data-aba="estudos">
    <p class="ama-dica">A cada período (6 meses), escolha até {{ config('ama.max_materias_periodo') }} matérias. Cada aula dá o bônus da matéria (no máximo {{ config('ama.max_por_habilidade') }} por matéria em cada habilidade). Ao passar na prova (média 7,00), escolha um Talento de Grade das matérias que fez. Os estudos terminam com no mínimo {{ config('ama.periodos_minimos') }} períodos. Os bônus de habilidade já entram nas rolagens.</p>
    <div id="ama-periodos"></div>
    <button type="button" class="button" id="ama-novo-periodo">+ Novo período</button>
    <h3>Currículo</h3>
    <div id="ama-curriculo"></div>
    <h3>Bônus dos estudos</h3>
    <div id="ama-bonus"></div>
    </div>
    <input type="hidden" name="estudos_ama" id="estudos-ama" value="">
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const card = document.getElementById("ama-card");
    if(!card) return;
    const abrir = document.getElementById("ama-abrir");
    if(abrir) abrir.addEventListener("click", function(){ card.classList.add("ativo"); });
    document.getElementById("fechar-ama").addEventListener("click", function(){ card.classList.remove("ativo"); });
    document.addEventListener("keydown", function(e){ if(e.key === "Escape") card.classList.remove("ativo"); });

    const CFG = {!! json_encode(['materias' => config('ama.materias'), 'talentos' => config('ama.talentos'), 'extras' => config('ama.extras'), 'maxMaterias' => config('ama.max_materias_periodo'), 'maxHab' => config('ama.max_por_habilidade')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};
    const somenteLeitura = !!document.querySelector("#form-ficha.somente-leitura");
    const hidden = document.getElementById("estudos-ama");
    const caixa = document.getElementById("ama-periodos");
    let estado = @json($amaSalvo);
    estado.periodos = (estado.periodos || []).map(p => ({ aprovado: !!p.aprovado, talento: p.talento || "", materias: (p.materias || []).filter(m => CFG.materias[m.id]).map(m => ({ id: +m.id, aulas: Math.max(0, +m.aulas || 0), esc: m.esc || {} })) }));

    const esc = s => String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
    // nome de uma chave: habilidade da ficha (lê o rótulo da linha) ou um dos extras (aura, BE...)
    function nomeChave(k){
        if(CFG.extras[k]) return CFG.extras[k];
        const input = document.getElementById(k);
        const linha = input && input.closest(".habilidade-linha");
        const nome = linha && linha.querySelector(".habilidade-nome");
        return nome ? nome.textContent.trim() : k;
    }
    const ehHabilidade = k => !CFG.extras[k] && !!document.getElementById(k) && !!document.getElementById(k).closest(".habilidade-linha");
    const fmt = n => (n > 0 ? "+" : "") + (Math.round(n * 100) / 100).toString().replace(".", ",");

    // matérias concluídas (período aprovado) antes do período p (ou em todos, com p = Infinity)
    function concluidas(p){
        const s = new Set();
        estado.periodos.forEach((per, i) => { if(i < p && per.aprovado) per.materias.forEach(m => { if(m.aulas > 0) s.add(m.id); }); });
        return s;
    }
    // ancestrais de uma matéria (requisitos diretos e indiretos)
    function ancestrais(id, vistos){
        vistos = vistos || new Set();
        (CFG.materias[id].req || []).forEach(r => { if(!vistos.has(r)){ vistos.add(r); ancestrais(r, vistos); } });
        return vistos;
    }
    // Currículo de uma linha: matérias concluídas que são ela ou dependem dela
    function curriculo(linha, feitas){
        let n = 0;
        feitas.forEach(id => { if(id === linha || ancestrais(id).has(linha)) n++; });
        return n;
    }

    function totais(){
        const t = {};
        estado.periodos.forEach(per => per.materias.forEach(m => {
            (CFG.materias[m.id].bonus || []).forEach((b, k) => {
                const alvo = b.hab.length > 1 ? (b.hab.includes(m.esc[k]) ? m.esc[k] : b.hab[0]) : b.hab[0];
                let v = b.v * m.aulas;
                if(v > CFG.maxHab) v = CFG.maxHab; // uma matéria dá no máximo 25 em cada habilidade
                t[alvo] = (t[alvo] || 0) + v;
            });
        }));
        return t;
    }

    function desenhar(){
        caixa.innerHTML = estado.periodos.map((per, p) => {
            const antes = concluidas(p);
            const ja = new Set(per.materias.map(m => m.id));
            const linhas = per.materias.map((m, k) => {
                const mt = CFG.materias[m.id];
                const faltam = (mt.req || []).filter(r => !antes.has(r));
                const escolhas = (mt.bonus || []).map((b, i) => b.hab.length > 1
                    ? '<select data-esc="' + p + ':' + k + ':' + i + '"' + (somenteLeitura ? " disabled" : "") + '>' + b.hab.map(h => '<option value="' + h + '"' + (m.esc[i] === h ? " selected" : "") + '>' + fmt(b.v) + " " + esc(nomeChave(h)) + '</option>').join("") + '</select>'
                    : '<span>' + fmt(b.v) + " " + esc(nomeChave(b.hab[0])) + '</span>').join(" · ");
                return '<div class="ama-materia"><span class="ama-materia-nome">' + esc(mt.nome) + '</span>' +
                    '<label class="ama-materia-aulas">Aulas <input type="number" min="0" max="' + mt.aulas + '" value="' + m.aulas + '" data-aulas="' + p + ':' + k + '"' + (somenteLeitura ? " disabled" : "") + '> / ' + mt.aulas + '</label>' +
                    (somenteLeitura ? '<span></span>' : '<button type="button" class="ama-x" data-tirar="' + p + ':' + k + '" title="Tirar a matéria" aria-label="Tirar a matéria">×</button>') +
                    '<div class="ama-materia-info">Por aula: ' + (escolhas || "sem bônus") +
                        (mt.cond ? ' · <span title="Confira na ficha">Requisito: ' + esc(mt.cond) + '</span>' : '') +
                        (faltam.length ? ' · <span class="ama-aviso">Falta concluir antes: ' + faltam.map(r => esc(CFG.materias[r].nome)).join(", ") + '</span>' : '') +
                    '</div></div>';
            }).join("");
            // matérias que dá para adicionar: requisitos concluídos antes, ainda não concluída e não está neste período
            const opcoes = Object.keys(CFG.materias).map(Number).filter(id => !ja.has(id) && !antes.has(id) && (CFG.materias[id].req || []).every(r => antes.has(r)));
            const add = !somenteLeitura && per.materias.length < CFG.maxMaterias && opcoes.length
                ? '<div class="ama-add"><select data-nova="' + p + '"><option value="">Adicionar matéria…</option>' + opcoes.map(id => '<option value="' + id + '">' + esc(CFG.materias[id].nome) + (CFG.materias[id].cond ? " (" + esc(CFG.materias[id].cond) + ")" : "") + '</option>').join("") + '</select></div>' : '';
            // talento do período: das linhas das matérias que fez, com Currículo até este período
            const feitas = concluidas(p + 1);
            const elegiveis = per.aprovado ? CFG.talentos.filter(t => per.materias.some(m => m.aulas > 0 && (m.id === t.linha || ancestrais(m.id).has(t.linha))) && curriculo(t.linha, feitas) > 0) : [];
            const talento = per.aprovado
                ? '<div class="ama-talento">Talento de Grade: <select data-talento="' + p + '"' + (somenteLeitura ? " disabled" : "") + '><option value="">Escolha…</option>' +
                    elegiveis.map(t => '<option' + (per.talento === t.nome ? " selected" : "") + '>' + esc(t.nome) + '</option>').join("") + '</select>' +
                    (per.talento && !somenteLeitura && window.fichaAdicionarTalentoMundano ? '<button type="button" class="arm-mini" data-add-talento="' + p + '">Adicionar à ficha</button>' : '') +
                    (!elegiveis.length ? '<span class="ama-aviso">Nenhum talento: conclua aulas de uma matéria com Talento de Grade.</span>' : '') + '</div>' +
                    (per.talento ? '<p class="ama-tal-desc">' + esc((CFG.talentos.find(t => t.nome === per.talento) || {}).descricao || "") + '</p>' : '')
                : '';
            return '<div class="ama-periodo"><div class="ama-periodo-cab"><strong>' + (p + 1) + 'º período</strong>' +
                '<label class="ama-prova"><input type="checkbox" data-aprovado="' + p + '"' + (per.aprovado ? " checked" : "") + (somenteLeitura ? " disabled" : "") + '> Passou na prova (média 7,00)</label>' +
                (somenteLeitura ? '' : '<button type="button" class="ama-x" data-tirar-periodo="' + p + '" title="Apagar o período" aria-label="Apagar o período">×</button>') + '</div>' +
                (linhas || '<p class="ama-dica">Nenhuma matéria neste período.</p>') + add + talento + '</div>';
        }).join("") || '<p class="ama-dica">Nenhum período ainda.</p>';

        // currículo de cada linha com Talento de Grade
        const todas = concluidas(Infinity);
        const cur = CFG.talentos.map(t => [CFG.materias[t.linha].nome, curriculo(t.linha, todas)]).filter(x => x[1] > 0);
        document.getElementById("ama-curriculo").innerHTML = cur.length
            ? '<table class="ama-tabela">' + cur.map(x => '<tr><td>' + esc(x[0]) + '</td><td>' + x[1] + '</td></tr>').join("") + '</table>'
            : '<p class="ama-dica">Sem matérias concluídas ainda.</p>';

        // bônus: habilidades (entram nas rolagens) e os demais (aplique onde valem)
        const t = totais();
        const chaves = Object.keys(t).filter(k => t[k]);
        document.getElementById("ama-bonus").innerHTML = chaves.length
            ? '<table class="ama-tabela">' + chaves.map(k => '<tr><td>' + esc(nomeChave(k)) + (ehHabilidade(k) ? '' : ' <small>(aplique na ficha)</small>') + '</td><td>' + fmt(t[k]) + '</td></tr>').join("") + '</table>'
            : '<p class="ama-dica">Nenhum bônus ainda.</p>';

        window.amaBonus = {};
        chaves.filter(ehHabilidade).forEach(k => { window.amaBonus[k] = t[k]; });
        document.querySelectorAll(".habilidade-linha .ama-badge").forEach(b => b.remove());
        Object.keys(window.amaBonus).forEach(k => {
            const nome = document.getElementById(k).closest(".habilidade-linha").querySelector(".habilidade-nome");
            if(nome) nome.insertAdjacentHTML("beforeend", '<span class="ama-badge" title="Bônus dos estudos na AMA (já entra na rolagem)">' + fmt(window.amaBonus[k]) + ' AMA</span>');
        });
        hidden.value = JSON.stringify(estado);
    }

    function mudou(){ desenhar(); hidden.dispatchEvent(new Event("input", { bubbles: true })); }

    document.getElementById("ama-novo-periodo").addEventListener("click", function(){
        if(somenteLeitura) return;
        estado.periodos.push({ aprovado: false, talento: "", materias: [] });
        mudou();
    });
    if(somenteLeitura) document.getElementById("ama-novo-periodo").hidden = true;
    caixa.addEventListener("change", function(e){
        const el = e.target;
        if(el.dataset.nova != null && el.value){
            estado.periodos[+el.dataset.nova].materias.push({ id: +el.value, aulas: 0, esc: {} });
        } else if(el.dataset.aprovado != null){
            const per = estado.periodos[+el.dataset.aprovado];
            per.aprovado = el.checked;
            if(!el.checked) per.talento = "";
        } else if(el.dataset.talento != null){
            estado.periodos[+el.dataset.talento].talento = el.value;
        } else if(el.dataset.esc){
            const [p, k, i] = el.dataset.esc.split(":").map(Number);
            estado.periodos[p].materias[k].esc[i] = el.value;
        } else if(el.dataset.aulas){
            const [p, k] = el.dataset.aulas.split(":").map(Number);
            const m = estado.periodos[p].materias[k];
            m.aulas = Math.max(0, Math.min(CFG.materias[m.id].aulas, Math.round(+el.value || 0)));
        } else return;
        mudou();
    });
    caixa.addEventListener("click", function(e){
        const b = e.target.closest("button");
        if(!b) return;
        if(b.dataset.tirar){
            const [p, k] = b.dataset.tirar.split(":").map(Number);
            estado.periodos[p].materias.splice(k, 1);
            mudou();
        } else if(b.dataset.tirarPeriodo != null){
            if(!confirm("Apagar este período e as matérias dele?")) return;
            estado.periodos.splice(+b.dataset.tirarPeriodo, 1);
            mudou();
        } else if(b.dataset.addTalento != null){
            const t = CFG.talentos.find(x => x.nome === estado.periodos[+b.dataset.addTalento].talento);
            if(t && window.fichaAdicionarTalentoMundano){
                window.fichaAdicionarTalentoMundano({ titulo: t.nome, descricao: t.descricao + "\n\nTalento de Grade (AMA): use seu Currículo em " + CFG.materias[t.linha].nome + ".", custo: "" });
                b.textContent = "Adicionado";
                b.disabled = true;
            }
        }
    });
    desenhar();
});

// ===== Carreira na AMA: patente, grau, salário e exércitos (campo carreira_ama) =====
document.addEventListener("DOMContentLoaded", function(){
    const campo = document.getElementById("carreira-ama");
    if(!campo) return;
    const PATENTES = @json(config('ama.patentes', []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    const GRAUS = @json(config('ama.graus', []));
    const somenteLeitura = !!document.querySelector("#form-ficha.somente-leitura");
    const esc = s => String(s == null ? "" : s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
    const reais = v => Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });

    let salvo = @json(is_array($personagem->carreira_ama ?? null) ? $personagem->carreira_ama : null) || {};
    const c = {
        patente: Math.max(0, Math.min(10, parseInt(salvo.patente, 10) || 0)),
        grau: Math.max(1, Math.min(5, parseInt(salvo.grau, 10) || 1)),
        salario: salvo.salario === undefined || salvo.salario === null || salvo.salario === "" ? null : Math.max(0, Number(salvo.salario) || 0),
        exercitos: (Array.isArray(salvo.exercitos) ? salvo.exercitos : []).map(x => ({
            nome: String(x.nome || ""), descricao: String(x.descricao || ""), nivel: Math.max(0, parseInt(x.nivel, 10) || 0), aprimoramentos: String(x.aprimoramentos || "")
        })),
    };

    const selPat = document.getElementById("ama-patente");
    const selGrau = document.getElementById("ama-grau");
    const inSal = document.getElementById("ama-salario");
    const info = document.getElementById("ama-patente-info");
    const dica = document.getElementById("ama-salario-dica");
    const caixaEx = document.getElementById("ama-exercitos");

    function gravar(avisar){
        campo.value = JSON.stringify(c);
        if(avisar) campo.dispatchEvent(new Event("input", { bubbles: true })); // salvamento automático
    }

    function desenharPatente(){
        const p = PATENTES[c.patente];
        selPat.value = String(c.patente);
        selGrau.value = String(c.grau);
        selGrau.disabled = somenteLeitura || !p || !!p.sem_grau;
        inSal.value = c.salario === null ? "" : String(c.salario);
        if(!p){
            info.innerHTML = "";
            dica.textContent = "Escolha a patente para ver a descrição, os benefícios e o salário dela.";
            return;
        }
        const nome = p.nome + (p.sem_grau ? "" : " " + (GRAUS[c.grau - 1] || ""));
        const linhas = [["Descrição", p.descricao], ["Salário mensal da patente", reais(p.salario)], ["Benefícios", p.beneficios], ["Bonificações", p.bonificacao]]
            .filter(l => l[1]);
        info.innerHTML = '<div class="ama-patente-card"><h4>' + esc(c.patente + ". " + nome) + "</h4><dl>" +
            linhas.map(l => "<div><dt>" + esc(l[0]) + "</dt><dd>" + esc(l[1]) + "</dd></div>").join("") + "</dl></div>";
        dica.textContent = c.salario !== null && Number(c.salario) !== Number(p.salario)
            ? "Salário ajustado à mão (o da patente é " + reais(p.salario) + ")."
            : "Salário da patente; mude à vontade (bônus, escalas, empresa...).";
    }

    function desenharExercitos(){
        const ro = somenteLeitura ? " disabled" : "";
        caixaEx.innerHTML = c.exercitos.map((x, i) =>
            '<div class="ama-exercito" data-i="' + i + '">' +
                '<label>Nome<input type="text" maxlength="120" data-campo="nome" value="' + esc(x.nome) + '"' + ro + "></label>" +
                '<label>Nível<input type="number" min="0" data-campo="nivel" value="' + x.nivel + '"' + ro + "></label>" +
                (somenteLeitura ? "<span></span>" : '<button type="button" class="ama-x" data-tirar-exercito="' + i + '" title="Apagar exército">✕</button>') +
                '<label class="largo">Descrição<textarea data-campo="descricao" maxlength="5000"' + ro + ">" + esc(x.descricao) + "</textarea></label>" +
                '<label class="largo">Aprimoramentos do exército<textarea data-campo="aprimoramentos" maxlength="5000"' + ro + ">" + esc(x.aprimoramentos) + "</textarea></label>" +
            "</div>").join("");
        document.getElementById("ama-exercitos-vazio").style.display = c.exercitos.length ? "none" : "block";
    }

    selPat.addEventListener("change", function(){
        c.patente = parseInt(selPat.value, 10) || 0;
        // a patente sugere o salário dela (o jogador muda depois, se quiser)
        c.salario = PATENTES[c.patente] ? PATENTES[c.patente].salario : null;
        desenharPatente(); gravar(true);
    });
    selGrau.addEventListener("change", function(){ c.grau = parseInt(selGrau.value, 10) || 1; desenharPatente(); gravar(true); });
    inSal.addEventListener("input", function(){
        c.salario = inSal.value === "" ? null : Math.max(0, Number(inSal.value) || 0);
        gravar(false); // o próprio input já dispara o salvamento
    });
    inSal.addEventListener("change", desenharPatente);

    document.getElementById("ama-novo-exercito").addEventListener("click", function(){
        if(c.exercitos.length >= 20) return;
        c.exercitos.push({ nome: "", descricao: "", nivel: 1, aprimoramentos: "" });
        desenharExercitos(); gravar(true);
        const ult = caixaEx.lastElementChild;
        if(ult) ult.querySelector("input").focus();
    });
    caixaEx.addEventListener("input", function(e){
        const box = e.target.closest(".ama-exercito");
        const k = e.target.dataset.campo;
        if(!box || !k) return;
        const x = c.exercitos[+box.dataset.i];
        x[k] = k === "nivel" ? Math.max(0, parseInt(e.target.value, 10) || 0) : e.target.value;
        gravar(false);
    });
    caixaEx.addEventListener("click", function(e){
        const b = e.target.closest("[data-tirar-exercito]");
        if(!b || !confirm("Apagar este exército?")) return;
        c.exercitos.splice(+b.dataset.tirarExercito, 1);
        desenharExercitos(); gravar(true);
    });
    if(somenteLeitura){ selPat.disabled = true; inSal.disabled = true; document.getElementById("ama-novo-exercito").hidden = true; }

    desenharPatente();
    desenharExercitos();
    gravar(false);
});
</script>
