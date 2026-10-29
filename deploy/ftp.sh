#!/usr/bin/env bash
# Canal de transferência local <-> produção (HostGator) por FTP, via curl.
# As credenciais ficam SÓ em ~/.netrc-hostgator (formato netrc), escrito pelo dono da conta; este script nunca as imprime.
#
# Uso (sempre a partir da raiz do projeto, caminhos relativos à raiz do Laravel):
#   deploy/ftp.sh check                       # testa login e descobre se a conta aponta para a pasta do projeto
#   deploy/ftp.sh ls [pasta]                  # lista uma pasta remota
#   deploy/ftp.sh get <remoto> [local]        # baixa um arquivo (padrão: mesmo caminho local)
#   deploy/ftp.sh put <arquivo>...            # envia arquivos (faz php -l antes em .php; cria pastas remotas)
#   deploy/ftp.sh put --changed               # envia tudo que o git mostra como modificado/novo (sem vendor etc.)
#   deploy/ftp.sh mkdir <pasta>               # cria uma pasta remota
#   deploy/ftp.sh mv <origem> <destino>       # move/renomeia um arquivo remoto
#
# Variáveis opcionais: PV_FTP_HOST, PV_NETRC, PV_FTP_BASE (prefixo remoto se a conta FTP NÃO aponta para a pasta do projeto,
# ex.: PV_FTP_BASE=praga-vermelha-rpg/praga-vermelha-rpg). Pode ser fixado em deploy/ftp.conf (linhas VAR=valor).
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"
[ -f deploy/ftp.conf ] && . deploy/ftp.conf

HOST="${PV_FTP_HOST:-br910.hostgator.com.br}"
NETRC="${PV_NETRC:-$HOME/.netrc-hostgator}"
BASE="${PV_FTP_BASE:-}"
PHP="${PV_PHP:-/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe}"

[ -f "$NETRC" ] || { echo "Arquivo de credenciais não encontrado: $NETRC" >&2; exit 2; }

# curl com TLS explícito quando o servidor oferecer (--ssl tenta e segue sem se não houver)
CURL=(curl -sS --netrc-file "$NETRC" --ssl --ftp-pasv --connect-timeout 20 --max-time 600)

url() { # caminho relativo -> URL remota
    local rel="${1#/}"
    local base="${BASE%/}"
    if [ -n "$base" ]; then printf 'ftp://%s/%s/%s' "$HOST" "$base" "$rel"; else printf 'ftp://%s/%s' "$HOST" "$rel"; fi
}

cmd_ls() { "${CURL[@]}" --list-only "$(url "${1:-}")/"; }

cmd_check() {
    echo "Host: $HOST  base remota: '${BASE:-/}'"
    local itens
    itens="$(cmd_ls "")" || { echo "Falha no login/conexão FTP." >&2; exit 1; }
    if grep -qx 'artisan' <<<"$itens"; then
        echo "OK: a conta aponta para a raiz do projeto Laravel (artisan encontrado)."
    else
        echo "A raiz remota NÃO é o projeto. Conteúdo:"; echo "$itens" | head -20
        echo "Defina PV_FTP_BASE em deploy/ftp.conf (ex.: PV_FTP_BASE=praga-vermelha-rpg/praga-vermelha-rpg) ou crie a conta FTP apontando para a pasta do projeto."
        exit 1
    fi
}

cmd_get() {
    local rem="$1" loc="${2:-$1}"
    mkdir -p "$(dirname "$loc")"
    "${CURL[@]}" -o "$loc" "$(url "$rem")"
    echo "baixado  $rem -> $loc ($(wc -c <"$loc") bytes)"
}

# Move/renomeia no servidor (a pasta de destino precisa existir)
cmd_mv() {
    local pre="${BASE:+${BASE%/}/}"
    "${CURL[@]}" -Q "RNFR ${pre}${1#/}" -Q "RNTO ${pre}${2#/}" "ftp://$HOST/" >/dev/null
    echo "movido  $1 -> $2"
}

cmd_mkdir() { "${CURL[@]}" -Q "MKD ${BASE:+${BASE%/}/}${1#/}" "ftp://$HOST/" >/dev/null 2>&1 || true; echo "pasta ok  $1"; }

cmd_put() {
    local arquivos=()
    if [ "${1:-}" = "--changed" ]; then
        mapfile -t arquivos < <(git status --porcelain --untracked-files=all | awk '$1!="D"{print $NF}' \
            | grep -Ev '^(vendor/|node_modules/|storage/|deploy/|\.claude/|\.env|tests/|\.git)')
        [ ${#arquivos[@]} -gt 0 ] || { echo "Nada modificado para enviar."; return 0; }
    else
        arquivos=("$@")
    fi
    local a
    for a in "${arquivos[@]}"; do
        a="${a#./}"
        [ -f "$a" ] || { echo "ignorado (não é arquivo): $a"; continue; }
        case "$a" in *.php) "$PHP" -l "$a" >/dev/null || { echo "ERRO de sintaxe, não enviado: $a" >&2; exit 1; };; esac
        "${CURL[@]}" --ftp-create-dirs -T "$a" "$(url "$a")"
        echo "enviado  $a ($(wc -c <"$a") bytes)"
    done
}

# Lista recursivamente uma pasta remota (uma linha por arquivo, caminho relativo à raiz do projeto)
listar_rec() {
    local pasta="${1%/}" linha nome
    "${CURL[@]}" "$(url "$pasta")/" | tr -d '\r' | while IFS= read -r linha; do
        [ -n "$linha" ] || continue
        nome="$(awk '{for(i=9;i<=NF;i++) printf "%s%s", $i, (i<NF?" ":"")}' <<<"$linha")"
        case "$nome" in .|..|"") continue;; esac
        case "$linha" in
            d*) listar_rec "${pasta:+$pasta/}$nome" ;;
            l*) ;;
            *)  echo "${pasta:+$pasta/}$nome" ;;
        esac
    done
}

# Baixa pastas remotas inteiras para um destino local, numa única conexão
cmd_pull() {
    local destino="$1"; shift
    local cfg; cfg="$(mktemp)"
    local total=0 p f
    for p in "$@"; do
        while IFS= read -r f; do
            printf 'url = "%s"\noutput = "%s/%s"\n' "$(url "$f")" "$destino" "$f" >>"$cfg"
            total=$((total + 1))
        done < <(listar_rec "$p")
    done
    echo "arquivos: $total"
    [ "$total" -gt 0 ] && "${CURL[@]}" --create-dirs -K "$cfg"
    rm -f "$cfg"
    echo "baixado em $destino"
}

case "${1:-}" in
    check) cmd_check ;;
    pull)  shift; cmd_pull "$@" ;;
    ls)    shift; cmd_ls "${1:-}" ;;
    get)   shift; cmd_get "$@" ;;
    put)   shift; cmd_put "$@" ;;
    mkdir) shift; cmd_mkdir "$1" ;;
    mv)    shift; cmd_mv "$1" "$2" ;;
    *) sed -n '2,15p' "$0"; exit 1 ;;
esac
