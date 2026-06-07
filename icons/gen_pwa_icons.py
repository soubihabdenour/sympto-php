#!/usr/bin/env python3
"""Generate PWA icon set (PNG) from the MedAgent brand palette using Pillow.

Mirrors the visual language of identity/logo/app-icon.svg:
  - Teal gradient squircle background (#22d3ee -> #155e75)
  - White medical "pulse" line
  - White AI sparkle

Outputs everything into ./icons/ and ./screenshots/ relative to project root.
"""
from __future__ import annotations
import math, os, sys
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = "/Users/ady/PycharmProjects/sympto-php"
ICONS = os.path.join(ROOT, "icons")
SHOTS = os.path.join(ROOT, "screenshots")
os.makedirs(ICONS, exist_ok=True)
os.makedirs(SHOTS, exist_ok=True)

# Brand stops, top-left -> bottom-right
STOPS = [
    (0.00, (34, 211, 238)),   # #22d3ee
    (0.25, (8, 145, 178)),    # #0891b2
    (0.70, (14, 116, 144)),   # #0e7490
    (1.00, (21, 94, 117)),    # #155e75
]

def lerp(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))

def grad_at(t):
    t = max(0.0, min(1.0, t))
    for i in range(1, len(STOPS)):
        t0, c0 = STOPS[i - 1]
        t1, c1 = STOPS[i]
        if t <= t1:
            u = (t - t0) / (t1 - t0) if t1 > t0 else 0
            return lerp(c0, c1, u)
    return STOPS[-1][1]

def diagonal_gradient(size: int) -> Image.Image:
    """A diagonal (TL->BR) gradient image."""
    img = Image.new("RGB", (size, size))
    px = img.load()
    diag = max(1, size * 1.4142)
    for y in range(size):
        for x in range(size):
            t = (x + y) / (size * 2)
            px[x, y] = grad_at(t)
    return img

def squircle_mask(size: int, radius: int) -> Image.Image:
    """Rounded-rect mask (iOS-style squircle approximation)."""
    m = Image.new("L", (size, size), 0)
    d = ImageDraw.Draw(m)
    d.rounded_rectangle((0, 0, size - 1, size - 1), radius=radius, fill=255)
    return m

def draw_glyph(img: Image.Image, scale: float = 1.0, stroke_color=(255, 255, 255)) -> None:
    """Draw the white pulse + sparkle, scaled to image size and centered."""
    W, H = img.size
    # Glyph reference box (works for both 512 base and arbitrary)
    s = min(W, H)
    cx, cy = W / 2, H / 2

    d = ImageDraw.Draw(img, "RGBA")

    # --- Pulse line (heart-rate style) ---
    sw = max(2, int(s * 0.043 * scale))  # stroke width
    # Path waypoints scaled to s
    # Drawn left-to-right across ~70% of the canvas, vertically centered.
    span_w = s * 0.62 * scale
    base_y = cy + s * 0.02 * scale
    amp = s * 0.18 * scale
    x0 = cx - span_w * 0.55
    pts = [
        (x0,                     base_y),
        (x0 + span_w * 0.18,     base_y),
        (x0 + span_w * 0.26,     base_y - amp * 0.55),   # small dip up
        (x0 + span_w * 0.40,     base_y + amp * 0.90),   # big down
        (x0 + span_w * 0.52,     base_y - amp * 1.10),   # tall spike up
        (x0 + span_w * 0.62,     base_y + amp * 0.20),
        (x0 + span_w * 0.72,     base_y),
    ]
    # Draw as connected segments with round joints
    for i in range(len(pts) - 1):
        d.line([pts[i], pts[i + 1]], fill=stroke_color, width=sw)
    # Round joins: cap each waypoint with a filled circle = sw/2
    r = sw // 2
    for (px_, py_) in pts:
        d.ellipse((px_ - r, py_ - r, px_ + r, py_ + r), fill=stroke_color)

    # --- AI sparkle (4-point star + small X) on the right ---
    spx = cx + span_w * 0.42
    spy = base_y
    arm = s * 0.085 * scale
    sw2 = max(2, int(s * 0.038 * scale))
    # plus
    d.line([(spx - arm, spy), (spx + arm, spy)], fill=stroke_color, width=sw2)
    d.line([(spx, spy - arm), (spx, spy + arm)], fill=stroke_color, width=sw2)
    # diag (slightly thinner, semi-opaque)
    diag_color = (255, 255, 255, 192)
    arm2 = arm * 0.60
    sw3 = max(1, int(s * 0.022 * scale))
    d.line([(spx - arm2, spy - arm2), (spx + arm2, spy + arm2)], fill=diag_color, width=sw3)
    d.line([(spx - arm2, spy + arm2), (spx + arm2, spy - arm2)], fill=diag_color, width=sw3)

