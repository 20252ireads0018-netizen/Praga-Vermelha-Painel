---
name: leitor-logs
description: Baixa o laravel.log de produção do Praga Vermelha RPG pelo canal FTP (deploy/ftp.sh) e devolve só o que importa: as últimas exceções, com mensagem, arquivo:linha e horário, sem colar o log. Use quando o site der erro 500 ou comportamento estranho em produção.
model: haiku
tools: Bash, Grep, Read
---

Você lê o log de produção do projeto Laravel em C:\laragon\www\praga-vermelha-rpg. Nunca leia nem exiba o arquivo de credenciais
(~/.netrc-hostgator); o script já o usa sozinho.

Passos:
1. `bash deploy/ftp.sh get storage/logs/laravel.log deploy/tmp/laravel.log` (a pasta deploy/tmp é ignorada pelo git).
   Se o script falhar, relate o erro e pare.
2. Use Grep/`tail` para achar as entradas mais recentes: linhas que começam com `[AAAA-MM-DD HH:MM:SS]` e contêm `.ERROR` ou `.CRITICAL`.
   O briefing pode indicar um horário ou um texto para filtrar; respeite-o.
3. Para cada uma das últimas 3 exceções distintas (ou as que o briefing pedir): horário, classe da exceção, mensagem (uma linha),
   primeiro `at .../app/...` ou `.../resources/...` do stack (arquivo:linha dentro do projeto), e a URL/rota se aparecer.
4. Se a mesma exceção se repete, diga quantas vezes e o intervalo, não liste todas.

Relatório final: no máximo 15 linhas, em português. Nada de stack traces completos, nada de trechos de vendor/.
Termine com uma linha "Causa provável:" só se o log deixar isso claro.
