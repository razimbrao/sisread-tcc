#!/bin/sh
set -e

BANCO="${DB_DATABASE:-/data/database.sqlite}"
DESTINO="${BACKUP_DIR:-/data/backups}"
MANTER="${BACKUP_KEEP:-7}"

mkdir -p "$DESTINO"
ARQUIVO="$DESTINO/database-$(date -u +%Y%m%d-%H%M%S).sqlite"

sqlite3 "$BANCO" ".backup '$ARQUIVO'"
gzip "$ARQUIVO"
chmod 600 "$ARQUIVO.gz"

ls -1t "$DESTINO"/database-*.sqlite.gz | tail -n +"$((MANTER + 1))" | xargs -r rm -f
echo "Backup gravado em $ARQUIVO.gz"
