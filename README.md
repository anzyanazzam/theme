# Nebula - Pterodactyl theme

Dark purple glass theme for the [Pterodactyl](https://pterodactyl.io) panel (1.11.x).
About 70% deep dark, 30% violet glow, frosted-glass cards, mobile friendly.
Based on [Elipso](https://github.com/instax-dutta/elipso-theme) (MIT) - same prebuilt panel code, so the
server pages (power buttons, console, files...) keep exactly the stock layout; only colours and surfaces change.

## Features
- Dark purple gradient + glassmorphism (blurred translucent cards, frosted top bar, rounded Apple-style buttons)
- Responsive: sticky compact top bar, 16px inputs on phones (no iOS zoom), touch-sized icon buttons
- **Admin > Theme** page (new):
  - upload a **logo** - shown top-left *before the panel name*, on the login page and in the admin area
  - **favicon follows the logo** automatically (also used as the Apple touch icon)
  - **login background**: default gradient or your own image, with darkening and blur sliders
  - option to hide the panel name and show only the logo
- New login page design; one-command installer with backup and revert

## Install
On the panel server:

```bash
curl -sL https://raw.githubusercontent.com/anzyanazzam/theme/main/install.sh -o /tmp/nebula.sh && sudo bash /tmp/nebula.sh
```

Custom panel path: `sudo bash /tmp/nebula.sh /path/to/panel`.
Then hard-refresh the browser (Ctrl+Shift+R) and open **Admin > Theme**.

The installer backs up everything it touches to `/var/www/nebula-backup-<time>/`, copies the views, controller and CSS,
swaps in the recoloured `public/assets`, adds one route block to `routes/admin.php`, clears Laravel caches and fixes ownership.
Running it again updates in place and keeps your original backup.

## Revert
```bash
sudo bash /tmp/nebula.sh --revert            # restores the backup taken before the first install
sudo bash /tmp/nebula.sh --revert /var/www/nebula-backup-YYYYMMDD-HHMMSS
```
Uploaded logos/backgrounds and `storage/app/nebula-theme.json` are kept.

## Notes
- Needs Pterodactyl 1.11.x (the prebuilt assets come from that build) and PHP 8.1+. After a panel upgrade
  (`p:upgrade`) run the installer again.
- Settings are stored in `storage/app/nebula-theme.json`, uploads in `public/themes/nebula/uploads/`. No migrations.
- Uploads: logo PNG/JPG/WebP/GIF up to 2 MB, background PNG/JPG/WebP up to 8 MB (SVG is rejected on purpose).
- Fonts (Inter, JetBrains Mono) load from Google Fonts; system fonts are the fallback.

## How it is built
- `tools/recolor.py` remaps the grey/blue palette inside the prebuilt Elipso bundle (`upstream/assets-original`)
  to violet, re-hashes the chunks and refreshes `manifest.json` integrity hashes -> `public/assets`.
  Edit the colour tables in the script and run `python3 tools/recolor.py` to change the palette.
- `public/themes/nebula/nebula.css` (panel) and `nebula-admin.css` (admin) add the gradient, glass, logo slot and mobile rules.
  The `--elipso-*` variable names are kept because the prebuilt bundle reads them.
- `app/Http/Controllers/Admin/NebulaThemeController.php` + `resources/views/admin/nebula/index.blade.php` = the Theme admin page.
- `upstream/` holds the untouched Elipso sources/assets for reference.

## License
MIT. Built on Elipso by instax-dutta (MIT) and Pterodactyl (MIT).
