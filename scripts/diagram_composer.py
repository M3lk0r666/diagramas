#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys, io
# Force UTF-8 stdout/stderr so emojis and accented chars work on Windows (cp1252)
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
"""
diagram_composer.py
═══════════════════
Compone un diagrama global de red a partir del canvas_json guardado
por el Ensamblador de Diagramas (DiagramAssemblerController).

Uso:
    python scripts/diagram_composer.py \
        --project-json  /ruta/al/canvas.json \
        --output        /ruta/salida.png \
        --storage-path  /ruta/storage/app/public \
        --scale         2.0

El JSON de entrada sigue el formato:
{
    "version": "1.0",
    "canvas": { "width": 5000, "height": 4000, "background": "#F8FAFC" },
    "objects": [
        {
            "id": "img_batch_1_cluster_0",
            "type": "image",
            "src": "batches/1/cluster_0.png",
            "x": 100, "y": 200,
            "width": 800, "height": 600,
            "scaleX": 1.0, "scaleY": 1.0,
            "angle": 0,
            "metadata": { ... }
        }
    ],
    "connectors": [
        {
            "id": "conn_1",
            "from": "img_batch_1_cluster_0",
            "to":   "img_batch_2_cluster_1",
            "type": "straight",
            "label": "10G trunk",
            "color": "#F97316",
            "strokeWidth": 2
        }
    ]
}
"""

import argparse
import json
import math
import os

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    print("ERROR: Pillow no instalado. Ejecuta: pip install Pillow", file=sys.stderr)
    sys.exit(1)


# ── Helpers ───────────────────────────────────────────────────────────────────

def hex_to_rgb(hex_color: str) -> tuple[int, int, int]:
    """Convierte '#RRGGBB' a (R, G, B)."""
    h = hex_color.lstrip('#')
    if len(h) == 3:
        h = ''.join(c * 2 for c in h)
    return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))


def load_image_cached(cache: dict, path: str) -> Image.Image | None:
    if path in cache:
        return cache[path]
    if not os.path.exists(path):
        print(f"  [WARN] Imagen no encontrada: {path}", file=sys.stderr)
        cache[path] = None
        return None
    try:
        img = Image.open(path).convert('RGBA')
        cache[path] = img
        return img
    except Exception as e:
        print(f"  [WARN] No se pudo abrir {path}: {e}", file=sys.stderr)
        cache[path] = None
        return None


def rotate_image(img: Image.Image, angle: float) -> Image.Image:
    """Rota la imagen manteniendo el fondo transparente."""
    if angle == 0:
        return img
    return img.rotate(-angle, expand=True, resample=Image.BICUBIC)


def get_font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    """Intenta cargar una fuente del sistema, cae a la fuente por defecto."""
    candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ]
    for path in candidates:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, size)
            except Exception:
                continue
    return ImageFont.load_default()


def get_object_center(obj: dict, scale: float) -> tuple[int, int]:
    """Devuelve el centro del objeto en píxeles escalados."""
    sx = obj.get('scaleX', 1.0)
    sy = obj.get('scaleY', 1.0)
    w  = obj.get('width',  0) * sx * scale
    h  = obj.get('height', 0) * sy * scale
    x  = obj.get('x', 0) * scale + w / 2
    y  = obj.get('y', 0) * scale + h / 2
    return int(x), int(y)


def build_connector_points(
    from_obj: dict, to_obj: dict, conn_type: str, scale: float
) -> list[tuple[int, int]]:
    """Calcula los puntos de la polilínea del conector."""
    x1, y1 = get_object_center(from_obj, scale)
    x2, y2 = get_object_center(to_obj,   scale)

    if conn_type == 'orthogonal':
        mx = (x1 + x2) // 2
        return [(x1, y1), (mx, y1), (mx, y2), (x2, y2)]

    # straight (default)
    return [(x1, y1), (x2, y2)]


# ── Main composer ─────────────────────────────────────────────────────────────

