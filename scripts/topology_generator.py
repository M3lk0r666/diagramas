#!/usr/bin/env python3
"""
topology_generator.py
---------------------
Genera un diagrama de red PNG a partir del JSON de topología producido por
TopologyBuilderService de Laravel.

Uso:
    python topology_generator.py <input_json> <output_png> [--dpi 150] [--icons path/to/icons]

Iconos personalizados (opcionales):
    Coloca archivos PNG en scripts/icons/ con los nombres:
        core_switch.png, backbone_switch.png, dist_switch.png,
        access_switch.png, stack_switch.png
    Si un ícono no existe, se dibuja la forma geométrica de respaldo.

Dependencias:
    pip install networkx matplotlib pillow scipy
"""

import json
import sys
import os
import math
import re
import textwrap
import argparse

# Force UTF-8 output on Windows (avoids cp1252 UnicodeEncodeError)
if sys.stdout.encoding and sys.stdout.encoding.lower() != 'utf-8':
    sys.stdout = open(sys.stdout.fileno(), mode='w', encoding='utf-8', buffering=1)
if sys.stderr.encoding and sys.stderr.encoding.lower() != 'utf-8':
    sys.stderr = open(sys.stderr.fileno(), mode='w', encoding='utf-8', buffering=1)

import networkx as nx
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyBboxPatch
from matplotlib.patheffects import withStroke
from matplotlib.offsetbox import OffsetImage, AnnotationBbox

try:
    from PIL import Image
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False

from matplotlib.legend_handler import HandlerBase

class ImageHandler(HandlerBase):
    """Handler de leyenda que muestra un PNG en lugar de un parche de color."""
    def __init__(self, img_array):
        super().__init__()
        self.img_array = img_array

    def create_artists(self, legend, orig_handle,
                       xdescent, ydescent, width, height, fontsize, trans):
        zoom = height / max(self.img_array.shape[:2]) * 2.2
        imagebox = OffsetImage(self.img_array, zoom=zoom)
        ab = AnnotationBbox(
            imagebox,
            (width / 2 - xdescent, height / 2 - ydescent),
            xycoords=trans, frameon=False,
        )
        return [ab]


# ── Paleta y estilos por rol ────────────────────────────────────────────
ROLE_STYLE = {
    'core':         {'bg': '#1E3A5F', 'fg': '#FFFFFF', 'border': '#0F2040', 'size': 1.4, 'icon': 'core_switch.png'},
    'backbone':     {'bg': '#1D4ED8', 'fg': '#FFFFFF', 'border': '#1E3A8A', 'size': 1.2, 'icon': 'backbone_switch.png'},
    'distribution': {'bg': '#0891B2', 'fg': '#FFFFFF', 'border': '#0E6E8C', 'size': 1.1, 'icon': 'dist_switch.png'},
    'access':       {'bg': '#16A34A', 'fg': '#FFFFFF', 'border': '#15803D', 'size': 1.0, 'icon': 'access_switch.png'},
}
STACK_ICON   = 'stack_switch.png'
EDGE_COLOR   = '#94A3B8'
LABEL_COLOR  = '#374151'
BG_COLOR     = '#F8FAFC'

# Directorio de iconos (relativo al script; se puede sobreescribir con --icons)
ICONS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'icons')

# Caché de imágenes cargadas para no releer el disco en cada nodo
_icon_cache: dict = {}


# ── Carga de iconos ─────────────────────────────────────────────────────

def load_icon(filename: str, icons_dir: str) -> 'np.ndarray | None':
    """
    Carga un PNG como array RGBA.
    Devuelve None si el archivo no existe o PIL no está disponible.
    """
    if not PIL_AVAILABLE:
        return None
    path = os.path.join(icons_dir, filename)
    if path in _icon_cache:
        return _icon_cache[path]
    if not os.path.isfile(path):
        _icon_cache[path] = None
        return None
    try:
        import numpy as np
        img = Image.open(path).convert('RGBA')
        arr = np.array(img)
        _icon_cache[path] = arr
        return arr
    except Exception as e:
        print(f"[WARN] No se pudo cargar el ícono {path}: {e}", file=sys.stderr)
        _icon_cache[path] = None
        return None


def get_role_icon(role: str, is_stacked: bool, icons_dir: str):
    """Devuelve el array del ícono para un rol dado, o None si no existe."""
    # Si está en stack, intentar primero stack_switch.png
    if is_stacked:
        arr = load_icon(STACK_ICON, icons_dir)
        if arr is not None:
            return arr
    style = ROLE_STYLE.get(role, ROLE_STYLE['access'])
    return load_icon(style['icon'], icons_dir)


# ── Dibujo de nodos ─────────────────────────────────────────────────────

