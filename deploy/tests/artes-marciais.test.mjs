// Teste da barra de experiência das artes marciais. Carrega o objeto real "ArtesMarciais" de form.blade.php
// (trecho entre ARTES-INICIO e ARTES-FIM). Uso: node deploy/tests/artes-marciais.test.mjs
import fs from "fs";
import path from "path";
import assert from "assert";
import { fileURLToPath } from "url";
const __dirname = path.dirname(fileURLToPath(import.meta.url));

const fonte = fs.readFileSync(path.join(__dirname, "..", "..", "resources", "views", "personagem", "form.blade.php"), "utf8");
const ini = fonte.indexOf("/*ARTES-INICIO*/");
const fim = fonte.indexOf("/*ARTES-FIM*/");
assert(ini >= 0 && fim > ini, "marcadores ARTES-INICIO/FIM não encontrados");
const AM = new Function(fonte.slice(ini, fim) + "\nreturn ArtesMarciais;")();

let ok = 0;
function t(nome, fn){ fn(); ok++; console.log("ok - " + nome); }

t("as 7 artes existem e começam em nível 0, barra 0/20", () => {
    const a = AM.normalizar(null);
    assert.deepStrictEqual(Object.keys(a), ["esgrima", "hexomancia", "fisiomancia", "boxing", "wrestling", "tiro_tatico", "kuro_stomper"]);
    Object.keys(a).forEach(k => {
        assert.deepStrictEqual([a[k].nivel, a[k].xp, a[k].talentos.length], [0, 0, 0], k);
        assert.strictEqual(AM.xpMaximo(a, k), 20, k);
    });
});
t("+80 no máximo da barra por nível da própria arte", () => {
    const a = AM.normalizar({ esgrima: { nivel: 1 }, boxing: { nivel: 3 } });
    assert.strictEqual(AM.xpMaximo(a, "esgrima"), 100);
    assert.strictEqual(AM.xpMaximo(a, "boxing"), 260);
    assert.strictEqual(AM.xpMaximo(a, "wrestling"), 20);
});
t("cada nível em Fisiomancia soma +15 na barra de Hexomancia, e vice-versa", () => {
    const a = AM.normalizar({ hexomancia: { nivel: 2 }, fisiomancia: { nivel: 3 } });
    assert.strictEqual(AM.xpMaximo(a, "hexomancia"), 20 + 160 + 45);
    assert.strictEqual(AM.xpMaximo(a, "fisiomancia"), 20 + 240 + 30);
    const so = AM.normalizar({ fisiomancia: { nivel: 1 } });
    assert.strictEqual(AM.xpMaximo(so, "hexomancia"), 35);
    assert.strictEqual(AM.xpMaximo(so, "esgrima"), 20); // as outras artes não recebem o bônus
});
t("subir de nível só com a barra cheia; gasta o máximo e guarda o resto", () => {
    const a = AM.normalizar({ esgrima: { nivel: 0, xp: 19 } });
    assert.strictEqual(AM.subirNivel(a, "esgrima"), false);
    a.esgrima.xp = 25;
    assert.strictEqual(AM.subirNivel(a, "esgrima"), true);
    assert.deepStrictEqual([a.esgrima.nivel, a.esgrima.xp], [1, 5]);
    assert.strictEqual(AM.xpMaximo(a, "esgrima"), 100);
});
t("valores inválidos viram 0 e talentos são normalizados", () => {
    const a = AM.normalizar({ boxing: { nivel: "-3", xp: "abc", talentos: [{ nome: "Jab", custo: "4", ativo: 1 }, null] }, invalida: { nivel: 9 } });
    assert.deepStrictEqual([a.boxing.nivel, a.boxing.xp], [0, 0]);
    assert.deepStrictEqual(a.boxing.talentos, [
        { nome: "Jab", custo: 4, descricao: "", ativo: true },
        { nome: "", custo: 0, descricao: "", ativo: false },
    ]);
    assert.strictEqual(a.invalida, undefined);
});

console.log(ok + " testes passaram");
