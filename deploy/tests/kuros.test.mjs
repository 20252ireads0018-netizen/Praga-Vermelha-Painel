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
const { calcular, doPronto } = mod.exports;

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
t("todos os prontos calculam sem habilidade desconhecida", () => {
    cfg.prontos.forEach(p => {
        Object.keys(p.habilidades).forEach(k => assert.ok(cfg.habilidades[k], p.nome + ": " + k));
        const f = calcular(doPronto(p), cfg);
        assert.ok(f.pv > 0, p.nome);
    });
});

console.log(ok + " testes passaram");