def draw_node(ax, x: float, y: float, node: dict, icons_dir: str, ds: float = 1.0,
              show_ip: bool = True, show_extra: bool = True):
    """
    Dibuja el nodo en (x, y).
    ds = density_scale: factor [0.45-1.0] que reduce icono/fuente en redes densas.
    show_ip / show_extra controlan qué info se muestra en la etiqueta.
    """
    role       = node.get('role', 'access')
    is_stacked = node.get('is_stacked', False)
    style      = ROLE_STYLE.get(role, ROLE_STYLE['access'])
    size       = style['size']

    icon_arr = get_role_icon(role, is_stacked, icons_dir)

    if icon_arr is not None:
        _draw_icon_node(ax, x, y, icon_arr, size, is_stacked, ds)
        _draw_label(ax, x, y, node, size, has_icon=True, ds=ds,
                    show_ip=show_ip, show_extra=show_extra)
    else:
        _draw_shape_node(ax, x, y, role, size, is_stacked)
        _draw_shape_label(ax, x, y, node, size, ds=ds)


def _draw_icon_node(ax, x: float, y: float, icon_arr, size: float,
                    is_stacked: bool, ds: float = 1.0):
    """Coloca la imagen PNG en la posición del nodo."""
    zoom = size * 0.055 * ds   # ds encoge el ícono en redes densas
    imagebox = OffsetImage(icon_arr, zoom=zoom)
    imagebox.image.axes = ax
    ab = AnnotationBbox(
        imagebox, (x, y),
        frameon=False,
        zorder=4,
        pad=0,
    )
    ax.add_artist(ab)

    # Insignia de stack encima del ícono
    if is_stacked:
        badge_r = size * 0.018
        circle  = plt.Circle((x + size * 0.045, y + size * 0.045),
                              badge_r, color='#F59E0B', zorder=6)
        ax.add_patch(circle)
        ax.text(x + size * 0.045, y + size * 0.045, 'S',
                ha='center', va='center', fontsize=4.5,
                fontweight='bold', color='white', zorder=7)


def _draw_shape_node(ax, x: float, y: float, role: str, size: float, is_stacked: bool):
    """Forma geométrica de respaldo cuando no hay ícono PNG."""
    s     = size * 0.055
    style = ROLE_STYLE.get(role, ROLE_STYLE['access'])

    if role == 'core':
        diamond = plt.Polygon(
            [[x, y + s*1.2], [x + s*0.9, y], [x, y - s*1.2], [x - s*0.9, y]],
            closed=True, facecolor=style['bg'], edgecolor=style['border'],
            linewidth=2, zorder=4,
        )
        ax.add_patch(diamond)

    elif role == 'backbone':
        angles = [math.radians(60 * i + 30) for i in range(6)]
        hex_patch = plt.Polygon(
            [(x + s * math.cos(a), y + s * math.sin(a)) for a in angles],
            closed=True, facecolor=style['bg'], edgecolor=style['border'],
            linewidth=2, zorder=4,
        )
        ax.add_patch(hex_patch)

    else:
        rect_w = s * 1.8
        rect_h = s * 1.0
        rect = FancyBboxPatch(
            (x - rect_w / 2, y - rect_h / 2), rect_w, rect_h,
            boxstyle='round,pad=0.005',
            facecolor=style['bg'], edgecolor=style['border'],
            linewidth=1.5, zorder=4,
        )
        ax.add_patch(rect)
        # "Puertos" decorativos
        port_y = y - rect_h * 0.15
        for i in range(6):
            px = x - rect_w * 0.38 + i * (rect_w * 0.76 / 5)
            ax.add_patch(FancyBboxPatch(
                (px - 0.003, port_y - 0.005), 0.006, 0.010,
                boxstyle='square,pad=0',
                facecolor='#FFFFFF', edgecolor='#CCCCCC',
                linewidth=0.5, zorder=5,
            ))

    if is_stacked:
        circle = plt.Circle((x + s * 0.85, y + s * 0.85), s * 0.25,
                             color='#F59E0B', zorder=6)
        ax.add_patch(circle)
        ax.text(x + s * 0.85, y + s * 0.85, 'S',
                ha='center', va='center', fontsize=5,
                fontweight='bold', color='white', zorder=7)


def _draw_label(ax, x: float, y: float, node: dict, size: float,
                has_icon: bool, ds: float = 1.0,
                show_ip: bool = True, show_extra: bool = True):
    """
    Etiqueta debajo del nodo con ícono PNG.
    ds = density_scale: reduce fuentes proporcionalmente en redes densas.
    show_ip / show_extra controlan qué info se muestra (para redes densas).
    """
    y_offset   = size * 0.095 * ds if has_icon else size * 0.08 * ds
    label      = node.get('label', '')
    ip         = node.get('ip', '')
    port_count = node.get('active_port_count', 0)
    is_stacked = node.get('is_stacked', False)
    slots      = node.get('slot_count', 1)
    LINE_H     = 0.028 * ds   # altura de línea escala con densidad

    fs_name  = max(3.5, 7.0   * ds)
    fs_ip    = max(3.0, 5.5   * ds)
    fs_extra = max(2.5, 5.0   * ds)

    wrapped = '\n'.join(textwrap.wrap(label, width=18))
    lines   = wrapped.count('\n') + 1

    ax.text(
        x, y - y_offset, wrapped,
        ha='center', va='top',
        fontsize=fs_name, fontweight='bold', color=LABEL_COLOR, zorder=5,
        path_effects=[withStroke(linewidth=2, foreground='white')],
    )

    cur_y = y - y_offset - LINE_H * lines
    if ip and show_ip:
        ax.text(
            x, cur_y, ip,
            ha='center', va='top',
            fontsize=fs_ip, color='#6B7280', zorder=5,
            path_effects=[withStroke(linewidth=2, foreground='white')],
        )
        cur_y -= LINE_H

    if show_extra:
        if port_count:
            ax.text(
                x, cur_y, f'{port_count} puertos',
                ha='center', va='top',
                fontsize=fs_extra, color='#4B5563', zorder=5,
                path_effects=[withStroke(linewidth=2, foreground='white')],
            )
            cur_y -= LINE_H
        if is_stacked:
            ax.text(
                x, cur_y, f'Stack ({slots})',
                ha='center', va='top',
                fontsize=fs_extra, color='#B45309', zorder=5,
                path_effects=[withStroke(linewidth=2, foreground='white')],
            )


