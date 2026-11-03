# Vídeos explicativos (aba /videos)

Os vídeos verticais (1080×1920, com narração) são gerados aqui, sem programa de edição:

1. **Roteiro:** edite `roteiros.json` (uma fala por cena; o nome da cena liga a fala à animação em `cenas.js`).
2. **Narração:** no PowerShell, rode `deploy/videos/narrar.ps1`. Ele usa a voz "Microsoft Maria Desktop" do Windows,
   grava um `.wav` por cena e escreve `linha.json` com a duração de cada fala. Salve `linha.json` sem BOM (UTF-8).
3. **Animação:** cada cena tem uma função em `cenas.js` (`CENAS.eletrico.caminho`, `CENAS.fisiomancia.metal`...).
   Ilustrações ficam acima de y = 1350; a legenda ocupa de 1400 para baixo.
4. **Exportar:** na raiz do projeto, `php -S 127.0.0.1:8031 -t public deploy/videos/router.php`, abra
   `http://127.0.0.1:8031/gravador?v=eletrico` num Chrome/Edge e rode `__exportar()` no console. O MP4
   (H.264 + AAC, com `mp4-muxer`, licença MIT) e a capa JPG caem em `deploy/videos/out/`.
5. **Publicar:** copie para `public/videos/` com o nome usado em `config/videos.php` e envie por FTP.

`Ver` toca a animação com som; `Gravar` usa o gravador do navegador, mas gera MP4 sem duração certa: prefira `__exportar()`.
