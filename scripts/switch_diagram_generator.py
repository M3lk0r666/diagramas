#!/usr/bin/env python3
"""
switch_diagram_generator.py
PNG diagram per switch.
Usage: python switch_diagram_generator.py <input.json> <output.png>
"""
import sys, os, json, math, re

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch
from PIL import Image
import numpy as np

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ICONS_DIR  = os.path.join(SCRIPT_DIR, 'icons')

ROLE_ICON = {
    'core':         'core_switch.png',
    'backbone':     'backbone_switch.png',
    'distribution': 'dist_switch.png',
    'access':       'access_switch.png',
    'stack':        'stack_switch.png',
}


def load_img(filename, size=None):
    path = os.path.join(ICONS_DIR, filename)
    if not os.path.exists(path):
        return None
    try:
        img = Image.open(path).convert('RGBA')
        if size:
            img = img.resize(size, Image.LANCZOS)
        return np.array(img)
    except Exception:
        return None


def detect_device_icon(desc):
    """Return icon filename for a non-DB neighbor based on display_string keywords."""
    d = (desc or '').lower()
    if 'router'           in d: return 'router.png'
    if 'firewall'         in d: return 'firewall.png'
    if 'wireless_controler' in d or 'wireless' in d: return 'wireless_controler.png'
    if 'acces_point' in d or 'access_point' in d or re.search(r'\bap\b', d): return 'acces_point.png'
    if 'modem'            in d: return 'modem.png'
    if 'server_torre'     in d or 'server torre' in d: return 'server_torre.png'
    if 'server_rack'      in d or 'server rack' in d or 'server' in d: return 'server_rack.png'
    if 'storage'          in d: return 'storage.png'
    if 'internet'         in d: return 'internet.png'
    if 'network_cloud'    in d or 'cloud' in d: return 'network_cloud.png'
    if 'pc_desktop'       in d or 'desktop' in d or re.search(r'\bpc\b', d): return 'pc_desktop.png'
    if 'laptop'           in d or 'notebook' in d: return 'laptop.png'
    if 'ip_phone'         in d or 'phone' in d or 'voip' in d: return 'ip_phone.png'
    if 'printer'          in d or 'impresora' in d: return 'printer.png'
    if 'security_camera'  in d or 'camera' in d or 'camara' in d: return 'security_camera.png'
    if 'vpn_conection'    in d or 'vpn' in d: return 'vpn_conection.png'
    if 'load_balancer'    in d or re.search(r'\blb\b', d): return 'load_balancer.png'
    return 'access_switch.png'


def detect_role(name):
    u = (name or '').upper()
    if 'CORE' in u: return 'core'
    if any(k in u for k in ('BB', 'BACKBONE', 'BLACKDIAMOND', 'BD')): return 'backbone'
    if any(k in u for k in ('DIST', 'DISTRIB')): return 'distribution'
    return 'access'


def detect_hw_icon(system_type):
    st = (system_type or '').upper()
    if '48' in st:
        c = 'switch-48p.png'
    elif '24' in st:
        c = 'switch-24p.png'
    else:
        return None
    return c if os.path.exists(os.path.join(ICONS_DIR, c)) else None


def abbrev(text, maxlen=20):
    if not text:
        return ''
    s = str(text)
    return s if len(s) <= maxlen else s[:maxlen - 1] + '...'


def main():
    if len(sys.argv) < 3:
        print("Usage: switch_diagram_generator.py <input.json> <output.png>", file=sys.stderr)
        sys.exit(1)

    with open(sys.argv[1], 'r', encoding='utf-8') as f:
        data = json.load(f)

    out_path  = sys.argv[2]
    sw        = data['switch']
    ports     = data.get('ports', [])
    vlans     = data.get('vlans', [])[:24]
    has_vlans = bool(vlans)

    fig_h = 14 if has_vlans else 9

    fig = plt.figure(figsize=(14, fig_h), dpi=150, facecolor='white')

    ratios = [0.9, 5.5, 3.6] if has_vlans else [0.9, 5.5]
    n_rows = 3 if has_vlans else 2
    gs = fig.add_gridspec(
        n_rows, 1, height_ratios=ratios,
        hspace=0.06, left=0.02, right=0.98, top=0.98, bottom=0.02,
    )
    ax_hdr  = fig.add_subplot(gs[0])
    ax_diag = fig.add_subplot(gs[1])
    for ax in (ax_hdr, ax_diag):
        ax.set_axis_off()

    if has_vlans:
        ax_vlan = fig.add_subplot(gs[2])
        ax_vlan.set_axis_off()

    draw_header(ax_hdr, sw)
    draw_diagram(ax_diag, sw, ports)
    if has_vlans:
        draw_vlan_table(ax_vlan, vlans, sw.get('gateway'))

    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    fig.savefig(out_path, dpi=150, bbox_inches='tight', facecolor='white')
    plt.close(fig)
    print("OK:" + out_path)


