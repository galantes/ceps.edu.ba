#!/bin/bash
# New hosting deployment. Never copy database, credentials, or uploads.
set -euo pipefail
umask 022
source_app="$(cd "$(dirname "$0")/.." && pwd)"
private_app="$HOME/ceps-app/php-app"
public_app="$HOME/public_html/ceps.edu.ba"
if [[ ! -f "$private_app/config.local.php" || ! -f "$private_app/var/admin.json" || ! -d "$public_app" ]]; then
  echo 'Create the document root and upload private config.local.php and var/admin.json first. No files copied.' >&2
  exit 1
fi
if [[ ! -f "$source_app/src/bootstrap.php" || ! -d "$source_app/public/assets" || ! -f "$source_app/hosting/index.php" || ! -f "$source_app/hosting/.htaccess" ]]; then
  echo 'Incomplete source checkout. No files copied.' >&2
  exit 1
fi
for directory in src content; do
  /usr/bin/install -d -m 755 "$private_app/$directory"
  /bin/cp -R "$source_app/$directory/." "$private_app/$directory/"
done
/usr/bin/install -d -m 755 "$private_app/public" "$public_app/assets"
/usr/bin/install -m 644 "$source_app/public/index.php" "$private_app/public/index.php"
/bin/cp -R "$source_app/public/assets/." "$public_app/assets/"
/usr/bin/install -m 644 "$source_app/hosting/.htaccess" "$public_app/.htaccess"
/usr/bin/install -m 644 "$source_app/hosting/index.php" "$public_app/index.php"
echo 'CEPS code deployed. Database, configuration, admin account, uploads and Files preserved.'
