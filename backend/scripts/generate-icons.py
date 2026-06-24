#!/usr/bin/env python3
"""Generate PWA icons matching AppLogo.vue design."""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw

ACCENT = (19, 236, 109, 255)
BG = (11, 15, 20, 255)
SURFACE = (21, 28, 38, 255)


def draw_icon(size: int) -> Image.Image:
    img = Image.new('RGBA', (size, size), BG)
    draw = ImageDraw.Draw(img)

    pad = int(size * 0.08)
    draw.rounded_rectangle(
        (pad, pad, size - pad, size - pad),
        radius=int(size * 0.18),
        fill=SURFACE,
    )

    stroke = max(2, int(size * 0.052))
    cy = size * 0.5
    x1 = size * 0.29
    x2 = size * 0.71
    ay1 = cy - size * 0.08
    ay2 = cy + size * 0.08
    ah = max(3, int(size * 0.045))

    draw.line((x1, ay1, x2, ay1), fill=ACCENT, width=stroke)
    draw.polygon([(x2, ay1), (x2 - ah, ay1 - ah), (x2 - ah, ay1 + ah)], fill=ACCENT)

    draw.line((x2, ay2, x1, ay2), fill=ACCENT, width=stroke)
    draw.polygon([(x1, ay2), (x1 + ah, ay2 - ah), (x1 + ah, ay2 + ah)], fill=ACCENT)

    return img


def main() -> None:
    output_dir = Path(__file__).resolve().parents[1] / 'public' / 'icons'
    output_dir.mkdir(parents=True, exist_ok=True)
    public_dir = Path(__file__).resolve().parents[1] / 'public'

    for icon_size in (192, 512):
        draw_icon(icon_size).save(output_dir / f'icon-{icon_size}.png', optimize=True)

    draw_icon(32).save(public_dir / 'favicon.ico', format='ICO', sizes=[(32, 32)])
    draw_icon(512).save(public_dir / 'logo.png', optimize=True)


if __name__ == '__main__':
    main()
