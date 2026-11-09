#!/usr/bin/env bash
# One-time setup of a fresh Ubuntu 24.04 server (Oracle Cloud Always Free ARM works).
# Usage (as root):  bash server-setup.sh <domain> <email> [git-repo-url]
# The same script is the "move to another host" plan: run it on any Ubuntu VM,
# then restore the latest backup (see docs/DEPLOY.md).
set -euo pipefail

DOMAIN="${1:?Usage: server-setup.sh <domain> <email> [repo-url]}"
EMAIL="${2:?Usage: server-setup.sh <domain> <email> [repo-url]}"
REPO="${3:-git@github.com:Kulthe9595/mahale_travles.git}"
APP_DIR=/var/www/mahale
PHP=8.3
DB_NAME=mahale
DB_USER=mahale

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (sudo -i)." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive
timedatectl set-timezone Asia/Kolkata

echo "==> Packages"
apt-get update -y
apt-get install -y software-properties-common curl git unzip ufw cron \
    nginx mysql-server certbot python3-certbot-nginx rclone \
    "php${PHP}-fpm" "php${PHP}-cli" "php${PHP}-mysql" "php${PHP}-mbstring" "php${PHP}-xml" \
    "php${PHP}-gd" "php${PHP}-zip" "php${PHP}-intl" "php${PHP}-bcmath" "php${PHP}-curl" "php${PHP}-sqlite3"

if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v node >/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt 22 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

echo "==> Swap (helps npm build on small VMs)"
if ! swapon --show | grep -q '/swapfile'; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Firewall"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
# Oracle's Ubuntu images ship iptables rules that reject 80/443 even when the
# VCN security list allows them. Open them and persist.
if iptables -L INPUT -n | grep -q 'REJECT'; then
    iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
    iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
    if command -v netfilter-persistent >/dev/null; then netfilter-persistent save; fi
fi

echo "==> Database"
if [[ ! -f /root/mahale-db.txt ]]; then
    DB_PASS="$(openssl rand -hex 16)"
    mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
    echo "${DB_PASS}" > /root/mahale-db.txt
    chmod 600 /root/mahale-db.txt
fi
DB_PASS="$(cat /root/mahale-db.txt)"

echo "==> Code"
if [[ ! -d "$APP_DIR/.git" ]]; then
    git clone "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"

if [[ ! -f .env ]]; then
    cp .env.example .env
    set_env() { sed -i "s|^#\?\s*$1=.*|$1=$2|" .env; grep -q "^$1=" .env || echo "$1=$2" >> .env; }
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "https://${DOMAIN}"
    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASS"
    set_env SESSION_SECURE_COOKIE true
fi

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
php artisan storage:link || true
php artisan db:seed --force
php artisan optimize

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Nginx"
cat > /etc/nginx/sites-available/mahale <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 5m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /sw.js {
        add_header Cache-Control "no-cache";
        try_files \$uri =404;
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP}-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/mahale /etc/nginx/sites-enabled/mahale
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> HTTPS"
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect

echo "==> Cron (scheduler + daily backup at 02:30 IST)"
cat > /etc/cron.d/mahale <<CRON
* * * * * www-data cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * root ${APP_DIR}/deploy/backup.sh >> /var/log/mahale-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/mahale

echo
echo "Done: https://${DOMAIN}"
echo "Next: set OWNER_EMAIL/OWNER_PASSWORD and MAIL_* in ${APP_DIR}/.env, run 'php artisan db:seed --force',"
echo "      and configure the Google Drive backup remote with 'rclone config' (see docs/DEPLOY.md)."
