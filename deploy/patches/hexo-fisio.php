<?php
// Aplica no form.blade.php: Talentos de Arte (Hexomancia/Fisiomancia), abas nas Técnicas e Propriedades fisiomantes.
// Cada trecho "antigo" precisa existir exatamente 1 vez; senão nada é gravado.
$arquivo = __DIR__ . '/../../resources/views/personagem/form.blade.php';
$src = file_get_contents($arquivo);
$crlf = str_contains($src, "\r\n");
$src = str_replace("\r\n", "\n", $src);

$pares = [];

// 1) @php: propriedades salvas + catálogo
$pares[] = [<<<'OLD'
    if (!is_array($tecnicasSalvas)) {
        $tecnicasSalvas = [];
    }
@endphp
<style>
OLD, <<<'NEW'
    if (!is_array($tecnicasSalvas)) {
        $tecnicasSalvas = [];
    }

    // Propriedades fisiomantes salvas na ficha (coluna propriedades_fisiomantes) e catálogo completo (resources/data)
    $propriedadesSalvas = $personagem->propriedades_fisiomantes ?? [];
    if (is_string($propriedadesSalvas)) {
        $propriedadesSalvas = json_decode($propriedadesSalvas, true) ?: [];
    }
    if (!is_array($propriedadesSalvas)) {
        $propriedadesSalvas = [];
    }
    $catalogoPropriedades = ['grupos' => [], 'fontes' => []];
    $arquivoCatalogo = resource_path('data/propriedades_fisiomantes.json');
    if (is_file($arquivoCatalogo)) {
        $catalogoPropriedades = json_decode((string) file_get_contents($arquivoCatalogo), true) ?: $catalogoPropriedades;
    }
@endphp
<style>
NEW];

// 2) CSS
$pares[] = [<<<'OLD'
/* ===== Talentos Mundanos ===== */
#talentos-mundanos-card {
    max-height: 85vh !important;
    overflow-y: auto;
    padding-bottom: 20px;
}
#talentos-mundanos-card > .botao-adicionar-talento { width: 100%; margin-top: 8px; }
OLD, <<<'NEW'
/* ===== Talentos (Mundanos e de Arte) ===== */
#talentos-mundanos-card, #talentos-arte-card {
    max-height: 85vh !important;
    overflow-y: auto;
    padding-bottom: 20px;
}
#talentos-mundanos-card > .botao-adicionar-talento,
#talentos-arte-card .aba-conteudo > .botao-adicionar-talento { width: 100%; margin-top: 8px; }

