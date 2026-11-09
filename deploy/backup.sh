#!/usr/bin/env bash
# Daily backup: MySQL dump + uploaded files (logo) -> dated .tar.gz -> Google Drive via rclone.
# Keeps 30 days on Drive and 7 days on the server.
# Env overrides: APP_DIR, BACKUP_DIR, BACKUP_REMOTE (default gdrive:mahale-backups), REMOTE_KEEP_DAYS, LOCAL_KEEP_DAYS.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mahale}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/mahale}"
BACKUP_REMOTE="${BACKUP_REMOTE:-gdrive:mahale-backups}"
REMOTE_KEEP_DAYS="${REMOTE_KEEP_DAYS:-30}"
LOCAL_KEEP_DAYS="${LOCAL_KEEP_DAYS:-7}"

env_value() {
    grep -E "^$1=" "$APP_DIR/.env" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

STAMP="$(date +%Y-%m-%d_%H%M)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$BACKUP_DIR"

MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces --routines \
    -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE" > "$WORK/database.sql"

mkdir -p "$WORK/files"
if [[ -d "$APP_DIR/storage/app/public" ]]; then
    cp -a "$APP_DIR/storage/app/public/." "$WORK/files/"
fi

ARCHIVE="$BACKUP_DIR/mahale-$STAMP.tar.gz"
tar -czf "$ARCHIVE" -C "$WORK" database.sql files

rclone copy "$ARCHIVE" "$BACKUP_REMOTE"
rclone delete "$BACKUP_REMOTE" --min-age "${REMOTE_KEEP_DAYS}d"
find "$BACKUP_DIR" -name 'mahale-*.tar.gz' -mtime "+$LOCAL_KEEP_DAYS" -delete

echo "Backup OK: $ARCHIVE -> $BACKUP_REMOTE"
