#!/usr/bin/env bash
# PostgreSQL backup: compressed pg_dump with retention and optional off-host copy to S3.
# Reads DB_* from .env. One dump covers every tenant (shared database).
#   scripts/db_backup.sh            # keep 14 daily dumps
#   RETENTION=30 S3_URI=s3://bucket/backups scripts/db_backup.sh
set -euo pipefail

set -a; . ./.env; set +a

BACKUP_DIR="${BACKUP_DIR:-DB_Backup}"
RETENTION="${RETENTION:-14}"
STAMP="$(date +%Y%m%d_%H%M%S)"
FILE="$BACKUP_DIR/${DB_DATABASE}_${STAMP}.sql.gz"
mkdir -p "$BACKUP_DIR"

PGPASSWORD="$DB_PASSWORD" pg_dump --host "$DB_HOST" --port "${DB_PORT:-5432}" --username "$DB_USERNAME" \
  --no-owner --format=plain "$DB_DATABASE" | gzip -9 > "$FILE"

# A dump that is suspiciously small means the dump failed silently.
[ "$(stat -c%s "$FILE")" -gt 1024 ] || { echo "Backup too small, aborting" >&2; rm -f "$FILE"; exit 1; }

if [ -n "${S3_URI:-}" ]; then
  aws s3 cp "$FILE" "$S3_URI/$(basename "$FILE")"
fi

ls -1t "$BACKUP_DIR"/*.sql.gz | tail -n +"$((RETENTION + 1))" | xargs -r rm -f
echo "Backup written to $FILE"
# Restore test (do this regularly): gunzip -c FILE | psql -h HOST -U USER -d scratch_db
