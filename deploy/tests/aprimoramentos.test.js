// Teste do cálculo de aprimoramentos. Carrega o objeto real "Aprimoramentos" de form.blade.php (trecho entre os marcadores).
// Uso: node deploy/tests/aprimoramentos.test.js
import fs from "fs";
import path from "path";
import assert from "assert";
import { fileURLToPath } from "url";
const __dirname = path.dirname(fileURLToPath(import.meta.url));

const fonte = fs.readFileSync(path.join(__dirname, "..", "..", "resources", "views", "personagem", "form.blade.php"), "utf8");
const ini = fonte.indexOf("/*APRIM-INICIO*/");
const fim = fonte.indexOf("/*APRIM-FIM*/");
assert(ini >= 0 && fim > ini, "marcadores APRIM-INICIO/FIM não encontrados");
const Aprimoramentos = new Function(fonte.slice(ini, fim) + "\nreturn Aprimoramentos;")();

let ok = 0;
function t(nome, fn){ fn(); ok++; console.log("ok - " + nome); }

t("base 10, eficiência 3, dano qtd 2 grátis 1, ação qtd 1 = 7 + 9 + 10 = 26", function(){
    const item = Aprimoramentos.normalizarItem({
        custo: 10, eficiencia: 3, aprimoramentos: [],
        gerais: { dano: { qtd: 2, gratis: 1 }, acao: { qtd: 1, gratis: 0 } },
        gratis_disponiveis: 1
    });
    assert.strictEqual(Aprimoramentos.custoBase(item), 7);
    assert.strictEqual(Aprimoramentos.custoAprimoramentos(item), 19);
    assert.strictEqual(Aprimoramentos.custoTotal(item), 26);
    assert.strictEqual(Aprimoramentos.textoCusto(item), "Custo: 10 − 3 de eficiência = 7 + 19 de aprimoramentos (1 grátis) = 26 Aura");
});

t("grátis maior que qtd é limitado", function(){
    const g = Aprimoramentos.normalizarGerais({ dano: { qtd: 1, gratis: 5 } });
    assert.deepStrictEqual(g.dano, { qtd: 1, gratis: 1 });
    const l = Aprimoramentos.normalizarLista([{ texto: "x", custo: 4, acumulativo: true, qtd: 2, gratis: 9 }]);
    assert.strictEqual(l[0].gratis, 2);
    assert.strictEqual(Aprimoramentos.custoAprimoramentos({ custo: 0, aprimoramentos: l }), 0);
});

t("soma de grátis não passa de gratis_disponiveis", function(){
    const item = Aprimoramentos.normalizarItem({
        custo: 5, aprimoramentos: [{ texto: "a", custo: 2, acumulativo: true, qtd: 3, gratis: 3 }],
        gerais: { alvos: { qtd: 2, gratis: 2 } }, gratis_disponiveis: 2
    });
    assert.strictEqual(Aprimoramentos.usadosGratis(item), 2);
    assert.strictEqual(item.gerais.alvos.gratis, 0); // tira dos últimos primeiro
});

t("aprimoramento antigo {ativo:true,custo:4} vira qtd 1 e soma 4", function(){
    const l = Aprimoramentos.normalizarLista([{ texto: "velho", ativo: true, custo: 4 }, { texto: "off", ativo: false, custo: 7 }]);
    assert.strictEqual(l[0].qtd, 1);
    assert.strictEqual(l[0].acumulativo, false);
    assert.strictEqual(l[0].gratis, 0);
    assert.strictEqual(l[1].qtd, 0);
    assert.strictEqual(Aprimoramentos.custoTotal({ custo: 3, eficiencia: 0, aprimoramentos: l }), 7);
});

t("não acumulativo limita a 1; eficiência nunca deixa custo base negativo e não afeta aprimoramentos", function(){
    const l = Aprimoramentos.normalizarLista([{ texto: "n", custo: 4, qtd: 5 }]);
    assert.strictEqual(l[0].qtd, 1);
    assert.strictEqual(Aprimoramentos.custoTotal({ custo: 2, eficiencia: 10, aprimoramentos: l }), 4);
});

t("serializarGerais só leva chaves com qtd > 0", function(){
    assert.deepStrictEqual(Aprimoramentos.serializarGerais({ dano: { qtd: 0 }, alcance: { qtd: 2, gratis: 1 } }), { alcance: { qtd: 2, gratis: 1 } });
});

console.log(ok + " testes passaram");
