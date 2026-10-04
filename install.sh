#!/usr/bin/env bash
#
# Nebula theme installer for Pterodactyl Panel (1.11.x)
# Ported from the Elipso installer (https://github.com/instax-dutta/elipso-theme, MIT).
#
#   sudo bash install.sh [PANEL_DIR]            install / update the theme
#   sudo bash install.sh --revert [BACKUP_DIR]  restore the files saved by the last install
#   bash install.sh --help
#
# Environment:
#   NEBULA_TARBALL_URL  override the archive that is downloaded when the script is run
#                       outside a clone of the repository
#   NEBULA_SRC          use this local directory as the theme source
#
set -euo pipefail

THEME="nebula"
TARBALL_URL="${NEBULA_TARBALL_URL:-https://github.com/anzyanazzam/theme/archive/refs/heads/main.tar.gz}"
BACKUP_ROOT="${NEBULA_BACKUP_ROOT:-/var/www}"
TMP_BASE="${TMPDIR:-/tmp}"
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROUTE_MARK_START=">>> nebula-theme"
ROUTE_MARK_END="<<< nebula-theme"

# files copied one by one (relative to the panel root)
THEME_FILES=(
    resources/views/templates/wrapper.blade.php
    resources/views/templates/base/core.blade.php
    resources/views/layouts/admin.blade.php
    resources/views/nebula/head.blade.php
    resources/views/admin/nebula/index.blade.php
    app/Http/Controllers/Admin/NebulaThemeController.php
    public/themes/nebula/nebula.css
    public/themes/nebula/nebula-admin.css
)

TMP_DIRS=()

log()  { printf '[nebula] %s\n' "$1" >&2; }
warn() { printf '[nebula] warning: %s\n' "$1" >&2; }
die()  { printf '[nebula] error: %s\n' "$1" >&2; exit 1; }

cleanup() {
    local d
    for d in "${TMP_DIRS[@]:-}"; do
        [[ -n "$d" && -d "$d" ]] && rm -rf "$d"
    done
}
trap cleanup EXIT

usage() {
    sed -n '3,15p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

need_cmd() { command -v "$1" >/dev/null 2>&1 || die "required command not found: $1"; }

as_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        need_cmd sudo
        sudo "$@"
    else
        "$@"
    fi
}

# ---------------------------------------------------------------- panel

detect_panel_dir() {
    local explicit="${1:-}"
    if [[ -n "$explicit" ]]; then
        [[ -f "$explicit/artisan" ]] || die "no artisan file found in $explicit"
        (cd "$explicit" && pwd)
        return
    fi

    local dir
    for dir in /var/www/pterodactyl /var/www/panel /var/www/html/pterodactyl /var/www/html/panel /opt/pterodactyl /opt/panel; do
        if [[ -f "$dir/artisan" ]]; then
            printf '%s\n' "$dir"
            return
        fi
    done

    die "could not find your panel automatically; run: sudo bash install.sh /path/to/panel"
}

check_panel() {
    local panel="$1"
    [[ -f "$panel/routes/admin.php" ]]                || die "$panel/routes/admin.php not found - is this a Pterodactyl 1.x panel?"
    [[ -d "$panel/app/Http/Controllers/Admin" ]]      || die "$panel/app/Http/Controllers/Admin not found - is this a Pterodactyl 1.x panel?"
    [[ -f "$panel/resources/views/layouts/admin.blade.php" ]] || die "admin layout not found in $panel"
    php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "PHP 8.1 or newer is required"
}

panel_owner() {
    local panel="$1"
    stat -c '%U:%G' "$panel/artisan" 2>/dev/null || echo 'www-data:www-data'
}

# ---------------------------------------------------------------- source

