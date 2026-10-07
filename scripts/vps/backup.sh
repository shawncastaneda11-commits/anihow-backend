#!/usr/bin/env bash
# Daily database backup. File archive runs on Sundays. Run as root.
set -euo pipefail

umask 077
install -d -m 700 /var/backups/anihow

stamp="$(date +%Y%m%d)"
mysqldump --defaults-extra-file=/root/anihow-mysqldump.cnf --single-transaction anihow \
    | gzip > "/var/backups/anihow/db-${stamp}.sql.gz"

find /var/backups/anihow -type f -name 'db-*.sql.gz' -mtime +14 -delete

if [[ "$(date +%u)" == "7" ]]; then
    tar -czf "/var/backups/anihow/files-${stamp}.tar.gz" -C /var/www/anihow/storage app
    mapfile -t old_archives < <(ls -1t /var/backups/anihow/files-*.tar.gz 2>/dev/null || true)
    if ((${#old_archives[@]} > 4)); then
        rm -f "${old_archives[@]:4}"
    fi
fi
