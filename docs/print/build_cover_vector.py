#!/usr/bin/env python3
"""Build a pure-vector CMYK EPS and a companion PDF for the Alex cover.

Geometry comes from Figma frame 5000:1645 (1291 x 1763 px). Text is already
outlined. Colors are forced to the printer's CMYK values. There is no
transparency, no image XObject and no font, so nothing in the file can be
rasterized by a transparency flattener.
"""
from __future__ import annotations

import math
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SRC = ROOT / "src" if (ROOT / "src" / "dots.txt").exists() else ROOT
OUT = ROOT
PT = 72 / 25.4
TRIM_X = 126.0
TRIM_Y = 126.0
TRIM_W = 1039.0
TRIM_H = 1511.0
SCALE = (165.0 / TRIM_W) * PT  # pt per px, 1039 px = 165 mm
PAGE_W = 205.0 * PT
PAGE_H = 280.0 * PT
BLEED = 20.0 * PT

CMYK = {
    "white": (0, 0, 0, 0),
    "black": (0, 0, 0, 1),
    "#000000": (0, 0, 0, 1),
    "#696969": (0, 0, 0, 0.59),
    "#3A3A3A": (0, 0, 0, 0.77),
    "#C9C9C9": (0, 0, 0, 0.21),
    "#97AE96": (0.337, 0.039, 0.361, 0.259),
    "#F4F4F4": (0, 0, 0, 1 - 244 / 255),
    "#E2E2E2": (0, 0, 0, 1 - 226 / 255),
}

TOKEN = re.compile(
    r"[MmLlHhVvCcSsQqTtAaZz]|[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?"
)


def djb(s: str) -> str:
    x = 5381
    for ch in s:
        a = x & 0xFFFFFFFF
        if a >= 0x80000000:
            a -= 0x100000000
        r = (a * 33) & 0xFFFFFFFF
        if r >= 0x80000000:
            r -= 0x100000000
        x = (r ^ ord(ch)) & 0xFFFFFFFF
        if x >= 0x80000000:
            x -= 0x100000000
    return f"{x & 0xFFFFFFFF:x}"


def read_piece(name: str) -> str:
    return (SRC / name).read_text().replace("\n", "")


def load_paths() -> dict[str, str]:
    found = {
        "a2c40e71": read_piece("p0.txt"),
        "6217ea7": read_piece("p1.txt"),
        "27130571": read_piece("p2a.txt") + read_piece("p2b.txt"),
        "53d14bc1": read_piece("p3.txt"),
        "a22fe398": read_piece("p4.txt"),
        "52b70948": read_piece("p5.txt"),
        "5fdfb0cf": read_piece("p7a.txt") + read_piece("p7b.txt"),
        "c01ecd1c": read_piece("p8.txt"),
    }
    if (SRC / "p6a.txt").exists() and (SRC / "p6b.txt").exists():
        found["90fc16a8"] = read_piece("p6a.txt") + read_piece("p6b.txt")
    parts = [SRC / f"p9{s}.txt" for s in "abcd"]
    if all(p.exists() for p in parts):
        found["35ceeda"] = "".join(p.read_text().replace("\n", "") for p in parts)
    bad = {k: djb(v) for k, v in found.items() if djb(v) != k}
    if bad:
        raise SystemExit(f"path hash mismatch: {bad}")
    return found


def parse_path(d: str) -> list[tuple]:
    tokens = TOKEN.findall(d)
    i = 0
    cmd = None
    cx = cy = 0.0
    sx = sy = 0.0
    out: list[tuple] = []

    def num() -> float:
        nonlocal i
        v = float(tokens[i])
        i += 1
        return v

    while i < len(tokens):
        tok = tokens[i]
        if tok.isalpha():
            cmd = tok
            i += 1
            if cmd in "Zz":
                out.append(("h",))
                cx, cy = sx, sy
                cmd = None
            continue
        if cmd is None:
            raise ValueError(f"number without command at {tok}")
        rel = cmd.islower()
        c = cmd.upper()
        if c == "M":
            x, y = num(), num()
            if rel:
                x += cx
                y += cy
            out.append(("m", x, y))
            cx, cy = sx, sy = x, y
            cmd = "l" if rel else "L"
        elif c == "L":
            x, y = num(), num()
            if rel:
                x += cx
                y += cy
            out.append(("l", x, y))
            cx, cy = x, y
        elif c == "H":
            x = num()
            if rel:
                x += cx
            out.append(("l", x, cy))
            cx = x
        elif c == "V":
            y = num()
            if rel:
                y += cy
            out.append(("l", cx, y))
            cy = y
        elif c == "C":
            x1, y1, x2, y2, x, y = num(), num(), num(), num(), num(), num()
            if rel:
                x1 += cx
                y1 += cy
                x2 += cx
                y2 += cy
                x += cx
                y += cy
            out.append(("c", x1, y1, x2, y2, x, y))
            cx, cy = x, y
        else:
            raise ValueError(f"unsupported path command {cmd}")
    return out