def _draw_shape_label(ax, x: float, y: float, node: dict,
                      size: float, ds: float = 1.0):
    """
    Para nodos sin ícono PNG: dibuja nombre + IP + puertos
    DENTRO de la forma geométrica, centrado.
    """
    label      = node.get('label', '')
    ip         = node.get('ip', '')
    port_count = node.get('active_port_count', 0)
    role       = node.get('role', 'access')
    style      = ROLE_STYLE.get(role, ROLE_STYLE['access'])

    # Acortar label si es muy largo
    short = (label[:11] + '…') if len(label) > 12 else label

    lines = [short]
    if ip:
        lines.append(ip)
    if port_count:
        lines.append(f'{port_count}p')

    # Dibujar líneas centradas, separadas verticalmente
    n_lines    = len(lines)
    line_gap   = size * 0.018 * ds
    start_y    = y + (n_lines - 1) * line_gap / 2

    for i, txt in enumerate(lines):
        fs = max(2.5, 4.5 * ds) if i == 0 else max(2.0, 4.0 * ds)
        fw = 'bold' if i == 0 else 'normal'
        ax.text(
            x, start_y - i * line_gap, txt,
            ha='center', va='center',
            fontsize=fs, fontweight=fw,
            color=style['fg'], zorder=5,
        )


# ── Etiquetas de puerto en aristas ──────────────────────────────────────

def draw_port_labels(ax, x1, y1, x2, y2, src_port, dst_port, offset=0.08):
    """
    Dibuja la etiqueta de puerto a una distancia fija (offset) desde cada nodo,
    siguiendo la dirección de la arista.
    Así el label siempre queda cerca del switch, sin importar el largo de la arista.
    """
    dx = x2 - x1
    dy = y2 - y1
    length = math.hypot(dx, dy)
    if length == 0:
        return
    ux, uy = dx / length, dy / length   # vector unitario de la arista

    for port, (px, py) in [(src_port, (x1, y1)), (dst_port, (x2, y2))]:
        if not port:
            continue
        # Desplazar desde el endpoint hacia el interior de la arista
        sign = 1 if (px, py) == (x1, y1) else -1
        lx = px + sign * ux * offset
        ly = py + sign * uy * offset
        ax.text(
            lx, ly, str(port),
            ha='center', va='center',
            fontsize=4, color='#334155',
            bbox=dict(facecolor='white', edgecolor='#CBD5E1',
                      alpha=0.88, pad=0.5, boxstyle='round,pad=0.25'),
            zorder=6,
        )


# ── Layout jerárquico por rol ────────────────────────────────────────────

