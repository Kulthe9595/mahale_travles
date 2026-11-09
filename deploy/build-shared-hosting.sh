#!/usr/bin/env bash
# Builds a ready-to-upload folder + zip for free shared hosting without SSH (e.g. InfinityFree).
# Usage: deploy/build-shared-hosting.sh [output-dir]
# Upload the *contents* of the produced mahale-upload/ folder into htdocs with FileZilla.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/build}"
PKG="$OUT/mahale-upload"

rm -rf "$PKG" && mkdir -p "$PKG"
echo "==> Copying committed code"
git -C "$ROOT" archive HEAD | tar -x -C "$PKG"

cd "$PKG"
echo "==> PHP packages (no dev)"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
echo "==> Building CSS/JS"
npm ci --silent
npm run build >/dev/null
rm -rf node_modules tests .github deploy docs/superpowers phpunit.xml vite.config.js package*.json \
    resources/js resources/css .editorconfig .gitattributes .styleci.yml AGENTS.md CLAUDE.md database/database.sqlite
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public bootstrap/cache

echo "==> Routing (htdocs -> public, storage files)"
cat > .htaccess <<'HT'
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^storage/(.*)$ storage/app/public/$1 [L]
RewriteCond %{REQUEST_URI} !^/public/
RewriteCond %{REQUEST_URI} !^/storage/app/public/
RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
<FilesMatch "^\.">
Require all denied
</FilesMatch>
HT
cat > storage/.htaccess <<'HT'
Require all denied
HT
cat > storage/app/public/.htaccess <<'HT'
Require all granted
HT

echo "==> Production .env"
APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
CODE="MT-$(LC_ALL=C tr -dc 'A-HJ-NP-Z2-9' </dev/urandom | head -c 4)-$(LC_ALL=C tr -dc 'A-HJ-NP-Z2-9' </dev/urandom | head -c 4)"
cp .env.example .env
set_env() { if grep -qE "^#?\s*$1=" .env; then sed -i -E "s|^#?\s*$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi; }
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_KEY "$APP_KEY"
set_env APP_URL "https://YOUR-SITE.infinityfreeapp.com"
set_env LOG_LEVEL warning
set_env DB_CONNECTION mysql
set_env DB_HOST "sqlXXX.infinityfree.com"
set_env DB_PORT 3306
set_env DB_DATABASE "if0_XXXXXXXX_mahale"
set_env DB_USERNAME "if0_XXXXXXXX"
set_env DB_PASSWORD "PUT-YOUR-DATABASE-PASSWORD-HERE"
set_env SESSION_DRIVER file
set_env CACHE_STORE file
set_env QUEUE_CONNECTION sync
set_env INSTALL_CODE "$CODE"
sed -i -E '/^OWNER_(NAME|EMAIL|PASSWORD)=/d' .env

cat > README.txt <<TXT
Mahale Tours billing - upload package

Setup code for /install:  $CODE
(Keep this private. It stops anyone else from creating the first login.)

1. Upload everything in this folder (including .env and .htaccess) into htdocs with FileZilla.
2. In the File Manager open htdocs/.env and fill in APP_URL and the four DB_ lines
   (host, database, username, password from InfinityFree > MySQL Databases).
3. Open https://your-site/install, enter the setup code, the owner email and a password.
Full guide: INFINITYFREE.md
TXT
cp "$ROOT/docs/INFINITYFREE.md" . 2>/dev/null || true

echo "==> Checking host limits"
fail=0
while IFS= read -r f; do echo "  too big (>10MB): $f"; fail=1; done < <(find . -type f -size +10M)
while IFS= read -r f; do echo "  JS/CSS/HTML over 1MB: $f"; fail=1; done < <(find . -type f \( -name '*.js' -o -name '*.css' -o -name '*.html' \) -size +1024k)
while IFS= read -r f; do echo "  .htaccess over 1KB: $f"; fail=1; done < <(find . -name .htaccess -size +1k)
[[ $fail -eq 0 ]] || { echo "Package breaks host limits." >&2; exit 1; }

echo "==> Zipping"
( cd "$OUT" && rm -f mahale-upload.zip && zip -qr mahale-upload.zip mahale-upload )
echo "Files: $(find "$PKG" -type f | wc -l)  Size: $(du -sh "$PKG" | cut -f1)  Zip: $(du -h "$OUT/mahale-upload.zip" | cut -f1)"
echo "Setup code: $CODE"