def rounded_rect(x: float, y: float, w: float, h: float, r: float) -> list[tuple]:
    r = min(r, w / 2, h / 2)
    k = 0.5522847498 * r
    return [
        ("m", x + r, y),
        ("l", x + w - r, y),
        ("c", x + w - r + k, y, x + w, y + r - k, x + w, y + r),
        ("l", x + w, y + h - r),
        ("c", x + w, y + h - r + k, x + w - r + k, y + h, x + w - r, y + h),
        ("l", x + r, y + h),
        ("c", x + r - k, y + h, x, y + h - r + k, x, y + h - r),
        ("l", x, y + r),
        ("c", x, y + r - k, x + r - k, y, x + r, y),
        ("h",),
    ]


def rect_path(x: float, y: float, w: float, h: float) -> list[tuple]:
    return [("m", x, y), ("l", x + w, y), ("l", x + w, y + h), ("l", x, y + h), ("h",)]


def map_point(x: float, y: float) -> tuple[float, float]:
    # Trim top-left (126, 126) sits 20 mm from the top and left of a 205x280 sheet.
    px = BLEED + (x - TRIM_X) * SCALE
    py = BLEED + ((TRIM_Y + TRIM_H) - y) * SCALE
    return px, py


def emit_path(cmds: list[tuple], fmt) -> str:
    parts = []
    for c in cmds:
        if c[0] == "m":
            x, y = map_point(c[1], c[2])
            parts.append(fmt.format(x=x, y=y, op="m"))
        elif c[0] == "l":
            x, y = map_point(c[1], c[2])
            parts.append(fmt.format(x=x, y=y, op="l"))
        elif c[0] == "c":
            x1, y1 = map_point(c[1], c[2])
            x2, y2 = map_point(c[3], c[4])
            x, y = map_point(c[5], c[6])
            parts.append(
                f"{x1:.3f} {y1:.3f} {x2:.3f} {y2:.3f} {x:.3f} {y:.3f} c"
            )
        elif c[0] == "h":
            parts.append("h")
    return "\n".join(parts)


def fmt_move(x, y, op):
    return f"{x:.3f} {y:.3f} {op}"


def cmyk_of(name: str) -> tuple[float, float, float, float]:
    if name not in CMYK:
        raise KeyError(name)
    return CMYK[name]


def load_rows(path: Path) -> list[str]:
    rows = []
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line[0] not in ".abg1":
            continue
        if set(line) <= set(".abg1"):
            rows.append(line)
    return rows


def dot_marks() -> list[tuple]:
    marks = []
    left = []
    for ln in (SRC / "dots.txt").read_text().splitlines():
        if ln.startswith("RIGHT"):
            break
        if ln and set(ln) <= set("ag."):
            left.append(ln)
    ys = [
        962, 990, 1018, 1045.5, 1073.5, 1101.5, 1129.5, 1157.5,
        1185.5, 1213.5, 1241.5, 1269.5, 1297.5, 1325.5, 1353.5, 1381.5,
    ]
    if len(left) != len(ys):
        raise SystemExit(f"left dot rows {len(left)} != {len(ys)}")
    for row, y in zip(left, ys):
        for col, ch in enumerate(row):
            if ch == ".":
                continue
            cx = 203.5 + col * 28
            fill = "#97AE96" if ch == "g" else "#F4F4F4"
            if y in (962, 990, 1018):
                marks.append(("ellipse", cx, y, 7.5, 7.0, fill))
            else:
                marks.append(("circle", cx, y, 7.5, fill))
    return marks


def right_dots() -> list[tuple]:
    text = (SRC / "dots.txt").read_text().splitlines()
    start = text.index("bbbbbbbb")
    rows = text[start:]
    marks = []
    for i, row in enumerate(rows):
        y = 826.438 + i * 26.574
        for col, ch in enumerate(row.strip()):
            if ch == ".":
                continue
            cx = 835.438 + col * 27.176
            fill = "#97AE96" if ch == "g" else "#E2E2E2"
            marks.append(("circle", cx, y, 11.438, fill))
    return marks


