{{-- resources/views/partials/anotacoes.blade.php --}}
@php
    $anotacoesSalvas = $personagem->anotacoes ?? [];
    if (is_string($anotacoesSalvas)) {
        $anotacoesSalvas = json_decode($anotacoesSalvas, true) ?: [];
    }
@endphp

<style>
    #anotacoes-card { overflow-x: hidden; }

    /* Quebra de linha automática: lado a lado se couber, empilhado se não */
    .anotacoes-layout {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 10px;
        text-align: left;
    }
    .anotacoes-lateral {
        flex: 1 1 190px;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .anotacoes-principal {
        flex: 2 1 260px;
        min-width: 0;
    }

    .anotacoes-lateral input[type="search"] {
        width: 100%;
        box-sizing: border-box;
    }
    .anotacoes-contagem {
        margin: 0;
        font-size: .8rem;
        color: var(--tinta-fraca, #aaa);
    }
    #anotacoes-lista {
        display: flex;
        flex-direction: column;
        gap: 6px;
        max-height: 260px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .anotacao-item {
        display: flex;
        align-items: stretch;
        gap: 4px;
        min-width: 0;
    }
    .anotacao-arquivo {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 8px 12px;
        text-align: left;
        cursor: pointer;
        border-radius: 8px;
        background: #1f1212;
        color: #fff;
        border: 1px solid var(--linha, #444);
        transition: .2s;
    }
    .anotacao-arquivo:hover { filter: brightness(1.4); }
    .anotacao-arquivo.ativa {
        border-color: rgba(189, 3, 3, 0.8);
        background: #2a1414;
    }
    .anotacao-titulo-lista,
    .anotacao-data-lista {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .anotacao-data-lista {
        font-size: .72rem;
        color: var(--tinta-fraca, #aaa);
    }

    .anotacao-pin {
        flex: 0 0 auto;
        width: 34px;
        padding: 0;
        cursor: pointer;
        border-radius: 8px;
        background: #1f1212;
        color: #fff;
        border: 1px solid var(--linha, #444);
        opacity: .55;
        transition: .2s;
    }
    .anotacao-pin:hover { opacity: 1; filter: brightness(1.3); }
    .anotacao-pin.fixada { opacity: 1; border-color: rgba(189, 3, 3, 0.8); }

    .anotacoes-editor {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }
    .anotacoes-editor input,
    .anotacoes-editor textarea {
        width: 100%;
        box-sizing: border-box;
    }
    .anotacoes-editor textarea {
        min-height: 240px;
        max-height: 50vh;
        resize: vertical;
        white-space: pre-wrap;
        word-break: break-word;
        overflow-wrap: break-word;
        font-family: inherit;
        line-height: 1.4;
    }
    .anotacoes-info {
        margin: 0;
        font-size: .78rem;
        color: var(--tinta-fraca, #aaa);
    }
    .anotacoes-barra {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .anotacoes-barra button { flex: 1 1 auto; }
    .anotacao-acao {
        padding: 8px 12px;
        cursor: pointer;
        border-radius: 8px;
        background: #1f1212;
        color: #fff;
        border: 1px solid var(--linha, #444);
        transition: .2s;
    }
    .anotacao-acao:hover { filter: brightness(1.4); }

    .anotacoes-vazio {
        text-align: center;
        color: var(--tinta-fraca, #aaa);
        margin: 24px 0;
    }
</style>

<div class="card card-rolagem card-talentos" id="anotacoes-card" style="max-height: 80%; overflow-y:auto;">
    <button type="button" class="botao-fechar" id="fechar-anotacoes">X</button>

    <div class="botoes-grid" style="text-align:center">
        <h1>Anotações</h1>
    </div>

    <div class="anotacoes-layout">
        <div class="anotacoes-lateral">
            <button type="button" class="botao-adicionar-talento" id="anotacao-nova">+ Nova nota</button>
            <input type="search" id="anotacao-busca" placeholder="Buscar nas notas..." autocomplete="off">
            <p class="anotacoes-contagem" id="anotacoes-contagem"></p>
            <div id="anotacoes-lista"></div>
        </div>

        <div class="anotacoes-principal">
            <p class="anotacoes-vazio" id="anotacoes-vazio">Nenhuma nota criada ainda.</p>

            <div class="anotacoes-editor" id="anotacoes-editor" style="display:none">
                <input type="text" id="anotacao-titulo" maxlength="100" placeholder="Nome da nota (ex: Pistas do caso)">
                <textarea id="anotacao-texto" placeholder="Escreva sua anotação aqui..."></textarea>
                <p class="anotacoes-info" id="anotacao-info"></p>
                <div class="anotacoes-barra">
                    <button type="button" class="anotacao-acao" id="anotacao-duplicar">Duplicar</button>
                    <button type="button" class="botao-remover-item" id="anotacao-excluir">Excluir nota</button>
                </div>
                <p class="anotacoes-info">As notas são gravadas junto com a ficha. Clique em salvar a ficha para guardá-las.</p>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="anotacoes" name="anotacoes" value="">

<script>
document.addEventListener("DOMContentLoaded", function(){
    let proximoId = 1;
    function normalizarNota(n){
        n = n || {};
        return {
            id: proximoId++,
            titulo: String(n.titulo || "Sem título"),
            texto: String(n.texto || ""),
            fixada: !!n.fixada,
            atualizado: Number(n.atualizado) || null
        };
    }

    let notas = @json($anotacoesSalvas);
    if(!Array.isArray(notas)) notas = [];
    notas = notas.map(normalizarNota);

    let atual = notas.length ? notas[0] : null;
    let busca = "";

    const card = document.getElementById("anotacoes-card");
    const botaoAbrir = document.getElementById("anotacoes-abrir");
    const botaoFechar = document.getElementById("fechar-anotacoes");
    const botaoNova = document.getElementById("anotacao-nova");
    const inputBusca = document.getElementById("anotacao-busca");
    const contagem = document.getElementById("anotacoes-contagem");
    const lista = document.getElementById("anotacoes-lista");
    const vazio = document.getElementById("anotacoes-vazio");
    const editor = document.getElementById("anotacoes-editor");
    const inputTitulo = document.getElementById("anotacao-titulo");
    const inputTexto = document.getElementById("anotacao-texto");
    const info = document.getElementById("anotacao-info");
    const botaoExcluir = document.getElementById("anotacao-excluir");
    const botaoDuplicar = document.getElementById("anotacao-duplicar");
    const campoOculto = document.getElementById("anotacoes");

    function norm(s){
        return String(s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }

    function formatarData(ts){
        if(!ts) return "";
        return new Date(ts).toLocaleString("pt-BR", {
            day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit"
        });
    }

    function sincronizar(){
        campoOculto.value = JSON.stringify(notas.map(function(n){
            return { titulo: n.titulo, texto: n.texto, fixada: n.fixada, atualizado: n.atualizado };
        }));
    }

    function visiveis(){
        const q = norm(busca.trim());
        const filtradas = notas.filter(function(n){
            return !q || norm(n.titulo).includes(q) || norm(n.texto).includes(q);
        });
        // fixadas primeiro, mantendo a ordem original dentro de cada grupo
        return filtradas.filter(n => n.fixada).concat(filtradas.filter(n => !n.fixada));
    }

    function renderLista(){
        lista.innerHTML = "";
        const itens = visiveis();

        contagem.textContent = notas.length
            ? (busca.trim() ? itens.length + " de " + notas.length + " notas" : notas.length + (notas.length === 1 ? " nota" : " notas"))
            : "";

        itens.forEach(function(n){
            const linha = document.createElement("div");
            linha.className = "anotacao-item";

            const b = document.createElement("button");
            b.type = "button";
            b.className = "anotacao-arquivo" + (n === atual ? " ativa" : "");
            b.title = n.titulo;

            const t = document.createElement("span");
            t.className = "anotacao-titulo-lista";
            t.textContent = "📄 " + (n.titulo || "Sem título");
            b.appendChild(t);

            if(n.atualizado){
                const d = document.createElement("span");
                d.className = "anotacao-data-lista";
                d.textContent = formatarData(n.atualizado);
                b.appendChild(d);
            }
            b.addEventListener("click", function(){ selecionar(n); });

            const pin = document.createElement("button");
            pin.type = "button";
            pin.className = "anotacao-pin" + (n.fixada ? " fixada" : "");
            pin.textContent = "📌";
            pin.title = n.fixada ? "Desafixar" : "Fixar no topo";
            pin.addEventListener("click", function(){
                n.fixada = !n.fixada;
                sincronizar();
                renderLista();
            });

            linha.appendChild(b);
            linha.appendChild(pin);
            lista.appendChild(linha);
        });
    }

    function atualizarInfo(){
        if(!atual){ info.textContent = ""; return; }
        const txt = atual.texto.trim();
        const palavras = txt ? txt.split(/\s+/).length : 0;
        const partes = [palavras + (palavras === 1 ? " palavra" : " palavras"), atual.texto.length + " caracteres"];
        if(atual.atualizado) partes.unshift("Editada em " + formatarData(atual.atualizado));
        info.textContent = partes.join(" · ");
    }

    function mostrarEditor(){
        const temNota = !!atual;
        editor.style.display = temNota ? "flex" : "none";
        vazio.style.display = temNota ? "none" : "block";
        vazio.textContent = notas.length ? "Selecione uma nota na lista." : "Nenhuma nota criada ainda.";
        if(temNota){
            inputTitulo.value = atual.titulo;
            inputTexto.value = atual.texto;
        }
        atualizarInfo();
    }

    function selecionar(n){
        atual = n;
        renderLista();
        mostrarEditor();
    }

    function marcarEditada(){
        atual.atualizado = Date.now();
        sincronizar();
        atualizarInfo();
        renderLista();
    }

    botaoNova.addEventListener("click", function(){
        const n = normalizarNota({ titulo: "Nova nota " + (notas.length + 1), texto: "" });
        n.atualizado = Date.now();
        notas.push(n);
        // limpa a busca para a nota nova aparecer na lista
        busca = "";
        inputBusca.value = "";
        sincronizar();
        selecionar(n);
        inputTitulo.focus();
        inputTitulo.select();
    });

    botaoDuplicar.addEventListener("click", function(){
        if(!atual) return;
        const copia = normalizarNota({
            titulo: (atual.titulo || "Sem título") + " (cópia)",
            texto: atual.texto,
            fixada: false
        });
        copia.atualizado = Date.now();
        notas.splice(notas.indexOf(atual) + 1, 0, copia);
        sincronizar();
        selecionar(copia);
    });

    inputBusca.addEventListener("input", function(){
        busca = inputBusca.value;
        renderLista();
    });
    inputBusca.addEventListener("keydown", function(e){
        if(e.key === "Enter") e.preventDefault();
    });

    inputTitulo.addEventListener("input", function(){
        if(!atual) return;
        atual.titulo = inputTitulo.value;
        marcarEditada();
    });

    // Enter no título não deve enviar o formulário
    inputTitulo.addEventListener("keydown", function(e){
        if(e.key === "Enter") e.preventDefault();
    });

    inputTexto.addEventListener("input", function(){
        if(!atual) return;
        atual.texto = inputTexto.value;
        marcarEditada();
    });

    botaoExcluir.addEventListener("click", function(){
        if(!atual) return;
        if(!confirm('Excluir a nota "' + (atual.titulo || "Sem título") + '"?')) return;
        const i = notas.indexOf(atual);
        notas.splice(i, 1);
        atual = notas.length ? notas[Math.min(i, notas.length - 1)] : null;
        sincronizar();
        renderLista();
        mostrarEditor();
    });

    botaoAbrir.addEventListener("click", function(){ card.classList.add("ativo"); });
    botaoFechar.addEventListener("click", function(){ card.classList.remove("ativo"); });
    document.addEventListener("keydown", function(e){
        if(e.key === "Escape") card.classList.remove("ativo");
    });

    // Garante o envio do JSON atualizado ao salvar a ficha
    const form = botaoAbrir.closest("form");
    if(form){
        form.addEventListener("submit", function(){
            notas.forEach(function(n){
                if(n.titulo.trim() === "") n.titulo = "Sem título";
            });
            // descarta só notas totalmente vazias (sem título real e sem texto)
            notas = notas.filter(function(n){
                return n.texto.trim() !== "" || n.titulo.trim() !== "Sem título";
            });
            sincronizar();
        });
    }

    sincronizar();
    renderLista();
    mostrarEditor();
});
</script>