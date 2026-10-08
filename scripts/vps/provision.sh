#!/usr/bin/env bash
# One-time (idempotent) AniHow VPS setup. Run as root:
#   bash provision.sh anihow.tech
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run provision.sh as root." >&2
    exit 1
fi

if [[ $# -ne 1 ]]; then
    echo "Usage: provision.sh <domain>" >&2
    exit 1
fi

DOMAIN="$1"
if [[ ! "${DOMAIN}" =~ ^[A-Za-z0-9.-]+$ ]]; then
    echo "Domain must contain only letters, digits, dots, and hyphens." >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export DEBIAN_FRONTEND=noninteractive

timedatectl set-timezone Asia/Manila

if [[ -z "$(swapon --show --noheadings)" ]]; then
    if [[ ! -f /swapfile ]]; then
        fallocate -l 2G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile
    fi
    swapon /swapfile
    if ! grep -qE '^/swapfile[[:space:]]' /etc/fstab; then
        printf '/swapfile none swap sw 0 0\n' >> /etc/fstab
    fi
fi

apt-get update
apt-get -y upgrade
apt-get -y install ca-certificates curl gnupg software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get -y install \
    nginx \
    mysql-server \
    supervisor \
    git \
    unzip \
    curl \
    certbot \
    python3-certbot-nginx \
    fail2ban \
    unattended-upgrades \
    ufw \
    php8.4-fpm \
    php8.4-cli \
    php8.4-mysql \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-gd \
    php8.4-opcache

install_composer() {
    local work expected actual
    work="$(mktemp -d)"
    expected="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '${work}/composer-setup.php');"
    actual="$(php -r "echo hash_file('sha384', '${work}/composer-setup.php');")"
    if [[ "${expected}" != "${actual}" ]]; then
        rm -rf "${work}"
        echo "Composer installer checksum did not match." >&2
        exit 1
    fi
    php "${work}/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "${work}"
}

install_composer

if ! id anihow >/dev/null 2>&1; then
    useradd --create-home --shell /bin/bash --user-group anihow
fi
passwd -l anihow >/dev/null
install -d -m 700 -o anihow -g anihow /home/anihow/.ssh
if [[ -f /root/.ssh/authorized_keys ]]; then
    install -m 600 -o anihow -g anihow /root/.ssh/authorized_keys /home/anihow/.ssh/authorized_keys
fi
install -d -m 755 -o anihow -g anihow /var/www/anihow

php_limits="$(cat <<'EOF'
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 60
date.timezone = Asia/Manila
EOF
)"
printf '%s\n' "${php_limits}" > /etc/php/8.4/fpm/conf.d/99-anihow.ini
printf '%s\n' "${php_limits}" > /etc/php/8.4/cli/conf.d/99-anihow.ini

pool="/etc/php/8.4/fpm/pool.d/www.conf"
sed -i 's/^user = .*/user = anihow/' "${pool}"
sed -i 's/^group = .*/group = anihow/' "${pool}"
sed -i 's/^listen\.owner = .*/listen.owner = www-data/' "${pool}"
sed -i 's/^listen\.group = .*/listen.group = www-data/' "${pool}"
systemctl enable php8.4-fpm
systemctl restart php8.4-fpm

sudoers_tmp="$(mktemp)"
cat > "${sudoers_tmp}" <<'EOF'
anihow ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm, /usr/bin/systemctl reload nginx, /usr/bin/supervisorctl restart anihow-reverb, /usr/bin/supervisorctl restart anihow-queue\:*, /usr/bin/supervisorctl status
EOF
visudo -cf "${sudoers_tmp}"
install -m 440 "${sudoers_tmp}" /etc/sudoers.d/anihow
rm -f "${sudoers_tmp}"

systemctl enable --now mysql

db_env="/root/anihow-db.env"
if [[ ! -s "${db_env}" ]]; then
    umask 077
    printf 'DB_PASSWORD=%s\n' "$(openssl rand -hex 24)" > "${db_env}"
    chmod 600 "${db_env}"
fi
# Hex-only password, read back for MySQL setup and never printed.
db_password="$(sed -n 's/^DB_PASSWORD=//p' "${db_env}" | head -n 1)"
if [[ -z "${db_password}" || ! "${db_password}" =~ ^[0-9a-f]+$ ]]; then
    unset db_password
    echo "Database password file is missing a hex password." >&2
    exit 1
fi
mysql --protocol=socket -u root <<SQL
CREATE DATABASE IF NOT EXISTS anihow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'anihow'@'localhost' IDENTIFIED BY '${db_password}';
ALTER USER 'anihow'@'localhost' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON anihow.* TO 'anihow'@'localhost';
FLUSH PRIVILEGES;
SQL
umask 077
printf '[client]\nuser=anihow\npassword=%s\n' "${db_password}" > /root/anihow-mysqldump.cnf
chmod 600 /root/anihow-mysqldump.cnf
unset db_password

if grep -q '^IPV6=' /etc/default/ufw; then
    sed -i 's/^IPV6=.*/IPV6=yes/' /etc/default/ufw
else
    printf 'IPV6=yes\n' >> /etc/default/ufw
fi
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

install -d /etc/fail2ban/jail.d
cat > /etc/fail2ban/jail.d/anihow-sshd.local <<'EOF'
[sshd]
enabled = true
EOF
systemctl enable --now fail2ban
systemctl restart fail2ban

cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
systemctl enable --now unattended-upgrades

nginx_tmp="$(mktemp)"
sed "s/__DOMAIN__/${DOMAIN}/g" "${SCRIPT_DIR}/nginx-anihow.conf" > "${nginx_tmp}"
install -m 644 "${nginx_tmp}" /etc/nginx/sites-available/anihow
rm -f "${nginx_tmp}"
ln -sfn /etc/nginx/sites-available/anihow /etc/nginx/sites-enabled/anihow
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable nginx
systemctl reload nginx

install -m 644 "${SCRIPT_DIR}/supervisor-anihow.conf" /etc/supervisor/conf.d/anihow.conf
systemctl enable supervisor
# Programs are loaded later, after the application exists.

install -d -m 700 /root/anihow-setup
src="$(realpath "${SCRIPT_DIR}/backup.sh")"
dst="/root/anihow-setup/backup.sh"
if [[ ! -e "${dst}" || "$(realpath "${dst}")" != "${src}" ]]; then
    install -m 700 "${src}" "${dst}"
fi
chmod 700 "${dst}"
install -d -m 700 /var/backups/anihow

cron_tmp="$(mktemp)"
crontab -u anihow -l > "${cron_tmp}" 2>/dev/null || true
if ! grep -Fq 'artisan schedule:run' "${cron_tmp}"; then
    printf '%s\n' '* * * * * cd /var/www/anihow && php artisan schedule:run >> /dev/null 2>&1' >> "${cron_tmp}"
    crontab -u anihow "${cron_tmp}"
fi
rm -f "${cron_tmp}"

cat > /etc/cron.d/anihow-backup <<'EOF'
30 2 * * * root /root/anihow-setup/backup.sh
EOF
chmod 644 /etc/cron.d/anihow-backup

echo "Provisioned ${DOMAIN}. Supervisor programs are installed and not started."