def ellipse_path(cx, cy, rx, ry) -> list[tuple]:
    k = 0.5522847498
    return [
        ("m", cx + rx, cy),
        ("c", cx + rx, cy + ky(ry, k), cx + kx(rx, k), cy + ry, cx, cy + ry),
        ("c", cx - kx(rx, k), cy + ry, cx - rx, cy + ky(ry, k), cx - rx, cy),
        ("c", cx - rx, cy - ky(ry, k), cx - kx(rx, k), cy - ry, cx, cy - ry),
        ("c", cx + kx(rx, k), cy - ry, cx + rx, cy - ky(ry, k), cx + rx, cy),
        ("h",),
    ]


def kx(r, k):
    return r * k


def ky(r, k):
    return r * k


def circle_path(cx, cy, r) -> list[tuple]:
    return ellipse_path(cx, cy, r, r)


CHIP_STROKES = [
    "M805 529H1053.66C1069.68 529 1082.66 541.984 1082.66 558V1315.71C1082.66 1331.73 1069.68 1344.71 1053.66 1344.71H805C788.984 1344.71 776 1331.73 776 1315.71V558C776 541.984 788.984 529 805 529Z",
    "M827.5 540.25H1031.16C1047.18 540.25 1060.16 553.233 1060.16 569.25V1256.64C1060.16 1272.65 1047.18 1285.64 1031.16 1285.64H1025.25C1008.13 1285.64 994.248 1299.51 994.248 1316.64V1344.71H864.416V1316.64C864.415 1299.51 850.536 1285.64 833.416 1285.64H827.5C811.483 1285.64 798.5 1272.65 798.5 1256.64V569.25C798.5 553.233 811.483 540.25 827.5 540.25Z",
    "M835.234 803.914H1023.43C1039.45 803.914 1052.43 816.897 1052.43 832.914V1259.43C1052.43 1268.81 1044.82 1276.43 1035.43 1276.43H1026.11C1003.46 1276.43 985.107 1294.78 985.107 1317.43V1344.71H873.556V1317.43C873.556 1294.78 855.199 1276.43 832.556 1276.43H823.234C813.845 1276.43 806.234 1268.82 806.234 1259.43V832.914C806.234 816.897 819.217 803.914 835.234 803.914Z",
]
CORNER_FILLS = [
    "M664 382L664 384L662 384L662 382L664 382ZM664 382L664 380L695 380L695 382L695 384L664 384L664 382ZM664 351L666 351L666 382L664 382L662 382L662 351L664 351Z",
    "M1090 382L1090 384L1088 384L1088 382L1090 382ZM1090 382L1090 380L1121 380L1121 382L1121 384L1090 384L1090 382ZM1090 351L1092 351L1092 382L1090 382L1088 382L1088 351L1090 351Z",
]
ARROW = "M759 636H789.8M785.4 630L792 636L785.4 642"

TEXT_ORDER = [
    ("a2c40e71", "#696969"),
    ("6217ea7", "#97AE96"),
    ("27130571", "#696969"),
    ("53d14bc1", "black"),
    ("a22fe398", "black"),
    ("52b70948", "black"),
    ("90fc16a8", "#696969"),
    ("5fdfb0cf", "#696969"),
    ("c01ecd1c", "#97AE96"),
    ("35ceeda", "#3A3A3A"),
]


def qr_modules() -> list[tuple]:
    lines = [
        ln.strip()
        for ln in (SRC / "qr.txt").read_text().splitlines()
        if ln.startswith(("1", "."))
    ]
    mods = []
    x0, y0, mod = 824.415, 565.415, 5.70732
    for r, row in enumerate(lines):
        for c, ch in enumerate(row):
            if ch == "1":
                mods.append((x0 + c * mod, y0 + r * mod, mod, mod))
    return mods


