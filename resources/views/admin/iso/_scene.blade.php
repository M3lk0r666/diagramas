<script>
(function () {
'use strict';

// ── Role metadata ─────────────────────────────────────────────────────────────
const ROLE_COLORS = { core:0xEF4444, backbone:0xF97316, distribution:0x8B5CF6, access:0x3B82F6 };
const ROLE_LABELS = { core:'Core', backbone:'Backbone', distribution:'Distribución', access:'Acceso' };

// ── Layout constants ──────────────────────────────────────────────────────────
const NODE_SPACING = 6;   // world units between node centers within a plane
const PLANE_PAD    = 5;   // padding around nodes inside a plane
const BATCH_GAP    = 12;  // gap between batch planes
const NODE_Y       = 1.8; // height of sprites above the plane
const INTER_Y      = 4.5; // height of inter-area connection lines
const MAX_ROW      = 6;   // max nodes per row inside a batch
const SPRITE_SCALE = 3.4;

// ── Scene state ───────────────────────────────────────────────────────────────
let scene, camera, renderer, raycaster;
let frustumSize  = 100;
let cameraTarget = new THREE.Vector3();
let isPanning    = false;
let lastMouse    = { x: 0, y: 0 };
let showLabels   = true;

const sprites   = [];  // THREE.Sprite objects (for raycasting)
const labelDivs = [];  // { el, pos3d }  — HTML labels synced each frame
let hoveredSprite = null;

// switchId → world { x, y, z }
const swPos  = {};
// switchId → { sw, batchName, batchColor }
const swMeta = {};

// Plane y=0 used for orthographic pan hit-testing
const _groundPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);

// ── Init ──────────────────────────────────────────────────────────────────────
function init() {
    const wrapper = document.getElementById('iso-wrapper');
    const canvas  = document.getElementById('iso-canvas');
    let W = wrapper.clientWidth;
    let H = wrapper.clientHeight;

    // Renderer
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
    renderer.setSize(W, H);
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.setClearColor(0xf1f5f9);

    // Scene
    scene = new THREE.Scene();
    scene.add(new THREE.AmbientLight(0xffffff, 0.95));
    const dl = new THREE.DirectionalLight(0xffffff, 0.4);
    dl.position.set(10, 20, 10);
    scene.add(dl);

    // Grid floor
    const grid = new THREE.GridHelper(2000, 400, 0xd1d5db, 0xe5e7eb);
    grid.position.y = -0.35;
    scene.add(grid);

    raycaster = new THREE.Raycaster();
    initCamera(W, H);

    // Pre-index switch metadata
    ISO_DATA.batches.forEach(b => b.switches.forEach(sw => {
        swMeta[sw.id] = { sw, batchName: b.name, batchColor: b.color };
    }));

    buildScene();
    fitCamera();
    setupControls(wrapper, canvas);

    window.addEventListener('resize', () => {
        W = wrapper.clientWidth;
        H = wrapper.clientHeight;
        renderer.setSize(W, H);
        updateFrustum(W, H);
    });

    // Render loop — also auto-corrects canvas size each frame so that if init()
    // ran before the browser finished laying out the page (height = 0 or partial),
    // the canvas self-heals on the next animation frame.
    let _lastW = 0, _lastH = 0;
    (function loop() {
        requestAnimationFrame(loop);
        const W = wrapper.clientWidth;
        const H = wrapper.clientHeight;
        if (W > 0 && H > 0 && (W !== _lastW || H !== _lastH)) {
            _lastW = W; _lastH = H;
            renderer.setSize(W, H);
            updateFrustum(W, H);
        }
        renderer.render(scene, camera);
        syncLabels();
    })();
}

// ── Camera ────────────────────────────────────────────────────────────────────
function initCamera(W, H) {
    const a = W / H;
    camera = new THREE.OrthographicCamera(
        -frustumSize * a / 2,  frustumSize * a / 2,
         frustumSize / 2,     -frustumSize / 2,
         0.1, 5000
    );
    pushCamPos();
}

function updateFrustum(W, H) {
    const a = W / H;
    camera.left   = -frustumSize * a / 2;
    camera.right  =  frustumSize * a / 2;
    camera.top    =  frustumSize / 2;
    camera.bottom = -frustumSize / 2;
    camera.updateProjectionMatrix();
}

function pushCamPos() {
    const d = frustumSize * 0.75;
    camera.position.set(cameraTarget.x + d, cameraTarget.y + d, cameraTarget.z + d);
    camera.lookAt(cameraTarget);
}

function fitCamera() {
    // Compute bounds from actual switch positions — NOT scene.setFromObject() which
    // includes the 2000-unit GridHelper and completely skews center + frustumSize.
    const positions = Object.values(swPos);
    if (positions.length) {
        let minX = Infinity, maxX = -Infinity, minZ = Infinity, maxZ = -Infinity;
        positions.forEach(p => {
            minX = Math.min(minX, p.x); maxX = Math.max(maxX, p.x);
            minZ = Math.min(minZ, p.z); maxZ = Math.max(maxZ, p.z);
        });
        cameraTarget.set((minX + maxX) / 2, 0, (minZ + maxZ) / 2);
        const spanX = maxX - minX + PLANE_PAD * 4;
        const spanZ = maxZ - minZ + PLANE_PAD * 4;
        frustumSize = Math.max(40, Math.min(600, Math.max(spanX, spanZ) * 1.2));
    } else {
        // Fallback: traverse scene but skip the GridHelper
        const box = new THREE.Box3();
        scene.traverse(obj => { if (!obj.isGridHelper && obj.geometry) box.expandByObject(obj); });
        if (box.isEmpty()) return;
        const c = new THREE.Vector3(); box.getCenter(c);
        cameraTarget.set(c.x, 0, c.z);
        const sz = new THREE.Vector3(); box.getSize(sz);
        frustumSize = Math.max(40, Math.min(600, Math.max(sz.x, sz.z) * 1.2));
    }
    updateFrustum(renderer.domElement.clientWidth, renderer.domElement.clientHeight);
    pushCamPos();
}

// ── Scene build ───────────────────────────────────────────────────────────────
function buildScene() {
    const loader  = new THREE.TextureLoader();
    const layouts = ISO_DATA.batches.map(b => layoutBatch(b.switches));
    const origins = placeBatches(layouts);

    ISO_DATA.batches.forEach((batch, i) => {
        const { bx, bz }             = origins[i];
        const { positions, planeW, planeD } = layouts[i];

        drawPlane(batch, bx, bz, planeW, planeD);

        batch.switches.forEach(sw => {
            const lp = positions[sw.id];
            if (!lp) return;
            const wx = bx + lp.lx;
            const wz = bz + lp.lz;
            swPos[sw.id] = { x: wx, y: NODE_Y, z: wz };
            drawNode(sw, batch, wx, wz, loader);
        });

        batch.connections.forEach(c => {
            const fp = swPos[c.src_id];
            const tp = swPos[c.dst_id];
            if (fp && tp) drawEdge(c, fp, tp, false);
        });
    });

    // Inter-area connections (drawn last so all swPos are populated)
    ISO_DATA.inter_area_connections.forEach(c => {
        const fp = swPos[c.src_id];
        const tp = swPos[c.dst_id];
        if (fp && tp) drawEdge(c, fp, tp, true);
    });
}

// ── Layout algorithms ─────────────────────────────────────────────────────────
function layoutBatch(switches) {
    const ORDER  = ['core', 'backbone', 'distribution', 'access'];
    const groups = { core: [], backbone: [], distribution: [], access: [] };
    switches.forEach(sw => (groups[sw.role] || groups.access).push(sw));

    const rows = [];
    ORDER.forEach(role => {
        if (!groups[role].length) return;
        for (let i = 0; i < groups[role].length; i += MAX_ROW) {
            rows.push(groups[role].slice(i, i + MAX_ROW));
        }
    });

    if (!rows.length) {
        return { positions: {}, planeW: PLANE_PAD * 2, planeD: PLANE_PAD * 2 };
    }

    const maxCols = rows.reduce((m, r) => Math.max(m, r.length), 0);
    const planeW  = (maxCols - 1) * NODE_SPACING + PLANE_PAD * 2;
    const planeD  = (rows.length - 1) * NODE_SPACING + PLANE_PAD * 2;
    const positions = {};

    rows.forEach((row, ri) => {
        const rowW   = (row.length - 1) * NODE_SPACING;
        const startX = (planeW - rowW) / 2;
        row.forEach((sw, ci) => {
            positions[sw.id] = {
                lx: startX + ci * NODE_SPACING,
                lz: PLANE_PAD + ri * NODE_SPACING,
            };
        });
    });

    return { positions, planeW, planeD };
}

function placeBatches(layouts) {
    const n = layouts.length;
    if (!n) return [];
    const cols = Math.max(1, Math.ceil(Math.sqrt(n)));
    const cw   = new Array(cols).fill(0);
    const rd   = [];

    layouts.forEach((l, i) => {
        const c = i % cols;
        const r = Math.floor(i / cols);
        cw[c]   = Math.max(cw[c], l.planeW);
        rd[r]   = Math.max(rd[r] || 0, l.planeD);
    });

    const cx = [0];
    for (let i = 1; i < cols; i++) cx[i] = cx[i - 1] + cw[i - 1] + BATCH_GAP;
    const rz = [0];
    for (let i = 1; i < rd.length; i++) rz[i] = rz[i - 1] + rd[i - 1] + BATCH_GAP;

    return layouts.map((_, i) => ({
        bx: cx[i % cols],
        bz: rz[Math.floor(i / cols)],
    }));
}

// ── Draw helpers ──────────────────────────────────────────────────────────────
function drawPlane(batch, bx, bz, pw, pd) {
    const col = new THREE.Color(batch.color);
    const geo = new THREE.BoxGeometry(pw, 0.22, pd);

    const mesh = new THREE.Mesh(geo,
        new THREE.MeshPhongMaterial({ color: col, transparent: true, opacity: 0.18, depthWrite: false })
    );
    mesh.position.set(bx + pw / 2, -0.11, bz + pd / 2);
    scene.add(mesh);

    const edges = new THREE.LineSegments(
        new THREE.EdgesGeometry(geo),
        new THREE.LineBasicMaterial({ color: col, transparent: true, opacity: 0.75 })
    );
    edges.position.copy(mesh.position);
    scene.add(edges);

    mkLabel(batch.name, new THREE.Vector3(bx + pw / 2, 0.5, bz + 0.8), 'area', batch.color);
}

function drawNode(sw, batch, wx, wz, loader) {
    const rc  = ROLE_COLORS[sw.role] ?? ROLE_COLORS.access;
    const hex = '#' + rc.toString(16).padStart(6, '0');

    const spr = new THREE.Sprite(
        new THREE.SpriteMaterial({ map: loader.load(ICON_BASE + sw.icon), transparent: true })
    );
    spr.scale.setScalar(SPRITE_SCALE);
    spr.position.set(wx, NODE_Y, wz);
    spr.userData = { sw, batchName: batch.name, batchColor: batch.color, rc, base: SPRITE_SCALE };
    scene.add(spr);
    sprites.push(spr);

    // Coloured disc shadow
    const disc = new THREE.Mesh(
        new THREE.CircleGeometry(1.5, 24),
        new THREE.MeshBasicMaterial({ color: rc, side: THREE.DoubleSide, transparent: true, opacity: 0.3 })
    );
    disc.rotation.x = -Math.PI / 2;
    disc.position.set(wx, 0.01, wz);
    scene.add(disc);

    mkLabel(sw.sys_name || ('SW-' + sw.id), new THREE.Vector3(wx, NODE_Y + 2.1, wz), 'node', hex);
}

function drawEdge(conn, fp, tp, inter) {
    const rc  = inter ? 0xF97316 : (ROLE_COLORS[swMeta[conn.src_id]?.sw?.role] ?? 0x94a3b8);
    const y1  = inter ? INTER_Y : fp.y;
    const y2  = inter ? INTER_Y : tp.y;
    const geo = new THREE.BufferGeometry().setFromPoints([
        new THREE.Vector3(fp.x, y1, fp.z),
        new THREE.Vector3(tp.x, y2, tp.z),
    ]);

    let line;
    if (inter) {
        const mat = new THREE.LineDashedMaterial({ color: rc, dashSize: 2.5, gapSize: 1.2 });
        line      = new THREE.Line(geo, mat);
        line.computeLineDistances();
    } else {
        line = new THREE.Line(geo, new THREE.LineBasicMaterial({ color: rc }));
    }
    scene.add(line);

    if (conn.src_port && conn.dst_port) {
        const mid = new THREE.Vector3(
            (fp.x + tp.x) / 2,
            Math.max(y1, y2) + 0.7,
            (fp.z + tp.z) / 2
        );
        mkLabel(conn.src_port + '↔' + conn.dst_port, mid, 'port', inter ? '#F97316' : '#64748b');
    }
}

// ── HTML labels ───────────────────────────────────────────────────────────────
function mkLabel(text, pos3d, type, color) {
    const el  = document.createElement('div');
    const fs  = type === 'area' ? '11px' : type === 'port' ? '9px' : '10px';
    const fw  = type === 'area' ? '700'  : type === 'port' ? '400' : '500';
    const tt  = type === 'area' ? 'uppercase' : 'none';
    const ls  = type === 'area' ? '.05em' : 'normal';
    const col = type === 'port' ? '#64748b' : (color || '#374151');

    el.style.cssText = [
        'position:absolute', 'pointer-events:none', 'white-space:nowrap',
        'transform:translate(-50%,-100%)',
        'background:rgba(255,255,255,.88)',
        'border-radius:3px', 'line-height:1.4',
        'border-left:2px solid ' + (color || '#94a3b8'),
        'padding:1px 5px',
        'font-size:' + fs,
        'font-weight:' + fw,
        'text-transform:' + tt,
        'letter-spacing:' + ls,
        'color:' + col,
    ].join(';');

    el.textContent = text;
    document.getElementById('iso-labels').appendChild(el);
    labelDivs.push({ el, pos3d });
}

function syncLabels() {
    // clientWidth/Height = CSS pixels (invariant to devicePixelRatio)
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;

    labelDivs.forEach(({ el, pos3d }) => {
        if (!showLabels) { el.style.display = 'none'; return; }
        const p = pos3d.clone().project(camera);
        if (p.z > 1) { el.style.display = 'none'; return; }
        const sx = (p.x * 0.5 + 0.5) * W;
        const sy = (-p.y * 0.5 + 0.5) * H;
        // Cull off-screen labels (overflow-hidden on wrapper clips the rest)
        if (sx < -50 || sx > W + 50 || sy < -30 || sy > H + 30) {
            el.style.display = 'none'; return;
        }
        el.style.display = 'block';
        el.style.left    = sx + 'px';
        el.style.top     = sy + 'px';
    });
}

// ── Controls ──────────────────────────────────────────────────────────────────
function setupControls(wrapper, canvas) {
    canvas.addEventListener('mousedown', e => {
        if (e.button !== 0) return;
        isPanning = true;
        lastMouse = { x: e.clientX, y: e.clientY };
        canvas.style.cursor = 'grabbing';
    });

    window.addEventListener('mouseup', () => {
        isPanning = false;
        canvas.style.cursor = 'default';
    });

    window.addEventListener('mousemove', e => {
        if (isPanning) {
            doPan(e.clientX - lastMouse.x, e.clientY - lastMouse.y, wrapper);
            lastMouse = { x: e.clientX, y: e.clientY };
        } else {
            doHover(e, wrapper, canvas);
        }
    });

    canvas.addEventListener('wheel', e => {
        e.preventDefault();
        doZoom(e.deltaY);
    }, { passive: false });

    canvas.addEventListener('click', e => {
        if (!isPanning) doClick(e, wrapper);
    });

    // Touch support
    let lastTouchDist = 0;
    canvas.addEventListener('touchstart', e => {
        if (e.touches.length === 1) {
            isPanning = true;
            lastMouse = { x: e.touches[0].clientX, y: e.touches[0].clientY };
        } else if (e.touches.length === 2) {
            lastTouchDist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
        }
    }, { passive: true });

    canvas.addEventListener('touchmove', e => {
        if (e.touches.length === 1 && isPanning) {
            doPan(e.touches[0].clientX - lastMouse.x, e.touches[0].clientY - lastMouse.y, wrapper);
            lastMouse = { x: e.touches[0].clientX, y: e.touches[0].clientY };
        } else if (e.touches.length === 2) {
            const dist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            doZoom((lastTouchDist - dist) * 2);
            lastTouchDist = dist;
        }
    }, { passive: true });

    canvas.addEventListener('touchend', () => { isPanning = false; });
}

function doPan(dx, dy, wrapper) {
    const r = wrapper.getBoundingClientRect();
    const W = r.width, H = r.height;

    // For OrthographicCamera, raycaster.setFromCamera sets a PARALLEL ray direction
    // (camera forward) with an offset origin per screen pixel. Manual unproject + normalize
    // was wrong because it mixed the lateral offset into the direction vector.
    function groundHit(sx, sy) {
        const ndc = new THREE.Vector2((sx / W) * 2 - 1, -(sy / H) * 2 + 1);
        raycaster.setFromCamera(ndc, camera);
        const hit = new THREE.Vector3();
        return raycaster.ray.intersectPlane(_groundPlane, hit) ? hit.clone() : null;
    }

    const p1 = groundHit(W / 2,      H / 2);
    const p2 = groundHit(W / 2 + dx, H / 2 + dy);
    if (!p1 || !p2) return;
    cameraTarget.sub(p2.sub(p1));
    pushCamPos();
}

function doZoom(deltaY) {
    // Clamp per-event delta so high-res trackpads don't zoom too fast in one step
    const clamped = Math.sign(deltaY) * Math.min(Math.abs(deltaY), 100);
    frustumSize = Math.max(20, Math.min(800, frustumSize * (1 + clamped * 0.001)));
    updateFrustum(renderer.domElement.clientWidth, renderer.domElement.clientHeight);
    pushCamPos();
}

function ndcOf(e, wrapper) {
    const r = wrapper.getBoundingClientRect();
    return new THREE.Vector2(
        ((e.clientX - r.left) / r.width) * 2 - 1,
        -((e.clientY - r.top) / r.height) * 2 + 1
    );
}

function doHover(e, wrapper, canvas) {
    raycaster.setFromCamera(ndcOf(e, wrapper), camera);
    const hits = raycaster.intersectObjects(sprites);
    const tip  = document.getElementById('iso-tooltip');

    if (hits.length) {
        const spr = hits[0].object;
        if (spr !== hoveredSprite) {
            if (hoveredSprite) hoveredSprite.scale.setScalar(hoveredSprite.userData.base);
            spr.scale.setScalar(spr.userData.base * 1.3);
            hoveredSprite = spr;
        }
        const sw = spr.userData.sw;
        tip.innerHTML =
            '<div style="font-weight:700;color:#111827;font-size:13px;margin-bottom:3px">' + (sw.sys_name || '—') + '</div>' +
            '<div style="color:#6366f1;font-size:11px">' + (ROLE_LABELS[sw.role] || sw.role) + '</div>' +
            (sw.management_ip ? '<div style="color:#374151;font-size:11px;margin-top:1px">IP: ' + sw.management_ip + '</div>' : '') +
            (sw.system_type   ? '<div style="color:#9ca3af;font-size:11px">' + sw.system_type + '</div>' : '');

        tip.style.cssText =
            'display:block;position:fixed;z-index:9999;' +
            'left:' + (e.clientX + 14) + 'px;top:' + (e.clientY - 8) + 'px;' +
            'background:#fff;border:1px solid #e5e7eb;border-radius:8px;' +
            'box-shadow:0 4px 16px rgba(0,0,0,.12);padding:8px 12px;' +
            'font-size:13px;min-width:150px;pointer-events:none;';
        canvas.style.cursor = 'pointer';
    } else {
        if (hoveredSprite) {
            hoveredSprite.scale.setScalar(hoveredSprite.userData.base);
            hoveredSprite = null;
        }
        tip.style.display = 'none';
        canvas.style.cursor = 'default';
    }
}

function doClick(e, wrapper) {
    raycaster.setFromCamera(ndcOf(e, wrapper), camera);
    const hits = raycaster.intersectObjects(sprites);
    if (hits.length) {
        const { sw, batchName } = hits[0].object.userData;
        openPanel(sw, batchName);
    }
}

// ── Side panel ────────────────────────────────────────────────────────────────
let panelIsOpen = false;

function setPanelState(open) {
    panelIsOpen = open;
    const panel = document.getElementById('iso-panel');
    const icon  = document.getElementById('iso-panel-toggle-icon');
    if (open) {
        panel.classList.remove('translate-x-full');
        if (icon) { icon.className = 'ri-layout-right-2-line'; }
    } else {
        panel.classList.add('translate-x-full');
        if (icon) { icon.className = 'ri-layout-right-line'; }
        // Reset sprite scales when panel is closed
        sprites.forEach(s => s.scale.setScalar(s.userData.base));
    }
}

function openPanel(sw, batchName) {
    const rc  = ROLE_COLORS[sw.role] ?? ROLE_COLORS.access;
    const hex = '#' + rc.toString(16).padStart(6, '0');
    const lbl = ROLE_LABELS[sw.role] || sw.role;

    const rows = [
        ['Área',       batchName],
        ['IP Gestión', sw.management_ip],
        ['Modelo',     sw.system_type],
        ['MAC',        sw.system_mac],
        ['Stacked',    sw.is_stacked ? 'Sí' : 'No'],
    ].filter(([, v]) => v);

    document.getElementById('iso-panel-body').innerHTML =
        '<div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:14px">' +
            '<img src="' + ICON_BASE + sw.icon + '" style="width:40px;height:40px;object-fit:contain" alt="">' +
            '<div>' +
                '<div style="font-weight:700;font-size:14px;color:#111827;line-height:1.3">' + (sw.sys_name || '—') + '</div>' +
                '<span style="display:inline-block;margin-top:4px;font-size:11px;padding:2px 9px;border-radius:20px;font-weight:500;' +
                      'background:' + hex + '22;color:' + hex + '">' + lbl + '</span>' +
            '</div>' +
        '</div>' +
        '<table style="width:100%;border-collapse:collapse;font-size:12px">' +
            rows.map(([k, v]) =>
                '<tr>' +
                    '<td style="color:#9ca3af;padding:4px 0;width:82px;vertical-align:top">' + k + '</td>' +
                    '<td style="color:#374151;font-family:monospace;font-size:11px;word-break:break-all">' + v + '</td>' +
                '</tr>'
            ).join('') +
        '</table>';

    setPanelState(true);

    sprites.forEach(s => s.scale.setScalar(
        s.userData.sw.id === sw.id ? s.userData.base * 1.4 : s.userData.base * 0.8
    ));
}

function closePanel() {
    setPanelState(false);
}

// ── Search ────────────────────────────────────────────────────────────────────
function doSearch() {
    const q  = (document.getElementById('iso-search-input').value || '').trim().toLowerCase();
    const st = document.getElementById('iso-search-status');
    if (!q) { st.textContent = ''; return; }

    const hit = sprites.find(s =>
        (s.userData.sw.sys_name   || '').toLowerCase().includes(q) ||
        (s.userData.sw.management_ip || '').includes(q)
    );

    if (!hit) {
        st.textContent = 'No encontrado';
        st.style.color = '#ef4444';
        return;
    }
    st.textContent = '';

    // Fly to node
    cameraTarget.set(hit.position.x, 0, hit.position.z);
    frustumSize = Math.min(frustumSize, 55);
    updateFrustum(renderer.domElement.clientWidth, renderer.domElement.clientHeight);
    pushCamPos();

    openPanel(hit.userData.sw, hit.userData.batchName);
}

// ── Public API (called from HTML buttons) ─────────────────────────────────────
window.isoReset = fitCamera;

window.isoToggleLabels = function () {
    showLabels = !showLabels;
    const btn = document.getElementById('btn-labels');
    if (btn) btn.textContent = showLabels ? 'Ocultar labels' : 'Mostrar labels';
};

window.isoSearch      = doSearch;
window.isoClosePanel  = closePanel;
window.isoPanelToggle = function () { setPanelState(!panelIsOpen); };

// ── Boot ──────────────────────────────────────────────────────────────────────
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    setTimeout(init, 0);
}

})();
</script>
