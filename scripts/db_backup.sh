#!/usr/bin/env bash
# MySQL backup: compressed mysqldump with retention and optional off-host copy to S3.
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

# Needs the MySQL 8 client on the host (--set-gtid-purged is not in MariaDB's mysqldump).
MYSQL_PWD="$DB_PASSWORD" mysqldump --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
  --ssl-mode=REQUIRED --single-transaction --no-tablespaces --set-gtid-purged=OFF --routines --triggers \
  "$DB_DATABASE" | gzip -9 > "$FILE"

# mysqldump writes this trailer only when it finishes; a truncated dump lacks it.
gunzip -c "$FILE" | tail -n 1 | grep -q "Dump completed" || { echo "Backup incomplete, aborting" >&2; rm -f "$FILE"; exit 1; }

if [ -n "${S3_URI:-}" ]; then
  aws s3 cp "$FILE" "$S3_URI/$(basename "$FILE")"
fi

ls -1t "$BACKUP_DIR"/*.sql.gz | tail -n +"$((RETENTION + 1))" | xargs -r rm -f
echo "Backup written to $FILE"
# Restore test (do this regularly): gunzip -c FILE | mysql -h HOST -u USER -p scratch_db