def draw_header(ax, sw):
    ax.set_xlim(0, 1)
    ax.set_ylim(0, 1)

    ax.add_patch(FancyBboxPatch(
        (0.005, 0.04), 0.99, 0.92,
        boxstyle='round,pad=0.02',
        linewidth=1.8, edgecolor='#93C5FD', facecolor='#EFF6FF', zorder=1,
    ))
    model    = sw.get('system_type')    or '-'
    sys_name = sw.get('sys_name')       or '-'
    mac      = sw.get('system_mac')     or '-'
    ip       = sw.get('management_ip')  or '-'

    # Row positions (top to bottom)
    ax.text(0.5, 0.80, model,
            ha='center', va='center', fontsize=12, fontweight='bold',
            color='#1E3A5F', zorder=2)
    ax.text(0.5, 0.57, sys_name,
            ha='center', va='center', fontsize=11,
            color='#1E40AF', zorder=2)
    ax.text(0.5, 0.34, "MAC: " + mac,
            ha='center', va='center', fontsize=9, color='#475569', zorder=2)
    ax.text(0.5, 0.14, "IP: " + ip,
            ha='center', va='center', fontsize=9, color='#475569', zorder=2)


def detect_center_icon(is_stacked, role):
    """Return (filename, use_hw_size) for the center switch image."""
    if is_stacked and role == 'core':
        return 'core-stack.png', False
    if is_stacked:
        return 'switch-stack.png', False
    if role == 'core':
        return 'core.png', False
    return None, True   # signal: use hw image fallback


def draw_diagram(ax, sw, ports):
    # Initial axis limits (will be recomputed based on documented port count)
    ax.set_xlim(-1.45, 1.45)
    ax.set_ylim(-1.45, 1.45)
    ax.set_aspect('equal')

    is_st  = sw.get('is_stacked', False)
    role   = sw.get('role') or detect_role(sw.get('sys_name', ''))

    center_file, use_hw = detect_center_icon(is_st, role)

    if use_hw:
        # Non-core, non-stack: prefer hardware photo (24p/48p), fall back to role icon
        hw_file   = detect_hw_icon(sw.get('system_type', ''))
        role_file = ROLE_ICON.get(role, ROLE_ICON['access'])
        hw_arr    = load_img(hw_file)   if hw_file   else None
        icon_arr  = load_img(role_file, size=(120, 120))
    else:
        hw_arr   = None
        icon_arr = load_img(center_file, size=(120, 120))

    icon_top = 0.50
    if hw_arr is not None:
        ih, iw  = hw_arr.shape[:2]
        iw_data = 0.86
        ih_data = iw_data * (ih / iw)
        ax.imshow(hw_arr,
                  extent=(-iw_data / 2, iw_data / 2, icon_top - ih_data, icon_top),
                  zorder=5, aspect='auto')
        cx, cy = 0.0, icon_top - ih_data
    elif icon_arr is not None:
        iw_data = 0.40
        ax.imshow(icon_arr,
                  extent=(-iw_data / 2, iw_data / 2, icon_top - iw_data, icon_top),
                  zorder=5, aspect='auto')
        cx, cy = 0.0, icon_top - iw_data
    else:
        cx, cy = 0.0, 0.0

    # Only documented ports go into the diagram
    doc_ports   = [p for p in ports if p.get('desc')]
    undoc_count = sum(1 for p in ports if not p.get('desc'))

    nd = len(doc_ports)
    if nd == 0 and undoc_count == 0:
        return

    # Recompute radii based on documented count only
    if nd > 0:
        r_near = max(0.74, min(0.88, nd * 0.010 + 0.60))
        r_far  = r_near + 0.34
        lim    = r_far + 0.32
        ax.set_xlim(-lim, lim)
        ax.set_ylim(-lim, lim)

    for i, port in enumerate(doc_ports):
        angle = math.pi / 2 + (2 * math.pi * i / max(nd, 1))
        r = r_near if i % 2 == 0 else r_far
        nx = r * math.cos(angle)
        ny = r * math.sin(angle)
        _draw_port_node(ax, cx, cy, nx, ny, port)

    # Legend badge for undocumented ports (bottom-right corner)
    if undoc_count > 0:
        corner_x = ax.get_xlim()[1] - 0.04
        corner_y = ax.get_ylim()[0] + 0.04
        label = str(undoc_count) + " puerto" + ("s" if undoc_count != 1 else "") + " activo" + ("s" if undoc_count != 1 else "") + "\nno documentado" + ("s" if undoc_count != 1 else "")
        ax.text(corner_x, corner_y, label,
                ha='right', va='bottom', fontsize=8,
                color='#6B7280', zorder=6,
                bbox=dict(boxstyle='round,pad=0.35', facecolor='#F9FAFB',
                          edgecolor='#D1D5DB', linewidth=1.2))