def hierarchical_layout(G, node_map: dict, col_gap: float = 1.2,
                        level_gap: float = 1.0, sub_y_step: float = 0.55) -> dict:
    """
    Layout jerárquico mejorado:
    • Bandas Y por nivel de rol (core arriba, access abajo)
    • Nodos del mismo nivel interconectados entre sí se apilan VERTICALMENTE
      en la misma columna (no horizontalmente)
    • Las columnas se ordenan según la X del padre en el nivel superior

    Parámetros:
        col_gap    separación horizontal entre columnas
        level_gap  separación vertical entre niveles de rol
        sub_y_step separación vertical entre nodos dentro de una columna
    """
    LEVEL_MAP = {'core': 0, 'backbone': 1, 'distribution': 2, 'access': 3}

    # ── Clasificar nodos por nivel ────────────────────────────────────
    levels: dict = {}
    for nid in G.nodes:
        role = node_map.get(nid, {}).get('role', 'access')
        lv   = node_map.get(nid, {}).get('level', LEVEL_MAP.get(role, 3))
        levels.setdefault(lv, []).append(nid)

    if not levels:
        return {}

    present = sorted(levels.keys())
    n_bands = len(present)

    pos   = {}
    x_pos = {}  # nid → x asignada (para que los hijos se alineen con el padre)

    for idx, lv in enumerate(present):
        nodes_at = set(levels[lv])
        base_y   = (n_bands - 1 - idx) * level_gap  # nivel 0 = y más alto

        # ── Agrupar en componentes conectadas dentro del mismo nivel ──
        intra: dict = {n: [] for n in nodes_at}
        for u, v in G.edges():
            if u in nodes_at and v in nodes_at:
                intra[u].append(v)
                intra[v].append(u)

        visited: set = set()
        components: list = []
        for seed in sorted(nodes_at):
            if seed in visited:
                continue
            comp: list = []
            stack = [seed]
            while stack:
                n = stack.pop()
                if n in visited:
                    continue
                visited.add(n)
                comp.append(n)
                for nb in intra[n]:
                    if nb not in visited:
                        stack.append(nb)
            components.append(comp)

        # ── Ordenar nodos dentro de cada componente en cadena ─────────
        def chain_order(comp: list) -> list:
            if len(comp) <= 1:
                return list(comp)
            sub: dict = {n: [nb for nb in intra[n] if nb in set(comp)] for n in comp}
            # Extremos (grado ≤ 1 en el subgrafo del mismo nivel)
            ends = [n for n in comp if len(sub[n]) <= 1]
            if not ends:
                ends = comp
            # Preferir el extremo con más conexiones hacia el nivel superior
            def ext_up(n):
                return sum(1 for nb in G.neighbors(n) if nb not in nodes_at)
            start = max(ends, key=ext_up)
            ordered: list = []
            vis2: set = set()
            q = [start]
            while q:
                n = q.pop(0)
                if n in vis2:
                    continue
                vis2.add(n)
                ordered.append(n)
                for nb in sub[n]:
                    if nb not in vis2:
                        q.append(nb)
            return ordered

        ordered_components = [chain_order(c) for c in components]

        # ── Ordenar columnas según X del padre en el nivel superior ───
        def comp_parent_x(comp: list) -> float:
            xs = [x_pos[nb]
                  for nid in comp
                  for nb in G.neighbors(nid)
                  if nb not in nodes_at and nb in x_pos]
            return sum(xs) / len(xs) if xs else 0.0

        ordered_components.sort(key=comp_parent_x)

        # ── Asignar X (columnas) e Y (filas dentro de columna) ────────
        n_cols = len(ordered_components)
        if n_cols == 1:
            col_xs = [0.0]
        else:
            total_w = col_gap * (n_cols - 1)
            col_xs  = [-total_w / 2 + i * col_gap for i in range(n_cols)]

        for col_i, comp in enumerate(ordered_components):
            cx = col_xs[col_i]
            for row_j, nid in enumerate(comp):
                nx_ = cx
                ny_ = base_y - row_j * sub_y_step
                x_pos[nid] = cx
                pos[nid]   = (nx_, ny_)

    return pos


# ── Función principal ───────────────────────────────────────────────────