def make_squircle_icon(size: int, padding_frac: float = 0.0) -> Image.Image:
    """Produce a final squircle icon at `size`. `padding_frac` shrinks artwork
    (used for maskable to ensure safe area)."""
    grad = diagonal_gradient(size)
    radius = int(size * 0.225)   # iOS squircle ratio
    mask = squircle_mask(size, radius)
    base = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    base.paste(grad, (0, 0), mask)
    # White sheen overlay (top -> transparent) -- clipped to the squircle
    sheen = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    sd = ImageDraw.Draw(sheen)
    sheen_h = int(size * 0.55)
    for y in range(sheen_h):
        a = int(56 * (1 - y / sheen_h))
        sd.line([(0, y), (size, y)], fill=(255, 255, 255, a))
    # Clip the sheen to the squircle by zeroing alpha outside the mask
    sheen_px = sheen.load()
    mask_px = mask.load()
    for y in range(size):
        for x in range(size):
            if mask_px[x, y] == 0:
                r, g, b, _ = sheen_px[x, y]
                sheen_px[x, y] = (r, g, b, 0)
    base.alpha_composite(sheen)
    # Glyph (shrunk for maskable so it stays in the safe area)
    glyph_scale = 1.0 - padding_frac * 1.4
    draw_glyph(base, scale=glyph_scale)
    return base

def make_maskable_icon(size: int) -> Image.Image:
    """Maskable icon: full-bleed colored background + glyph in 80% safe area."""
    grad = diagonal_gradient(size)
    base = Image.new("RGBA", (size, size), (0, 0, 0, 255))
    base.paste(grad, (0, 0))
    draw_glyph(base, scale=0.72)  # safe area ~80%
    return base

def make_apple_touch_icon(size: int = 180) -> Image.Image:
    """Apple touch icon — opaque (no transparency), iOS adds its own corners."""
    grad = diagonal_gradient(size)
    out = Image.new("RGB", (size, size), (14, 116, 144))
    out.paste(grad, (0, 0))
    rgba = out.convert("RGBA")
    draw_glyph(rgba, scale=1.0)
    return rgba.convert("RGB")

def make_apple_splash(w: int, h: int) -> Image.Image:
    """iOS splash screen — solid brand bg + centered squircle icon + wordmark."""
    img = Image.new("RGB", (w, h), (8, 145, 178))
    # subtle radial-ish wash via gradient stripe
    grad = diagonal_gradient(max(w, h))
    grad = grad.resize((w, h))
    img.paste(grad, (0, 0))
    # Center icon at 22% of min dimension
    icon_size = int(min(w, h) * 0.22)
    icon = make_squircle_icon(icon_size)
    cx = (w - icon_size) // 2
    cy = (h - icon_size) // 2 - int(min(w, h) * 0.04)
    img.paste(icon, (cx, cy), icon)
    # Wordmark (basic) below
    try:
        # Try a few common system fonts
        font_path = None
        for cand in [
            "/System/Library/Fonts/SFNS.ttf",
            "/System/Library/Fonts/Helvetica.ttc",
            "/Library/Fonts/Arial.ttf",
        ]:
            if os.path.exists(cand):
                font_path = cand
                break
        if font_path:
            f = ImageFont.truetype(font_path, int(icon_size * 0.22))
        else:
            f = ImageFont.load_default()
    except Exception:
        f = ImageFont.load_default()
    d = ImageDraw.Draw(img)
    text = "MedAgent AI"
    bbox = d.textbbox((0, 0), text, font=f)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    tx = (w - tw) // 2
    ty = cy + icon_size + int(min(w, h) * 0.025)
    d.text((tx, ty), text, fill=(255, 255, 255), font=f)
    return img