class Draw:
    def __init__(self):
        self.chunks: list[str] = []
        self.missing: list[str] = []

    def fill(self, color: str, cmds: list[tuple]):
        c, m, y, k = cmyk_of(color)
        body = emit_path(cmds, "{x:.3f} {y:.3f} {op}")
        self.chunks.append(f"{c:.4f} {m:.4f} {y:.4f} {k:.4f} k\n{body}\nf")

    def stroke(self, color: str, width_px: float, cmds: list[tuple], cap="butt", join="miter"):
        c, m, y, k = cmyk_of(color)
        caps = {"butt": 0, "round": 1, "square": 2}
        joins = {"miter": 0, "round": 1, "bevel": 2}
        body = emit_path(cmds, "{x:.3f} {y:.3f} {op}")
        w = width_px * SCALE
        self.chunks.append(
            f"{caps[cap]} J {joins[join]} j {w:.4f} w\n"
            f"{c:.4f} {m:.4f} {y:.4f} {k:.4f} K\n{body}\nS"
        )

def build_stream(paths: dict[str, str]) -> tuple[str, list[str]]:
    d = Draw()
    # Sheet is pure white. The frame's white rects are not painted.
    for key, color in TEXT_ORDER:
        if key == "35ceeda":
            continue  # disclaimer is painted after the rules and the QR plate
        if key not in paths:
            d.missing.append(key)
            continue
        # Corner ticks sit between the green lead-in and the second gray block.
        if key == "27130571":
            for path in CORNER_FILLS:
                d.fill("#C9C9C9", parse_path(path))
        d.fill(color, parse_path(paths[key]))

    # Every left dot already sits inside the old clip rectangle, so the
    # clip is not emitted. Illustrator's EPS reader rejects clip and rectclip.
    for item in dot_marks():
        if item[0] == "ellipse":
            _, cx, cy, rx, ry, fill = item
            d.fill(fill, ellipse_path(cx, cy, rx, ry))
        else:
            _, cx, cy, r, fill = item
            d.fill(fill, circle_path(cx, cy, r))
    for kind, cx, cy, r, fill in right_dots():
        d.fill(fill, circle_path(cx, cy, r))

    for path in CHIP_STROKES:
        d.stroke("#C9C9C9", 2, parse_path(path))
    d.fill("#E2E2E2", rounded_rect(805.234, 546.984, 248.195, 248.195, 20))
    d.fill("white", rounded_rect(812, 554, 236, 234, 8))
    d.stroke("#696969", 1.25, parse_path(ARROW), cap="round", join="round")
    # Crop ticks from the SVG. The 1e-7 matrix skew is below 0.001 pt and is dropped.
    # y is flipped by the first two transforms; the third swaps axes.
    d.stroke("#C9C9C9", 2, rect_path(637, 381, 28, 25))
    d.stroke("#C9C9C9", 1, rect_path(1062.5, 380.5, 29, 1))
    d.stroke("#C9C9C9", 1, rect_path(1090.5, 377.5, 1, 29))
    d.fill("#C9C9C9", rect_path(0, 405, 1291, 2))
    d.fill("#C9C9C9", rect_path(0, 1456, 1291, 2))
    for x, y, w, h in qr_modules():
        d.fill("black", rect_path(x, y, w, h))
    if "35ceeda" in paths:
        d.fill("#3A3A3A", parse_path(paths["35ceeda"]))
    else:
        d.missing.append("35ceeda")
    d.fill("#C9C9C9", rect_path(636, 0, 2, 1501))
    d.fill("#C9C9C9", rect_path(1119, 0, 2, 1501))
    return "\n".join(d.chunks) + "\n", d.missing


def pdf_stream_to_ps(stream: str) -> str:
    """Translate the PDF content stream into Level-2 PostScript.

    The page is built once as PDF operators. EPS must not keep those
    operators, and it must not be distilled through a flattener.
    """
    out = ["0 setlinecap", "0 setlinejoin", "1 setlinewidth"]
    for raw in stream.splitlines():
        line = raw.strip()
        if not line:
            continue
        if line == "q":
            out.append("gsave")
        elif line == "Q":
            out.append("grestore")
        elif line == "W":
            out.append("clip")
        elif line == "n":
            out.append("newpath")
        elif line == "f":
            out.append("fill")
        elif line == "S":
            out.append("stroke")
        elif line == "h":
            out.append("closepath")
        elif line.endswith(" m"):
            out.append(line[:-2] + " moveto")
        elif line.endswith(" l"):
            out.append(line[:-2] + " lineto")
        elif line.endswith(" c"):
            out.append(line[:-2] + " curveto")
        elif line.endswith(" k") or line.endswith(" K"):
            out.append(line[:-2] + " setcmykcolor")
        elif line.endswith(" w") and " J " in line:
            cap, _, join, _, width, _ = line.split()
            out.append(f"{cap} setlinecap {join} setlinejoin {width} setlinewidth")
        else:
            raise ValueError(f"untranslated PDF op: {line[:80]}")
    return "\n".join(out) + "\n"


