// As contas do time skip na tela (public/js/timeskip-calc.js) têm de bater com as do servidor (App\Support\TimeSkipCalc).
// Uso: node deploy/tests/timeskip.test.mjs (precisa do PHP do Laragon)
import fs from "fs";
import { execFileSync } from "child_process";
import path from "path";
import assert from "assert";
import { fileURLToPath } from "url";
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const raiz = path.join(__dirname, "..", "..");
const php = process.env.PHP || "C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe";
const mod = { exports: {} };
new Function("module", fs.readFileSync(path.join(raiz, "public", "js", "timeskip-calc.js"), "utf8"))(mod);
const { calcular } = mod.exports;

// escolhas sorteadas (com e sem erros) sobre o modelo de 10 anos
let semente = 7;
const sorte = n => { semente = (semente + 0x6D2B79F5) | 0; let t = Math.imul(semente ^ (semente >>> 15), 1 | semente); t ^= t + Math.imul(t ^ (t >>> 7), 61 | t); return ((t ^ (t >>> 14)) >>> 0) % n; }; // mulberry32
const cfgBruta = JSON.parse(execFileSync(php, ["-r", "echo json_encode((require 'config/timeskip.php')['modelos']['dez_anos']['config']);"], { cwd: raiz }).toString());
const casos = [];
for (let i = 0; i < 40; i++) {
    const r = { ramo: ["", "politico", "militar", "intelectual"][sorte(4)], salario: sorte(8) * 1000, dinheiro_inicial: sorte(4) * 150000, atividades: {}, propriedades: {} };
    cfgBruta.atividades.forEach(a => { if (sorte(12) === 0) r.atividades[a.id] = 1 + sorte(3); });
    cfgBruta.propriedades.forEach(p => { if (sorte(9) === 0) r.propriedades[p.id] = 1; });
    casos.push(r);
}
const entrada = JSON.stringify({ cfg: cfgBruta, casos });
const codigo = `require 'vendor/autoload.php'; $e = json_decode(stream_get_contents(STDIN), true);
$cfg = App\\Support\\TimeSkipCalc::limparConfig($e['cfg']);
echo json_encode(['cfg' => $cfg, 'contas' => array_map(fn ($r) => App\\Support\\TimeSkipCalc::calcular($cfg, App\\Support\\TimeSkipCalc::limparResposta($r, $cfg)), $e['casos'])], JSON_PRESERVE_ZERO_FRACTION);`;
const saida = JSON.parse(execFileSync(php, ["-r", codigo], { cwd: raiz, input: entrada }).toString());

let erros = 0;
casos.forEach((r, i) => {
    const js = calcular(saida.cfg, r);
    const ph = saida.contas[i];
    if (!Array.isArray(ph.totais)) Object.keys(ph.totais).forEach(k => { ph.totais[k] = +ph.totais[k]; }); else ph.totais = {};
    ph.salario_mensal = +ph.salario_mensal; ph.salarios_extra = +ph.salarios_extra;
    assert.deepStrictEqual(js, ph, "caso " + i);
    if (js.erros.length) erros++;
    if (process.env.VER) console.log(i, js.usadas, js.escolhas, js.saldo, js.erros);
});
assert.ok(erros > 0 && erros < casos.length, "os casos sorteados deveriam ter alguns com erro e alguns sem");
console.log(`ok - ${casos.length} casos iguais na tela e no servidor (${erros} com erros)`);