/* ===== Abas dentro dos painéis (Talentos de Arte, Técnicas) ===== */
.abas-painel { display: flex; gap: 6px; margin: 10px 0 6px; border-bottom: 1px solid rgba(255, 255, 255, .14); }
.aba-painel {
    flex: 1 1 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    min-height: 38px; padding: 0 10px; background: rgba(0, 0, 0, .25); color: inherit; font: inherit; font-size: .9rem;
    border: 1px solid rgba(255, 255, 255, .14); border-bottom: none; border-radius: 10px 10px 0 0; cursor: pointer; opacity: .8;
    transition: border-color .15s ease, background .15s ease, opacity .15s ease;
}
.aba-painel:hover { opacity: 1; border-color: rgba(220, 70, 70, .6); }
.aba-painel.ativo { opacity: 1; background: #8b1a1a; border-color: rgba(220, 70, 70, .85); }
.aba-conteudo { display: none; }
.aba-conteudo.ativo { display: block; }

/* ===== Propriedades fisiomantes (aba Fisiomancia das Técnicas) ===== */
.propriedades-fisio .maldicao-subtitulo { margin: 14px 0 8px; }
.prop-busca-linha { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 190px); gap: 8px; align-items: center; }
.prop-busca-linha input, .prop-busca-linha select { height: 42px; min-width: 0; width: 100%; box-sizing: border-box; }
.prop-grupo-descricao { font-size: .82rem; opacity: .75; margin: 8px 0 0; }
.prop-grupo-descricao:empty { display: none; }
#prop-catalogo { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.prop-fonte { background: rgba(0, 0, 0, .25); border: 1px solid rgba(255, 255, 255, .10); border-radius: 10px; padding: 0 12px; }
.prop-fonte > summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px; min-height: 40px; font-weight: 600; }
.prop-fonte > summary::-webkit-details-marker { display: none; }
.prop-fonte > summary::before { content: "▸"; opacity: .7; font-size: .8rem; }
.prop-fonte[open] > summary::before { content: "▾"; }
.prop-fonte .prop-qtd { opacity: .65; font-size: .78rem; font-weight: 400; font-variant-numeric: tabular-nums; }
.prop-fonte .prop-fonte-descricao { font-size: .82rem; opacity: .75; margin: 0 0 8px; }
.prop-item { padding: 8px 0 10px; border-top: 1px solid rgba(255, 255, 255, .08); display: flex; flex-direction: column; gap: 4px; }
.prop-item-cab { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.prop-item-cab strong { font-size: .95rem; }
.prop-ameaca { font-size: .75rem; padding: 2px 8px; border-radius: 999px; background: rgba(220, 70, 70, .22); border: 1px solid rgba(220, 70, 70, .5); white-space: nowrap; }
.prop-item-cab .button { margin-left: auto; min-height: 32px; min-width: 0; padding: 0 12px; font-size: .8rem; }
.prop-item p, .prop-card p { margin: 0; font-size: .86rem; line-height: 1.4; }
.prop-item .prop-ref { opacity: .7; font-style: italic; }
#prop-minhas-lista { display: flex; flex-direction: column; gap: 8px; }
.prop-card { background: rgba(0, 0, 0, .25); border: 1px solid rgba(220, 70, 70, .35); border-radius: 10px; padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.prop-card .botao-remover-item { margin-left: auto; }
.prop-card-linha { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: .82rem; }
.prop-card-linha label { margin: 0; width: auto; display: inline-flex; align-items: center; gap: 6px; }
.prop-card-linha input[type="number"] { width: 76px; height: 34px; }
.prop-card-linha input[type="text"] { flex: 1 1 160px; min-width: 0; height: 34px; }
NEW];

// 3) X preso ao topo também no novo painel
$pares[] = [<<<'OLD'
#form-ficha #talentos-mundanos-card > .botao-fechar,
#form-ficha #tecnicas-card > .botao-fechar,
OLD, <<<'NEW'
#form-ficha #talentos-mundanos-card > .botao-fechar,
#form-ficha #talentos-arte-card > .botao-fechar,
#form-ficha #tecnicas-card > .botao-fechar,
NEW];

// 4) botão na grade de acessos
$pares[] = [<<<'OLD'
                <button type="button" class="button" style="margin-top:8px;" id="talentos-mundanos">
                    Talentos Mundanos <span class="contador-painel" id="cont-talentos"></span>
                </button>
OLD, <<<'NEW'
                <button type="button" class="button" style="margin-top:8px;" id="talentos-mundanos">
                    Talentos Mundanos <span class="contador-painel" id="cont-talentos"></span>
                </button>
                <button type="button" class="button" style="margin-top:8px;" id="talentos-arte-abrir" title="Talentos de Hexomancia e de Fisiomancia">
                    Talentos de Arte <span class="contador-painel" id="cont-talentos-arte"></span>
                </button>
NEW];

// 5) painel Talentos de Arte
$pares[] = [<<<'OLD'
            <p class="vazio-lista" id="talentos-vazio" style="display:none"></p>
        </div>

        <div class="card card-rolagem" id="card-rolagem">
OLD, <<<'NEW'
            <p class="vazio-lista" id="talentos-vazio" style="display:none"></p>
        </div>

        {{-- Talentos de Arte: Hexomancia e Fisiomancia em abas. Mesma mecânica dos Talentos Mundanos (custo de Aura, ativar, pastas);
             todos ficam no mesmo JSON "talentos", separados pelo campo "tipo". --}}
        <div class="card card-rolagem card-talentos" id="talentos-arte-card">
            <button type="button" class="botao-fechar" id="fechar-talentos-arte" title="Fechar (Esc)" aria-label="Fechar">✕</button>

            <div class="botoes-grid" style="text-align:center">
                <h1>Talentos de Arte</h1>
            </div>

            <div class="abas-painel" role="tablist">
                <button type="button" class="aba-painel ativo" data-aba="hexomancia">Talentos de Hexomancia <span class="contador-painel" id="cont-talentos-hexomancia"></span></button>
                <button type="button" class="aba-painel" data-aba="fisiomancia">Talentos de Fisiomancia <span class="contador-painel" id="cont-talentos-fisiomancia"></span></button>
            </div>

            <div class="aba-conteudo ativo" data-aba="hexomancia">
                <div class="pastas-barra" id="pastas-talentos-hexomancia"></div>
                <button type="button" class="botao-adicionar-talento" id="adicionar-talento-hexomancia">+ Adicionar Talento</button>
                <div class="lista-talentos" id="lista-talentos-hexomancia"></div>
                <p class="vazio-lista" id="talentos-hexomancia-vazio" style="display:none"></p>
            </div>

            <div class="aba-conteudo" data-aba="fisiomancia">
                <div class="pastas-barra" id="pastas-talentos-fisiomancia"></div>
                <button type="button" class="botao-adicionar-talento" id="adicionar-talento-fisiomancia">+ Adicionar Talento</button>
                <div class="lista-talentos" id="lista-talentos-fisiomancia"></div>
                <p class="vazio-lista" id="talentos-fisiomancia-vazio" style="display:none"></p>
            </div>
        </div>

        <div class="card card-rolagem" id="card-rolagem">
NEW];

// 6) abas nas Técnicas + Propriedades fisiomantes
$pares[] = [<<<'OLD'
                <h1>Técnicas</h1>
            </div>

            <div class="pastas-barra" id="pastas-tecnicas"></div>
OLD, <<<'NEW'
                <h1>Técnicas</h1>
            </div>

            <div class="abas-painel" role="tablist">
                <button type="button" class="aba-painel ativo" data-aba="hexomancia">Técnicas de Hexomancia <span class="contador-painel" id="cont-tecnicas-hexomancia"></span></button>
                <button type="button" class="aba-painel" data-aba="fisiomancia">Técnicas de Fisiomancia <span class="contador-painel" id="cont-tecnicas-fisiomancia"></span></button>
            </div>

            {{-- Só na aba Fisiomancia: catálogo de propriedades fisiomantes (resources/data/propriedades_fisiomantes.json)
                 e as propriedades salvas na ficha (coluna propriedades_fisiomantes) --}}
            <div class="aba-conteudo" data-aba="fisiomancia">
                <details class="tecnica-novo propriedades-fisio" id="propriedades-fisio">
                    <summary>Propriedades fisiomantes <span class="contador-painel" id="cont-propriedades"></span></summary>
                    <input type="hidden" id="propriedades-fisiomantes" name="propriedades_fisiomantes" value="">

                    <h5 class="maldicao-subtitulo">Minhas propriedades</h5>
                    <div id="prop-minhas-lista"></div>
                    <p class="vazio-lista" id="prop-minhas-vazio">Nenhuma propriedade salva. Pesquise no catálogo e clique em “Salvar”.</p>

                    <h5 class="maldicao-subtitulo">Catálogo</h5>
                    <div class="prop-busca-linha">
                        <input type="search" id="prop-busca" class="prop-busca" placeholder="Pesquisar propriedade, material ou efeito…" autocomplete="off">
                        <select id="prop-grupo" class="prop-grupo"><option value="">Todos os grupos</option></select>
                    </div>
                    <p class="prop-grupo-descricao" id="prop-grupo-descricao"></p>
                    <div id="prop-catalogo"></div>
                    <p class="vazio-lista" id="prop-catalogo-vazio" style="display:none">Nenhuma propriedade encontrada.</p>

                    <details class="tecnica-novo" id="prop-personalizada">
                        <summary>Propriedade personalizada</summary>
                        <div class="tecnica-form">
                            <div class="tecnica-form-linha">
                                <div class="campo">
                                    <label for="prop-pers-nome">Nome</label>
                                    <input id="prop-pers-nome" type="text" placeholder="Nome da propriedade">
                                </div>
                                <div class="campo">
                                    <label for="prop-pers-fonte">Fonte</label>
                                    <input id="prop-pers-fonte" type="text" placeholder="Material, ser ou situação">
                                </div>
                            </div>
                            <div class="tecnica-form-linha">
                                <div class="campo">
                                    <label for="prop-pers-ameaca">Ameaça</label>
                                    <input id="prop-pers-ameaca" type="number" placeholder="0">
                                </div>
                            </div>
                            <div class="campo">
                                <label for="prop-pers-descricao">Descrição</label>
                                <textarea id="prop-pers-descricao" placeholder="Efeito da propriedade"></textarea>
                            </div>
                            <button type="button" class="botao-adicionar-talento" id="prop-pers-salvar">+ Salvar propriedade</button>
                        </div>
                    </details>
                </details>
            </div>

            <div class="pastas-barra" id="pastas-tecnicas"></div>
NEW];

$pares[] = [<<<'OLD'
                        <label for="tec-nivel">Nível de maldição requisitado</label>
OLD, <<<'NEW'
                        <label for="tec-nivel" id="tec-nivel-rotulo">Nível de maldição requisitado</label>
NEW];

// 7) abas genéricas + painel redimensionável
$pares[] = [<<<'OLD'
// Painéis flutuantes redimensionáveis: arraste o canto inferior direito; o tamanho fica lembrado no navegador
(function(){
    ["talentos-mundanos-card", "tecnicas-card", "anotacoes-card", "maldicao-card"].forEach(function(id){
OLD, <<<'NEW'
// Abas dentro dos painéis (.abas-painel > .aba-painel[data-aba]; conteúdos em .aba-conteudo[data-aba] filhos diretos do painel).
// A aba escolhida fica lembrada no navegador; o painel recebe o evento "aba-mudou".
(function(){
    document.querySelectorAll(".abas-painel").forEach(function(abas){
        const painel = abas.parentElement;
        function ativar(nome, lembrar){
            const b = abas.querySelector('.aba-painel[data-aba="' + nome + '"]');
            if(!b) return;
            abas.querySelectorAll(".aba-painel").forEach(x => x.classList.toggle("ativo", x === b));
            painel.querySelectorAll(":scope > .aba-conteudo").forEach(c => c.classList.toggle("ativo", c.dataset.aba === nome));
            if(lembrar){ try { localStorage.setItem("ficha-aba:" + painel.id, nome); } catch(e) {} }
            painel.dispatchEvent(new CustomEvent("aba-mudou", { detail: nome }));
        }
        abas.addEventListener("click", function(e){
            const b = e.target.closest(".aba-painel");
            if(b) ativar(b.dataset.aba, true);
        });
        try {
            const salva = localStorage.getItem("ficha-aba:" + painel.id);
            if(salva) ativar(salva, false);
        } catch(e) {}
    });
})();

// Painéis flutuantes redimensionáveis: arraste o canto inferior direito; o tamanho fica lembrado no navegador
(function(){
    ["talentos-mundanos-card", "talentos-arte-card", "tecnicas-card", "anotacoes-card", "maldicao-card"].forEach(function(id){
NEW];

// 8) Pastas: novos tipos
$pares[] = [<<<'OLD'
    const TIPOS = ["talentos", "tecnicas", "anotacoes"];
OLD, <<<'NEW'
    const TIPOS = ["talentos", "tecnicas", "anotacoes", "talentos_hexomancia", "talentos_fisiomancia"];
NEW];

// 9) Talentos genéricos
$pares[] = [<<<'OLD'
    //Talentos Mundanos
    const talentosMundanosCard = document.getElementById("talentos-mundanos-card");
    const talentosMundanos = document.getElementById("talentos-mundanos");
    const fecharTalentos = document.getElementById("fechar-talentos");
    const adicionarTalentos = document.getElementById("adicionar-talento");
    const listaTalentos = document.getElementById("lista-talentos");

    talentosMundanos.addEventListener("click", function(){
        talentosMundanosCard.classList.add("ativo");
    });

    fecharTalentos.addEventListener("click", function(){
        talentosMundanosCard.classList.remove("ativo");
    });
OLD, <<<'NEW'
    //Talentos: Mundanos (painel próprio) e de Arte (Hexomancia / Fisiomancia em abas). Mesma mecânica, listas separadas.
    const talentosMundanosCard = document.getElementById("talentos-mundanos-card");
    const talentosArteCard = document.getElementById("talentos-arte-card");
    document.getElementById("talentos-mundanos").addEventListener("click", () => talentosMundanosCard.classList.add("ativo"));
    document.getElementById("fechar-talentos").addEventListener("click", () => talentosMundanosCard.classList.remove("ativo"));
    document.getElementById("talentos-arte-abrir").addEventListener("click", () => talentosArteCard.classList.add("ativo"));
    document.getElementById("fechar-talentos-arte").addEventListener("click", () => talentosArteCard.classList.remove("ativo"));
NEW];

$pares[] = [<<<'OLD'
    function cardsTalentos(){
        return Array.from(listaTalentos.querySelectorAll(".talento-card"));
    }
OLD, <<<'NEW'
    // Monta uma lista de talentos (cfg: tipo, pastaTipo, lista, barra, botaoAdicionar, vazio, contador, aoMudar)
    function montarTalentos(cfg){
    const listaTalentos = cfg.lista;
    const adicionarTalentos = cfg.botaoAdicionar;

    function cardsTalentos(){
        return Array.from(listaTalentos.querySelectorAll(".talento-card"));
    }
NEW];

$pares[] = [<<<'OLD'
            card.querySelector(".pasta-select").innerHTML = PastasFicha.opcoes("talentos", card.dataset.pasta || "");
OLD, <<<'NEW'
            card.querySelector(".pasta-select").innerHTML = PastasFicha.opcoes(cfg.pastaTipo, card.dataset.pasta || "");
NEW];

$pares[] = [<<<'OLD'
            const ok = card.dataset.confirmado !== "1" || PastasFicha.visivel("talentos", card.dataset.pasta || "");
OLD, <<<'NEW'
            const ok = card.dataset.confirmado !== "1" || PastasFicha.visivel(cfg.pastaTipo, card.dataset.pasta || "");
NEW];

$pares[] = [<<<'OLD'
        const vazio = document.getElementById("talentos-vazio");
OLD, <<<'NEW'
        const vazio = cfg.vazio;
NEW];

$pares[] = [<<<'OLD'
        document.getElementById("cont-talentos").textContent = qtd > 0 ? qtd : "";
        PastasFicha.atualizar("talentos");
OLD, <<<'NEW'
        cfg.contador.textContent = qtd > 0 ? qtd : "";
        if(cfg.aoMudar) cfg.aoMudar();
        PastasFicha.atualizar(cfg.pastaTipo);
NEW];

$pares[] = [<<<'OLD'
    PastasFicha.registrar("talentos", {
        barra: document.getElementById("pastas-talentos"),
OLD, <<<'NEW'
    PastasFicha.registrar(cfg.pastaTipo, {
        barra: cfg.barra,
NEW];

$pares[] = [<<<'OLD'
        card.dataset.ativo = t.ativo ? "1" : "0";
        card.dataset.pasta = typeof t.pasta === "string" ? t.pasta : PastasFicha.filtroAtual("talentos");
        card.querySelector(".pasta-select").innerHTML = PastasFicha.opcoes("talentos", card.dataset.pasta);
OLD, <<<'NEW'
        card.dataset.ativo = t.ativo ? "1" : "0";
        card.dataset.tipo = cfg.tipo;
        card.dataset.pasta = typeof t.pasta === "string" ? t.pasta : PastasFicha.filtroAtual(cfg.pastaTipo);
        card.querySelector(".pasta-select").innerHTML = PastasFicha.opcoes(cfg.pastaTipo, card.dataset.pasta);
NEW];

$pares[] = [<<<'OLD'
    // Recria os talentos salvos já confirmados (com custo, estado ativo e pasta)
    @json($talentosSalvos).forEach(function(t){
        criarCardTalento(t, true, false);
    });
    depoisDeMudarTalentos();
OLD, <<<'NEW'
    return {
        carregar: function(t){ criarCardTalento(t, true, false); },
        atualizar: depoisDeMudarTalentos
    };
    }

    function atualizarContadorTalentosArte(){
        const qtd = talentosArteCard.querySelectorAll('.talento-card[data-confirmado="1"]').length;
        document.getElementById("cont-talentos-arte").textContent = qtd > 0 ? qtd : "";
    }

    const gruposTalentos = {
        mundano: montarTalentos({
            tipo: "mundano", pastaTipo: "talentos",
            lista: document.getElementById("lista-talentos"), barra: document.getElementById("pastas-talentos"),
            botaoAdicionar: document.getElementById("adicionar-talento"), vazio: document.getElementById("talentos-vazio"),
            contador: document.getElementById("cont-talentos")
        }),
        hexomancia: montarTalentos({
            tipo: "hexomancia", pastaTipo: "talentos_hexomancia",
            lista: document.getElementById("lista-talentos-hexomancia"), barra: document.getElementById("pastas-talentos-hexomancia"),
            botaoAdicionar: document.getElementById("adicionar-talento-hexomancia"), vazio: document.getElementById("talentos-hexomancia-vazio"),
            contador: document.getElementById("cont-talentos-hexomancia"), aoMudar: atualizarContadorTalentosArte
        }),
        fisiomancia: montarTalentos({
            tipo: "fisiomancia", pastaTipo: "talentos_fisiomancia",
            lista: document.getElementById("lista-talentos-fisiomancia"), barra: document.getElementById("pastas-talentos-fisiomancia"),
            botaoAdicionar: document.getElementById("adicionar-talento-fisiomancia"), vazio: document.getElementById("talentos-fisiomancia-vazio"),
            contador: document.getElementById("cont-talentos-fisiomancia"), aoMudar: atualizarContadorTalentosArte
        })
    };

    // Recria os talentos salvos já confirmados (com custo, estado ativo, pasta e tipo), cada um na sua lista
    @json($talentosSalvos).forEach(function(t){
        const grupo = (t && gruposTalentos[t.tipo]) || gruposTalentos.mundano;
        grupo.carregar(t);
    });
    Object.values(gruposTalentos).forEach(g => g.atualizar());
NEW];

// 10) sincronizarTalentos
$pares[] = [<<<'OLD'
    document.querySelectorAll("#lista-talentos .talento-card").forEach(function(card){
        const titulo = card.querySelector(".talento-titulo").value.trim();
        if(titulo === "") return;
        lista.push({
            titulo: titulo,
OLD, <<<'NEW'
    document.querySelectorAll(".lista-talentos .talento-card").forEach(function(card){
        const titulo = card.querySelector(".talento-titulo").value.trim();
        if(titulo === "") return;
        lista.push({
            tipo: card.dataset.tipo || "mundano",
            titulo: titulo,
NEW];

// 11) Técnicas
$pares[] = [<<<'OLD'
tecnicas.forEach(t => { t.id = proximoIdTecnica++; t.aprimoramentos = t.aprimoramentos || []; t.pasta = typeof t.pasta === "string" ? t.pasta : ""; t.eficiencia = Math.max(0, parseInt(t.eficiencia, 10) || 0); });
OLD, <<<'NEW'
tecnicas.forEach(t => { t.id = proximoIdTecnica++; t.aprimoramentos = t.aprimoramentos || []; t.pasta = typeof t.pasta === "string" ? t.pasta : ""; t.eficiencia = Math.max(0, parseInt(t.eficiencia, 10) || 0); t.tipo = t.tipo === "fisiomancia" ? "fisiomancia" : "hexomancia"; });
NEW];

$pares[] = [<<<'OLD'
document.getElementById("fechar-tecnicas").addEventListener("click", () => tecnicasCard.classList.remove("ativo"));
OLD, <<<'NEW'
document.getElementById("fechar-tecnicas").addEventListener("click", () => tecnicasCard.classList.remove("ativo"));

// Abas: Hexomancia (técnicas de maldição) e Fisiomancia (técnicas + propriedades fisiomantes). Cada técnica guarda o "tipo".
function abaTecnicas(){
    const b = tecnicasCard.querySelector(".aba-painel.ativo");
    return b && b.dataset.aba === "fisiomancia" ? "fisiomancia" : "hexomancia";
}
function rotuloNivelTecnica(tipo){
    return tipo === "fisiomancia" ? "Nível de arte requisitado" : "Nível de maldição requisitado";
}
tecnicasCard.addEventListener("aba-mudou", function(){ renderizarTecnicas(); });
NEW];

$pares[] = [<<<'OLD'
        pasta: document.getElementById("tec-pasta").value,
        aprimoramentos: aprimoramentos
    });
OLD, <<<'NEW'
        pasta: document.getElementById("tec-pasta").value,
        tipo: abaTecnicas(),
        aprimoramentos: aprimoramentos
    });
NEW];

$pares[] = [<<<'OLD'
function renderizarTecnicas(){
    const visiveis = tecnicas.filter(t => PastasFicha.visivel("tecnicas", t.pasta || ""));
OLD, <<<'NEW'
function renderizarTecnicas(){
    const aba = abaTecnicas();
    document.getElementById("tec-nivel-rotulo").textContent = rotuloNivelTecnica(aba);
    const daAba = tecnicas.filter(t => (t.tipo || "hexomancia") === aba);
    const visiveis = daAba.filter(t => PastasFicha.visivel("tecnicas", t.pasta || ""));
NEW];

$pares[] = [<<<'OLD'
                dadoTecnica("Nível de maldição requisitado", String(t.nivel)) +
OLD, <<<'NEW'
                dadoTecnica(rotuloNivelTecnica(t.tipo), String(t.nivel)) +
NEW];

$pares[] = [<<<'OLD'
    vazio.textContent = tecnicas.length === 0
        ? "Nenhuma técnica ainda. Abra “Nova técnica” acima para criar a primeira."
        : "Nenhuma técnica nesta pasta.";

    document.getElementById("cont-tecnicas").textContent = tecnicas.length > 0 ? tecnicas.length : "";
OLD, <<<'NEW'
    vazio.textContent = daAba.length === 0
        ? "Nenhuma técnica de " + (aba === "fisiomancia" ? "Fisiomancia" : "Hexomancia") + " ainda. Abra “Nova técnica” acima para criar a primeira."
        : "Nenhuma técnica nesta pasta.";

    document.getElementById("cont-tecnicas").textContent = tecnicas.length > 0 ? tecnicas.length : "";
    ["hexomancia", "fisiomancia"].forEach(function(tipo){
        const n = tecnicas.filter(t => (t.tipo || "hexomancia") === tipo).length;
        document.getElementById("cont-tecnicas-" + tipo).textContent = n > 0 ? n : "";
    });
NEW];

$pares[] = [<<<'OLD'
            mostrarPopup("Nível de Hexomante insuficiente. Esta técnica exige nível " + req + ".");
OLD, <<<'NEW'
            mostrarPopup((t.tipo === "fisiomancia" ? "Nível de arte insuficiente." : "Nível de Hexomante insuficiente.") + " Esta técnica exige nível " + req + ".");
NEW];

// 12) Propriedades fisiomantes (JS)
$pares[] = [<<<'OLD'
// ==================== Preenchimento Rápido (Teste) ====================
OLD, <<<'NEW'
// ==================== Propriedades fisiomantes (aba Fisiomancia das Técnicas) ====================
// Catálogo completo em resources/data/propriedades_fisiomantes.json; as propriedades salvas na ficha vão no JSON
// "propriedades_fisiomantes" (nome, fonte, ameaça, descrição e anotação). Pesquisa ignora acentos e maiúsculas.
(function(){
    const catalogo = @json($catalogoPropriedades);
    const fontes = Array.isArray(catalogo.fontes) ? catalogo.fontes : [];
    const grupos = (catalogo.grupos && typeof catalogo.grupos === "object") ? catalogo.grupos : {};
    let minhas = @json($propriedadesSalvas);
    if(!Array.isArray(minhas)) minhas = [];
    minhas = minhas.filter(p => p && typeof p.nome === "string" && p.nome.trim() !== "").map(p => ({
        nome: p.nome,
        fonte: p.fonte || "",
        ameaca: (p.ameaca === null || p.ameaca === undefined || p.ameaca === "") ? null : Number(p.ameaca),
        descricao: p.descricao || "",
        anotacao: p.anotacao || ""
    }));

    const campo = document.getElementById("propriedades-fisiomantes");
    const listaMinhas = document.getElementById("prop-minhas-lista");
    const vazioMinhas = document.getElementById("prop-minhas-vazio");
    const busca = document.getElementById("prop-busca");
    const selGrupo = document.getElementById("prop-grupo");
    const descGrupo = document.getElementById("prop-grupo-descricao");
    const cat = document.getElementById("prop-catalogo");
    const vazioCat = document.getElementById("prop-catalogo-vazio");
    if(!campo || !cat) return;

    const escP = t => String(t == null ? "" : t).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    const norm = s => String(s == null ? "" : s).normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
    const temAmeaca = p => p.ameaca !== null && p.ameaca !== undefined && p.ameaca !== "";

    // "Duro 2" em Pedras não repete o texto: a descrição vem da primeira fonte que a descreve (Metal)
    const porNome = {};
    fontes.forEach(f => (f.propriedades || []).forEach(p => {
        const k = norm(p.nome).replace(/\s+inverso$/, "");
        if(p.descricao && !/^consulte/i.test(p.descricao) && !porNome[k]) porNome[k] = { fonte: f.nome, texto: p.descricao };
    }));
    function textoDe(p){
        if(p.descricao) return { texto: p.descricao, ref: "" };
        const r = porNome[norm(p.nome).replace(/\s+inverso$/, "")];
        return r ? { texto: r.texto, ref: "Descrição de " + r.fonte + (/inverso$/i.test(p.nome) ? " (aqui com reversão)" : "") } : { texto: "", ref: "" };
    }

    function sincronizar(avisar){
        campo.value = JSON.stringify(minhas);
        document.getElementById("cont-propriedades").textContent = minhas.length > 0 ? minhas.length : "";
        if(avisar) campo.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function renderMinhas(){
        listaMinhas.innerHTML = minhas.map(function(p, i){
            return '<div class="prop-card" data-i="' + i + '">' +
                '<div class="prop-item-cab"><strong>' + escP(p.nome) + '</strong>' +
                    (p.fonte ? '<span class="prop-ameaca">' + escP(p.fonte) + '</span>' : '') +
                    '<button type="button" class="botao-remover-item prop-remover">Remover</button></div>' +
                (p.descricao ? '<p>' + escP(p.descricao) + '</p>' : '') +
                '<div class="prop-card-linha">' +
                    '<label>Ameaça <input type="number" class="prop-ameaca-input" value="' + (p.ameaca === null ? '' : escP(p.ameaca)) + '" placeholder="—"></label>' +
                    '<input type="text" class="prop-anotacao-input" maxlength="2000" placeholder="Anotação (alvo, duração, origem…)" value="' + escP(p.anotacao) + '">' +
                '</div></div>';
        }).join("");
        vazioMinhas.style.display = minhas.length === 0 ? "block" : "none";
    }

    function renderGrupos(){
        Object.keys(grupos).forEach(function(k){
            const o = document.createElement("option");
            o.value = k;
            o.textContent = grupos[k].nome || k;
            selGrupo.appendChild(o);
        });
    }

    function renderCatalogo(){
        const termo = norm(busca.value.trim());
        const grupo = selGrupo.value;
        descGrupo.textContent = grupo && grupos[grupo] ? (grupos[grupo].descricao || "") : "";
        let total = 0;
        cat.innerHTML = fontes.map(function(f, fi){
            if(grupo && f.categoria !== grupo) return "";
            const fonteBate = termo !== "" && norm(f.nome).includes(termo);
            const props = (f.propriedades || []).map((p, pi) => ({ p: p, pi: pi })).filter(function(x){
                if(termo === "" || fonteBate) return true;
                const d = textoDe(x.p);
                return norm(x.p.nome).includes(termo) || norm(d.texto).includes(termo) || norm(x.p.ganho).includes(termo);
            });
            if(props.length === 0) return "";
            total += props.length;
            const aberto = termo !== "" || f.categoria === "pessoal" || f.categoria === "variavel";
            return '<details class="prop-fonte"' + (aberto ? " open" : "") + '><summary>' + escP(f.nome) +
                ' <span class="prop-qtd">' + props.length + (props.length === 1 ? " propriedade" : " propriedades") + '</span></summary>' +
                (f.descricao ? '<p class="prop-fonte-descricao">' + escP(f.descricao) + '</p>' : '') +
                props.map(function(x){
                    const d = textoDe(x.p);
                    return '<div class="prop-item"><div class="prop-item-cab"><strong>' + escP(x.p.nome) + '</strong>' +
                        (temAmeaca(x.p) ? '<span class="prop-ameaca">Ameaça ' + escP(x.p.ameaca) + '</span>' : '') +
                        '<button type="button" class="button prop-salvar" data-f="' + fi + '" data-p="' + x.pi + '">Salvar</button></div>' +
                        (x.p.ganho ? '<p><em>Ganho de ameaça:</em> ' + escP(x.p.ganho) + '</p>' : '') +
                        (d.texto ? '<p>' + escP(d.texto) + '</p>' : '') +
                        (d.ref ? '<p class="prop-ref">' + escP(d.ref) + '</p>' : '') +
                    '</div>';
                }).join("") + '</details>';
        }).join("");
        vazioCat.style.display = total === 0 ? "block" : "none";
    }

    busca.addEventListener("input", renderCatalogo);
    selGrupo.addEventListener("change", renderCatalogo);

    cat.addEventListener("click", function(e){
        const b = e.target.closest(".prop-salvar");
        if(!b) return;
        const f = fontes[Number(b.dataset.f)];
        const p = f && f.propriedades ? f.propriedades[Number(b.dataset.p)] : null;
        if(!p) return;
        const d = textoDe(p);
        minhas.unshift({
            nome: p.nome,
            fonte: f.nome,
            ameaca: (typeof p.ameaca === "number") ? p.ameaca : null,
            descricao: ((p.ganho ? "Ganho de ameaça: " + p.ganho + " " : "") + d.texto).trim(),
            anotacao: ""
        });
        renderMinhas();
        sincronizar(true);
        b.textContent = "Salva ✓";
        setTimeout(function(){ b.textContent = "Salvar"; }, 1200);
    });

    listaMinhas.addEventListener("click", function(e){
        if(!e.target.classList.contains("prop-remover")) return;
        const i = Number(e.target.closest(".prop-card").dataset.i);
        if(!minhas[i] || !confirm("Remover “" + minhas[i].nome + "” das suas propriedades?")) return;
        minhas.splice(i, 1);
        renderMinhas();
        sincronizar(true);
    });

    listaMinhas.addEventListener("input", function(e){
        const card = e.target.closest(".prop-card");
        if(!card) return;
        const p = minhas[Number(card.dataset.i)];
        if(!p) return;
        if(e.target.classList.contains("prop-ameaca-input")) p.ameaca = e.target.value === "" ? null : (parseInt(e.target.value, 10) || 0);
        if(e.target.classList.contains("prop-anotacao-input")) p.anotacao = e.target.value;
        sincronizar(true);
    });

    document.getElementById("prop-pers-salvar").addEventListener("click", function(){
        const nome = document.getElementById("prop-pers-nome").value.trim();
        if(nome === ""){
            mostrarPopup("Digite um nome para a propriedade.");
            return;
        }
        const am = document.getElementById("prop-pers-ameaca").value;
        minhas.unshift({
            nome: nome,
            fonte: document.getElementById("prop-pers-fonte").value.trim(),
            ameaca: am === "" ? null : (parseInt(am, 10) || 0),
            descricao: document.getElementById("prop-pers-descricao").value.trim(),
            anotacao: ""
        });
        ["prop-pers-nome", "prop-pers-fonte", "prop-pers-ameaca", "prop-pers-descricao"].forEach(id => document.getElementById(id).value = "");
        document.getElementById("prop-personalizada").open = false;
        renderMinhas();
        sincronizar(true);
    });

    renderGrupos();
    renderMinhas();
    renderCatalogo();
    sincronizar(false);
})();

// ==================== Preenchimento Rápido (Teste) ====================
NEW];

// 13) somente leitura: pesquisa livre e abas clicáveis
$pares[] = [<<<'OLD'
        if(el.type === "hidden") return;
        if(el.tagName === "SELECT" || ["checkbox", "file", "color", "range", "number"].includes(el.type)) el.disabled = true;
OLD, <<<'NEW'
        if(el.type === "hidden" || el.id === "prop-busca" || el.id === "prop-grupo") return; // a pesquisa do catálogo continua livre
        if(el.tagName === "SELECT" || ["checkbox", "file", "color", "range", "number"].includes(el.type)) el.disabled = true;
NEW];

$pares[] = [<<<'OLD'
        const permitido = /abrir|fechar|ocultar|botao-rolar-habilidade|rolar-dado|rolarD100|pasta-chip|pasta-link/i.test(marca)
OLD, <<<'NEW'
        const permitido = /abrir|fechar|ocultar|botao-rolar-habilidade|rolar-dado|rolarD100|pasta-chip|pasta-link|aba-painel/i.test(marca)
NEW];

// ---- conferência e gravação ----
$erros = [];
foreach ($pares as $i => [$antigo, $novo]) {
    $n = substr_count($src, $antigo);
    if ($n !== 1) $erros[] = "trecho " . ($i + 1) . ": encontrado $n vezes (" . substr(strtok($antigo, "\n"), 0, 70) . ")";
}
if ($erros) { fwrite(STDERR, implode("\n", $erros) . "\nNada gravado.\n"); exit(1); }
foreach ($pares as [$antigo, $novo]) $src = str_replace($antigo, $novo, $src);
if ($crlf) $src = str_replace("\n", "\r\n", $src);
file_put_contents($arquivo, $src);
echo count($pares) . " trechos aplicados.\n";
