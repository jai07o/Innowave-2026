import os
from PIL import Image, ImageOps

src_path = r"C:\Users\kakan\.gemini\antigravity-ide\brain\ded7221e-de97-4937-b8c5-1f64ae9f0872\.user_uploaded\media_1787585812094.jpg"
out_jpg = r"c:\Users\kakan\Downloads\InnoWave2026_Website\innowave-website\public\assets\header_accreditation.jpg"
out_png = r"c:\Users\kakan\Downloads\InnoWave2026_Website\innowave-website\public\assets\header_accreditation.png"

img = Image.open(src_path).convert("RGB")
w, h = img.size
print(f"Original size: {w}x{h}")

# Auto-crop uniform background border (top/bottom/left/right whitespace/grey)
# Bounding box of content where pixels differ from background color #f5f5f5 / #ffffff
bg = Image.new(img.mode, img.size, (245, 245, 245))
diff = ImageChops.difference(img, bg) if hasattr(Image, 'ImageChops') else None

from PIL import ImageChops
diff = ImageChops.difference(img, bg)
bbox = diff.getbbox()

if bbox:
    print(f"Content bbox: {bbox}")
    # Expand slightly by 2px margin
    left = max(0, bbox[0] - 2)
    top = max(0, bbox[1] - 2)
    right = min(w, bbox[2] + 2)
    bottom = min(h, bbox[3] + 2)
    cropped = img.crop((left, top, right, bottom))
else:
    cropped = img

cw, ch = cropped.size
print(f"Cropped size: {cw}x{ch}")

# Save high quality
cropped.save(out_jpg, "JPEG", quality=100, optimize=True)
cropped.save(out_png, "PNG", compress_level=1)
print("Image successfully cropped and saved to public/assets!")