resolve_source_dir() {
    if [[ -n "${NEBULA_SRC:-}" ]]; then
        [[ -f "$NEBULA_SRC/resources/views/nebula/head.blade.php" ]] || die "NEBULA_SRC does not look like the theme"
        printf '%s\n' "$NEBULA_SRC"
        return
    fi

    if [[ -f "$SELF_DIR/resources/views/nebula/head.blade.php" ]]; then
        printf '%s\n' "$SELF_DIR"
        return
    fi

    need_cmd curl
    need_cmd tar
    local work
    work="$(mktemp -d "$TMP_BASE/nebula-install.XXXXXX")"
    TMP_DIRS+=("$work")

    log "downloading the theme from $TARBALL_URL"
    curl -fsSL "$TARBALL_URL" -o "$work/theme.tar.gz" || die "download failed (is the repository public?)"
    mkdir -p "$work/src"
    tar -xzf "$work/theme.tar.gz" -C "$work/src"

    local extracted
    extracted="$(find "$work/src" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
    [[ -n "$extracted" && -f "$extracted/resources/views/nebula/head.blade.php" ]] || die "the downloaded archive does not contain the theme"
    printf '%s\n' "$extracted"
}

# ---------------------------------------------------------------- backup

# true when this panel already runs Nebula (route block + patched wrapper)
is_installed() {
    local panel="$1"
    grep -qF "$ROUTE_MARK_START" "$panel/routes/admin.php" 2>/dev/null \
        && grep -q "nebula.head" "$panel/resources/views/templates/wrapper.blade.php" 2>/dev/null
}

# newest backup that was taken *before* Nebula was installed (optionally for one panel)
find_pristine_backup() {
    local panel="${1:-}" d
    while IFS= read -r d; do
        [[ -f "$d/pristine" ]] || continue
        if [[ -n "$panel" && "$(cat "$d/panel_path" 2>/dev/null)" != "$panel" ]]; then
            continue
        fi
        printf '%s\n' "$d"
        return 0
    done < <(ls -1d "$BACKUP_ROOT"/nebula-backup-* 2>/dev/null | sort -r)
    return 1
}

backup_panel() {
    local panel="$1" backup="$2" rel

    log "backing up the current files to $backup"
    as_root mkdir -p "$backup/files"
    : > "$backup/added.txt"

    for rel in "${THEME_FILES[@]}" routes/admin.php; do
        if [[ -f "$panel/$rel" ]]; then
            as_root install -D -m 0644 "$panel/$rel" "$backup/files/$rel"
        else
            printf '%s\n' "$rel" | as_root tee -a "$backup/added.txt" >/dev/null
        fi
    done

    if [[ -d "$panel/public/assets" ]]; then
        as_root cp -a "$panel/public/assets" "$backup/assets"
    fi
    printf '%s\n' "$panel" | as_root tee "$backup/panel_path" >/dev/null
    if ! is_installed "$panel"; then
        as_root touch "$backup/pristine"
    fi
}

# ---------------------------------------------------------------- install

install_files() {
    local src="$1" panel="$2" rel

    log "installing views, controller and stylesheets"
    for rel in "${THEME_FILES[@]}"; do
        [[ -f "$src/$rel" ]] || die "theme file missing from the source: $rel"
        as_root install -D -m 0644 "$src/$rel" "$panel/$rel"
    done

    as_root install -d -m 0755 "$panel/public/themes/$THEME/uploads"

    if [[ -f "$src/public/assets/manifest.json" ]]; then
        log "installing the prebuilt panel assets"
        local tmp="$panel/public/assets.nebula.$$"
        as_root rm -rf "$tmp"
        as_root cp -a "$src/public/assets" "$tmp"
        as_root rm -rf "$panel/public/assets"
        as_root mv "$tmp" "$panel/public/assets"
    else
        warn "no prebuilt assets in the theme source - leaving public/assets untouched"
    fi
}

patch_routes() {
    local panel="$1" file="$1/routes/admin.php"

    if grep -qF "$ROUTE_MARK_START" "$file"; then
        log "admin route already present"
        return
    fi

    log "adding the admin route (Admin > Theme)"
    # make sure the file ends with a newline, then append the block after a blank line
    if [[ -s "$file" && -n "$(tail -c1 "$file")" ]]; then
        printf '\n' | as_root tee -a "$file" >/dev/null
    fi
    { printf '\n'; cat "$SOURCE_DIR/stubs/admin-routes.php"; } | as_root tee -a "$file" >/dev/null
}

unpatch_routes() {
    local file="$1/routes/admin.php"
    [[ -f "$file" ]] || return 0
    if grep -qF "$ROUTE_MARK_START" "$file"; then
        as_root sed -i "/\/\/ $ROUTE_MARK_START/,/\/\/ $ROUTE_MARK_END/d" "$file"
        # drop the blank line(s) the block left at the end of the file
        as_root sed -i -e :a -e '/^\n*$/{$d;N;ba' -e '}' "$file"
    fi
}

