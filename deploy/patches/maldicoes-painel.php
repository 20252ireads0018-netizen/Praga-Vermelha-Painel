<?php
$arquivo = __DIR__ . '/../../resources/views/personagem/form.blade.php';
$src = file_get_contents($arquivo);
$crlf = str_contains($src, "\r\n");
$src = str_replace("\r\n", "\n", $src);
$pares = [];

$pares[] = [<<<'OLD'
    const CATALOGO_MALDICOES = @json(config('maldicoes', []));
OLD, <<<'NEW'
    const CATALOGO_MALDICOES = @json(config('maldicoes', []));
    // Maldições que este usuário pode escolher: sem a chave do mestre, as não catalogadas ficam de fora
    const LISTA_MALDICOES = @json(\App\Support\AcessoMaldicoes::lista(auth()->user()));
NEW];

$pares[] = [<<<'OLD'
        let opcoes = '<option value="">Selecione uma maldição</option>';
        for(const slug in CATALOGO_MALDICOES){
            const c = CATALOGO_MALDICOES[slug];
            opcoes += '<option value="' + esc(slug) + '">' + esc(c.nome) + " (" + esc(c.raridade) + ")</option>";
        }
OLD, <<<'NEW'
        let opcoes = '<option value="">Selecione uma maldição</option>';
        LISTA_MALDICOES.forEach(function(item, i){
            const c = item.slug ? CATALOGO_MALDICOES[item.slug] : null;
            const valor = c ? item.slug : "lista:" + i;
            const extra = c ? c.raridade : item.apelido;
            opcoes += '<option value="' + esc(valor) + '">' + esc(item.nome) + (extra ? " (" + esc(extra) + ")" : "") + "</option>";
        });
NEW];

$pares[] = [<<<'OLD'
        if(v && v !== CUSTOM_MALDICAO){
            const c = CATALOGO_MALDICOES[v];
OLD, <<<'NEW'
        if(v && v.indexOf("lista:") === 0){
            const item = LISTA_MALDICOES[Number(v.slice(6))] || {};
            maldicaoPrevia.textContent = [item.categoria, item.orgao, item.apelido].filter(Boolean).join(" · ") + " · sem página detalhada";
        } else if(v && v !== CUSTOM_MALDICAO){
            const c = CATALOGO_MALDICOES[v];
NEW];

$pares[] = [<<<'OLD'
            adicionarEntrada({ slug: null, nome: nome, ameaca: ameaca });
            maldicaoNomeCustom.value = "";
        } else {
OLD, <<<'NEW'
            adicionarEntrada({ slug: null, nome: nome, ameaca: ameaca });
            maldicaoNomeCustom.value = "";
        } else if(escolha.indexOf("lista:") === 0){
            const item = LISTA_MALDICOES[Number(escolha.slice(6))];
            if(!item) return;
            if(maldicoesAdicionadas.some(m => (m.nome || "").toLowerCase() === item.nome.toLowerCase())){
                mostrarPopup("Essa maldição já foi adicionada.");
                return;
            }
            adicionarEntrada({ slug: null, nome: item.nome, ameaca: ameaca });
        } else {
NEW];

$pares[] = [<<<'OLD'
                <select id="maldicao-catalogo"></select>
            </div>
            <p class="maldicao-previa" id="maldicao-previa"></p>
OLD, <<<'NEW'
                <select id="maldicao-catalogo"></select>
            </div>
            <p class="maldicao-previa" id="maldicao-previa"></p>
            @auth
                @unless (\App\Support\AcessoMaldicoes::podeVerRestritas(auth()->user()))
                    <p class="maldicao-previa"><a href="{{ route('maldicoes.index') }}">Tem a chave do mestre? Use-a no catálogo de maldições.</a></p>
                @endunless
            @endauth
NEW];

$erros = [];
foreach ($pares as $i => [$a, $n]) { $c = substr_count($src, $a); if ($c !== 1) $erros[] = "trecho " . ($i+1) . ": $c vezes"; }
if ($erros) { fwrite(STDERR, implode("\n", $erros) . "\nNada gravado.\n"); exit(1); }
foreach ($pares as [$a, $n]) $src = str_replace($a, $n, $src);
if ($crlf) $src = str_replace("\n", "\r\n", $src);
file_put_contents($arquivo, $src);
echo count($pares) . " trechos aplicados.\n";