def _draw_port_node(ax, cx, cy, nx, ny, port):
    in_db         = port.get('in_db', False)
    desc          = port.get('desc') or ''
    port_num      = str(port.get('port', ''))
    is_documented = bool(desc)

    if is_documented:
        e_color = '#16A34A' if in_db else '#EA580C'
        ls_val  = '-'   # always solid — dashed removed
        box_w   = 0.38
    else:
        e_color = '#9CA3AF'
        ls_val  = (0, (3, 2))
        box_w   = 0.28

    fac = 0.78
    lx  = cx + (nx - cx) * fac
    ly  = cy + (ny - cy) * fac

    ax.plot([cx, lx], [cy, ly],
            color='#94A3B8', lw=1.2, zorder=1, solid_capstyle='round')

    # Port number label (~72% along the line, near destination)
    p1 = 0.72
    ax.text(cx + (lx - cx) * p1, cy + (ly - cy) * p1,
            port_num,
            ha='center', va='center', fontsize=8, fontweight='bold',
            color='#1E293B', zorder=3,
            bbox=dict(boxstyle='round,pad=0.20', facecolor='#FFFFFF',
                      edgecolor='#64748B', linewidth=1.1))

    if is_documented:
        if in_db:
            nb_role = detect_role(desc)
            nb_icon = load_img(ROLE_ICON.get(nb_role, ROLE_ICON['access']), size=(56, 56))
        else:
            nb_icon = load_img(detect_device_icon(desc), size=(56, 56))

        nb_model = abbrev(port.get('model') or '', 20)
        nb_ip    = port.get('ip') or ''
        lines    = [abbrev(desc, 20)]
        if nb_model and nb_model.upper() != desc.upper():
            lines.append(nb_model)
        if nb_ip:
            lines.append(nb_ip)

        icon_sz = 0.092   # display size in axes coords

        # No rectangle — just icon centered above, text label below
        if nb_icon is not None:
            ax.imshow(nb_icon,
                      extent=(nx - icon_sz / 2, nx + icon_sz / 2,
                               ny + 0.004, ny + 0.004 + icon_sz),
                      zorder=4, aspect='auto')
            txt_y = ny + 0.002
        else:
            txt_y = ny + icon_sz / 2

        ax.text(nx, txt_y, '\n'.join(lines),
                ha='center', va='top', fontsize=7,
                color='#1E293B', zorder=4, linespacing=1.3)

    else:
        nd_icon = load_img('port-not-data.png', size=(24, 24))
        box_h   = 0.09

        ax.add_patch(FancyBboxPatch(
            (nx - box_w / 2, ny - box_h / 2), box_w, box_h,
            boxstyle='round,pad=0.015',
            linewidth=1.0, edgecolor=e_color, linestyle=ls_val,
            facecolor='#F9FAFB', zorder=3,
        ))

        if nd_icon is not None:
            ix0 = nx - box_w / 2 + 0.010
            ax.imshow(nd_icon,
                      extent=(ix0, ix0 + 0.038,
                               ny - 0.019, ny + 0.019),
                      zorder=4, aspect='auto')
            txt_x = ix0 + 0.038 + 0.006 + (box_w - 0.038 - 0.016) / 2
        else:
            txt_x = nx

        ax.text(txt_x, ny, 'Sin docum.',
                ha='center', va='center', fontsize=6.5,
                color='#9CA3AF', zorder=4)


def draw_vlan_table(ax, vlans, gateway=None):
    ax.set_xlim(0, 1)
    ax.set_ylim(0, 1)
    y = 0.97

    if gateway:
        ax.add_patch(FancyBboxPatch(
            (0.01, y - 0.11), 0.24, 0.11,
            boxstyle='round,pad=0.015',
            facecolor='#16A34A', edgecolor='none',
        ))
        ax.text(0.13, y - 0.055, "Gateway: " + str(gateway),
                ha='center', va='center', fontsize=8.5,
                color='white', fontweight='bold')
        y -= 0.15

    headers  = ['ID', 'Nombre', 'IP / Mascara', 'Parametros']
    col_x    = [0.01, 0.09, 0.30, 0.67]
    col_w    = [0.08, 0.21, 0.37, 0.31]
    max_rows = min(len(vlans), 22)
    row_h    = min(0.090, (y - 0.03) / (max_rows + 1))

    for h, x, w in zip(headers, col_x, col_w):
        ax.add_patch(FancyBboxPatch(
            (x, y - row_h), w - 0.004, row_h,
            boxstyle='square,pad=0',
            facecolor='#1E3A5F', edgecolor='#1E3A5F', linewidth=0,
        ))
        ax.text(x + 0.010, y - row_h / 2, h,
                va='center', fontsize=8, color='white', fontweight='bold')
    y -= row_h

    for i, vlan in enumerate(vlans[:max_rows]):
        bg    = '#F0F9FF' if i % 2 == 0 else 'white'
        cells = [
            str(vlan.get('vid', '-')),
            str(vlan.get('name', '-')),
            str(vlan.get('ip') or ''),
            '',
        ]
        for val, x, w in zip(cells, col_x, col_w):
            ax.add_patch(FancyBboxPatch(
                (x, y - row_h), w - 0.004, row_h,
                boxstyle='square,pad=0',
                facecolor=bg, edgecolor='#E2E8F0', linewidth=0.5,
            ))
            ax.text(x + 0.010, y - row_h / 2,
                    abbrev(val, 34),
                    va='center', fontsize=7.5, color='#374151', clip_on=True)
        y -= row_h


if __name__ == '__main__':
    main()
