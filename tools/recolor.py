#!/usr/bin/env python3
"""
Nebula theme - palette remap for the prebuilt panel bundle.

Reads the pristine Elipso/Pterodactyl bundle from  upstream/assets-original/
and writes a purple-tinted copy to  public/assets/ :

  1. every grey/blue palette colour in the compiled JS is swapped for a
     violet-tinted equivalent (layout, class names and logic are untouched),
  2. changed chunks get new content hashes (so browser caches can't serve a
     stale file) and the webpack chunk map is updated to match,
  3. manifest.json gets the new file names and fresh SRI (sha384) hashes.

Run:  python3 tools/recolor.py
"""
import base64, hashlib, json, os, re, shutil, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "upstream", "assets-original")
OUT = os.path.join(ROOT, "public", "assets")

# (h, s, l) -> (r, g, b)      Pterodactyl grey scale -> violet-tinted scale
HSL = {
    (216, 33, 97): (247, 245, 255),  # 50   brightest text
    (214, 15, 91): (236, 233, 248),  # 100
    (210, 16, 82): (214, 208, 236),  # 200
    (211, 13, 65): (176, 168, 207),  # 300
    (211, 10, 53): (145, 136, 179),  # 400  muted text
    (211, 12, 43): (122, 112, 163),  # 500
    (209, 14, 37): (91, 80, 128),    # 600  strong borders
    (209, 18, 30): (58, 48, 96),     # 700  borders / disabled
    (209, 20, 25): (46, 37, 80),     # hover surfaces
    (210, 24, 16): (28, 22, 51),     # 800  inputs / cards
    (212, 92, 43): (124, 58, 237),   # focus blue -> violet
    (178, 78, 57): (167, 139, 250),  # mint accent -> light violet
}

RGB = {
    (19, 26, 32): (12, 8, 24),        # 900 / console background
    (10, 10, 10): (9, 6, 15),
    (17, 22, 29): (20, 13, 38),
    (12, 16, 22): (14, 9, 28),
    (34, 211, 238): (167, 139, 250),  # cyan-400 -> violet-400
    (6, 182, 212): (139, 92, 246),    # cyan-500 -> violet-500
    (59, 130, 246): (139, 92, 246),   # blue-500
    (37, 99, 235): (124, 58, 237),    # blue-600
    (96, 165, 250): (167, 139, 250),  # blue-400
    (147, 197, 253): (196, 181, 253),  # blue-300
    (29, 78, 216): (109, 40, 217),    # blue-700
    (30, 64, 175): (91, 33, 182),     # blue-800
    (239, 246, 255): (245, 243, 255),  # blue-50
    (9, 103, 210): (139, 92, 246),    # checkbox focus ring
    (40, 109, 255): (139, 92, 246),
    (86, 212, 176): (167, 139, 250),
}

HEX = {
    "#131a20": "#0c0818",
    "#0a0a0a": "#09060f",
    "#11161d": "#140d26",
    "#121821": "#150f28",
    "#22d3ee": "#a78bfa",
    "#06b6d4": "#8b5cf6",
    "#0891b2": "#7c3aed",
    "#0e7490": "#6d28d9",
    "#2563eb": "#7c3aed",
    "#1f2430": "#120c22",  # code editor background
    "#34455a": "#3a2d66",  # code editor selection
}

HSL_RE = re.compile(
    r"hsla?\(\s*(\d+)(?:deg)?\s*[, ]\s*(\d+)%\s*[, ]\s*(\d+)%\s*(?:[,/]\s*([\d.]+|var\([^()]*\))\s*)?\)"
)
RGB_RE = re.compile(
    r"rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+|var\([^()]*\))\s*)?\)"
)
HEX_RE = re.compile(r"(?<![0-9a-fA-F#])#[0-9a-fA-F]{6}(?![0-9a-fA-F])")


def sub_hsl(m):
    key = (int(m.group(1)), int(m.group(2)), int(m.group(3)))
    if key not in HSL:
        return m.group(0)
    r, g, b = HSL[key]
    a = m.group(4)
    return f"rgba({r}, {g}, {b}, {a})" if a is not None else f"rgb({r}, {g}, {b})"


def sub_rgb(m):
    key = (int(m.group(1)), int(m.group(2)), int(m.group(3)))
    if key not in RGB:
        return m.group(0)
    r, g, b = RGB[key]
    a = m.group(4)
    return f"rgba({r}, {g}, {b}, {a})" if a is not None else f"rgb({r}, {g}, {b})"


def sub_hex(m):
    return HEX.get(m.group(0).lower(), m.group(0))


def recolor(text):
    text = HSL_RE.sub(sub_hsl, text)
    text = RGB_RE.sub(sub_rgb, text)
    text = HEX_RE.sub(sub_hex, text)
    return text


def sri(data):
    return "sha384-" + base64.b64encode(hashlib.sha384(data).digest()).decode()


def main():
    if not os.path.isdir(SRC):
        sys.exit(f"missing {SRC}")
    if os.path.isdir(OUT):
        shutil.rmtree(OUT)
    shutil.copytree(SRC, OUT)

    manifest = json.load(open(os.path.join(OUT, "manifest.json")))
    js_files = sorted(f for f in os.listdir(OUT) if f.endswith(".js"))

    # 1. recolour
    contents = {}
    for f in js_files:
        contents[f] = recolor(open(os.path.join(OUT, f), encoding="utf-8").read())

    # 2. re-hash chunks (everything except the main bundle, which carries the map)
    bundle = next(f for f in js_files if f.startswith("bundle."))
    renamed = {}
    for f in js_files:
        if f == bundle:
            continue
        name, old_hash, _ = f.rsplit(".", 2)
        new_hash = hashlib.sha256(contents[f].encode()).hexdigest()[:8]
        renamed[f] = f"{name}.{new_hash}.js"
        contents[bundle] = contents[bundle].replace(f'"{old_hash}"', f'"{new_hash}"')

    # the main bundle gets its own hash last (its content is now final)
    name, old_hash, _ = bundle.rsplit(".", 2)
    new_hash = hashlib.sha256(contents[bundle].encode()).hexdigest()[:8]
    renamed[bundle] = f"{name}.{new_hash}.js"

    # 3. write files + manifest
    for f in js_files:
        os.remove(os.path.join(OUT, f))
    for f, new in renamed.items():
        with open(os.path.join(OUT, new), "w", encoding="utf-8") as fh:
            fh.write(contents[f])
    for key, entry in manifest.items():
        old = os.path.basename(entry["src"])
        if old in renamed:
            new = renamed[old]
            entry["src"] = f"/assets/{new}"
            entry["integrity"] = sri(open(os.path.join(OUT, new), "rb").read())
    with open(os.path.join(OUT, "manifest.json"), "w") as fh:
        json.dump(manifest, fh, indent=2)
        fh.write("\n")

    n = sum(len(HSL_RE.findall(open(os.path.join(SRC, f), encoding='utf-8').read())) for f in js_files)
    print(f"recoloured {len(js_files)} files ({n} hsl literals scanned) -> {OUT}")


if __name__ == "__main__":
    main()
