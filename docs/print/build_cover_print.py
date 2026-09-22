#!/usr/bin/env python3
"""Build the Geotar print package for the ALEX book cover.

Source of truth: docs/print/src/cover-figma-source.pdf (Figma frame
"165x240 - для типографии (205x280, без меток)", 1291x1763 pt where 1 pt = 1 px).

Applies the prepress corrections requested by the publisher (18.09.2026):
  1. design/text kept inside the 145x220 mm type area (10 mm off trim)
  2. frame rules bleed the full 20 mm instead of stopping at the trim
  3. no grey tint on the bleed - the sheet is pure white
  4. logo and QR print as 100% K instead of four-colour black
  5. QR redrawn from its decoded payload, no JPEG artefacts
  6. CMYK output, neutrals separated K-only so total ink stays <= 100%
"""

from __future__ import annotations

import hashlib
import io
import json
import re
import subprocess
from pathlib import Path

import numpy as np
import pymupdf
import qrcode
from PIL import Image, ImageCms

Image.MAX_IMAGE_PIXELS = None

HERE = Path(__file__).resolve().parent
SRC_PDF = HERE / "src" / "cover-figma-source.pdf"
CMYK_ICC = HERE / "FOGRA39L.icc"

OUT_TIF = HERE / "alex-cover-165x240-bleed20-cmyk-300dpi.tif"
OUT_PDF = HERE / "alex-cover-165x240-bleed20-300dpi.pdf"
OUT_EPS = HERE / "alex-cover-165x240-bleed20-cmyk-vector.eps"
OUT_EPS_PDF = HERE / "src" / "_vector-corrected.pdf"
OUT_PREVIEW = HERE / "alex-cover-165x240-preview.jpg"
OUT_REPORT = HERE / "print-check-report.json"

PAGE_W_MM, PAGE_H_MM = 205.0, 280.0
BLEED_MM = 20.0
TRIM_W_MM, TRIM_H_MM = 165.0, 240.0
SAFE_MM = 10.0
DPI = 300
SUPERSAMPLE = 2

# Uniform reduction about the trim centre that pulls every design element
# inside the 145x220 type area. Worst offender is the bottom disclaimer
# (24.8..180.6 mm), which is wider than the type area and cannot be fixed by
# shifting alone.
CONTENT_SCALE = 0.92

# The source draws a black rectangle exactly on the trim and paints it white on
# top, so cropping flush with the trim drags a hairline of that rectangle into
# the result. Nothing but the frame rules lives within 4 mm of the trim, so the
# crop is taken slightly inside and the rules are redrawn anyway.
CROP_INSET_MM = 1.0

# Geometry measured off the source PDF, in mm on the 205x280 sheet.
RULES = {
    "horizontal": [
        {"y0": 64.325, "y1": 64.643},
        {"y0": 231.215, "y1": 231.533},
    ],
    "vertical": [
        {"x0": 99.721, "x1": 100.039},
        {"x0": 177.688, "x1": 178.005},
    ],
}
QR_RECT_MM = {"x0": 129.596, "y0": 88.618, "x1": 165.500, "y1": 124.522}
QR_ECC = qrcode.constants.ERROR_CORRECT_M

BRAND_GREENS = {
    "sage": (151, 174, 150),
    "mint": (210, 232, 209),
}
INTENT = 1  # relative colorimetric


def px(mm: float, dpi: int) -> float:
    return mm / 25.4 * dpi


def scaled(value_mm: float, centre_mm: float) -> float:
    return centre_mm + (value_mm - centre_mm) * CONTENT_SCALE


def render_source(dpi: int) -> Image.Image:
    doc = pymupdf.open(SRC_PDF)
    page = doc[0]
    # The Figma export is 1 pt per px; ask for the zoom that lands on `dpi`
    # across the intended 205 mm sheet.
    zoom = px(PAGE_W_MM, dpi) / page.rect.width
    pix = page.get_pixmap(matrix=pymupdf.Matrix(zoom, zoom), alpha=False)
    return Image.frombytes("RGB", (pix.width, pix.height), pix.samples)


