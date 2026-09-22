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
import json
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

    report = verify(cmyk_image, sheet, payload_digest)
    report["brand_greens"] = locked
    OUT_REPORT.write_text(json.dumps(report, indent=2, ensure_ascii=False))
    print(json.dumps(report, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    main()