def compose(
    canvas_json: dict,
    output_path: str,
    storage_path: str,
    scale: float = 1.0,
) -> None:
    canvas_cfg = canvas_json.get('canvas', {})
    cw = int(canvas_cfg.get('width',  5000) * scale)
    ch = int(canvas_cfg.get('height', 4000) * scale)
    bg = canvas_cfg.get('background', '#F8FAFC')
    bg_rgb = hex_to_rgb(bg)

    print(f"  Canvas: {cw}×{ch} px (escala ×{scale})")
    out_img = Image.new('RGBA', (cw, ch), (*bg_rgb, 255))

    objects    = {obj['id']: obj for obj in canvas_json.get('objects', [])}
    connectors = canvas_json.get('connectors', [])
    img_cache  = {}

    # ── 1. Pegar imágenes ─────────────────────────────────────────────────────
    for obj in canvas_json.get('objects', []):
        if obj.get('type') != 'image':
            continue

        # Prefer abs_path (injected by PHP controller); fall back to joining storage_path + src
        abs_path = obj.get('abs_path', '').replace('/', os.sep).replace('\\', os.sep)
        src_rel  = obj.get('src', '')
        src_path = abs_path if abs_path else os.path.join(storage_path, src_rel)
        img      = load_image_cached(img_cache, src_path)

        if img is None:
            # Last-resort fallback: try the other separator style
            alt_path = os.path.join(storage_path, src_rel)
            if alt_path != src_path:
                img = load_image_cached(img_cache, alt_path)

        if img is None:
            # Placeholder rojo para que el error sea visible en el PNG de salida
            sw = max(1, int(obj.get('width',  400) * obj.get('scaleX', 1) * scale))
            sh = max(1, int(obj.get('height', 300) * obj.get('scaleY', 1) * scale))
            placeholder = Image.new('RGBA', (sw, sh), (220, 60, 60, 160))
            label = obj.get('id', 'NOT FOUND')[:30]
            ImageDraw.Draw(placeholder).text((8, sh // 2 - 10), f"[img no encontrada]\n{label}", fill=(255, 255, 255, 255))
            px = int(obj.get('x', 0) * scale)
            py = int(obj.get('y', 0) * scale)
            out_img.paste(placeholder, (px, py), placeholder)
            print(f"  [ERR] No encontrada: {src_path}", file=sys.stderr)
            continue

        # Escalar al tamaño indicado por scaleX/scaleY
        target_w = int(obj.get('width',  img.width)  * obj.get('scaleX', 1.0) * scale)
        target_h = int(obj.get('height', img.height) * obj.get('scaleY', 1.0) * scale)
        target_w = max(1, target_w)
        target_h = max(1, target_h)

        img_resized = img.resize((target_w, target_h), Image.LANCZOS)

        # Rotar si hay ángulo
        angle = obj.get('angle', 0)
        if angle:
            img_resized = rotate_image(img_resized, angle)

        px = int(obj.get('x', 0) * scale)
        py = int(obj.get('y', 0) * scale)

        out_img.paste(img_resized, (px, py), img_resized)
        print(f"  ✓ {obj['id']} → ({px}, {py}) {target_w}×{target_h}")

    # ── 2. Dibujar conectores ─────────────────────────────────────────────────
    draw = ImageDraw.Draw(out_img, 'RGBA')
    font = get_font(max(10, int(13 * scale)))

    for conn in connectors:
        from_id = conn.get('from')
        to_id   = conn.get('to')

        if from_id not in objects or to_id not in objects:
            print(f"  [WARN] Conector {conn.get('id')}: objeto(s) no encontrado(s) ({from_id} → {to_id})")
            continue

        from_obj  = objects[from_id]
        to_obj    = objects[to_id]
        conn_type = conn.get('type', 'straight')
        color_hex = conn.get('color', '#F97316')
        stroke_w  = max(1, int(conn.get('strokeWidth', 2) * scale))
        label     = conn.get('label', '')

        try:
            color_rgb = hex_to_rgb(color_hex)
        except Exception:
            color_rgb = (249, 115, 22)  # naranja por defecto

        points = build_connector_points(from_obj, to_obj, conn_type, scale)

        # Dibujar segmentos
        for i in range(len(points) - 1):
            draw.line([points[i], points[i + 1]], fill=(*color_rgb, 230), width=stroke_w)

        # Flecha en el destino
        if len(points) >= 2:
            p1, p2 = points[-2], points[-1]
            dx, dy = p2[0] - p1[0], p2[1] - p1[1]
            length = math.hypot(dx, dy)
            if length > 0:
                arrow_len = max(10, int(14 * scale))
                arrow_w   = max(5,  int(7  * scale))
                ux, uy    = dx / length, dy / length
                px, py    = p2[0] - ux * arrow_len, p2[1] - uy * arrow_len
                perp_x    = -uy * arrow_w / 2
                perp_y    =  ux * arrow_w / 2
                draw.polygon([
                    p2,
                    (int(px + perp_x), int(py + perp_y)),
                    (int(px - perp_x), int(py - perp_y)),
                ], fill=(*color_rgb, 230))

        # Etiqueta del conector
        if label and len(points) >= 2:
            mid_idx = len(points) // 2
            if mid_idx < len(points):
                mx = (points[mid_idx - 1][0] + points[mid_idx][0]) // 2
                my = (points[mid_idx - 1][1] + points[mid_idx][1]) // 2
            else:
                mx = (points[0][0] + points[-1][0]) // 2
                my = (points[0][1] + points[-1][1]) // 2

            # Background pill for label
            try:
                bbox = draw.textbbox((mx, my), label, font=font)
            except AttributeError:
                w, h = draw.textsize(label, font=font)
                bbox = (mx, my, mx + w, my + h)

            pad = max(3, int(4 * scale))
            draw.rounded_rectangle(
                [bbox[0] - pad, bbox[1] - pad, bbox[2] + pad, bbox[3] + pad],
                radius=4, fill=(255, 255, 255, 210),
            )
            draw.text((mx, my), label, fill=(*color_rgb, 255), font=font, anchor='mm')

        print(f"  ✓ Conector {conn.get('id', '?')} ({conn_type}): {from_id} → {to_id}")

    # ── 3. Guardar ────────────────────────────────────────────────────────────
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    final = out_img.convert('RGB')
    final.save(output_path, 'PNG', optimize=True)
    size_kb = os.path.getsize(output_path) // 1024
    print(f"\n✅ PNG guardado: {output_path} ({size_kb} KB)")


# ── Entry point ───────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description='Diagram Composer — genera PNG global de red')
    parser.add_argument('--project-json',  required=True, help='Ruta al JSON del canvas')
    parser.add_argument('--output',        required=True, help='Ruta del PNG de salida')
    parser.add_argument('--storage-path',  default='storage/app/public',
                        help='Ruta base de almacenamiento de PNGs (storage/app/public)')
    parser.add_argument('--scale',         type=float, default=1.0,
                        help='Factor de escala (1.0 = tamaño original, 2.0 = doble resolución)')
    args = parser.parse_args()

    if not os.path.exists(args.project_json):
        print(f"ERROR: JSON no encontrado: {args.project_json}", file=sys.stderr)
        sys.exit(1)

    scale = max(0.1, min(3.0, args.scale))

    with open(args.project_json, 'r', encoding='utf-8') as f:
        canvas_json = json.load(f)

    print(f"\n📐 Generando diagrama ({scale}×)…")
    compose(canvas_json, args.output, args.storage_path, scale)


if __name__ == '__main__':
    main()
