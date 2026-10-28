// Teste do cálculo de dano com armadura. Carrega o objeto real "Dano" de form.blade.php (trecho entre DANO-INICIO e DANO-FIM).
// Uso: node deploy/tests/dano.test.mjs
import fs from "fs";
import path from "path";
import assert from "assert";
import { fileURLToPath } from "url";
const __dirname = path.dirname(fileURLToPath(import.meta.url));

const fonte = fs.readFileSync(path.join(__dirname, "..", "..", "resources", "views", "personagem", "form.blade.php"), "utf8");
const ini = fonte.indexOf("/*DANO-INICIO*/");
const fim = fonte.indexOf("/*DANO-FIM*/");
assert(ini >= 0 && fim > ini, "marcadores DANO-INICIO/FIM não encontrados");
const Dano = new Function(fonte.slice(ini, fim) + "\nreturn Dano;")();

const braceiras = { nome: "Braceiras", alcance_min: 46, alcance_max: 60, defesa: 4, contra_corte_impacto: 14, equipada: true };
const base = { natural: 10, usarRes: true, resistencias: [], imunidades: [] };
const calc = (o) => Dano.calcular(Object.assign({}, base, o));

let ok = 0;
function t(nome, fn){ fn(); ok++; console.log("ok - " + nome); }

t("exemplo do usuário: 20 impacto + 20 tóxico, natural 5, peça defesa 5, resistência a tóxico = 10 + 10", () => {
    const peca = { nome: "Peitoral", alcance_min: 1, alcance_max: 100, defesa: 5, contra_corte_impacto: 0, equipada: true };
    const r = Dano.calcularAtaque(Object.assign({}, base, { natural: 5, peca, resistencias: ["tóxico"],
        linhas: [{ tipo: "impacto", valor: 20 }, { tipo: "tóxico", valor: 20 }] }));
    assert.deepStrictEqual(r.linhas.map(l => [l.natural, l.defesa, l.final]), [[5, 5, 10], [0, 0, 10]]);
    assert.strictEqual(r.total, 20);
});
t("impacto/cortante com contra corte/impacto usa só ele: braceiras (def 4, contra 14) + natural 10, 30 cortante = 6", () => {
    const l = calc({ valor: 30, tipo: "cortante", peca: braceiras });
    assert.deepStrictEqual([l.natural, l.contra, l.defesa, l.final], [10, 14, 0, 6]);
});
t("impacto/cortante sem contra corte/impacto usa a defesa", () => {
    const peca = Object.assign({}, braceiras, { contra_corte_impacto: 0 });
    assert.strictEqual(calc({ valor: 30, tipo: "impacto", peca }).final, 16); // 30 − 10 − 4
});
t("30 flamejante ignora armadura = 30", () => {
    assert.strictEqual(calc({ valor: 30, tipo: "flamejante", peca: braceiras }).final, 30);
});
t("flamejante com resistência = 15", () => {
    assert.strictEqual(calc({ valor: 30, tipo: "flamejante", peca: braceiras, resistencias: ["flamejante"] }).final, 15);
});
t("cortante com imunidade = 0", () => {
    assert.strictEqual(calc({ valor: 30, tipo: "cortante", peca: braceiras, imunidades: ["cortante"] }).final, 0);
});
t("sem peça (80), 30 cortante, natural 10 = 20", () => {
    assert.strictEqual(Dano.pecaAtingida([braceiras], 80), null);
    assert.strictEqual(calc({ valor: 30, tipo: "cortante", peca: null }).final, 20);
});
t("dano menor que a redução = 0", () => {
    assert.strictEqual(calc({ valor: 5, tipo: "cortante", peca: braceiras }).final, 0);
});
t("perfurante não usa contra corte/impacto: 30 − 4 − 10 = 16", () => {
    assert.strictEqual(calc({ valor: 30, tipo: "perfurante", peca: braceiras }).final, 16);
});
t("opção desmarcada ignora resistências; só peças equipadas contam", () => {
    assert.strictEqual(calc({ valor: 30, tipo: "flamejante", usarRes: false, resistencias: ["flamejante"] }).final, 30);
    assert.strictEqual(Dano.pecaAtingida([Object.assign({}, braceiras, { equipada: false })], 50), null);
    assert.strictEqual(Dano.pecaAtingida([braceiras], 50), braceiras);
});