def write_eps(stream: str, dest: Path) -> None:
    ps = pdf_stream_to_ps(stream)
    # The integer box must contain the HiRes box. ceil(581.102)=582.
    box_w = math.ceil(PAGE_W - 1e-9)
    box_h = math.ceil(PAGE_H - 1e-9)
    header = f"""%!PS-Adobe-3.0 EPSF-3.0
%%BoundingBox: 0 0 {box_w} {box_h}
%%HiResBoundingBox: 0 0 {PAGE_W:.3f} {PAGE_H:.3f}
%%LanguageLevel: 2
%%DocumentProcessColors: Cyan Magenta Yellow Black
%%Title: ALEX cover 165x240 mm, bleed 20 mm, vector CMYK
%%DocumentData: Clean7Bit
%%EndComments
%%BeginProlog
%%EndProlog
%%BeginSetup
%%EndSetup
gsave
"""
    # No rectclip, clip or showpage: Illustrator (including the web app's
    # importer, and the desktop EPS parser) stops on those operators.
    dest.write_text(header + ps + "grestore\n%%EOF\n")


def write_pdf(stream: str, dest: Path) -> None:
    stream_bytes = stream.encode("latin1")
    objects = []

    def add(body: bytes) -> int:
        objects.append(body)
        return len(objects)

    content = b"<< /Length %d >>\nstream\n" % len(stream_bytes) + stream_bytes + b"endstream"
    content_id = add(content)
    trim = f"[ {BLEED:.3f} {BLEED:.3f} {BLEED + 165 * PT:.3f} {BLEED + 240 * PT:.3f} ]"
    media = f"[ 0 0 {PAGE_W:.3f} {PAGE_H:.3f} ]"
    page = (
        f"<< /Type /Page /Parent 2 0 R /MediaBox {media} "
        f"/TrimBox {trim} /BleedBox {media} /ArtBox {trim} "
        f"/Contents {content_id} 0 R /Resources << >> >>"
    ).encode()
    page_id = add(page)
    pages = f"<< /Type /Pages /Count 1 /Kids [{page_id} 0 R] >>".encode()
    pages_id = add(pages)
    # Fix parent id: we don't know pages_id before. Rebuild page with real parent.
    # Simpler: reserve order catalog, pages, page, content and patch.
    objects.clear()
    # 1 catalog, 2 pages, 3 page, 4 content
    objects = [b"", b"", b"", b""]
    objects[3] = content
    objects[2] = (
        f"<< /Type /Page /Parent 2 0 R /MediaBox {media} "
        f"/TrimBox {trim} /BleedBox {media} /ArtBox {trim} "
        f"/Contents 4 0 R /Resources << >> >>"
    ).encode()
    objects[1] = b"<< /Type /Pages /Count 1 /Kids [3 0 R] >>"
    objects[0] = b"<< /Type /Catalog /Pages 2 0 R >>"
    out = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
    offsets = [0]
    for i, body in enumerate(objects, 1):
        offsets.append(len(out))
        out += f"{i} 0 obj\n".encode() + body + b"\nendobj\n"
    xref = len(out)
    out += f"xref\n0 {len(objects)+1}\n".encode()
    out += b"0000000000 65535 f \n"
    for off in offsets[1:]:
        out += f"{off:010d} 00000 n \n".encode()
    out += (
        f"trailer\n<< /Size {len(objects)+1} /Root 1 0 R >>\n"
        f"startxref\n{xref}\n%%EOF\n"
    ).encode()
    dest.write_bytes(out)


def main() -> None:
    paths = load_paths()
    stream, missing = build_stream(paths)
    eps = OUT / "alex-cover-165x240-bleed20-cmyk-vector.eps"
    pdf = OUT / "alex-cover-165x240-bleed20-cmyk.pdf"
    write_eps(stream, eps)
    write_pdf(stream, pdf)
    print(f"eps {eps.stat().st_size} pdf {pdf.stat().st_size}")
    print("paths", sorted(paths))
    print("missing", missing)
    # Reject anything that would force a flattener.
    blob = eps.read_text()
    for banned in ("image", "setrgbcolor", "setgray", " imagemask"):
        if banned in blob:
            raise SystemExit(f"banned operator {banned}")


if __name__ == "__main__":
    main()
