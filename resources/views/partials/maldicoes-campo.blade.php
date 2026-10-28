@php
    $valorMaldicoes = old('maldicoes', $personagem->maldicoes ?? '');
    $usuarioAtual   = auth()->user();
    $listaMaldicoes = collect(\App\Support\AcessoMaldicoes::lista($usuarioAtual))->values()->all();
    $podeVerRestr   = \App\Support\AcessoMaldicoes::podeVerRestritas($usuarioAtual);
    $nomesLista     = array_map(fn ($i) => ['nome' => $i['nome'] ?? '', 'slug' => $i['slug'] ?? null], $listaMaldicoes);
    $campoSoLeitura = !empty($somenteLeitura ?? false);
@endphp

<style>
    .campo-maldicoes{
        align-items: start;
    }

    .campo-maldicoes > label{
        padding-top: 12px;
    }

    .maldicoes-wrap{
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }

    .campo-maldicoes input{
        width: 100%;
    }

    .campo-dica{
        margin: 0;
        font-family: var(--fonte-corpo);
        font-size: .75rem;
        line-height: 1.5;
        color: var(--tinta-fraca);
    }

    .campo-dica code{
        background: #150c0c;
        border: 1px solid var(--linha);
        border-radius: 6px;
        padding: 1px 6px;
        color: var(--sangue-4);
        font-size: .72rem;
    }

    .maldicoes-preview{
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-height: 4px;
    }

    .maldicao-chip{
        display: inline-flex;
        align-items: center;
        gap: 8px;

        background: rgba(189, 3, 3, .15);
        border: 1px solid var(--sangue-2);
        border-radius: 999px;
        padding: 4px 12px;

        font-family: var(--fonte-titulo);
        font-size: .7rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--sangue-4);

        max-width: 100%;
        overflow-wrap: anywhere;
        transition: .3s;
    }

    .maldicao-chip:hover{
        background: rgba(189, 3, 3, .28);
        box-shadow: 0 0 10px rgba(182, 37, 37, .3);
    }

    .maldicao-chip .ameaca{
        padding-left: 8px;
        border-left: 1px solid var(--sangue-1);
        color: var(--tinta);
    }

    .maldicao-chip.sem-ficha{
        background: #1f1212;
        border-color: var(--linha);
        color: var(--tinta-fraca);
    }

    .maldicao-chip.sem-ficha::after{
        content: "sem página";
        font-size: .6rem;
        letter-spacing: .5px;
        opacity: .7;
    }

    .maldicoes-adicionar{ display: flex; gap: 8px; flex-wrap: wrap; }
    .maldicoes-adicionar select{ flex: 1 1 180px; min-width: 0; }
    .maldicoes-add{
        cursor: pointer; background: rgba(0,0,0,.25);
        border: 1px solid rgba(255,255,255,.14); border-radius: 8px;
        padding: 6px 14px; color: var(--tinta);
    }
    .maldicoes-add:hover{ border-color: #8b1a1a; }
    .campo-maldicoes .campo-dica a{ color: var(--tinta-fraca); text-decoration: underline; }
</style>

<div class="campo campo-maldicoes">
    <label for="maldicoes">Maldições</label>
    <div class="maldicoes-wrap">
        <input
            id="maldicoes"
            name="maldicoes"
            type="text"
            autocomplete="off"
            value="{{ $valorMaldicoes }}"
            placeholder="Ex: Maldição do Justiceiro (Ameaça 2); Outra Maldição (Ameaça 0)">
        <div class="maldicoes-preview" id="maldicoes-preview"></div>
    </div>
</div>

<script>
(function(){
    const input   = document.getElementById("maldicoes");
    const preview = document.getElementById("maldicoes-preview");
    const itens = @json($nomesLista);
    const comPagina = new Set(itens.filter(function(i){ return i.slug; }).map(function(i){ return slugify(i.nome); }));
    const escolha = document.getElementById("maldicoes-escolha");
    const botaoAdd = document.getElementById("maldicoes-add");

    function slugify(texto){
        return texto
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }

    function renderizar(){
        preview.innerHTML = "";

        input.value.split(/[;,\n]+/).forEach(function(entrada){
            entrada = entrada.trim();
            if(entrada === "" || entrada.toLowerCase() === "nenhuma") return;

            let nome = entrada;
            let ameaca = null;
            const m = entrada.match(/^(.*?)\s*\(\s*Amea[çc]a\s*(\d+)\s*\)$/i);
            if(m){
                nome = m[1].trim();
                ameaca = m[2];
            }

            const chip = document.createElement("span");
            chip.className = "maldicao-chip" + (comPagina.has(slugify(nome)) ? "" : " sem-ficha");

            const rotulo = document.createElement("span");
            rotulo.textContent = nome;
            chip.appendChild(rotulo);

            if(ameaca !== null){
                const a = document.createElement("span");
                a.className = "ameaca";
                a.textContent = "Ameaça " + ameaca;
                chip.appendChild(a);
            }

            preview.appendChild(chip);
        });
    }

    if(botaoAdd && escolha){
        botaoAdd.addEventListener("click", function(){
            const nome = escolha.value;
            if(!nome) return;
            const atual = input.value.trim().replace(/[;,\s]+$/, "");
            input.value = (atual === "" || atual.toLowerCase() === "nenhuma" ? "" : atual + "; ") + nome + " (Ameaça 0)";
            escolha.value = "";
            input.dispatchEvent(new Event("input", { bubbles: true }));
        });
    }

    input.addEventListener("input", renderizar);
    input.addEventListener("change", renderizar);
    renderizar();
})();
</script>