// ---- Ataques com várias linhas de dano ----
const seq = (...v) => { let i = 0; return () => v[i++ % v.length]; }; // gerador determinístico no lugar de Math.random
const atk = (linhas, o) => Dano.calcularAtaque(Object.assign({}, base, { linhas: linhas.map(([tipo, valor]) => ({ tipo, valor })) }, o));
const finais = (r) => r.linhas.map(l => l.final);

t("100 impacto + 100 amaldiçoado, resistência a impacto, natural 10, sem peça: 45 + 100 = 145", () => {
    const r = atk([["impacto", 100], ["amaldiçoado", 100]], { peca: null, resistencias: ["impacto"] });
    assert.deepStrictEqual(finais(r), [45, 100]); // (100−10)/2 = 45; amaldiçoado ignora armadura e não sofre a resistência
    assert.strictEqual(r.total, 145);
});
t("nem peça nem armadura natural reduzem explosivo ou os tipos que ignoram armadura", () => {
    assert.strictEqual(atk([["explosivo", 30]], { peca: null }).total, 30);
    assert.strictEqual(atk([["explosivo", 30]], { peca: braceiras }).total, 30); // peça só bloqueia impacto/cortante/perfurante
    assert.strictEqual(atk([["flamejante", 30]], { peca: braceiras }).total, 30);
    ["congelante", "eletrizante", "tóxico", "amaldiçoado", "queda"].forEach(tp =>
        assert.strictEqual(atk([[tp, 30]], { peca: braceiras }).total, 30, tp));
});
t("a defesa da peça atingida reduz impacto, cortante e perfurante", () => {
    const peca = { nome: "Peitoral", alcance_min: 1, alcance_max: 100, defesa: 7, contra_corte_impacto: 0, equipada: true };
    ["impacto", "cortante", "perfurante"].forEach(tp => assert.strictEqual(atk([[tp, 30]], { peca, natural: 0 }).total, 23, tp));
    assert.deepStrictEqual(finais(atk([["explosivo", 10], ["perfurante", 10]], { peca, natural: 0 })), [10, 3]); // explosivo não gasta a defesa
});
t("armadura natural reduz impacto, cortante e perfurante", () => {
    ["impacto", "cortante", "perfurante"].forEach(tp => assert.strictEqual(atk([[tp, 30]], { peca: null }).total, 20, tp));
});
t("reserva da defesa compartilhada: peça def 10, cortante 6 + impacto 20 → 0 e 16", () => {
    const peca = { nome: "Peitoral", alcance_min: 1, alcance_max: 100, defesa: 10, contra_corte_impacto: 0, equipada: true };
    const r = atk([["cortante", 6], ["impacto", 20]], { peca, natural: 0 });
    assert.deepStrictEqual(finais(r), [0, 16]); // cortante consome 6 da defesa; sobram 4 para o impacto (20−4)
    assert.strictEqual(r.linhas[0].defesa, 6);
    assert.strictEqual(r.linhas[1].defesa, 4);
    assert.strictEqual(r.total, 16);
});
t("com contra corte/impacto, a defesa não soma em impacto/cortante: def 10, contra 5, cortante 6 + impacto 20 → 1 e 20", () => {
    const peca = { nome: "Peitoral", alcance_min: 1, alcance_max: 100, defesa: 10, contra_corte_impacto: 5, equipada: true };
    const r = atk([["cortante", 6], ["impacto", 20]], { peca, natural: 0 });
    assert.deepStrictEqual(finais(r), [1, 20]); // a reserva de 5 do contra é gasta no cortante; o impacto não recorre à defesa
    assert.deepStrictEqual(r.linhas.map(l => [l.contra, l.defesa]), [[5, 0], [0, 0]]);
    assert.deepStrictEqual(finais(atk([["perfurante", 20]], { peca, natural: 0 })), [10]); // perfurante continua usando a defesa
});
t("contra corte/impacto não é gasto por perfurante nem por tipos que ignoram", () => {
    const peca = { nome: "X", alcance_min: 1, alcance_max: 100, defesa: 0, contra_corte_impacto: 5, equipada: true };
    assert.deepStrictEqual(finais(atk([["perfurante", 10], ["flamejante", 10], ["cortante", 10]], { peca, natural: 0 })), [10, 10, 5]);
});
t("armadura natural compartilhada: natural 10, perfurante 4 + impacto 20 + cortante 5 → 0, 14, 5", () => {
    assert.deepStrictEqual(finais(atk([["perfurante", 4], ["impacto", 20], ["cortante", 5]], { peca: null })), [0, 14, 5]);
});
t("linha que ignora armadura não consome reservas", () => {
    assert.deepStrictEqual(finais(atk([["flamejante", 50], ["cortante", 10]], { peca: braceiras, natural: 0 })), [50, 0]); // contra 14 cobre tudo
    assert.deepStrictEqual(finais(atk([["queda", 50], ["perfurante", 10]], { peca: braceiras, natural: 0 })), [50, 6]); // defesa 4
});
t("imunidade e resistência valem só para a linha do tipo; floor da metade", () => {
    const r = atk([["impacto", 21], ["cortante", 21], ["perfurante", 21]], { peca: null, natural: 0, resistencias: ["impacto"], imunidades: ["cortante"] });
    assert.deepStrictEqual(finais(r), [10, 0, 21]);
    assert.deepStrictEqual(finais(atk([["impacto", 21]], { peca: null, natural: 0, resistencias: ["impacto"], usarRes: false })), [21]);
});
t("dano total pode superar a vida (PV negativo = 20 − total)", () => {
    assert.strictEqual(20 - atk([["amaldiçoado", 100]], { peca: null }).total, -80);
});