def make_shortcut_icon(size: int, glyph: str) -> Image.Image:
    """Generic small icon: squircle bg + a centered character (for shortcuts)."""
    bg = make_squircle_icon(size)
    # Replace glyph: simpler — draw a "+" using lines centered
    d = ImageDraw.Draw(bg, "RGBA")
    cx, cy = size / 2, size / 2
    arm = size * 0.22
    sw = max(2, int(size * 0.10))
    d.line([(cx - arm, cy), (cx + arm, cy)], fill=(255, 255, 255), width=sw)
    d.line([(cx, cy - arm), (cx, cy + arm)], fill=(255, 255, 255), width=sw)
    return bg

def make_screenshot(w: int, h: int, label: str) -> Image.Image:
    """Lightweight branded placeholder screenshot for the manifest."""
    img = Image.new("RGB", (w, h), (248, 250, 252))
    d = ImageDraw.Draw(img)
    # Header bar
    d.rectangle((0, 0, w, int(h * 0.08)), fill=(14, 116, 144))
    # Sidebar (desktop) or top app bar (mobile) — narrow if portrait
    if w >= h:
        d.rectangle((0, 0, int(w * 0.18), h), fill=(255, 255, 255))
        d.line((int(w * 0.18), 0, int(w * 0.18), h), fill=(226, 232, 240), width=2)
    # Cards
    pad = int(min(w, h) * 0.04)
    col_x = int(w * 0.20) if w >= h else pad
    y = int(h * 0.12)
    card_w = w - col_x - pad
    for i in range(4):
        d.rounded_rectangle(
            (col_x, y, col_x + card_w, y + int(h * 0.14)),
            radius=int(min(w, h) * 0.02),
            fill=(255, 255, 255),
            outline=(226, 232, 240),
            width=2,
        )
        y += int(h * 0.16)
    # Label
    try:
        f = ImageFont.truetype("/System/Library/Fonts/Helvetica.ttc", int(min(w, h) * 0.04))
    except Exception:
        f = ImageFont.load_default()
    d.text((pad, int(h * 0.025)), label, fill=(255, 255, 255), font=f)
    return img

def save(img: Image.Image, path: str) -> None:
    img.save(path, optimize=True)
    print("wrote", path)

# --- Generate ---
# Standard "any purpose" icons
for size in (72, 96, 128, 144, 152, 192, 384, 512):
    save(make_squircle_icon(size), os.path.join(ICONS, f"icon-{size}.png"))

# Maskable icons (safe-area aware, opaque background)
for size in (192, 512):
    save(make_maskable_icon(size), os.path.join(ICONS, f"icon-{size}-maskable.png"))

# Apple touch icon
save(make_apple_touch_icon(180), os.path.join(ICONS, "apple-touch-icon.png"))
save(make_apple_touch_icon(167), os.path.join(ICONS, "apple-touch-icon-ipad.png"))
save(make_apple_touch_icon(152), os.path.join(ICONS, "apple-touch-icon-ipad-old.png"))
save(make_apple_touch_icon(120), os.path.join(ICONS, "apple-touch-icon-iphone.png"))

# Favicon (32x32)
save(make_squircle_icon(32), os.path.join(ICONS, "favicon-32.png"))
save(make_squircle_icon(16), os.path.join(ICONS, "favicon-16.png"))

# Shortcut icon (Android "Add shortcut" / manifest shortcuts)
save(make_shortcut_icon(192, "+"), os.path.join(ICONS, "shortcut-new-case.png"))

# Apple splash screens (a useful subset covering common iPhone sizes)
splash_sizes = [
    # (w, h, label)
    (1290, 2796, "iPhone 15 Pro Max"),
    (1179, 2556, "iPhone 15 Pro"),
    (1170, 2532, "iPhone 13/14"),
    (1125, 2436, "iPhone X/XS"),
    (1080, 1920, "Generic Android"),
    (1668, 2388, "iPad Pro 11"),
    (2048, 2732, "iPad Pro 12.9"),
]
for w, h, _label in splash_sizes:
    save(make_apple_splash(w, h), os.path.join(ICONS, f"apple-splash-{w}x{h}.png"))

# Screenshots for the manifest (Chrome's "Install" dialog)
save(make_screenshot(1280, 720, "MedAgent AI – Dashboard"), os.path.join(SHOTS, "desktop-1280x720.png"))
save(make_screenshot(750, 1334, "MedAgent AI"), os.path.join(SHOTS, "mobile-750x1334.png"))

print("done.")
