#!/usr/bin/env bash
# Baixa por FTP (deploy/ftp.sh) uma cópia das pastas de produção com arquivos enviados pelos usuários e dos backups do banco.
# Uso, a partir da raiz do projeto:  bash deploy/backup-arquivos.sh [pasta remota ...]
# Padrão: storage/app/private (fotos, sons, imagens das cartas, playmats) e storage/app/backups (banco .sql.gz).
# Destino: backups/producao-AAAAMMDD-HHMMSS/ (fora do git).
set -euo pipefail
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

DESTINO="backups/producao-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DESTINO"
[ $# -gt 0 ] || set -- storage/app/private storage/app/backups

baixar_pasta() { # $1 = pasta remota relativa
    local rem="$1" item
    while IFS= read -r item; do
        item="${item%$'\r'}"
        case "$item" in ''|.|..) continue ;; esac
        # pasta? tenta listar; se listar, desce nela
        if bash deploy/ftp.sh ls "$rem/$item" >/dev/null 2>&1 && [ -n "$(bash deploy/ftp.sh ls "$rem/$item" 2>/dev/null | grep -v '^\.\{1,2\}\r\?$' || true)" ]; then
            baixar_pasta "$rem/$item"
        else
            bash deploy/ftp.sh get "$rem/$item" "$DESTINO/$rem/$item" >/dev/null 2>&1 || mkdir -p "$DESTINO/$rem/$item"
        fi
    done < <(bash deploy/ftp.sh ls "$rem" 2>/dev/null || true)
}

for p in "$@"; do
    echo "baixando $p ..."
    baixar_pasta "${p%/}"
done
find "$DESTINO" -type d -empty -delete 2>/dev/null || true
echo "Backup dos arquivos em $DESTINO: $(find "$DESTINO" -type f | wc -l) arquivos, $(du -sh "$DESTINO" | cut -f1)"