def generate(input_path: str, output_path: str, dpi: int = 150, icons_dir: str = ICONS_DIR):
    # ── Cargar JSON ───────────────────────────────────────────────────
    with open(input_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    meta  = data.get('meta', {})
    nodes = data.get('nodes', [])
    edges = data.get('edges', [])

    if not nodes:
        print("No hay nodos en el JSON. Saliendo.", file=sys.stderr)
        sys.exit(1)

    using_icons = PIL_AVAILABLE and os.path.isdir(icons_dir)
    if using_icons:
        present = [f for f in os.listdir(icons_dir) if f.endswith('.png')]
        print(f"[INFO] Directorio de iconos: {icons_dir} ({len(present)} PNG encontrados)")
    else:
        print("[INFO] Usando formas geometricas (sin iconos PNG)")

    # ── Construir grafo NetworkX ──────────────────────────────────────
    G        = nx.Graph()
    node_map = {}

    for n in nodes:
        G.add_node(n['id'])
        node_map[n['id']] = n

    for e in edges:
        if e['from'] in node_map and e['to'] in node_map:
            G.add_edge(e['from'], e['to'], **e)

    # ── Parámetros según densidad de la red ──────────────────────────
    n_count = len(nodes)
    if n_count <= 30:
        ds, k_spring = 1.00, 1.5
    elif n_count <= 80:
        ds, k_spring = 0.85, 1.0
    elif n_count <= 150:
        ds, k_spring = 0.70, 0.70
    else:                                   # 150+ nodos
        ds, k_spring = 0.58, 0.50

    show_port_labels = n_count <= 60     # etiquetas de puerto en aristas
    show_ip_label    = n_count <= 100    # IP del equipo en la etiqueta
    show_extra_label = n_count <= 60     # puertos activos / stack info

    # ── Layout spring / orgánico ──────────────────────────────────────
    # Hubs (backbone/core) quedan al centro, accesos irradian hacia afuera.
    # Equivalente al layout de vis-network.js en el portal web.
    if len(G.nodes) == 1:
        pos = {list(G.nodes)[0]: (0, 0)}
    elif len(G.nodes) <= 3:
        pos = nx.circular_layout(G, scale=2.0)
    else:
        pos = nx.spring_layout(G, k=k_spring, seed=42, iterations=150, scale=3.0)

    # ── Calcular límites reales del layout ────────────────────────────
    all_x = [p[0] for p in pos.values()]
    all_y = [p[1] for p in pos.values()]
    x_min, x_max = min(all_x), max(all_x)
    y_min, y_max = min(all_y), max(all_y)
    pad_x = max(0.6, (x_max - x_min) * 0.10)
    pad_y = max(0.6, (y_max - y_min) * 0.12)

    x_range = (x_max - x_min) + 2 * pad_x
    y_range = (y_max - y_min) + 2 * pad_y

    SCALE_FACTOR = 4.5
    fig_w = max(16, min(80, x_range * SCALE_FACTOR))
    fig_h = max(12, min(60, y_range * SCALE_FACTOR))

    # ── Figura ────────────────────────────────────────────────────────
    # Usamos subplots_adjust (NO tight_layout) porque vamos a añadir
    # sub-axes por cada ícono mediante plt.axes(), que son incompatibles
    # con tight_layout.
    fig, ax = plt.subplots(figsize=(fig_w, fig_h))
    fig.subplots_adjust(left=0.03, right=0.97, top=0.94, bottom=0.04)
    ax.set_facecolor(BG_COLOR)
    fig.patch.set_facecolor(BG_COLOR)
    ax.axis('off')
    ax.set_xlim(x_min - pad_x, x_max + pad_x)
    ax.set_ylim(y_min - pad_y, y_max + pad_y)

    # Título y footer en coordenadas de figura
    fig.text(0.5, 0.97, meta.get('batch_name', 'Topologia de red'),
             ha='center', va='top', fontsize=13, fontweight='bold', color='#1E293B')

    gen_at = meta.get('generated_at', '')
    footer = (f"Batch #{meta.get('batch_id', '')}  ·  {len(nodes)} equipos"
              f"  ·  {len(edges)} enlaces")
    if gen_at:
        footer += f"  ·  {gen_at[:10]}"
    fig.text(0.5, 0.01, footer, ha='center', va='bottom', fontsize=6, color='#94A3B8')

    # ── Tamaño de ícono y margen de aristas ───────────────────────────
    # base_icon_frac: fracción de la figura que ocupa un ícono base (access, size=1.0)
    base_icon_frac = max(0.012, min(0.045,
                         0.22 * math.sqrt(fig_w * fig_h / max(1, n_count)) / fig_w))
    # Margen en px para que las aristas no solapen los íconos
    icon_radius_px = base_icon_frac * fig_h * dpi / 2
    edge_margin_px = max(8, int(icon_radius_px * 0.85))

    # ── Aristas ──────────────────────────────────────────────────────
    # FancyArrowPatch permite min_source/target_margin para dejar espacio limpio
    # alrededor de los íconos (igual que en el ejemplo custom-node-icons.py).
    nx.draw_networkx_edges(
        G, pos=pos, ax=ax,
        arrows=True, arrowstyle='-',
        edge_color=EDGE_COLOR, width=1.0, alpha=0.75,
        min_source_margin=edge_margin_px,
        min_target_margin=edge_margin_px,
    )

    if show_port_labels:
        for e in edges:
            fid, tid = e.get('from'), e.get('to')
            if fid not in pos or tid not in pos:
                continue
            draw_port_labels(
                ax,
                pos[fid][0], pos[fid][1],
                pos[tid][0], pos[tid][1],
                e.get('src_port', ''), e.get('dst_port', ''),
                offset=0.10,
            )

    # ── Nodos: íconos como plt.axes + etiquetas con fig.text ─────────
    # Técnica: transformar coordenadas de datos → display → figura,
    # luego crear un axes de matplotlib en esa posición e imshow() el ícono.
    # Las etiquetas se dibujan en coordenadas de figura con fig.text().
    # Ref: https://matplotlib.org/stable/gallery/images_contours_and_fields/
    #      custom_node_icons.html

    # Necesitamos un render previo para que los transforms estén inicializados.
    fig.canvas.draw()
    tr_fig  = ax.transData.transform            # data → display (px)
    tr_axes = fig.transFigure.inverted().transform   # display → figura [0,1]

    for nid, n in node_map.items():
        if nid not in pos:
            continue

        role       = n.get('role', 'access')
        is_stacked = n.get('is_stacked', False)
        style      = ROLE_STYLE.get(role, ROLE_STYLE['access'])
        size_mult  = style['size']
        icon_arr   = get_role_icon(role, is_stacked, icons_dir)

        x, y    = pos[nid]
        xd, yd  = tr_fig((x, y))
        xa, ya  = tr_axes((xd, yd))
        icon_sz = base_icon_frac * size_mult   # fracción de figura para este rol

        if icon_arr is not None:
            # Ícono como sub-axes en posición (xa, ya) en coordenadas de figura
            a = plt.axes([xa - icon_sz/2, ya - icon_sz/2, icon_sz, icon_sz],
                         facecolor='none')
            a.imshow(icon_arr)
            a.axis('off')

            # Insignia de stack: círculo naranja con "S"
            if is_stacked:
                bsz = max(0.006, icon_sz * 0.32)
                bx  = xa + icon_sz * 0.40
                by  = ya + icon_sz * 0.40
                bd  = plt.axes([bx - bsz/2, by - bsz/2, bsz, bsz])
                bd.set_facecolor('#F59E0B')
                bd.text(0.5, 0.5, 'S', ha='center', va='center',
                        fontsize=max(3, int(5 * ds)), fontweight='bold',
                        color='white', transform=bd.transAxes)
                bd.axis('off')
        else:
            # Sin ícono PNG: forma geométrica de respaldo en el ax principal
            draw_node(ax, x, y, n, icons_dir, ds=ds,
                      show_ip=show_ip_label, show_extra=show_extra_label)
            continue   # draw_node ya añade las etiquetas

        # ── Etiquetas en coordenadas de figura ────────────────────────
        if n_count > 150 and role == 'access':
            continue   # sin texto en redes muy densas para nodos de acceso

        label   = n.get('label', '') or ''
        fs_name = max(4.0, 8.0 * ds)
        lbl_y   = ya - icon_sz * 0.56   # justo bajo el borde inferior del ícono

        # Nombre (wrapping simple para etiquetas largas)
        if len(label) > 15:
            mid = len(label) // 2
            # Buscar '-' o '_' más cercano al centro
            for i in range(mid, min(mid + 6, len(label))):
                if label[i] in ('-', '_'):
                    label_str = label[:i+1] + '\n' + label[i+1:]
                    break
            else:
                label_str = label
            lbl_lines = label_str.count('\n') + 1
        else:
            label_str = label
            lbl_lines = 1

        lbl_step = 0.012 * ds

        fig.text(xa, lbl_y, label_str,
                 ha='center', va='top', fontsize=fs_name, fontweight='bold',
                 color=LABEL_COLOR, linespacing=1.2,
                 path_effects=[withStroke(linewidth=2, foreground='white')])

        cur_y = lbl_y - lbl_step * lbl_lines

        if show_ip_label:
            ip = n.get('ip') or ''
            if ip:
                fig.text(xa, cur_y, ip,
                         ha='center', va='top',
                         fontsize=max(3.5, 6.0 * ds), color='#6B7280',
                         path_effects=[withStroke(linewidth=2, foreground='white')])
                cur_y -= lbl_step * 0.9

        if show_extra_label:
            port_count = n.get('active_port_count', 0)
            if port_count:
                fig.text(xa, cur_y, f'{port_count} puertos',
                         ha='center', va='top',
                         fontsize=max(3.0, 5.5 * ds), color='#4B5563',
                         path_effects=[withStroke(linewidth=2, foreground='white')])
                cur_y -= lbl_step * 0.9
            if is_stacked:
                slots = n.get('slot_count', 1)
                fig.text(xa, cur_y, f'Stack ({slots})',
                         ha='center', va='top',
                         fontsize=max(3.0, 5.5 * ds), color='#B45309',
                         path_effects=[withStroke(linewidth=2, foreground='white')])

    # ── Leyenda ──────────────────────────────────────────────────────
    roles_present = {node_map[n].get('role', 'access') for n in node_map}
    role_labels   = {'core': 'Core', 'backbone': 'Backbone',
                     'distribution': 'Distribucion', 'access': 'Acceso'}

    handles, labels_leg, handler_map = [], [], {}

    for role in ['core', 'backbone', 'distribution', 'access']:
        if role not in roles_present:
            continue
        icon_arr = load_icon(ROLE_STYLE[role]['icon'], icons_dir) if using_icons else None
        if icon_arr is not None:
            handle = mpatches.Patch(visible=False)
            handler_map[handle] = ImageHandler(icon_arr)
        else:
            s      = ROLE_STYLE[role]
            handle = mpatches.Patch(facecolor=s['bg'], edgecolor=s['border'])
        handles.append(handle)
        labels_leg.append(role_labels[role])

    handles.append(mpatches.Patch(facecolor='#F59E0B', edgecolor='#D97706'))
    labels_leg.append('En Stack')

    ax.legend(
        handles=handles, labels=labels_leg,
        handler_map=handler_map if handler_map else None,
        loc='upper left', fontsize=7,
        framealpha=0.90, edgecolor='#CBD5E1',
        title='Tipos de equipo', title_fontsize=7,
    )

    # ── Guardar ──────────────────────────────────────────────────────
    # No usamos tight_layout (incompatible con plt.axes sub-axes).
    # subplots_adjust ya fija los márgenes apropiados.
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    plt.savefig(output_path, dpi=dpi, facecolor=BG_COLOR, edgecolor='none')
    plt.close(fig)
    print(f"[OK] Diagrama guardado en: {output_path}", flush=True)


# ── Generación por clúster ───────────────────────────────────────────────

def _get_cluster_nodes(hub_nid: str, G: nx.Graph, node_map: dict) -> list:
    """
    BFS desde el hub incluyendo todos los nodos de acceso conectados (cadenas).
    Se detiene al llegar a otro hub (core/backbone/distribution), pero lo incluye
    como nodo terminal para mostrar la conexión inter-hub.
    """
    HUB_ROLES = {'core', 'backbone', 'distribution'}
    cluster   = {hub_nid}
    queue     = [hub_nid]

    while queue:
        current = queue.pop(0)
        for nb in G.neighbors(current):
            if nb in cluster:
                continue
            cluster.add(nb)
            nb_role = node_map.get(nb, {}).get('role', 'access')
            if nb_role not in HUB_ROLES:   # expandir solo desde accesos
                queue.append(nb)

    return list(cluster)


def generate_cluster_image(hub_nid: str, G: nx.Graph, node_map: dict,
                            edges_data: list, output_path: str,
                            dpi: int = 150, icons_dir: str = ICONS_DIR) -> dict:
    """
    Genera un PNG detallado de un hub y su clúster de nodos conectados.
    Etiquetas completas (nombre, IP, puertos) y etiquetas de puerto en aristas.
    Retorna dict con metadatos del clúster.
    """
    cluster_nids = _get_cluster_nodes(hub_nid, G, node_map)
    SG           = G.subgraph(cluster_nids)

    n_cluster = len(cluster_nids)
    hub_info  = node_map.get(hub_nid, {})
    hub_label = hub_info.get('label', hub_nid)
    hub_role  = hub_info.get('role', 'backbone')

    # ── Layout: hub al centro, spring para el resto ───────────────────
    direct = [n for n in SG.neighbors(hub_nid)]
    n_dir  = max(1, len(direct))
    r1     = max(1.5, n_dir * 0.20)

    init_pos = {hub_nid: (0.0, 0.0)}
    for i, nb in enumerate(direct):
        angle       = 2 * math.pi * i / n_dir
        init_pos[nb] = (r1 * math.cos(angle), r1 * math.sin(angle))

    if n_cluster > 1:
        pos = nx.spring_layout(SG, k=1.2, seed=42, iterations=80,
                               fixed=[hub_nid], pos=init_pos)
    else:
        pos = init_pos

    # ── Límites del layout ────────────────────────────────────────────
    all_x = [p[0] for p in pos.values()]
    all_y = [p[1] for p in pos.values()]
    x_min, x_max = min(all_x), max(all_x)
    y_min, y_max = min(all_y), max(all_y)
    pad  = max(0.8, max(x_max - x_min, y_max - y_min) * 0.14)

    x_range = (x_max - x_min) + 2 * pad
    y_range = (y_max - y_min) + 2 * pad

    SCALE = 3.8
    dim   = max(12, min(36, max(x_range, y_range) * SCALE))
    fig_w, fig_h = dim, dim

    # ── Figura ────────────────────────────────────────────────────────
    fig, ax = plt.subplots(figsize=(fig_w, fig_h))
    fig.subplots_adjust(left=0.03, right=0.97, top=0.93, bottom=0.04)
    ax.set_facecolor(BG_COLOR)
    fig.patch.set_facecolor(BG_COLOR)
    ax.axis('off')
    ax.set_xlim(x_min - pad, x_max + pad)
    ax.set_ylim(y_min - pad, y_max + pad)

    # Título con nombre del hub y cantidad de equipos
    fig.text(0.5, 0.97, f'Clúster: {hub_label}  ({n_cluster} equipos)',
             ha='center', va='top', fontsize=11, fontweight='bold', color='#1E293B')

    # ── Tamaño de ícono y margen ──────────────────────────────────────
    base_icon_frac = max(0.018, min(0.055,
                         0.28 * math.sqrt(fig_w * fig_h / max(1, n_cluster)) / fig_w))
    icon_radius_px = base_icon_frac * fig_h * dpi / 2
    edge_margin_px = max(10, int(icon_radius_px * 0.85))

    # ── Aristas con FancyArrowPatch ───────────────────────────────────
    nx.draw_networkx_edges(
        SG, pos=pos, ax=ax,
        arrows=True, arrowstyle='-',
        edge_color=EDGE_COLOR, width=1.2, alpha=0.80,
        min_source_margin=edge_margin_px,
        min_target_margin=edge_margin_px,
    )

    # Etiquetas de puerto en todas las aristas (detalle completo)
    for e in edges_data:
        fid, tid = e.get('from'), e.get('to')
        if fid not in pos or tid not in pos:
            continue
        draw_port_labels(
            ax,
            pos[fid][0], pos[fid][1],
            pos[tid][0], pos[tid][1],
            e.get('src_port', ''), e.get('dst_port', ''),
            offset=0.12,
        )

    # ── Íconos plt.axes() + etiquetas fig.text() ─────────────────────
    fig.canvas.draw()
    tr_fig  = ax.transData.transform
    tr_axes = fig.transFigure.inverted().transform

    for nid in cluster_nids:
        if nid not in pos:
            continue
        n       = node_map.get(nid, {})
        role    = n.get('role', 'access')
        stacked = n.get('is_stacked', False)
        style   = ROLE_STYLE.get(role, ROLE_STYLE['access'])
        # Hub propio ligeramente más grande
        sz_mult  = style['size'] * (1.25 if nid == hub_nid else 1.0)
        icon_arr = get_role_icon(role, stacked, icons_dir)

        x, y   = pos[nid]
        xd, yd = tr_fig((x, y))
        xa, ya = tr_axes((xd, yd))
        icon_sz = base_icon_frac * sz_mult

        if icon_arr is not None:
            a = plt.axes([xa - icon_sz/2, ya - icon_sz/2, icon_sz, icon_sz],
                         facecolor='none')
            a.imshow(icon_arr)
            a.axis('off')

            if stacked:
                bsz = max(0.007, icon_sz * 0.30)
                bx  = xa + icon_sz * 0.40
                by  = ya + icon_sz * 0.40
                bd  = plt.axes([bx - bsz/2, by - bsz/2, bsz, bsz])
                bd.set_facecolor('#F59E0B')
                bd.text(0.5, 0.5, 'S', ha='center', va='center',
                        fontsize=4, fontweight='bold', color='white',
                        transform=bd.transAxes)
                bd.axis('off')
        else:
            draw_node(ax, x, y, n, icons_dir)
            continue

        # Etiqueta completa: nombre, IP, puertos activos
        label      = n.get('label', '') or ''
        ip         = n.get('ip') or ''
        port_count = n.get('active_port_count', 0)
        lbl_y      = ya - icon_sz * 0.56
        step       = 0.014

        fig.text(xa, lbl_y, label,
                 ha='center', va='top', fontsize=max(6.0, 9.0), fontweight='bold',
                 color=LABEL_COLOR,
                 path_effects=[withStroke(linewidth=2, foreground='white')])
        cur_y = lbl_y - step

        if ip:
            fig.text(xa, cur_y, ip, ha='center', va='top',
                     fontsize=8.0, color='#6B7280',
                     path_effects=[withStroke(linewidth=2, foreground='white')])
            cur_y -= step * 0.85

        if port_count:
            fig.text(xa, cur_y, f'{port_count} puertos', ha='center', va='top',
                     fontsize=7.0, color='#4B5563',
                     path_effects=[withStroke(linewidth=2, foreground='white')])
            cur_y -= step * 0.85

        if stacked:
            slots = n.get('slot_count', 1)
            fig.text(xa, cur_y, f'Stack ({slots})', ha='center', va='top',
                     fontsize=7.0, color='#B45309',
                     path_effects=[withStroke(linewidth=2, foreground='white')])

    # ── Guardar ──────────────────────────────────────────────────────
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    plt.savefig(output_path, dpi=dpi, facecolor=BG_COLOR, edgecolor='none')
    plt.close(fig)

    return {
        'hub_id'    : hub_nid,
        'hub_label' : hub_label,
        'hub_role'  : hub_role,
        'node_count': n_cluster,
        'image_path': output_path,
    }


def generate_all_clusters(input_path: str, output_dir: str,
                           dpi: int = 150, icons_dir: str = ICONS_DIR) -> list:
    """
    Lee el JSON de topología y genera un PNG de clúster por cada hub
    (core / backbone / distribution).
    Retorna lista de dicts con metadatos de cada clúster.
    """
    HUB_ROLES = {'core', 'backbone', 'distribution'}

    with open(input_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    nodes      = data.get('nodes', [])
    edges_data = data.get('edges', [])

    G        = nx.Graph()
    node_map = {}
    for n in nodes:
        G.add_node(n['id'])
        node_map[n['id']] = n
    for e in edges_data:
        if e['from'] in node_map and e['to'] in node_map:
            G.add_edge(e['from'], e['to'], **e)

    hub_nids = [n['id'] for n in nodes if n.get('role', 'access') in HUB_ROLES]

    os.makedirs(output_dir, exist_ok=True)
    results = []

    for hub_nid in hub_nids:
        hub_label = node_map[hub_nid].get('label', hub_nid)
        safe_name = re.sub(r'[^\w\-]', '_', hub_label)
        out_path  = os.path.join(output_dir, f'cluster_{safe_name}.png')

        print(f"[INFO] Cluster: {hub_label} -> {out_path}", flush=True)
        info = generate_cluster_image(
            hub_nid, G, node_map, edges_data, out_path, dpi, icons_dir
        )
        results.append(info)
        print(f"[OK]   {hub_label}: {info['node_count']} equipos", flush=True)

    return results


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Genera PNG de topologia de red')
    parser.add_argument('input',  help='Ruta al archivo JSON de topologia')
    parser.add_argument('output', help='Ruta de salida del PNG global')
    parser.add_argument('--dpi',      type=int, default=150,
                        help='Resolucion DPI (default 150)')
    parser.add_argument('--icons',    default=ICONS_DIR,
                        help='Directorio con los PNG de iconos')
    parser.add_argument('--clusters', metavar='DIR', default=None,
                        help='Si se indica, genera ademas PNGs de cluster por hub '
                             'en este directorio y emite JSON con los metadatos')
    args = parser.parse_args()

    generate(args.input, args.output, dpi=args.dpi, icons_dir=args.icons)

    if args.clusters:
        results = generate_all_clusters(
            args.input, args.clusters, dpi=args.dpi, icons_dir=args.icons
        )
        # Emitir JSON a stdout para que PHP pueda parsear los resultados
        print(json.dumps(results, ensure_ascii=False))
