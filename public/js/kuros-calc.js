/**
 * Cálculo da ficha de um Kuro (regras em config/kuros.php). Puro, sem DOM: usado pela página de Kuros e pelos testes
 * (deploy/tests/kuros.test.mjs).
 *
 * d (o que o mestre escolheu): { tipo, circulos, tamanho (+/- categorias), aprim {chave: qtd}, modo_pvs ('con'|'tipo'),
 *   modo_armadura, atributos {attr: vezes}, rolagens {hab: vezes}, resistencias[], fraquezas[], sentidos[], tipo_dano,
 *   habilidades {chave: escolhas} }
 */
(function(raiz){
    function calcular(d, cfg){
        d = d || {};
        const base = cfg.base;
        const T = Math.max(1, +d.tipo || 1);
        const circ = Math.max(0, +d.circulos || 0);
        const a = d.aprim || {};
        const hab = d.habilidades || {};
        const q = k => Math.max(0, +a[k] || 0);
        const h = k => Math.max(0, +hab[k] || 0);
        const avisos = [];

        // atributos: base + 40 por Aumento de atributo; Múltiplos membros dá +20 em força e destreza por escolha (dobra no tipo 3)
        const atributos = {};
        Object.keys(base.atributos).forEach(k => { atributos[k] = base.atributos[k] + 40 * Math.max(0, +(d.atributos || {})[k] || 0); });
        if(h("multiplos_membros")){
            const v = 20 * h("multiplos_membros") * (T >= 3 ? 2 : 1);
            atributos.forca += v;
            atributos.destreza += v;
        }
        const con = atributos.constituicao;

        const pv = base.pv + q("pvs") * (d.modo_pvs === "tipo" ? T * 10 : con);
        const armadura = base.armadura + q("armadura") * (d.modo_armadura === "tipo" ? T * 5 : Math.floor(con / 4));
        const deslocamento = base.deslocamento + q("deslocamento") * T * 2;
        const voo = h("asas") ? 5 * h("asas") * (T >= 3 ? 2 : 1) : 0;

        // rolagens: -10 de base; cada vez que uma habilidade recebe Rolagem poderosa, +tipo × 10
        const rolagens = {};
        Object.keys(base.rolagens).forEach(k => { rolagens[k] = base.rolagens[k]; });
        Object.keys(d.rolagens || {}).forEach(k => {
            const n = Math.max(0, +d.rolagens[k] || 0);
            if(!n) return;
            rolagens[k] = (rolagens[k] != null ? rolagens[k] : -10) + n * T * 10;
        });

        // ataque comum: 1d6 + 1 amaldiçoado; cada Dano adicional: +1 passo, +2 dados e +2×tipo de dano extra
        const passos = cfg.passos_dado || [4, 6, 8, 10, 12, 20];
        const iBase = Math.max(0, passos.indexOf(base.ataque.lados));
        const lados = passos[Math.min(passos.length - 1, iBase + q("dano"))];
        const dados = base.ataque.dados + 2 * q("dano");
        const extra = 2 * T * q("dano");
        const ataque = dados + "d" + lados + (extra ? " + " + extra : "") + " (" + (d.tipo_dano || "cortante") + ") + " + base.ataque.extra + " amaldiçoado";

        // resistências e fraquezas: as escolhidas e as que vêm de habilidades (Gosmento)
        const resistencias = (d.resistencias || []).slice();
        const fraquezas = (d.fraquezas || []).slice();
        if(h("gosmento")){
            ["impacto", "queda"].forEach(t => { if(resistencias.indexOf(t) < 0) resistencias.push(t); });
            ["cortante", "tóxico"].forEach(t => { if(fraquezas.indexOf(t) < 0) fraquezas.push(t); });
        }

        const tamanhos = cfg.tamanhos || ["Minúsculo", "Pequeno", "Médio", "Grande", "Enorme", "Colossal"];
        const iTam = Math.max(0, Math.min(tamanhos.length - 1, 2 + (+d.tamanho || 0)));
        const sentidos = base.sentidos.concat(d.sentidos || []);

        // escolhas: 4 aprimoramentos e 3 habilidades por tipo; cada 3 círculos +1 de cada
        const extraCirc = Math.floor(circ / (base.circulos_por_escolha || 3));
        const aprimTotal = base.aprim_por_tipo * T + extraCirc;
        const habTotal = base.habil_por_tipo * T + extraCirc;
        const aprimUsados = Object.keys(a).reduce((s, k) => s + q(k), 0);
        const habUsados = Object.keys(hab).reduce((s, k) => s + h(k), 0);
        if(aprimUsados > aprimTotal) avisos.push("Aprimoramentos: " + aprimUsados + " usados, o tipo " + T + " permite " + aprimTotal + ".");
        if(habUsados > habTotal) avisos.push("Habilidades sobrenaturais: " + habUsados + " escolhas, o tipo " + T + " permite " + habTotal + ".");

        // conferências das distribuições
        const vezesAtrib = Object.keys(d.atributos || {}).reduce((s, k) => s + Math.max(0, +d.atributos[k] || 0), 0);
        if(vezesAtrib !== q("atributo")) avisos.push("Aumento de atributo: " + q("atributo") + " escolhido(s), " + vezesAtrib + " distribuído(s) nos atributos.");
        if(q("diversificacao") && ((d.resistencias || []).length < q("diversificacao")))
            avisos.push("Diversificação danosa: escolha " + q("diversificacao") + " resistência(s).");
        if(T >= 4 && q("diversificacao") && (d.fraquezas || []).length) avisos.push("A partir do tipo 4, Diversificação danosa não adiciona fraquezas.");
        if(q("sentidos") && (d.sentidos || []).length < q("sentidos")) avisos.push("Sentidos aprimorados: escreva " + q("sentidos") + " sentido(s) novo(s).");

        // requisitos das habilidades sobrenaturais
        const lista = Object.keys(hab).filter(k => h(k) && cfg.habilidades[k]).map(k => {
            const def = cfg.habilidades[k];
            const r = def.req || {};
            const faltas = [];
            if(r.tipo_min && T < r.tipo_min) faltas.push("tipo " + r.tipo_min + " ou mais");
            if(r.tipo_max && T > r.tipo_max) faltas.push("tipo " + r.tipo_max + " ou menos");
            if(r.conhecimento && atributos.conhecimento < r.conhecimento) faltas.push("Conhecimento " + r.conhecimento);
            if(r.carisma && atributos.carisma < r.carisma) faltas.push("Carisma " + r.carisma);
            (r.habilidades || []).forEach(x => { if(!h(x)) faltas.push((cfg.habilidades[x] || {}).nome || x); });
            if(faltas.length) avisos.push(def.nome + ": falta " + faltas.join(", ") + ".");
            return { chave: k, nome: def.nome, escolhas: h(k), texto: def.texto, faltas: faltas };
        });

        return {
            tipo: T, circulos: circ, atributos, pv, armadura, deslocamento, voo, rolagens, ataque, resistencias, fraquezas,
            tamanho: tamanhos[iTam], sentidos, habilidades: lista,
            aprim: { total: aprimTotal, usados: aprimUsados }, habil: { total: habTotal, usados: habUsados }, avisos
        };
    }

    /** Ficha nova a partir de um Kuro pronto do livro (config kuros.prontos). */
    function doPronto(p){
        return {
            descricao: p.descricao || "", comportamento: p.comportamento || "", surgimento: p.surgimento || "", daemon: p.daemon || "",
            tipo: p.tipo || 1, circulos: 0, tamanho: p.tamanho || 0, aprim: Object.assign({}, p.aprim || {}), modo_pvs: "con", modo_armadura: "con",
            atributos: Object.assign({}, p.atributos || {}), rolagens: Object.assign({}, p.rolagens || {}), resistencias: [], fraquezas: [],
            sentidos: (p.sentidos || []).slice(), tipo_dano: "cortante", habilidades: Object.assign({}, p.habilidades || {}), notas_mestre: p.notas || ""
        };
    }

    const api = { calcular, doPronto };
    if(typeof module !== "undefined" && module.exports) module.exports = api;
    else raiz.KurosCalc = api;
})(typeof window !== "undefined" ? window : this);
