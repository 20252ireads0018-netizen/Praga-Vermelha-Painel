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
        const e = k => Math.max(0, h(k) - 1); // escolhas além da primeira (livro: "para cada vez além da primeira")
        const avisos = [];

        // "B (+I por escolha)" vale B + I × (escolhas − 1): o +I vale a cada escolha além da primeira (p. 515 do livro);
        // "+V por escolha" sem base vale V × escolhas; "Dobre ao tipo 3" dobra os bônus numéricos do tipo 3 em diante
        const dobra = T >= 3 ? 2 : 1;
        const nome = k => (cfg.habilidades[k] || {}).nome || k;
        const bonus = { atributos: {}, rolagens: {} }; // de onde veio cada valor somado: { chave: [{ de, v }] }
        const testes = {};   // modificadores em testes de atributo: { atributo: [{ de, v | texto }] }
        const efeitos = [];  // o que as habilidades dão além dos números da tabela: [{ rot, valor, de }]

        // atributos: base + 40 por Aumento de atributo; Múltiplos membros dá +20 em força e destreza por escolha (dobra no tipo 3)
        const atributos = {};
        Object.keys(base.atributos).forEach(k => { atributos[k] = base.atributos[k] + 40 * Math.max(0, +(d.atributos || {})[k] || 0); });
        const somarAtrib = (k, v, hab) => { atributos[k] += v; (bonus.atributos[k] = bonus.atributos[k] || []).push({ de: nome(hab), v }); };
        if(h("multiplos_membros")){
            const v = 20 * h("multiplos_membros") * dobra;
            somarAtrib("forca", v, "multiplos_membros");
            somarAtrib("destreza", v, "multiplos_membros");
            efeitos.push({ rot: "Membros extras", valor: "+" + h("multiplos_membros") * dobra, de: nome("multiplos_membros") });
        }
        if(h("conhecedor")) somarAtrib("carisma", 15 + 15 * e("conhecedor"), "conhecedor");
        const teste = (k, item, hab) => (testes[k] = testes[k] || []).push(Object.assign({ de: nome(hab) }, item));
        if(h("gosmento")){
            teste("forca", { v: -10 * h("gosmento") }, "gosmento");
            teste("constituicao", { v: 15 * h("gosmento") }, "gosmento");
        }
        if(h("liquido")){
            teste("destreza", { v: 20 + 15 * e("liquido") }, "liquido");
            teste("forca", { texto: "falha automática" }, "liquido");
        }
        const con = atributos.constituicao;

        const pv = base.pv + q("pvs") * (d.modo_pvs === "tipo" ? T * 10 : con);
        const armadura = base.armadura + q("armadura") * (d.modo_armadura === "tipo" ? T * 5 : Math.floor(con / 4));
        const deslocamento = base.deslocamento + q("deslocamento") * T * 2;
        const voo = h("asas") ? (5 + 5 * e("asas")) * dobra : 0;

        // rolagens: -10 de base; cada vez que uma habilidade recebe Rolagem poderosa, +tipo × 10
        const rolagens = {};
        Object.keys(base.rolagens).forEach(k => { rolagens[k] = base.rolagens[k]; });
        Object.keys(d.rolagens || {}).forEach(k => {
            const n = Math.max(0, +d.rolagens[k] || 0);
            if(!n) return;
            rolagens[k] = (rolagens[k] != null ? rolagens[k] : -10) + n * T * 10;
        });
        // vantagens fixas das habilidades nas rolagens
        const somarRol = (k, v, hab) => { rolagens[k] = (rolagens[k] != null ? rolagens[k] : -10) + v; (bonus.rolagens[k] = bonus.rolagens[k] || []).push({ de: nome(hab), v }); };
        if(h("sombroso")) somarRol("furtividade", 25 * h("sombroso"), "sombroso");
        if(h("conhecedor")){
            somarRol("labia", 15 + 15 * e("conhecedor"), "conhecedor");
            efeitos.push({ rot: "Tecnologia, manipulação, intimidar, fotografia", valor: "+" + (15 + 15 * e("conhecedor")), de: nome("conhecedor") });
        }

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
            return { chave: k, nome: def.nome, escolhas: h(k), texto: def.texto, partes: interpretar(def.texto, h(k), T, cfg), faltas: faltas };
        });

        // o que as habilidades dão além da tabela: crítico, alcance, cura, ataques extras, imunidades
        const passo = (lados, mais) => passos[Math.min(passos.length - 1, Math.max(0, passos.indexOf(lados)) + mais)];
        const critico = h("lacerador") ? { margem: 10 + 8 * e("lacerador"), mult: 1 + e("lacerador") } : null;
        const ef = (k, rot, valor) => { if(h(k)) efeitos.push({ rot, valor, de: nome(k) }); };
        ef("alongado", "Alcance de adjacência", "+" + (3 + 2 * e("alongado")) + " m");
        ef("regeneracao", "Regeneração", 3 * h("regeneracao") * dobra + " PVs no início de cada turno");
        ef("carniceiro", "Devorar cadáver (ação completa)", "recupera " + (10 + 5 * e("carniceiro")) + " PVs");
        ef("lodeiro", "Comer poça de lodo (ação bônus)", "recupera " + (5 + 3 * e("lodeiro")) * dobra * (h("comedor_lodo") ? 2 : 1) + " PVs");
        ef("cusparada", "Cusparada (ação principal, testa Maldição)", (3 + 2 * e("cusparada")) + "d" + passo(6, e("cusparada")) + " no dano à escolha");
        ef("auto_destruicao", "Explosão ao morrer", (1 + e("auto_destruicao")) + "d" + passo(6, e("auto_destruicao")) + " explosivo" + (T >= 3 ? " ×2" : "") + " em " + h("auto_destruicao") + " m");
        ef("extrator", "Extrator", "tira aura igual a 1/2 do dano" + (e("extrator") ? " + " + 7 * e("extrator") : ""));
        ef("espinhos", "Espinhos amaldiçoados", "devolve 1/" + Math.max(1, 10 - 2 * e("espinhos")) + " do dano corpo a corpo");
        ef("hexomante", "Técnicas de maldição", (1 + e("hexomante")) + " (" + h("hexomante") + " conjurações por cena, " + h("hexomante") + " aprimoramentos grátis)");
        ef("criados", "Criados por turno (em combate)", String(e("criados")));
        ef("proliferacao", "Proliferação (ação completa)", (1 + Math.floor(e("proliferacao") / 2)) + " cópia(s), até " + T + " vez(es) por cena");
        ef("aterrorizar", "Aterrorizar (ação bônus)", "amedronta " + (1 + e("aterrorizar")) + " vez(es) e causa " + 3 * h("aterrorizar") + " de dano de sanidade");
        ef("teleportar", "Teleporte", 5 * h("teleportar") + " m");
        ef("oni_presente", "Deslocamento", "planetário ilimitado");
        ef("sem_orgaos", "Morte", "só ao chegar a −" + pv + " PVs");
        // danos que dá para rolar: o ataque comum e as ações das habilidades com dados ({ nome, dados, lados, extra, mult, tipo, amaldicoado })
        const danos = [{ nome: "Ataque comum", dados, lados, extra, mult: 1, tipo: d.tipo_dano || "cortante", amaldicoado: base.ataque.extra }];
        if(h("cusparada")) danos.push({ nome: "Cusparada", dados: 3 + 2 * e("cusparada"), lados: passo(6, e("cusparada")), extra: 0, mult: 1, tipo: "à escolha", amaldicoado: 0 });
        if(h("auto_destruicao")) danos.push({ nome: "Explosão ao morrer", dados: 1 + e("auto_destruicao"), lados: passo(6, e("auto_destruicao")), extra: 0, mult: T >= 3 ? 2 : 1, tipo: "explosivo", amaldicoado: 0 });
        const imunidades = [];
        const imune = (k, lista) => { if(h(k)) lista.forEach(x => { if(imunidades.indexOf(x) < 0) imunidades.push(x); }); };
        imune("fantasmagorico", ["dano físico não amaldiçoado"]);
        imune("liquido", ["todo dano que não seja eletrizante, flamejante, tóxico ou amaldiçoado"]);
        imune("oni_visao", ["desprevenido", "cego", "ofuscado"]);
        imune("entidade", ["sangrando", "desprevenido", "ferida grave"]);
        imune("sem_orgaos", ["morrendo"]);

        return {
            tipo: T, circulos: circ, atributos, pv, armadura, deslocamento, voo, rolagens, ataque, critico, resistencias, fraquezas,
            tamanho: tamanhos[iTam], sentidos, habilidades: lista, bonus, testes, efeitos, imunidades, danos,
            aprim: { total: aprimTotal, usados: aprimUsados }, habil: { total: habTotal, usados: habUsados }, avisos
        };
    }

    /*
     * Interpretador do texto de uma habilidade: acha cada valor que depende das escolhas (ou do tipo) e calcula.
     * Devolve partes [{ s }] e [{ s, v }] (s = o trecho original, v = o valor já calculado), na ordem do texto.
     */
    function interpretar(texto, n, T, cfg){
        texto = String(texto || "");
        n = Math.max(0, +n || 0);
        T = Math.max(1, +T || 1);
        const e = Math.max(0, n - 1); // o que vem entre parênteses "(+I por escolha)" vale a cada escolha além da primeira
        const passos = (cfg && cfg.passos_dado) || [4, 6, 8, 10, 12, 20];
        const x2 = /dobre ao tipo 3/i.test(texto) && T >= 3 ? 2 : 1;
        const num = s => /^um/i.test(s) ? 1 : parseFloat(String(s).replace(",", "."));
        const fmt = (v, sinal) => (sinal && v > 0 ? "+" : "") + String(Math.round(v * 100) / 100).replace(".", ",");
        const regras = [
            // 3d6 (+2 dados e +1 passo por escolha)
            [/(\d+)d(\d+)\s*\(\+(\d+) dados? e \+(\d+) passos? por escolha\)/gi, m => {
                const i = Math.max(0, passos.indexOf(+m[2]));
                return (+m[1] + m[3] * e) + "d" + passos[Math.min(passos.length - 1, i + m[4] * e)] + (/dobre o dano em tipo 3/i.test(texto) && T >= 3 ? " ×2" : "");
            }],
            // (D50 -20 por escolha): dificuldade
            [/\(D(\d+)\s*-(\d+) por escolha\)/gi, m => "D" + Math.max(0, m[1] - m[2] * e)],
            // 1/10 (-2 denominador por escolha)
            [/(\d+)\/(\d+)\s*\(-(\d+) denominador por escolha\)/gi, m => m[1] + "/" + Math.max(1, m[2] - m[3] * e)],
            // 1/2 (+7 fixo por escolha)
            [/(\d+)\/(\d+)\s*\(\+(\d+) fixo por escolha\)/gi, m => m[1] + "/" + m[2] + (e ? " + " + m[3] * e : "")],
            // B (+I por escolha) · uma técnica (+1 por escolha) · 8 (-1 a cada escolha, mínimo 1 mês) · uma (+1 a cada 2 escolhas)
            [/([+-]?\d+(?:,\d+)?|\buma?\b)((?:\s+[a-zà-ú]+)?)\s*\(([+-]\d+(?:,\d+)?)\s*(?:fixo\s*)?(?:por|a cada)\s*(\d+\s*)?escolhas?([^)]*)\)/gi, m => {
                const vezes = m[4] ? Math.floor(e / +m[4]) : e;
                let v = num(m[1]) + num(m[3]) * vezes;
                const min = /m[ií]nimo(?: de)? (\d+)/i.exec(m[5] || "");
                if(min) v = Math.max(+min[1], v);
                return fmt(v * x2, /^[+]/.test(m[1]));
            }],
            [/(?:suas )?escolhas triplicadas/gi, () => fmt(3 * n * x2)],
            [/(?:(?:igual|iguais) (?:a|à)s? )?(?:suas )?escolhas vezes (\d+)/gi, m => fmt(n * m[1])],
            [/(?:igual|iguais) (?:a|à)s? (?:suas )?escolhas\b(?! vezes| triplicadas)/gi, () => fmt(n)],
            [/(?:seu |o )?tipo vezes (\d+)/gi, m => fmt(T * m[1])],
            [/iguais ao seu tipo/gi, () => fmt(T)],
            // o nível de ameaça de um Kuro é o tipo dele (ficha base, p. 514)
            [/(?:sua )?ameaça vezes (\d+)/gi, m => fmt(T * m[1])],
            [/em escolha (\d+)/gi, m => n >= +m[1] ? "valendo" : "a partir de " + m[1] + " escolhas"],
        ];
        const achados = [];
        regras.forEach(([re, calc]) => {
            let m;
            re.lastIndex = 0;
            while((m = re.exec(texto))) achados.push({ i: m.index, f: m.index + m[0].length, v: calc(m) });
        });
        // frases com "por escolha" / "para cada escolha" fora de parênteses: cada número da frase vale por escolha
        const frases = /[^.;]+[.;]?/g;
        let fr;
        while((fr = frases.exec(texto))){
            if(!/(?:por|para cada) escolha/i.test(fr[0].replace(/\([^)]*\)/g, ""))) continue;
            const re = /[+-]\d+(?:,\d+)?|(?<=\b(?:de|vantagem|desvantagem) )\d+(?:,\d+)?(?!d\d|\/)|\buma\b(?= desvantagem)|\b1(?= fenômeno)/gi;
            let m;
            while((m = re.exec(fr[0]))){
                const ini = fr.index + m.index;
                const dentro = texto.slice(0, ini).split("(").length > texto.slice(0, ini).split(")").length; // está entre parênteses
                if(dentro) continue;
                achados.push({ i: ini, f: ini + m[0].length, v: fmt(num(m[0]) * n * x2, /^[+-]/.test(m[0])) });
            }
        }
        // sobreposições: fica o que começa antes (e, empatado, o maior)
        achados.sort((a, b) => a.i - b.i || b.f - a.f);
        const partes = [];
        let pos = 0;
        achados.forEach(a => {
            if(a.i < pos) return;
            if(a.i > pos) partes.push({ s: texto.slice(pos, a.i) });
            partes.push({ s: texto.slice(a.i, a.f), v: a.v });
            pos = a.f;
        });
        if(pos < texto.length) partes.push({ s: texto.slice(pos) });
        return partes;
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

    /**
     * Rolagens do Kuro. Teste: d100 − valor (o valor da rolagem, ou do atributo com os modificadores de teste).
     * Dano: os dados, + extra, vezes o multiplicador, + o amaldiçoado. aleatorio(n) devolve 1..n.
     */
    function rolarTeste(valor, aleatorio){
        const d100 = aleatorio(100);
        return { d100, valor, total: d100 - valor };
    }
    function rolarDano(dn, aleatorio){
        const dados = [];
        for(let i = 0; i < dn.dados; i++) dados.push(aleatorio(dn.lados));
        const soma = dados.reduce((s, x) => s + x, 0);
        return { dados, soma, total: (soma + (dn.extra || 0)) * (dn.mult || 1), amaldicoado: dn.amaldicoado || 0 };
    }

    const api = { calcular, doPronto, interpretar, rolarTeste, rolarDano };
    if(typeof module !== "undefined" && module.exports) module.exports = api;
    else raiz.KurosCalc = api;
})(typeof window !== "undefined" ? window : this);
