<?php
$arquivo = __DIR__ . '/../../resources/views/personagem/form.blade.php';
$src = file_get_contents($arquivo);
$crlf = str_contains($src, "\r\n");
$src = str_replace("\r\n", "\n", $src);
$pares = [
[<<<'OLD'
.prop-busca-linha { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 190px); gap: 8px; align-items: center; }
OLD, <<<'NEW'
.prop-busca-linha { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; align-items: center; }
NEW],
[<<<'OLD'
                    <p class="vazio-lista" id="prop-catalogo-vazio" style="display:none">Nenhuma propriedade encontrada.</p>
OLD, <<<'NEW'
                    <p class="vazio-lista" id="prop-catalogo-vazio">Digite o nome de uma propriedade, material ou efeito para pesquisar.</p>
NEW],
[<<<'OLD'
    function renderCatalogo(){
        const termo = norm(busca.value.trim());
        const grupo = selGrupo.value;
        descGrupo.textContent = grupo && grupos[grupo] ? (grupos[grupo].descricao || "") : "";
        let total = 0;
OLD, <<<'NEW'
    function renderCatalogo(){
        const termo = norm(busca.value.trim());
        const grupo = selGrupo.value;
        descGrupo.textContent = grupo && grupos[grupo] ? (grupos[grupo].descricao || "") : "";
        // O catálogo só mostra resultados de uma pesquisa (2+ letras); o grupo apenas filtra esses resultados
        if(termo.length < 2){
            cat.innerHTML = "";
            vazioCat.textContent = "Digite o nome de uma propriedade, material ou efeito para pesquisar.";
            vazioCat.style.display = "block";
            return;
        }
        vazioCat.textContent = "Nenhuma propriedade encontrada.";
        let total = 0;
NEW],
];
foreach ($pares as $i => [$a, $n]) { if (substr_count($src, $a) !== 1) { fwrite(STDERR, "trecho " . ($i + 1) . " não encontrado 1 vez\n"); exit(1); } }
foreach ($pares as [$a, $n]) $src = str_replace($a, $n, $src);
if ($crlf) $src = str_replace("\n", "\r\n", $src);
file_put_contents($arquivo, $src);
echo "aplicado\n";