clear_caches() {
    local panel="$1" cmd
    log "clearing Laravel caches"
    for cmd in view:clear route:clear cache:clear config:clear; do
        as_root php "$panel/artisan" "$cmd" >/dev/null 2>&1 || warn "artisan $cmd failed (not fatal)"
    done
}

fix_permissions() {
    local panel="$1" owner
    owner="$(panel_owner "$panel")"
    log "setting ownership to $owner"
    as_root chown -R "$owner" \
        "$panel/resources/views" \
        "$panel/public/themes/$THEME" \
        "$panel/public/assets" \
        "$panel/app/Http/Controllers/Admin/NebulaThemeController.php" >/dev/null 2>&1 || warn "could not change ownership"
    as_root install -d -o "${owner%%:*}" -g "${owner##*:}" -m 0755 "$panel/storage/app" 2>/dev/null || true
}

# ---------------------------------------------------------------- commands

do_install() {
    need_cmd php
    need_cmd tar

    local panel
    panel="$(detect_panel_dir "${1:-}")"
    check_panel "$panel"

    SOURCE_DIR="$(resolve_source_dir)"
    [[ -f "$SOURCE_DIR/stubs/admin-routes.php" ]] || die "stubs/admin-routes.php missing from the theme source"

    local backup
    log "panel found at $panel"
    if is_installed "$panel" && backup="$(find_pristine_backup "$panel")"; then
        log "Nebula is already installed - updating in place (your original backup is kept)"
    else
        backup="$BACKUP_ROOT/nebula-backup-$(date +%Y%m%d-%H%M%S)"
        backup_panel "$panel" "$backup"
    fi
    install_files "$SOURCE_DIR" "$panel"
    patch_routes "$panel"
    clear_caches "$panel"
    fix_permissions "$panel"

    printf '\n'
    printf '  Panel:   %s\n' "$panel"
    printf '  Backup:  %s\n' "$backup"
    printf '  Theme:   Nebula installed\n\n'
    printf '  1. Hard refresh your browser (Ctrl+Shift+R / Cmd+Shift+R).\n'
    printf '  2. Open  Admin > Theme  to set the logo, favicon and login background.\n'
    printf '  3. To undo:  sudo bash %s --revert %s\n\n' "$0" "$backup"
}

do_revert() {
    local backup="${1:-}"

    if [[ -z "$backup" ]]; then
        backup="$(find_pristine_backup || true)"
        [[ -n "$backup" ]] || backup="$(ls -1d "$BACKUP_ROOT"/nebula-backup-* 2>/dev/null | sort | tail -n 1 || true)"
        [[ -n "$backup" ]] || die "no backup found in $BACKUP_ROOT"
    fi
    [[ -d "$backup/files" ]] || die "$backup is not a Nebula backup"

    local panel
    panel="$(cat "$backup/panel_path" 2>/dev/null || true)"
    [[ -n "$panel" && -f "$panel/artisan" ]] || die "the panel recorded in the backup ($panel) no longer exists"

    log "restoring from $backup"

    local rel
    # files that existed before: put them back
    while IFS= read -r rel; do
        [[ "$rel" == routes/admin.php ]] && continue
        as_root install -D -m 0644 "$backup/files/$rel" "$panel/$rel"
    done < <(cd "$backup/files" && find . -type f ! -path './routes/admin.php' | sed 's#^\./##')

    # files the theme added: remove them (uploads and saved settings are kept)
    while IFS= read -r rel; do
        [[ -n "$rel" && "$rel" != routes/admin.php ]] && as_root rm -f "$panel/$rel"
    done < "$backup/added.txt"

    unpatch_routes "$panel"

    if [[ -d "$backup/assets" ]]; then
        as_root rm -rf "$panel/public/assets"
        as_root cp -a "$backup/assets" "$panel/public/assets"
    fi

    clear_caches "$panel"
    fix_permissions "$panel" || true
    log "reverted - hard refresh your browser"
}

case "${1:-}" in
    -h|--help) usage ;;
    --revert)  do_revert "${2:-}" ;;
    *)         do_install "${1:-}" ;;
esac