// ---- Expressões de dados ----
t("2d6+3 com gerador determinístico (0.5 → 4, 0 → 1): 4+1+3 = 8", () => {
    const r = Dano.rolar("2d6+3", seq(0.5, 0));
    assert.strictEqual(r.ok, true);
    assert.deepStrictEqual(r.dados, [4, 1]);
    assert.strictEqual(r.total, 8);
});
t("inteiro, subtração, soma de termos e espaços", () => {
    assert.deepStrictEqual([Dano.rolar("12").total, Dano.rolar("12").dados.length], [12, 0]);
    assert.strictEqual(Dano.rolar("1d8-1", () => 0).total, 0);
    assert.strictEqual(Dano.rolar(" 2D6 + 1d4 + 3 ", () => 0.999).total, 6 + 6 + 4 + 3);
    assert.strictEqual(Dano.rolar("1d4-10", () => 0).total, 0); // nunca negativo
});
t("expressões inválidas são rejeitadas com mensagem", () => {
    ["", "abc", "2d", "d6", "0d6", "1d1", "2d6++3", "2d6+", "1.5", "-", "101d6", "2d6*2"].forEach(x => {
        const r = Dano.rolar(x);
        assert.strictEqual(r.ok, false, JSON.stringify(x));
        assert.ok(r.erro && r.erro.length > 5);
    });
});
t("texto do log por linha (exemplo do usuário)", () => {
    const peca = { nome: "Braceiras", alcance_min: 46, alcance_max: 60, defesa: 4, contra_corte_impacto: 14, equipada: true };
    const rolsA = Dano.rolar("2d6+90", seq(0.6, 0.4)); // 4 e 3 → 97
    const rolsB = Dano.rolar("100");
    const r = atk([["impacto", rolsA.total], ["amaldiçoado", rolsB.total]], { peca, resistencias: ["impacto"] });
    assert.strictEqual(Dano.textoLinha(r.linhas[0], rolsA), "impacto 2d6+90 (4,3)=97 − 10 (natural) − 14 (corte/impacto) = 73 → resistência 36");
    assert.strictEqual(Dano.textoLinha(r.linhas[1], rolsB), "amaldiçoado 100 = 100");
    assert.strictEqual(r.total, 136);
});

console.log(ok + " testes passaram");
