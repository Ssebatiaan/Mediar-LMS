#!/bin/sh
# Despliega los plugins de Mediar dentro del Moodle del contenedor.
#
# Uso (desde la raíz del proyecto, en el host):
#   docker compose exec moodle sh /opt/mediar/scripts/deploy.sh
#
# Cada carpeta de plugins/ se nombra con el "frankenstyle" de Moodle
# (theme_mediar, local_mediar, block_mediar_dashboard...) y se copia a su ruta
# dentro de public/. Luego registra cambios (upgrade.php) y purga cachés.
set -eu

SRC=/opt/mediar/plugins
DST=/var/www/html/public

[ -d "$SRC" ] || { echo "No existe $SRC (¿falta el volumen en docker-compose?)"; exit 1; }

target_dir() {
  type="${1%%_*}"
  name="${1#*_}"
  case "$type" in
    theme)  echo "$DST/theme/$name" ;;
    local)  echo "$DST/local/$name" ;;
    block)  echo "$DST/blocks/$name" ;;
    mod)    echo "$DST/mod/$name" ;;
    report) echo "$DST/report/$name" ;;
    tool)   echo "$DST/admin/tool/$name" ;;
    format) echo "$DST/course/format/$name" ;;
    *)      echo "" ;;
  esac
}

count=0
for dir in "$SRC"/*/; do
  [ -d "$dir" ] || continue
  plugin="$(basename "$dir")"
  if [ ! -f "$dir/version.php" ]; then
    echo "  omitido $plugin (sin version.php)"
    continue
  fi
  target="$(target_dir "$plugin")"
  if [ -z "$target" ]; then
    echo "  omitido $plugin (tipo de plugin no reconocido)"
    continue
  fi
  mkdir -p "$target"
  rsync -a --delete --exclude .git "$dir" "$target/"
  echo "  desplegado $plugin -> ${target#/var/www/html/}"
  count=$((count + 1))
done

echo "Plugins desplegados: $count"
php /var/www/html/admin/cli/upgrade.php --non-interactive
php /var/www/html/admin/cli/purge_caches.php
echo "Listo."
