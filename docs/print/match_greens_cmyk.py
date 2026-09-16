#!/usr/bin/env python3
"""Convert cover RGB → FOGRA39 CMYK and lock brand greens to monitor appearance."""

from pathlib import Path

import numpy as np
from PIL import Image, ImageCms, ImageDraw

SRGB = "/usr/share/color/icc/ghostscript/srgb.icc"
CMYK_ICC = str(Path(__file__).resolve().parent / "FOGRA39L.icc")
RGB_PATH = Path("/workspace/docs/print/alex-book-cover-export-300dpi-rgb.png")
OUT_TIF = Path("/workspace/docs/print/alex-book-cover-geotar-300dpi-cmyk.tif")
PREVIEW = Path("/opt/cursor/artifacts/cover-print-matched-greens.jpg")

TARGETS = {
    "sage": (151, 174, 150),  # #97ae96
    "mint": (210, 232, 209),  # #d2e8d1
}

INTENT = 1  # relative colorimetric


def profiles():
    return ImageCms.getOpenProfile(SRGB), ImageCms.getOpenProfile(CMYK_ICC)


def rgb_to_cmyk(im, srgb, cmyk):
    return ImageCms.profileToProfile(im, srgb, cmyk, outputMode="CMYK", renderingIntent=INTENT)


def cmyk_to_rgb(im, srgb, cmyk):
    return ImageCms.profileToProfile(im, cmyk, srgb, outputMode="RGB", renderingIntent=INTENT)


def find_cmyk_batch(target, srgb, cmyk):
    """One-shot grid search: CMYK 0–255 whose soft-proof is closest to target RGB."""
    cs = np.arange(0, 161, 5, dtype=np.uint8)
    ms = np.arange(0, 81, 5, dtype=np.uint8)
    ys = np.arange(0, 161, 5, dtype=np.uint8)
    ks = np.arange(0, 61, 5, dtype=np.uint8)
    grid = np.stack(np.meshgrid(cs, ms, ys, ks, indexing="ij"), axis=-1)
    flat = grid.reshape(-1, 4)
    n = len(flat)
    side = int(np.ceil(np.sqrt(n)))
    canvas = np.zeros((side * side, 4), dtype=np.uint8)
    canvas[:n] = flat
    im = Image.fromarray(canvas.reshape(side, side, 4), mode="CMYK")
    proof = np.array(cmyk_to_rgb(im, srgb, cmyk)).reshape(-1, 3)[:n]
    d = np.sqrt(((proof.astype(np.int16) - np.array(target, dtype=np.int16)) ** 2).sum(axis=1))
    i = int(d.argmin())
    best = tuple(int(x) for x in flat[i])
    proof_rgb = tuple(int(x) for x in proof[i])

    # Fine refine ±6 around coarse winner
    c0, m0, y0, k0 = best
    cs = np.arange(max(0, c0 - 6), min(256, c0 + 7), dtype=np.uint8)
    ms = np.arange(max(0, m0 - 6), min(256, m0 + 7), dtype=np.uint8)
    ys = np.arange(max(0, y0 - 6), min(256, y0 + 7), dtype=np.uint8)
    ks = np.arange(max(0, k0 - 6), min(256, k0 + 7), dtype=np.uint8)
    grid = np.stack(np.meshgrid(cs, ms, ys, ks, indexing="ij"), axis=-1)
    flat = grid.reshape(-1, 4)
    n = len(flat)
    side = int(np.ceil(np.sqrt(n)))
    canvas = np.zeros((side * side, 4), dtype=np.uint8)
    canvas[:n] = flat
    im = Image.fromarray(canvas.reshape(side, side, 4), mode="CMYK")
    proof = np.array(cmyk_to_rgb(im, srgb, cmyk)).reshape(-1, 3)[:n]
    d = np.sqrt(((proof.astype(np.int16) - np.array(target, dtype=np.int16)) ** 2).sum(axis=1))
    i = int(d.argmin())
    return tuple(int(x) for x in flat[i]), tuple(int(x) for x in proof[i]), float(d[i])


def pair(a, b, la, lb):
    w, h = a.size
    canvas = Image.new("RGB", (w * 2 + 40, h + 50), (255, 255, 255))
    canvas.paste(a, (0, 40))
    canvas.paste(b, (w + 40, 40))
    d = ImageDraw.Draw(canvas)
    d.text((8, 8), la, fill=(0, 0, 0))
    d.text((w + 48, 8), lb, fill=(0, 0, 0))
    return canvas


def main():
    srgb, cmyk = profiles()
    matches = {}
    for name, rgb in TARGETS.items():
        cmyk_t, proof, delta = find_cmyk_batch(rgb, srgb, cmyk)
        matches[name] = {"target": rgb, "cmyk": cmyk_t, "proof": proof, "delta": delta}
        c, m, y, k = cmyk_t
        print(
            f"{name} RGB{rgb} -> CMYK {c}/{m}/{y}/{k} "
            f"({c/2.55:.1f} {m/2.55:.1f} {y/2.55:.1f} {k/2.55:.1f}%) "
            f"proof{proof} Δ={delta:.2f}"
        )

    print("Converting full image via FOGRA39…")
    src = Image.open(RGB_PATH).convert("RGB")
    src_arr = np.array(src)
    cmyk_im = rgb_to_cmyk(src, srgb, cmyk)
    cmyk_arr = np.array(cmyk_im)

    proof_before = np.array(cmyk_to_rgb(cmyk_im, srgb, cmyk))
    for name, rgb in TARGETS.items():
        mask = np.all(np.abs(src_arr.astype(np.int16) - np.array(rgb)) <= 4, axis=2)
        if mask.any():
            print(f"uncorrected {name} proof mean={proof_before[mask].mean(axis=0)}")

    r = src_arr[:, :, 0].astype(np.int16)
    g = src_arr[:, :, 1].astype(np.int16)
    b = src_arr[:, :, 2].astype(np.int16)
    greenish = (g > r + 8) & (g > b + 6)

    for name, info in matches.items():
        target = np.array(info["target"], dtype=np.int16)
        d = np.sqrt(((src_arr.astype(np.int16) - target) ** 2).sum(axis=2))
        mask = greenish & (d <= 16)
        cmyk_arr[mask] = info["cmyk"]
        print(f"locked {name}: {int(mask.sum())} px")

    out = Image.fromarray(cmyk_arr, mode="CMYK")
    out.save(OUT_TIF, compression="tiff_lzw", dpi=(300, 300))
    proof = cmyk_to_rgb(out, srgb, cmyk)
    proof.save(PREVIEW, quality=92)

    proof_arr = np.array(proof)
    for name, rgb in TARGETS.items():
        mask = np.all(np.abs(src_arr.astype(np.int16) - np.array(rgb)) <= 4, axis=2)
        if mask.any():
            print(f"corrected {name} proof mean={proof_arr[mask].mean(axis=0)} target={rgb}")

    pair(
        src.crop((180, 720, 980, 1180)),
        proof.crop((180, 720, 980, 1180)),
        "монитор RGB",
        "печать CMYK (просмотр)",
    ).save("/opt/cursor/artifacts/text-green-rgb-vs-matched.jpg", quality=92)
    pair(
        src.crop((1750, 880, 2450, 1980)),
        proof.crop((1750, 880, 2450, 1980)),
        "монитор RGB",
        "печать CMYK (просмотр)",
    ).save("/opt/cursor/artifacts/chip-green-rgb-vs-matched.jpg", quality=92)
    print("done", OUT_TIF, out.size)


if __name__ == "__main__":
    main()
