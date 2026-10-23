(function(){
    function lerSpan(span){
        const p = span.textContent.split("/").map(s => parseInt(s.trim(), 10));
        return { atual: isNaN(p[0]) ? null : p[0], max: isNaN(p[1]) ? null : p[1] };
    }

    function pct(atual, max){
        return max > 0 ? Math.max(0, Math.min(100, (atual / max) * 100)) : 0;
    }

    // Campo com botões − / + e input numérico
    // Campo com input numérico (as setinhas nativas ajustam de 1 em 1)
    function criarControle(rotulo, aoMudar){
        const wrap = document.createElement("div");
        wrap.className = "vital-ajuste-campo";

        const lbl = document.createElement("span");
        lbl.textContent = rotulo;

        const input = document.createElement("input");
        input.type = "number";

        // "input" atualiza ao digitar ou clicar nas setinhas; "change" garante o valor final ao sair do campo
        input.addEventListener("change", () => aoMudar(Number(input.value || 0)));

        wrap.append(lbl, input);

        return {
            el: wrap,
            set(v){
                if(document.activeElement !== input){
                    input.value = (v === null || v === undefined) ? "" : v;
                }
            }
        };
    }

    // ---------- Vida, Sanidade e Aura (os spans continuam sendo a fonte da verdade) ----------
    ["vida", "sanidade", "aura"].forEach(function(id){
        const span = document.getElementById(id);
        const painel = document.querySelector('.vital-ajuste[data-vital="' + id + '"]');
        const barra = span.closest(".barra-grupo").querySelector(".barra-preenchimento");

        function ler(){
            const l = lerSpan(span);
            return { atual: l.atual, max: l.max };
        }

        function gravar(atual, max){
            span.textContent = atual + " / " + max;
        }

        const ctrlAtual = criarControle("Atual", function(v){
            const s = ler();
            const max = s.max === null ? 0 : s.max;
            gravar(Math.min(v, max), max);
        });

        const ctrlMax = criarControle("Máx", function(v){
            const s = ler();
            const novoMax = Math.max(0, v);
            // se ainda não havia valor rolado, o atual começa cheio
            const atual = s.atual === null ? novoMax : Math.min(s.atual, novoMax);
            gravar(atual, novoMax);
        });

        painel.append(ctrlAtual.el, ctrlMax.el);

        function atualizar(){
            const s = ler();
            ctrlAtual.set(s.atual);
            ctrlMax.set(s.max);
            span.style.color = (s.atual !== null && s.atual < 0) ? "#ff6b6b" : "white";
            barra.style.width = (s.atual === null || s.max === null) ? "100%" : pct(s.atual, s.max) + "%";
        }

        // qualquer código existente que altere o texto do span também atualiza barra e campos
        new MutationObserver(atualizar).observe(span, { childList: true, characterData: true, subtree: true });
        atualizar();
    });

    // ---------- Atributos vitais personalizados ----------
    const listaExtras = document.getElementById("vitais-extras-lista");
    const campoExtras = document.getElementById("vitais_extras");
    const extrasSalvos = Array.isArray(window.VITAIS_EXTRAS) ? window.VITAIS_EXTRAS : [];
    let extras = extrasSalvos.map(function(v, i){
        const max = Math.max(0, parseInt(v.max, 10) || 0);
        return {
            id: i + 1,
            nome: String(v.nome || "Sem nome"),
            cor: /^#[0-9a-f]{6}$/i.test(v.cor) ? v.cor : "#3b82f6",
            atual: Math.min(parseInt(v.atual, 10) || 0, max),
            max: max
        };
    });
    let proximoId = extras.length + 1;

    function salvarExtras(){
        campoExtras.value = JSON.stringify(extras.map(function(v){
            return { nome: v.nome, cor: v.cor, atual: v.atual, max: v.max };
        }));
    }

    function criarBlocoExtra(v){
        const grupo = document.createElement("div");
        grupo.className = "barra-grupo vital-extra";

        const topo = document.createElement("div");
        topo.className = "barra-topo";
        const nome = document.createElement("span");
        nome.className = "barra-nome";
        nome.textContent = v.nome;
        const valor = document.createElement("span");
        valor.className = "barra-valor";
        valor.style.color = "white";
        topo.append(nome, valor);

        const fundo = document.createElement("div");
        fundo.className = "barra-fundo";
        const preench = document.createElement("div");
        preench.className = "barra-preenchimento";
        fundo.appendChild(preench);

        const painel = document.createElement("div");
        painel.className = "vital-ajuste";

        const ctrlAtual = criarControle("Atual", function(n){
            v.atual = Math.min(n, v.max);
            atualizar();
        });
        const ctrlMax = criarControle("Máx", function(n){
            v.max = Math.max(0, n);
            v.atual = Math.min(v.atual, v.max);
            atualizar();
        });

        const campoCor = document.createElement("div");
        campoCor.className = "vital-ajuste-campo";
        const lblCor = document.createElement("span");
        lblCor.textContent = "Cor";
        const inputCor = document.createElement("input");
        inputCor.type = "color";
        inputCor.value = v.cor;
        inputCor.addEventListener("input", function(){
            v.cor = inputCor.value;
            atualizar();
        });
        campoCor.append(lblCor, inputCor);

        const remover = document.createElement("button");
        remover.type = "button";
        remover.className = "botao-remover-item";
        remover.textContent = "Remover";
        remover.addEventListener("click", function(){
            extras = extras.filter(x => x.id !== v.id);
            grupo.remove();
            salvarExtras();
        });

        painel.append(ctrlAtual.el, ctrlMax.el, campoCor, remover);
        grupo.append(topo, fundo, painel);

        function atualizar(){
            valor.textContent = v.atual + " / " + v.max;
            valor.style.color = v.atual < 0 ? "#ff6b6b" : "white";
            preench.style.width = pct(v.atual, v.max) + "%";
            preench.style.background = v.cor;
            ctrlAtual.set(v.atual);
            ctrlMax.set(v.max);
            salvarExtras();
        }

        atualizar();
        return grupo;
    }

    extras.forEach(v => listaExtras.appendChild(criarBlocoExtra(v)));
    salvarExtras();

    document.getElementById("vital-novo-adicionar").addEventListener("click", function(){
        const inNome = document.getElementById("vital-novo-nome");
        const inCor = document.getElementById("vital-novo-cor");
        const inAtual = document.getElementById("vital-novo-atual");
        const inMax = document.getElementById("vital-novo-max");

        const nome = inNome.value.trim();
        if(nome === ""){
            mostrarPopup("Digite um nome para o atributo vital antes de adicionar.");
            return;
        }

        const max = Math.max(0, parseInt(inMax.value, 10) || 0);
        const atual = inAtual.value === "" ? max : Math.min(parseInt(inAtual.value, 10) || 0, max);

        const v = { id: proximoId++, nome: nome, cor: inCor.value, atual: atual, max: max };
        extras.push(v);
        listaExtras.appendChild(criarBlocoExtra(v));

        inNome.value = "";
        inAtual.value = "";
        inMax.value = "";
    });
})();