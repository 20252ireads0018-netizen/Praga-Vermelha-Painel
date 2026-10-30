{{-- Aba Dinheiro da ficha: o saldo do personagem. Ganhar, gastar e definir gravam na hora (rota própria), fora do
     salvamento da ficha: o autosave não repete nada e as apostas do Gladio mexem no mesmo saldo sem conflito. --}}
@php($fichaExiste = isset($personagem) && $personagem->exists)
<style>
    #dinheiro-card { overflow-x: hidden; }
    .dinheiro-saldo { text-align: center; font-size: 2.2rem; font-weight: 800; margin: 6px 0 2px; color: #f3d27a; }
    .dinheiro-saldo small { display: block; font-size: .8rem; font-weight: 400; opacity: .7; color: inherit; }
    .dinheiro-operar { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px; align-items: center; margin: 14px 0 8px; }
    .dinheiro-operar input { height: 42px; box-sizing: border-box; min-width: 0; text-align: center; font-size: 1.05rem; }
    .dinheiro-operar button { height: 42px; padding: 0 14px; white-space: nowrap; }
    .dinheiro-ajuste { display: flex; align-items: center; gap: 8px; font-size: .85rem; opacity: .85; flex-wrap: wrap; }
    .dinheiro-ajuste input { width: 140px; height: 34px; box-sizing: border-box; text-align: center; }
    .dinheiro-historico { list-style: none; margin: 12px 0 0; padding: 0; font-size: .85rem; display: flex; flex-direction: column; gap: 4px; }
    .dinheiro-historico li { display: flex; justify-content: space-between; gap: 10px; padding: 4px 8px; border-radius: 6px; background: rgba(0, 0, 0, .25); }
    .dinheiro-historico .mais { color: #86efac; }
    .dinheiro-historico .menos { color: #fca5a5; }
    .dinheiro-dica { font-size: .8rem; opacity: .7; margin-top: 10px; }
    .dinheiro-erro { color: #fca5a5; font-size: .85rem; min-height: 1.1em; margin: 4px 0 0; }
</style>

<div class="card card-rolagem card-talentos" id="dinheiro-card" style="max-height: 80%; overflow-y:auto;">
    <button type="button" class="botao-fechar" id="fechar-dinheiro" title="Fechar (Esc)" aria-label="Fechar">✕</button>
    <div class="botoes-grid" style="text-align:center"><h1>Dinheiro</h1></div>

    @if ($fichaExiste)
        <p class="dinheiro-saldo"><span id="dinheiro-saldo" data-url="{{ route('personagens.dinheiro', $personagem->id) }}" data-valor="{{ (int) ($personagem->dinheiro ?? 0) }}">{{ number_format((int) ($personagem->dinheiro ?? 0), 0, ',', '.') }}</span> $<small>Dinheiro do personagem</small></p>
        <div class="dinheiro-operar">
            <input type="number" id="dinheiro-valor" min="1" placeholder="Valor">
            <button type="button" class="button" id="dinheiro-ganhar">+ Ganhar</button>
            <button type="button" class="button" id="dinheiro-gastar">− Gastar</button>
        </div>
        <label class="dinheiro-ajuste">Ou defina o saldo: <input type="number" id="dinheiro-definir" min="0" placeholder="Novo saldo"></label>
        <p class="dinheiro-erro" id="dinheiro-erro"></p>
        <ul class="dinheiro-historico" id="dinheiro-historico"></ul>
        <p class="dinheiro-dica">Grava na hora, sem precisar salvar a ficha. No Gladio, dá para apostar este Dinheiro: o valor sai daqui quando a partida começa e o prêmio volta para cá.</p>
    @else
        <p class="dinheiro-dica">Crie a ficha primeiro: o Dinheiro do personagem aparece aqui depois que ela for salva.</p>
    @endif
</div>

<script>
(function(){
    const card = document.getElementById("dinheiro-card");
    const abrir = document.getElementById("dinheiro-abrir");
    if(!card) return;
    if(abrir) abrir.addEventListener("click", function(){ card.classList.add("ativo"); });
    document.getElementById("fechar-dinheiro").addEventListener("click", function(){ card.classList.remove("ativo"); });
    document.addEventListener("keydown", function(e){ if(e.key === "Escape") card.classList.remove("ativo"); });

    const saldo = document.getElementById("dinheiro-saldo");
    if(!saldo) return;
    const valor = document.getElementById("dinheiro-valor");
    const definir = document.getElementById("dinheiro-definir");
    const historico = document.getElementById("dinheiro-historico");
    const erro = document.getElementById("dinheiro-erro");
    const fmt = n => Number(n).toLocaleString("pt-BR");
    const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || (document.querySelector('input[name="_token"]') || {}).value || "";
    let enviando = false;

    async function enviar(dados, texto){
        if(enviando) return;
        enviando = true;
        erro.textContent = "";
        const antes = Number(saldo.dataset.valor) || 0;
        try {
            const fd = new FormData();
            fd.append("_token", token);
            Object.keys(dados).forEach(k => fd.append(k, dados[k]));
            const r = await fetch(saldo.dataset.url, { method: "POST", body: fd, headers: { Accept: "application/json" }, credentials: "same-origin" });
            const j = await r.json().catch(() => ({}));
            if(!r.ok || j.ok === false) throw new Error(j.erro || j.message || "Não foi possível gravar (erro " + r.status + ").");
            const novo = Number(j.dinheiro) || 0;
            saldo.dataset.valor = String(novo);
            saldo.textContent = fmt(novo);
            if(novo !== antes){
                const li = document.createElement("li");
                li.innerHTML = "<span></span><span></span>";
                li.firstChild.textContent = texto;
                li.lastChild.className = novo > antes ? "mais" : "menos";
                li.lastChild.textContent = (novo > antes ? "+" : "−") + fmt(Math.abs(novo - antes)) + " → " + fmt(novo);
                historico.prepend(li);
            }
        } catch(e){ erro.textContent = e.message; }
        finally { enviando = false; }
    }
    document.getElementById("dinheiro-ganhar").addEventListener("click", function(){
        const v = Math.max(0, parseInt(valor.value, 10) || 0);
        valor.value = "";
        if(v > 0) enviar({ delta: v }, "Ganhou");
    });
    document.getElementById("dinheiro-gastar").addEventListener("click", function(){
        const v = Math.max(0, parseInt(valor.value, 10) || 0);
        valor.value = "";
        if(v > 0) enviar({ delta: -v }, "Gastou");
    });
    definir.addEventListener("change", function(){
        if(definir.value === "") return;
        const v = Math.max(0, parseInt(definir.value, 10) || 0);
        definir.value = "";
        enviar({ definir: v }, "Ajuste");
    });
})();
</script>
