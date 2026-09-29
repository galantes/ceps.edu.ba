#!/bin/bash
# Update the manually installed TEST website; runtime data is never copied.
set -euo pipefail
umask 022
source_app="$(cd "$(dirname "$0")/.." && pwd)"
private_app="$HOME/ceps-test/php-app"
public_app="$HOME/public_html/ceps.doter.info"

if [[ ! -f "$private_app/config.local.php" || ! -f "$public_app/index.php" ]]; then
  echo 'Expected existing CEPS test installation was not found. No files copied.' >&2
  exit 1
fi
if [[ ! -f "$source_app/src/bootstrap.php" || ! -d "$source_app/public/assets" ]]; then
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
echo 'CEPS test code updated. Database, configuration, admin account and uploads preserved.'
