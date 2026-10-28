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

t("braceiras + natural 10, 30 cortante = 2", () => {
    assert.strictEqual(calc({ valor: 30, tipo: "cortante", peca: braceiras }).final, 2);
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

console.log(ok + " testes passaram");
