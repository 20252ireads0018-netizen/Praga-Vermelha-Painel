<?php
// Servidor local do gravador de vídeos. Uso (na raiz do projeto): php -S 127.0.0.1:8031 -t public deploy/videos/router.php
// e abra http://127.0.0.1:8031/gravador?v=eletrico (ou ?v=fisiomancia). Veja deploy/videos/LEIA-ME.md.
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$V = __DIR__;
@mkdir($V . '/out');
if ($u === '/gravador') { readfile(__DIR__ . '/gravador.html'); return true; }
if (str_starts_with($u, '/vid/')) {
    $f = $V . '/' . basename($u);
    if (!is_file($f)) { http_response_code(404); return true; }
    $tipos = ['js' => 'text/javascript', 'json' => 'application/json', 'wav' => 'audio/wav', 'mp4' => 'video/mp4', 'jpg' => 'image/jpeg'];
    header('Content-Type: ' . ($tipos[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header('Cache-Control: no-store');
    readfile($f); return true;
}
if ($u === '/salvar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = preg_replace('/[^a-z0-9.\-]/', '', basename($_GET['nome'] ?? 'x.bin'));
    file_put_contents($V . '/out/' . $nome, file_get_contents('php://input'));
    echo 'ok'; return true;
}
return false; // o resto (CSS, imagens) vem da pasta public
