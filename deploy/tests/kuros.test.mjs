// Teste do cálculo da ficha de Kuro (public/js/kuros-calc.js) com as regras de config/kuros.php.
// Uso: node deploy/tests/kuros.test.mjs (precisa do PHP do Laragon para ler o config)
import fs from "fs";
import { execFileSync } from "child_process";
import path from "path";
import assert from "assert";
import { fileURLToPath } from "url";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const raiz = path.join(__dirname, "..", "..");
const php = process.env.PHP || "C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe";
const cfg = JSON.parse(execFileSync(php, ["-r", "echo json_encode(require 'config/kuros.php');"], { cwd: raiz }).toString());
// o arquivo é um script de navegador: roda com um "module" de mentira para pegar a API
const mod = { exports: {} };
new Function("module", fs.readFileSync(path.join(raiz, "public", "js", "kuros-calc.js"), "utf8"))(mod);
const { calcular, doPronto, interpretar } = mod.exports;

let ok = 0;
function t(nome, fn){ fn(); ok++; console.log("ok - " + nome); }
const pronto = nome => doPronto(cfg.prontos.find(p => p.nome.startsWith(nome)));

t("ficha base tipo 1", () => {
    const f = calcular({}, cfg);
    assert.deepStrictEqual([f.pv, f.armadura, f.deslocamento, f.rolagens.luta, f.ataque, f.tamanho], [10, 0, 5, -10, "1d6 (cortante) + 1 amaldiçoado", "Médio"]);
    assert.deepStrictEqual([f.aprim.total, f.habil.total], [4, 3]);
});
t("Infestador (T2): PVs +90 (constituição 90), luta +60, dano 5d10 + 8", () => {
    const f = calcular(pronto("Infestador"), cfg);
    assert.strictEqual(f.atributos.constituicao, 90);
    assert.strictEqual(f.pv, 100);
    assert.strictEqual(f.rolagens.luta, -10 + 3 * 20);
    assert.strictEqual(f.ataque, "5d10 + 8 (cortante) + 1 amaldiçoado");
    assert.deepStrictEqual([f.aprim.usados, f.aprim.total, f.habil.usados, f.habil.total], [8, 8, 6, 6]);
    assert.deepStrictEqual(f.avisos, []);
});
t("Monolisco (T3): PVs +180, armadura 22, Grande, maldição +60", () => {
    const f = calcular(pronto("Monolisco"), cfg);
    assert.deepStrictEqual([f.pv, f.armadura, f.tamanho, f.rolagens.maldicao], [190, 22, "Grande", 50]);
});
t("requisitos e limites aparecem nos avisos", () => {
    const f = calcular({ tipo: 1, habilidades: { mundializador: 1, carniceiro: 1, farsante: 1 }, aprim: { pvs: 5 } }, cfg);
    assert.ok(f.avisos.some(a => a.startsWith("Mundializador: falta tipo 4 ou mais, Hexomante")), f.avisos.join(" | "));
    assert.ok(f.avisos.some(a => a.startsWith("Farsante: falta Conhecimento 50, Transformação carniceira")), f.avisos.join(" | "));
    assert.ok(f.avisos.some(a => a.startsWith("Aprimoramentos: 5 usados")));
});
t("Múltiplos membros e Gosmento aplicam os números; 3 círculos dão +1 escolha", () => {
    const f = calcular({ tipo: 3, circulos: 3, habilidades: { multiplos_membros: 2, gosmento: 1 } }, cfg);
    assert.deepStrictEqual([f.atributos.forca, f.atributos.destreza], [90, 90]);
    assert.deepStrictEqual([f.resistencias, f.fraquezas], [["impacto", "queda"], ["cortante", "tóxico"]]);
    assert.deepStrictEqual([f.aprim.total, f.habil.total], [13, 10]);
});
// interpretador: "B (+I por escolha)" vale B + I × escolhas (o +I conta desde a 1ª)
const valores = (k, n, T) => interpretar(cfg.habilidades[k].texto, n, T, cfg).filter(p => p.v != null).map(p => p.v);
t("interpretador: base mais o que vem por escolha", () => {
    assert.deepStrictEqual(valores("carniceiro", 1, 1), ["15"]);
    assert.deepStrictEqual(valores("carniceiro", 3, 1), ["25"]);
    assert.deepStrictEqual(valores("criados", 1, 3), ["1"]);
    assert.deepStrictEqual(valores("lacerador", 2, 1), ["+26", "+3"]);
    assert.deepStrictEqual(valores("ampulheta", 4, 1), ["2"], "mínimo de 2 turnos");
    assert.deepStrictEqual(valores("crescimento", 1, 2), ["7", "20"], "8 − 1 por escolha; tipo vezes 10");
    assert.deepStrictEqual(valores("proliferacao", 3, 2), ["2", "2"], "iguais ao tipo; uma +1 a cada 2 escolhas");
});
t("interpretador: vezes, triplicadas, dados, dificuldade e fração", () => {
    assert.deepStrictEqual(valores("maldicao", 2, 3), ["6", "6", "6"]);
    assert.deepStrictEqual(valores("regeneracao", 2, 1), ["6"]);
    assert.deepStrictEqual(valores("regeneracao", 2, 3), ["12"], "dobra ao tipo 3");
    assert.deepStrictEqual(valores("lodeiro", 1, 3), ["16"], "(5 + 3) dobrado");
    assert.deepStrictEqual(valores("cusparada", 1, 1), ["5d8"]);
    assert.deepStrictEqual(valores("metamorfo", 1, 1), ["D30"]);
    assert.deepStrictEqual(valores("espinhos", 2, 1), ["1/6"]);
    assert.deepStrictEqual(valores("extrator", 1, 1), ["1/2 + 7"]);
    assert.deepStrictEqual(valores("nulificador", 3, 1), ["valendo"]);
});
t("interpretador: frase inteira por escolha (Múltiplos membros, Gosmento, Nomeador)", () => {
    assert.deepStrictEqual(valores("multiplos_membros", 2, 1), ["+2", "+40"]);
    assert.deepStrictEqual(valores("multiplos_membros", 2, 3), ["+4", "+80"]);
    assert.deepStrictEqual(valores("gosmento", 2, 1), ["20", "30"]);
    assert.deepStrictEqual(valores("nomeador", 1, 1), ["+2", "+15", "+2", "+5"]);
    const partes = interpretar(cfg.habilidades.carniceiro.texto, 1, 1, cfg);
    assert.strictEqual(partes.map(p => p.s).join(""), cfg.habilidades.carniceiro.texto, "o texto original fica inteiro");
});
t("habilidades somam na ficha: Sombroso, Conhecedor, Asas, Lacerador, Gosmento, imunidades", () => {
    const f = calcular({ tipo: 2, habilidades: { sombroso: 2, conhecedor: 1, asas: 1, lacerador: 1, gosmento: 1, oni_visao: 1, regeneracao: 1 } }, cfg);
    assert.strictEqual(f.rolagens.furtividade, -10 + 50);
    assert.deepStrictEqual([f.atributos.carisma, f.rolagens.labia], [30, 20]);
    assert.deepStrictEqual(f.bonus.atributos.carisma, [{ de: "Conhecedor", v: 30 }]);
    assert.strictEqual(f.voo, 10);
    assert.deepStrictEqual(f.critico, { margem: 18, mult: 2 });
    assert.deepStrictEqual(f.testes.forca.map(x => x.v), [-10]);
    assert.deepStrictEqual(f.imunidades, ["desprevenido", "cego", "ofuscado"]);
    assert.ok(f.efeitos.some(e => e.rot === "Regeneração" && e.valor.startsWith("3 PVs")));
    assert.strictEqual(calcular(pronto("Arpão"), cfg).voo, 15, "Arpão indagador: Asas ×2 no tipo 2");
});
t("todos os prontos calculam sem habilidade desconhecida", () => {
    cfg.prontos.forEach(p => {
        Object.keys(p.habilidades).forEach(k => assert.ok(cfg.habilidades[k], p.nome + ": " + k));
        const f = calcular(doPronto(p), cfg);
        assert.ok(f.pv > 0, p.nome);
    });
});

console.log(ok + " testes passaram");
