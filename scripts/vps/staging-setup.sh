#!/usr/bin/env bash
# One-time (idempotent) setup of the AniHow test copy (staging) next to the
# live site. Run as root after the DNS record for the staging name exists:
#   bash /var/www/anihow/scripts/vps/staging-setup.sh staging.anihow.tech
#
# Staging gets its own folder, database, database user, Reverb, queue worker
# and cron line. Its database user cannot read the live database. Nothing in
# /var/www/anihow or the live database is changed.
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run staging-setup.sh as root." >&2
    exit 1
fi

if [[ $# -ne 1 ]]; then
    echo "Usage: staging-setup.sh <staging domain>" >&2
    exit 1
fi

DOMAIN="$1"
if [[ ! "${DOMAIN}" =~ ^[A-Za-z0-9.-]+$ ]]; then
    echo "Domain must contain only letters, digits, dots, and hyphens." >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIVE_DIR=/var/www/anihow
STAGING_DIR=/var/www/anihow-staging
STAGING_DB=anihow_staging
STAGING_DB_USER=anihow_staging

if [[ ! -f "${LIVE_DIR}/.env" ]]; then
    echo "The live site is missing at ${LIVE_DIR}. Run provision.sh and deploy first." >&2
    exit 1
fi

resolved="$(getent ahostsv4 "${DOMAIN}" | awk 'NR == 1 { print $1 }' || true)"
if [[ -z "${resolved}" ]]; then
    echo "${DOMAIN} does not resolve yet. Add an A record for it pointing at this server, wait a few minutes, then run this again." >&2
    exit 1
fi
if ! hostname -I | tr ' ' '\n' | grep -qx "${resolved}"; then
    echo "Warning: ${DOMAIN} points at ${resolved}, which is not an address of this server. HTTPS setup may fail." >&2
fi

as_anihow() {
    sudo -u anihow -H "$@"
}

# Database and its own user, with a hex password that is never printed.
db_env=/root/anihow-staging-db.env
if [[ ! -s "${db_env}" ]]; then
    umask 077
    printf 'DB_PASSWORD=%s\n' "$(openssl rand -hex 24)" > "${db_env}"
    chmod 600 "${db_env}"
fi
db_password="$(sed -n 's/^DB_PASSWORD=//p' "${db_env}" | head -n 1)"
if [[ -z "${db_password}" || ! "${db_password}" =~ ^[0-9a-f]+$ ]]; then
    unset db_password
    echo "Staging database password file is missing a hex password." >&2
    exit 1
fi
mysql --protocol=socket -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${STAGING_DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${STAGING_DB_USER}'@'localhost' IDENTIFIED BY '${db_password}';
ALTER USER '${STAGING_DB_USER}'@'localhost' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON ${STAGING_DB}.* TO '${STAGING_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# Code: a second checkout of the same repository.
install -d -m 755 -o anihow -g anihow "${STAGING_DIR}"
if [[ ! -d "${STAGING_DIR}/.git" ]]; then
    origin="$(as_anihow git -C "${LIVE_DIR}" remote get-url origin)"
    as_anihow git clone --branch main "${origin}" "${STAGING_DIR}"
fi

# .env: start from the live one (mail, Firebase, Reverb keys), then point
# everything that must differ at staging. Values are written, never printed.
env_file="${STAGING_DIR}/.env"
fresh_env=0
if [[ ! -f "${env_file}" ]]; then
    install -m 600 -o anihow -g anihow "${LIVE_DIR}/.env" "${env_file}"
    fresh_env=1
fi

set_env() {
    local key="$1" value="$2" tmp
    tmp="$(mktemp)"
    if grep -q "^${key}=" "${env_file}"; then
        KEY="${key}" VALUE="${value}" awk '
            BEGIN { key = ENVIRON["KEY"]; value = ENVIRON["VALUE"] }
            index($0, key "=") == 1 { print key "=" value; next }
            { print }
        ' "${env_file}" > "${tmp}"
    else
        cat "${env_file}" > "${tmp}"
        printf '%s=%s\n' "${key}" "${value}" >> "${tmp}"
    fi
    cat "${tmp}" > "${env_file}"
    rm -f "${tmp}"
}

if [[ "${fresh_env}" -eq 1 ]]; then
    set_env APP_KEY ''
fi
set_env APP_NAME '"AniHow Staging"'
set_env APP_ENV staging
set_env APP_DEBUG false
set_env APP_URL "https://${DOMAIN}"
set_env DB_DATABASE "${STAGING_DB}"
set_env DB_USERNAME "${STAGING_DB_USER}"
set_env DB_PASSWORD "${db_password}"
unset db_password
set_env SESSION_DOMAIN null
set_env MAIL_FROM_NAME '"AniHow Staging"'
set_env REVERB_HOST "${DOMAIN}"
set_env REVERB_PORT 443
set_env REVERB_SCHEME https
set_env REVERB_SERVER_HOST 127.0.0.1
set_env REVERB_SERVER_PORT 8081

# A Firebase key path relative to the live folder would not exist in staging.
firebase_path="$(sed -n 's/^FIREBASE_CREDENTIALS=//p' "${env_file}" | head -n 1 | tr -d '"')"
if [[ -n "${firebase_path}" && "${firebase_path}" != /* && -f "${LIVE_DIR}/${firebase_path}" ]]; then
    set_env FIREBASE_CREDENTIALS "${LIVE_DIR}/${firebase_path}"
fi
unset firebase_path
chown anihow:anihow "${env_file}"
chmod 600 "${env_file}"

# Web server and HTTPS.
nginx_tmp="$(mktemp)"
sed "s/__DOMAIN__/${DOMAIN}/g" "${SCRIPT_DIR}/nginx-anihow-staging.conf" > "${nginx_tmp}"
if [[ ! -f /etc/nginx/sites-available/anihow-staging ]] || ! grep -q 'ssl_certificate' /etc/nginx/sites-available/anihow-staging; then
    install -m 644 "${nginx_tmp}" /etc/nginx/sites-available/anihow-staging
fi
rm -f "${nginx_tmp}"
ln -sfn /etc/nginx/sites-available/anihow-staging /etc/nginx/sites-enabled/anihow-staging
nginx -t
systemctl reload nginx
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos --redirect --keep-until-expiring

# Queue worker and Reverb, and what the deploy script may restart.
install -m 644 "${SCRIPT_DIR}/supervisor-anihow-staging.conf" /etc/supervisor/conf.d/anihow-staging.conf
sudoers_tmp="$(mktemp)"
cat > "${sudoers_tmp}" <<'EOF'
anihow ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart anihow-staging-reverb, /usr/bin/supervisorctl restart anihow-staging-queue\:*
EOF
visudo -cf "${sudoers_tmp}"
install -m 440 "${sudoers_tmp}" /etc/sudoers.d/anihow-staging
rm -f "${sudoers_tmp}"

# Install, key, migrate and cache.
as_anihow bash -c "cd '${STAGING_DIR}' && composer install --no-dev --optimize-autoloader --no-interaction"
if ! grep -q '^APP_KEY=base64:' "${env_file}"; then
    as_anihow bash -c "cd '${STAGING_DIR}' && php artisan key:generate --force"
fi
as_anihow bash "${STAGING_DIR}/scripts/vps/deploy-staging.sh" main
supervisorctl reread
supervisorctl update

# Test data, only into an empty staging database.
users="$(mysql --protocol=socket -u root -N -e "SELECT COUNT(*) FROM ${STAGING_DB}.users")"
if [[ "${users}" == "0" ]]; then
    as_anihow bash -c "cd '${STAGING_DIR}' && php artisan db:seed --force && php artisan anihow:demo-data && php artisan anihow:test-fixtures"
fi

cron_tmp="$(mktemp)"
crontab -u anihow -l > "${cron_tmp}" 2>/dev/null || true
if ! grep -Fq "${STAGING_DIR} && php artisan schedule:run" "${cron_tmp}"; then
    printf '%s\n' "* * * * * cd ${STAGING_DIR} && php artisan schedule:run >> /dev/null 2>&1" >> "${cron_tmp}"
    crontab -u anihow "${cron_tmp}"
fi
rm -f "${cron_tmp}"

supervisorctl status | grep 'anihow-staging' || true
echo "Staging is ready at https://${DOMAIN} (CMS at https://${DOMAIN}/admin)."