def place_content(src: Image.Image, dpi: int) -> Image.Image:
    """Crop the trim area, shrink it and centre it on a pure white sheet.

    Cropping at the trim also drops the #FCFCFC rectangle the publisher flagged:
    the tint only ever covered the bleed, inside the trim the artwork is white.
    """
    sheet_w, sheet_h = round(px(PAGE_W_MM, dpi)), round(px(PAGE_H_MM, dpi))
    box = (
        round(px(BLEED_MM + CROP_INSET_MM, dpi)),
        round(px(BLEED_MM + CROP_INSET_MM, dpi)),
        round(px(BLEED_MM + TRIM_W_MM - CROP_INSET_MM, dpi)),
        round(px(BLEED_MM + TRIM_H_MM - CROP_INSET_MM, dpi)),
    )
    trim = src.crop(box)
    new_w = round(trim.width * CONTENT_SCALE)
    new_h = round(trim.height * CONTENT_SCALE)
    trim = trim.resize((new_w, new_h), Image.LANCZOS)

    sheet = Image.new("RGB", (sheet_w, sheet_h), (255, 255, 255))
    sheet.paste(trim, ((sheet_w - new_w) // 2, (sheet_h - new_h) // 2))
    return sheet


def bleed_the_rules(sheet: Image.Image, dpi: int) -> Image.Image:
    """Run the frame rules off all four edges.

    In the source they are clipped to the trim, so a shifted sheet would show a
    white gap. The rules already exist inside the artwork; we only copy their
    pixel profile outwards, which keeps colour and anti-aliasing identical.
    """
    arr = np.array(sheet)
    h, w, _ = arr.shape
    cx, cy = PAGE_W_MM / 2, PAGE_H_MM / 2
    inner_lo = round(px(scaled(BLEED_MM + CROP_INSET_MM, cx), dpi))
    inner_hi = round(px(scaled(BLEED_MM + TRIM_W_MM - CROP_INSET_MM, cx), dpi))

    for rule in RULES["horizontal"]:
        y0 = int(np.floor(px(scaled(rule["y0"], cy), dpi))) - 1
        y1 = int(np.ceil(px(scaled(rule["y1"], cy), dpi))) + 1
        y0, y1 = max(0, y0), min(h, y1)
        sample = arr[y0:y1, inner_lo + 40 : inner_lo + 41]
        arr[y0:y1, :inner_lo] = sample
        arr[y0:y1, inner_hi:] = sample

    top_lo = round(px(scaled(BLEED_MM + CROP_INSET_MM, cy), dpi))
    for rule in RULES["vertical"]:
        x0 = int(np.floor(px(scaled(rule["x0"], cx), dpi))) - 1
        x1 = int(np.ceil(px(scaled(rule["x1"], cx), dpi))) + 1
        x0, x1 = max(0, x0), min(w, x1)
        sample = arr[top_lo + 40 : top_lo + 41, x0:x1]
        arr[:top_lo, x0:x1] = sample

    return Image.fromarray(arr)


def redraw_qr(sheet: Image.Image, dpi: int) -> tuple[Image.Image, str]:
    """Repaint the QR from its payload so it is crisp and single-ink.

    Returns the sheet and a digest of the encoded link.
    """
    doc = pymupdf.open(SRC_PDF)
    import cv2

    source_qr = None
    for xref, *_ in doc[0].get_images(full=True):
        info = doc.extract_image(xref)
        if info["width"] >= 400:
            source_qr = cv2.imdecode(
                np.frombuffer(info["image"], np.uint8), cv2.IMREAD_GRAYSCALE
            )
            break
    if source_qr is None:
        raise RuntimeError("QR image not found in the source PDF")

    payload, _, _ = cv2.QRCodeDetector().detectAndDecode(source_qr)
    if not payload:
        raise RuntimeError("could not decode the original QR")

    code = qrcode.QRCode(error_correction=QR_ECC, border=0, box_size=1)
    code.add_data(payload)
    code.make(fit=True)
    matrix = np.array(code.get_matrix(), dtype=bool)

    # Guard against silently shipping a different code than the printed one.
    crop = source_qr < 128
    ys, xs = np.where(crop)
    tight = crop[ys.min() : ys.max() + 1, xs.min() : xs.max() + 1].astype(np.float32)
    n = matrix.shape[0]
    resampled = cv2.resize(tight, (n, n), interpolation=cv2.INTER_AREA) > 0.5
    if not (resampled == matrix).all():
        raise RuntimeError("regenerated QR does not match the original modules")

    cx, cy = PAGE_W_MM / 2, PAGE_H_MM / 2
    x0 = px(scaled(QR_RECT_MM["x0"], cx), dpi)
    x1 = px(scaled(QR_RECT_MM["x1"], cx), dpi)
    y0 = px(scaled(QR_RECT_MM["y0"], cy), dpi)
    y1 = px(scaled(QR_RECT_MM["y1"], cy), dpi)

    arr = np.array(sheet)
    step_x = (x1 - x0) / n
    step_y = (y1 - y0) / n
    for row in range(n):
        ry0, ry1 = round(y0 + row * step_y), round(y0 + (row + 1) * step_y)
        for col in range(n):
            rx0, rx1 = round(x0 + col * step_x), round(x0 + (col + 1) * step_x)
            arr[ry0:ry1, rx0:rx1] = 0 if matrix[row, col] else 255
    return Image.fromarray(arr), hashlib.sha256(payload.encode()).hexdigest()


def k_from_luma(luma: np.ndarray) -> np.ndarray:
    """Neutral grey -> K ink, with the artwork's near-black forced to solid K.

    The logo is drawn #1F1F1F, so a plain 255-L separation would print it at
    88% K. The publisher asked for solid black, so everything at or below
    SOLID_BLACK_LUMA becomes K100 and the range up to KEEP_TONES_ABOVE is
    ramped into it; lighter tints keep their original density.
    """
    solid, hinge = 31.0, 64.0
    k = 255.0 - luma
    ramp = 255.0 - (luma - solid) * (255.0 - (255.0 - hinge)) / (hinge - solid)
    k = np.where(luma <= hinge, ramp, k)
    return np.clip(np.rint(k), 0, 255).astype(np.int16)


def match_green(target: tuple[int, int, int], srgb, cmyk) -> tuple[int, ...]:
    """Grid-search the CMYK mix whose soft proof matches the brand green."""
    coarse = (
        np.arange(0, 161, 5),
        np.arange(0, 81, 5),
        np.arange(0, 161, 5),
        np.arange(0, 61, 5),
    )

    def search(ranges):
        grid = np.stack(np.meshgrid(*ranges, indexing="ij"), axis=-1)
        flat = grid.reshape(-1, 4).astype(np.uint8)
        side = int(np.ceil(np.sqrt(len(flat))))
        canvas = np.zeros((side * side, 4), dtype=np.uint8)
        canvas[: len(flat)] = flat
        proof = np.array(
            ImageCms.profileToProfile(
                Image.fromarray(canvas.reshape(side, side, 4), mode="CMYK"),
                cmyk,
                srgb,
                outputMode="RGB",
                renderingIntent=INTENT,
            )
        ).reshape(-1, 3)[: len(flat)]
        d = np.linalg.norm(proof.astype(np.int16) - np.array(target, np.int16), axis=1)
        return flat[int(d.argmin())]

    best = search(coarse)
    fine = tuple(
        np.arange(max(0, int(v) - 6), min(256, int(v) + 7)) for v in best
    )
    return tuple(int(v) for v in search(fine))


def to_cmyk(sheet: Image.Image) -> tuple[Image.Image, dict]:
    """Separate neutrals as K-only and keep the brand greens on profile.

    Geotar caps total ink at 330%; a straight ICC conversion turns the logo and
    the QR into ~300-340% four-colour black, which is what they rejected.
    """
    srgb = ImageCms.createProfile("sRGB")
    cmyk = ImageCms.getOpenProfile(str(CMYK_ICC))

    rgb = np.array(sheet).astype(np.int16)
    icc = np.array(
        ImageCms.profileToProfile(
            sheet, srgb, cmyk, outputMode="CMYK", renderingIntent=INTENT
        )
    )

    spread = rgb.max(axis=2) - rgb.min(axis=2)
    neutral = spread <= 6

    out = icc.copy()
    out[neutral] = 0
    out[..., 3][neutral] = np.clip(k_from_luma(rgb.mean(axis=2))[neutral], 0, 255)

    locked = {}
    for name, target in BRAND_GREENS.items():
        mix = match_green(target, srgb, cmyk)
        distance = np.linalg.norm(rgb - np.array(target, np.int16), axis=2)
        mask = (~neutral) & (distance <= 16)
        out[mask] = mix
        locked[name] = {"rgb": target, "cmyk": mix, "pixels": int(mask.sum())}

    return Image.fromarray(out.astype(np.uint8), mode="CMYK"), locked


def verify(cmyk_image: Image.Image, rgb_sheet: Image.Image, payload_digest: str) -> dict:
    arr = np.array(cmyk_image).astype(np.int16)
    h, w, _ = arr.shape
    s = w / PAGE_W_MM

    def band(x0, y0, x1, y1):
        return arr[round(y0 * s) : round(y1 * s), round(x0 * s) : round(x1 * s)]

    ink = arr.sum(axis=2) / 2.55
    rgb = np.array(rgb_sheet).astype(np.int16)
    dark = rgb.max(axis=2) < 60
    darkest = arr[dark] if dark.any() else np.zeros((1, 4), np.int16)

    safe_x0, safe_y0 = BLEED_MM + SAFE_MM, BLEED_MM + SAFE_MM
    safe_x1, safe_y1 = BLEED_MM + TRIM_W_MM - SAFE_MM, BLEED_MM + TRIM_H_MM - SAFE_MM

    # The frame rules are meant to bleed, so exclude their rows/columns when
    # checking that nothing else sits in the outer 10 mm of the trim.
    cx, cy = PAGE_W_MM / 2, PAGE_H_MM / 2
    pad = round(px(0.6, DPI))
    ignore_cols = set()
    for rule in RULES["vertical"]:
        lo = int(px(scaled(rule["x0"], cx), DPI)) - pad
        hi = int(px(scaled(rule["x1"], cx), DPI)) + pad
        ignore_cols.update(range(max(0, lo), min(w, hi)))
    ignore_rows = set()
    for rule in RULES["horizontal"]:
        lo = int(px(scaled(rule["y0"], cy), DPI)) - pad
        hi = int(px(scaled(rule["y1"], cy), DPI)) + pad
        ignore_rows.update(range(max(0, lo), min(h, hi)))

    inked = arr.sum(axis=2) > 0
    inked[:, sorted(ignore_cols)] = False
    inked[sorted(ignore_rows), :] = False

    def strip(x0, y0, x1, y1):
        sub = inked[round(y0 * s) : round(y1 * s), round(x0 * s) : round(x1 * s)]
        return int(sub.sum())

    trim_x1, trim_y1 = BLEED_MM + TRIM_W_MM, BLEED_MM + TRIM_H_MM
    solid = arr[rgb.max(axis=2) < 16]
    solid_black = (
        [round(float(v) / 2.55, 1) for v in solid.max(axis=0)]
        if len(solid)
        else [0, 0, 0, 0]
    )
    return {
        "solid_black_cmyk_percent": solid_black,
        "bleed_tint_px": {
            "top": strip(0, 0, PAGE_W_MM, BLEED_MM),
            "bottom": strip(0, trim_y1, PAGE_W_MM, PAGE_H_MM),
            "left": strip(0, 0, BLEED_MM, PAGE_H_MM),
            "right": strip(trim_x1, 0, PAGE_W_MM, PAGE_H_MM),
        },
        "sheet_mm": [PAGE_W_MM, PAGE_H_MM],
        "trim_mm": [TRIM_W_MM, TRIM_H_MM],
        "bleed_mm": BLEED_MM,
        "dpi": DPI,
        "pixels": [w, h],
        "content_scale": CONTENT_SCALE,
        # The payload is the public quiz link; keep a digest so a changed QR is
        # obvious in review without printing the URL into the repository.
        "qr_payload_sha256": payload_digest,
        "type_area_intrusions_px": {
            "top": strip(BLEED_MM, BLEED_MM, trim_x1, safe_y0),
            "bottom": strip(BLEED_MM, safe_y1, trim_x1, trim_y1),
            "left": strip(BLEED_MM, BLEED_MM, safe_x0, trim_y1),
            "right": strip(safe_x1, BLEED_MM, trim_x1, trim_y1),
        },
        "max_total_ink_percent": round(float(ink.max()), 1),
        "darkest_ink_cmyk_percent": [
            round(float(v) / 2.55, 1) for v in darkest.mean(axis=0)
        ],
        "rules_reach_sheet_edge": {
            "left_edge": bool(arr[:, 0].sum() > 0),
            "right_edge": bool(arr[:, -1].sum() > 0),
            "top_edge": bool(arr[0, :].sum() > 0),
        },
    }


MM_PER_SRC_PT = PAGE_W_MM / 1291.0
FLAT_SRC = HERE / "src" / "_opaque-source.pdf"

# pdftocairo rounds the EPS BoundingBox to whole points and clips to it, so the
# EPS sheet is laid out on exact points. The trim stays 165x240 dead centre;
# only the bleed differs from 20 mm by a hair (19.98 / 20.03).
EPS_SHEET_W_PT, EPS_SHEET_H_PT = 581, 794
EPS_SHEET_W_MM = EPS_SHEET_W_PT / 72 * 25.4
EPS_SHEET_H_MM = EPS_SHEET_H_PT / 72 * 25.4


def flatten_transparency() -> dict[str, int]:
    """Resolve the artwork's transparency so EPS can stay vector.

    Ghostscript rasterises a whole page rather than emit transparent EPS, which
    is why the first EPS we sent arrived as one flat bitmap. Everything here
    sits on white, so a constant-alpha fill is exactly equal to an opaque fill
    of the composited colour, and the two group masks are plain rectangles the
    size of the trim - the clip we apply ourselves already does that job.
    """
    doc = pymupdf.open(SRC_PDF)
    stats = {"alpha_fills": 0, "masks_dropped": 0, "images_baked": 0}

    # Image alpha: composite onto white and drop the soft mask.
    for xref in range(1, doc.xref_length()):
        try:
            obj = doc.xref_object(xref)
        except Exception:
            continue
        if "/Subtype /Image" not in obj and "/Subtype/Image" not in obj:
            continue
        smask = doc.xref_get_key(xref, "SMask")
        if not smask or smask[0] == "null":
            continue
        base = doc.extract_image(xref)
        mask_xref = int(smask[1].split()[0].lstrip("["))
        alpha = doc.extract_image(mask_xref)
        rgb = Image.open(io.BytesIO(base["image"])).convert("RGB")
        a = Image.open(io.BytesIO(alpha["image"])).convert("L").resize(rgb.size)
        flat = Image.new("RGB", rgb.size, (255, 255, 255))
        flat.paste(rgb, mask=a)
        buf = io.BytesIO()
        flat.save(buf, format="PNG")
        doc.update_stream(xref, buf.getvalue(), new=True)
        doc.xref_set_key(xref, "SMask", "null")
        doc.xref_set_key(xref, "Filter", "/FlateDecode")
        doc.xref_set_key(xref, "ColorSpace", "/DeviceRGB")
        doc.xref_set_key(xref, "BitsPerComponent", "8")
        # PNG carries its own filtering, so hand Ghostscript raw samples.
        doc.update_stream(xref, np.array(flat).tobytes(), compress=True)
        stats["images_baked"] += 1

    gs_token = re.compile(rb"/([A-Za-z0-9]+)\s+gs\b")
    colour = re.compile(rb"((?:[-\d.]+\s+){3})scn\b")
    entry = re.compile(r"/([A-Za-z0-9]+)\s*<<(.*?)>>", re.S)

    def ext_g_states(source: str) -> tuple[dict[bytes, float], set[bytes]]:
        """Read `name -> fill alpha` and the names that carry a soft mask."""
        if "/ExtGState" not in source:
            return {}, set()
        tail = source.split("/ExtGState", 1)[1]
        alphas: dict[bytes, float] = {}
        masked: set[bytes] = set()
        for name, body in entry.findall(tail):
            if "/SMask" in body and "/None" not in body:
                masked.add(name.encode())
            found = re.search(r"/ca\s+([\d.]+)", body)
            if found:
                alphas[name.encode()] = float(found.group(1))
        return alphas, masked

    def rewrite(stream_xref: int, alphas: dict[bytes, float], masked: set[bytes]):
        data = doc.xref_stream(stream_xref)
        out, cursor = bytearray(), 0
        for token in gs_token.finditer(data):
            name = token.group(1)
            if name in masked:
                # The mask only clips to the trim; our own clip already does it.
                out += data[cursor : token.start()]
                cursor = token.end()
                stats["masks_dropped"] += 1
                continue
            out += data[cursor : token.end()]
            cursor = token.end()
            alpha = alphas.get(name, 1.0)
            if alpha >= 1.0:
                continue
            nxt = colour.search(data, cursor)
            if not nxt:
                continue
            rgb = [float(v) for v in nxt.group(1).split()]
            mixed = [1.0 - alpha * (1.0 - v) for v in rgb]
            out += data[cursor : nxt.start()]
            out += f"{mixed[0]:.6f} {mixed[1]:.6f} {mixed[2]:.6f} scn".encode()
            cursor = nxt.end()
            stats["alpha_fills"] += 1
        out += data[cursor:]
        doc.update_stream(stream_xref, bytes(out))

    for xref in range(1, doc.xref_length()):
        try:
            obj = doc.xref_object(xref)
        except Exception:
            continue
        if not doc.xref_is_stream(xref) or "/ExtGState" not in obj:
            continue
        alphas, masked = ext_g_states(obj)
        if alphas or masked:
            rewrite(xref, alphas, masked)

    # The page keeps its resources in a separate dictionary.
    page_xref = doc[0].xref
    resources = doc.xref_get_key(page_xref, "Resources")
    if resources and resources[0] == "xref":
        res_xref = int(resources[1].split()[0])
        alphas, masked = ext_g_states(doc.xref_object(res_xref))
        contents = doc.xref_get_key(page_xref, "Contents")
        if (alphas or masked) and contents and contents[0] == "xref":
            rewrite(int(contents[1].split()[0]), alphas, masked)

    # Editing stream dictionaries by hand detaches them from their data, so the
    # group flags go through the key API and stay as harmless nulls.
    for xref in range(1, doc.xref_length()):
        try:
            if "/Group" in doc.xref_object(xref):
                doc.xref_set_key(xref, "Group", "null")
        except Exception:
            continue

    doc.save(FLAT_SRC, garbage=3, deflate=True)
    doc.close()
    return stats


def mm_to_pt(value: float) -> float:
    return value / 25.4 * 72.0


def qr_matrix() -> tuple[np.ndarray, str]:
    """Rebuild the QR from the artwork and prove it still encodes the same link."""
    import cv2

    doc = pymupdf.open(SRC_PDF)
    bitmap = None
    for xref, *_ in doc[0].get_images(full=True):
        info = doc.extract_image(xref)
        if info["width"] >= 400:
            bitmap = cv2.imdecode(
                np.frombuffer(info["image"], np.uint8), cv2.IMREAD_GRAYSCALE
            )
            break
    if bitmap is None:
        raise RuntimeError("QR image not found in the source PDF")

    payload, _, _ = cv2.QRCodeDetector().detectAndDecode(bitmap)
    if not payload:
        raise RuntimeError("could not decode the original QR")

    code = qrcode.QRCode(error_correction=QR_ECC, border=0, box_size=1)
    code.add_data(payload)
    code.make(fit=True)
    matrix = np.array(code.get_matrix(), dtype=bool)

    mask = bitmap < 128
    ys, xs = np.where(mask)
    tight = mask[ys.min() : ys.max() + 1, xs.min() : xs.max() + 1].astype(np.float32)
    n = matrix.shape[0]
    resampled = cv2.resize(tight, (n, n), interpolation=cv2.INTER_AREA) > 0.5
    if not (resampled == matrix).all():
        raise RuntimeError("regenerated QR does not match the original modules")
    return matrix, hashlib.sha256(payload.encode()).hexdigest()


def recolour_streams(doc: pymupdf.Document, greens: dict) -> dict[str, int]:
    """Neutrals to DeviceGray, brand greens to DeviceCMYK.

    Everything downstream then separates the way Geotar asked for: grey and
    black ride on K alone, so solid black is 0/0/0/100 instead of a
    four-colour build, and Ghostscript has nothing left to guess at.
    """
    mixes = {name: info["cmyk"] for name, info in greens.items()}
    pattern = re.compile(rb"((?:[-\d.]+\s+){3})(scn|rg)\b")
    counts = {"gray": 0, "cmyk": 0}

    def replace(match: re.Match) -> bytes:
        rgb = [float(v) for v in match.group(1).split()]
        if max(rgb) - min(rgb) <= 0.02:
            counts["gray"] += 1
            return f"{sum(rgb) / 3:.4f} g".encode()
        nearest, best = None, 1e9
        for name, target in BRAND_GREENS.items():
            d = sum((rgb[i] * 255 - target[i]) ** 2 for i in range(3))
            if d < best:
                nearest, best = name, d
        c, m, y, k = (v / 255 for v in mixes[nearest])
        counts["cmyk"] += 1
        return f"{c:.4f} {m:.4f} {y:.4f} {k:.4f} k".encode()

    for xref in range(1, doc.xref_length()):
        if not doc.xref_is_stream(xref):
            continue
        try:
            data = doc.xref_stream(xref)
        except Exception:
            continue
        patched, n = pattern.subn(replace, data)
        if n:
            doc.update_stream(xref, patched)
    return counts


def build_vector_eps(greens: dict) -> dict:
    """Vector EPS for the publisher, fonts flattened to curves.

    The previous EPS was a single flattened bitmap, which they could not check.
    """
    flat_stats = flatten_transparency()
    src = pymupdf.open(FLAT_SRC)
    out = pymupdf.open()
    page = out.new_page(width=EPS_SHEET_W_PT, height=EPS_SHEET_H_PT)

    # Source coordinates are relative to the trim centre, which sits at the
    # centre of either sheet, so the mapping carries over unchanged.
    cx, cy = EPS_SHEET_W_MM / 2, EPS_SHEET_H_MM / 2
    src_cx, src_cy = PAGE_W_MM / 2, PAGE_H_MM / 2
    lo_x, hi_x = BLEED_MM + CROP_INSET_MM, BLEED_MM + TRIM_W_MM - CROP_INSET_MM
    lo_y, hi_y = BLEED_MM + CROP_INSET_MM, BLEED_MM + TRIM_H_MM - CROP_INSET_MM

    def place_x(value_mm: float) -> float:
        return mm_to_pt(cx + (value_mm - src_cx) * CONTENT_SCALE)

    def place_y(value_mm: float) -> float:
        return mm_to_pt(cy + (value_mm - src_cy) * CONTENT_SCALE)

    # pdftocairo derives the EPS BoundingBox from the inked area, so the sheet
    # needs an explicit white ground or the empty foot of the page is cropped.
    page.draw_rect(
        pymupdf.Rect(0, 0, EPS_SHEET_W_PT, EPS_SHEET_H_PT),
        color=None,
        fill=(1, 1, 1),
    )
    page.show_pdf_page(
        pymupdf.Rect(place_x(lo_x), place_y(lo_y), place_x(hi_x), place_y(hi_y)),
        src,
        0,
        clip=pymupdf.Rect(
            lo_x / MM_PER_SRC_PT,
            lo_y / MM_PER_SRC_PT,
            hi_x / MM_PER_SRC_PT,
            hi_y / MM_PER_SRC_PT,
        ),
    )

    # Overshoot the sheet so the rules are unambiguously trimmed off, not
    # stopped a fraction short of the edge.
    rule_grey = (0.7865, 0.7865, 0.7865)
    for rule in RULES["horizontal"]:
        page.draw_rect(
            pymupdf.Rect(
                0,
                place_y(rule["y0"]),
                EPS_SHEET_W_PT,
                place_y(rule["y1"]),
            ),
            color=None,
            fill=rule_grey,
        )
    for rule in RULES["vertical"]:
        page.draw_rect(
            pymupdf.Rect(
                place_x(rule["x0"]),
                0,
                place_x(rule["x1"]),
                place_y(238.346),
            ),
            color=None,
            fill=rule_grey,
        )

    matrix, digest = qr_matrix()
    n = matrix.shape[0]
    x0, x1 = place_x(QR_RECT_MM["x0"]), place_x(QR_RECT_MM["x1"])
    y0, y1 = place_y(QR_RECT_MM["y0"]), place_y(QR_RECT_MM["y1"])
    page.draw_rect(pymupdf.Rect(x0, y0, x1, y1), color=None, fill=(1, 1, 1))
    step_x, step_y = (x1 - x0) / n, (y1 - y0) / n
    for row in range(n):
        for col in range(n):
            if not matrix[row, col]:
                continue
            page.draw_rect(
                pymupdf.Rect(
                    x0 + col * step_x,
                    y0 + row * step_y,
                    x0 + (col + 1) * step_x,
                    y0 + (row + 1) * step_y,
                ),
                color=None,
                fill=(0, 0, 0),
            )

    out.save(OUT_EPS_PDF, garbage=3)
    out.close()

    patched = pymupdf.open(OUT_EPS_PDF)
    counts = recolour_streams(patched, greens)
    trim = pymupdf.Rect(
        (EPS_SHEET_W_PT - mm_to_pt(TRIM_W_MM)) / 2,
        (EPS_SHEET_H_PT - mm_to_pt(TRIM_H_MM)) / 2,
        (EPS_SHEET_W_PT + mm_to_pt(TRIM_W_MM)) / 2,
        (EPS_SHEET_H_PT + mm_to_pt(TRIM_H_MM)) / 2,
    )
    box = f"[{trim.x0:.4f} {trim.y0:.4f} {trim.x1:.4f} {trim.y1:.4f}]"
    patched.xref_set_key(patched[0].xref, "TrimBox", box)
    patched.xref_set_key(
        patched[0].xref, "BleedBox", f"[0 0 {EPS_SHEET_W_PT} {EPS_SHEET_H_PT}]"
    )
    patched.save(OUT_EPS_PDF, incremental=True, encryption=pymupdf.PDF_ENCRYPT_KEEP)
    patched.close()

    # Ghostscript's eps2write rasterises this artwork wholesale, which is how the
    # first EPS ended up as a single bitmap. pdftocairo keeps the vectors.
    subprocess.run(
        [
            "pdftocairo",
            "-eps",
            "-level3",
            str(OUT_EPS_PDF),
            str(OUT_EPS),
        ],
        check=True,
        capture_output=True,
    )

    # The sheet is whole points, so cairo's rounded box must already be exact -
    # anything else would mean the artwork shifted inside the box.
    found = re.search(rb"%%BoundingBox:([^\r\n]*)", OUT_EPS.read_bytes())
    box_pt = [int(float(v)) for v in found.group(1).split()]
    if box_pt != [0, 0, EPS_SHEET_W_PT, EPS_SHEET_H_PT]:
        raise RuntimeError(f"unexpected EPS BoundingBox: {box_pt}")

    for scratch in (FLAT_SRC, OUT_EPS_PDF):
        scratch.unlink(missing_ok=True)

    return {
        "sheet_mm": [round(EPS_SHEET_W_MM, 2), round(EPS_SHEET_H_MM, 2)],
        "trim_mm": [TRIM_W_MM, TRIM_H_MM],
        "bleed_mm": [
            round((EPS_SHEET_W_MM - TRIM_W_MM) / 2, 2),
            round((EPS_SHEET_H_MM - TRIM_H_MM) / 2, 2),
        ],
        "recoloured_ops": counts,
        "flattened": flat_stats,
        "qr_payload_sha256": digest,
        **verify_eps(),
    }


def verify_eps() -> dict:
    """Run the publisher's checklist against the EPS itself, not its source."""
    import cv2

    raster = HERE / "_tmp_eps_check.tif"
    subprocess.run(
        [
            "gs",
            "-q",
            "-dBATCH",
            "-dNOPAUSE",
            "-dSAFER",
            "-dEPSCrop",
            "-sDEVICE=tiff32nc",
            f"-r{DPI}",
            f"-sOutputFile={raster}",
            str(OUT_EPS),
        ],
        check=True,
        capture_output=True,
    )
    arr = np.array(Image.open(raster)).astype(np.int16)
    raster.unlink()

    h, w, _ = arr.shape
    s = w / EPS_SHEET_W_MM
    ink = arr.sum(axis=2)
    bleed_x = (EPS_SHEET_W_MM - TRIM_W_MM) / 2
    bleed_y = (EPS_SHEET_H_MM - TRIM_H_MM) / 2
    cx, cy = EPS_SHEET_W_MM / 2, EPS_SHEET_H_MM / 2

    pad = round(px(0.7, DPI))
    mask = ink > 0
    for rule in RULES["vertical"]:
        c = px(cx + (rule["x0"] - PAGE_W_MM / 2) * CONTENT_SCALE, DPI)
        mask[:, max(0, int(c) - pad) : int(c) + pad] = False
    for rule in RULES["horizontal"]:
        c = px(cy + (rule["y0"] - PAGE_H_MM / 2) * CONTENT_SCALE, DPI)
        mask[max(0, int(c) - pad) : int(c) + pad, :] = False

    def count(x0, y0, x1, y1):
        return int(
            mask[round(y0 * s) : round(y1 * s), round(x0 * s) : round(x1 * s)].sum()
        )

    tx1, ty1 = bleed_x + TRIM_W_MM, bleed_y + TRIM_H_MM
    solid = arr[arr[..., 3] > 240]
    grey = (255 - np.clip(ink / 4, 0, 255)).astype(np.uint8)
    return {
        "eps_solid_black_cmy_percent": [
            round(float(v) / 2.55, 1) for v in solid[:, :3].max(axis=0)
        ],
        "eps_max_total_ink_percent": round(float(ink.max()) / 2.55, 1),
        "eps_bleed_tint_px": count(0, 0, EPS_SHEET_W_MM, bleed_y)
        + count(0, ty1, EPS_SHEET_W_MM, EPS_SHEET_H_MM)
        + count(0, 0, bleed_x, EPS_SHEET_H_MM)
        + count(tx1, 0, EPS_SHEET_W_MM, EPS_SHEET_H_MM),
        "eps_type_area_intrusions_px": {
            "top": count(bleed_x, bleed_y, tx1, bleed_y + SAFE_MM),
            "bottom": count(bleed_x, ty1 - SAFE_MM, tx1, ty1),
            "left": count(bleed_x, bleed_y, bleed_x + SAFE_MM, ty1),
            "right": count(tx1 - SAFE_MM, bleed_y, tx1, ty1),
        },
        "eps_rules_reach_sheet_edge": {
            "left": bool((ink[:, 0] > 0).any()),
            "right": bool((ink[:, -1] > 0).any()),
            "top": bool((ink[0, :] > 0).any()),
        },
        "eps_qr_scans": bool(cv2.QRCodeDetector().detectAndDecode(grey)[0]),
    }


def write_pdf(cmyk_image: Image.Image) -> None:
    """Sheet-sized PDF with TrimBox/BleedBox so the trim is unambiguous."""
    doc = pymupdf.open()
    w_pt, h_pt = PAGE_W_MM / 25.4 * 72, PAGE_H_MM / 25.4 * 72
    page = doc.new_page(width=w_pt, height=h_pt)
    preview = HERE / "_tmp_cmyk_page.jpg"
    cmyk_image.save(preview, quality=95)
    page.insert_image(pymupdf.Rect(0, 0, w_pt, h_pt), filename=str(preview))
    inset = BLEED_MM / 25.4 * 72
    trim = pymupdf.Rect(inset, inset, w_pt - inset, h_pt - inset)
    doc.save(OUT_PDF)
    doc.close()
    preview.unlink()

    # PyMuPDF has no TrimBox setter, so patch the page dictionary directly.
    doc = pymupdf.open(OUT_PDF)
    xref = doc[0].xref
    box = f"[{trim.x0:.4f} {trim.y0:.4f} {trim.x1:.4f} {trim.y1:.4f}]"
    doc.xref_set_key(xref, "TrimBox", box)
    doc.xref_set_key(xref, "ArtBox", box)
    doc.xref_set_key(xref, "BleedBox", f"[0 0 {w_pt:.4f} {h_pt:.4f}]")
    doc.save(OUT_PDF, incremental=True, encryption=pymupdf.PDF_ENCRYPT_KEEP)
    doc.close()


def main() -> None:
    hi_dpi = DPI * SUPERSAMPLE
    source = render_source(hi_dpi)
    sheet = place_content(source, hi_dpi)
    sheet = bleed_the_rules(sheet, hi_dpi)
    sheet = sheet.resize(
        (round(px(PAGE_W_MM, DPI)), round(px(PAGE_H_MM, DPI))), Image.LANCZOS
    )
    sheet, payload_digest = redraw_qr(sheet, DPI)

    cmyk_image, locked = to_cmyk(sheet)
    cmyk_image.save(OUT_TIF, compression="tiff_lzw", dpi=(DPI, DPI))

    srgb = ImageCms.createProfile("sRGB")
    cmyk = ImageCms.getOpenProfile(str(CMYK_ICC))
    ImageCms.profileToProfile(
        cmyk_image, cmyk, srgb, outputMode="RGB", renderingIntent=INTENT
    ).save(OUT_PREVIEW, quality=92)

    write_pdf(cmyk_image)
    eps_info = build_vector_eps(locked)

    report = verify(cmyk_image, sheet, payload_digest)
    report["brand_greens"] = locked
    report["vector_eps"] = eps_info
    OUT_REPORT.write_text(json.dumps(report, indent=2, ensure_ascii=False))
    print(json.dumps(report, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
