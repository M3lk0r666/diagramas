/**
 * IVE — Layout Engine (Fase 6)
 *
 * Responsabilidad única: asignar posiciones 3D a cada DeviceNode
 * y dimensiones (bounds) a cada AreaNode.
 *
 * Algoritmos:
 *   - 'grid'   → Grid isométrico, Core primero  (Fase 3)
 *   - 'radial' → Capas concéntricas por rol      (Fase 6, default)
 *
 * API pública:
 *   applyLayout(graph, mode)   — muta graph en su lugar
 *   computeSceneCenter(graph)  — centro geométrico de todas las áreas
 *   computeFitZoom(graph)      — zoom inicial para encuadrar la escena
 */

// ── Constantes comunes ────────────────────────────────────────────────────────

const AREA_GAP     = 20   // Separación entre áreas (unidades mundo)
const AREA_PADDING =  6   // Espacio entre el borde del área y el último dispositivo

// ── Constantes — Grid ─────────────────────────────────────────────────────────

const DEVICE_SPACING  = 8
const DEVICES_PER_ROW = 5

const ROLE_PRIORITY = { core: 0, backbone: 1, distribution: 2, access: 3, unknown: 4 }

// ── Constantes — Radial ───────────────────────────────────────────────────────

const RADIAL_DEVICE_SPACING = 6   // Arco mínimo entre centros de dispositivos en un anillo
const RADIAL_MIN_RADIUS     = 8   // Radio mínimo de cualquier capa (evita colapso)
const RADIAL_RING_GAP       = 5   // Espacio adicional entre capas concéntricas

// Capa concéntrica por rol (0 = centro, 3 = periferia)
const ROLE_LAYER = { core: 0, backbone: 1, distribution: 2, access: 3, unknown: 3 }

// ═════════════════════════════════════════════════════════════════════════════
//  GRID LAYOUT
// ═════════════════════════════════════════════════════════════════════════════

function computeAreaSizeGrid(count) {
    if (count === 0) return { width: AREA_PADDING * 2, depth: AREA_PADDING * 2 }
    const cols = Math.min(count, DEVICES_PER_ROW)
    const rows = Math.ceil(count / DEVICES_PER_ROW)
    return {
        width: cols * DEVICE_SPACING + AREA_PADDING * 2,
        depth: rows * DEVICE_SPACING + AREA_PADDING * 2,
    }
}

function computeAreaOriginGrid(index, areas) {
    const cols = Math.ceil(Math.sqrt(areas.length))
    const col  = index % cols
    const row  = Math.floor(index / cols)

    let x = 0
    for (let c = 0; c < col; c++) {
        const idx = row * cols + c
        if (idx < areas.length) x += computeAreaSizeGrid(areas[idx].devices.length).width + AREA_GAP
    }

    let z = 0
    for (let r = 0; r < row; r++) {
        let maxDepth = 0
        for (let c = 0; c < cols; c++) {
            const idx = r * cols + c
            if (idx < areas.length) {
                maxDepth = Math.max(maxDepth, computeAreaSizeGrid(areas[idx].devices.length).depth)
            }
        }
        z += maxDepth + AREA_GAP
    }

    return [x, z]
}

function applyGridLayout(sceneGraph) {
    const { areas } = sceneGraph

    areas.forEach((area, i) => {
        const { width, depth } = computeAreaSizeGrid(area.devices.length)
        const [ax, az]         = computeAreaOriginGrid(i, areas)

        area.bounds = { x: ax, z: az, width, depth }
        if (!area.devices.length) return

        const sorted  = [...area.devices].sort(
            (a, b) => (ROLE_PRIORITY[a.role] ?? 4) - (ROLE_PRIORITY[b.role] ?? 4)
        )
        const cols    = Math.min(sorted.length, DEVICES_PER_ROW)
        const rows    = Math.ceil(sorted.length / cols)
        const centerX = ax + width  / 2
        const centerZ = az + depth  / 2

        sorted.forEach((device, j) => {
            device.position = [
                centerX + (j % cols - (cols - 1) / 2) * DEVICE_SPACING,
                0,
                centerZ + (Math.floor(j / cols) - (rows - 1) / 2) * DEVICE_SPACING,
            ]
        })
    })

    console.info('[IVE LayoutEngine] Grid →', areas.length, 'areas')
    return sceneGraph
}

// ═════════════════════════════════════════════════════════════════════════════
//  RADIAL LAYOUT
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Calcula el radio exterior de un área en layout radial (no muta nada).
 */
function computeRadiusForArea(devices) {
    if (!devices.length) return AREA_PADDING

    // Contar dispositivos por capa
    const layerCount = new Map()
    devices.forEach(d => {
        const l = ROLE_LAYER[d.role] ?? 3
        layerCount.set(l, (layerCount.get(l) ?? 0) + 1)
    })

    const layers = [...layerCount.entries()].sort(([a], [b]) => a - b)
    let r = 0

    layers.forEach(([, count], i) => {
        if (i === 0 && count === 1) {
            r = RADIAL_MIN_RADIUS
        } else {
            const minR = (count * RADIAL_DEVICE_SPACING) / (2 * Math.PI)
            r = Math.max(r + RADIAL_RING_GAP, minR, RADIAL_MIN_RADIUS)
            r += RADIAL_DEVICE_SPACING / 2
        }
    })

    return r + AREA_PADDING
}

/**
 * Distribuye los dispositivos en capas concéntricas centradas en (cx, cz).
 * MUTA device.position.
 */
function placeDevicesRadial(devices, cx, cz) {
    // Agrupar por capa
    const layerMap = new Map()
    devices.forEach(d => {
        const l = ROLE_LAYER[d.role] ?? 3
        if (!layerMap.has(l)) layerMap.set(l, [])
        layerMap.get(l).push(d)
    })

    const layers = [...layerMap.entries()].sort(([a], [b]) => a - b)
    let r = 0

    layers.forEach(([, group], i) => {
        if (i === 0 && group.length === 1) {
            // Único dispositivo en capa 0 → centro exacto
            group[0].position = [cx, 0, cz]
            r = RADIAL_MIN_RADIUS
        } else {
            const minR = (group.length * RADIAL_DEVICE_SPACING) / (2 * Math.PI)
            r = Math.max(r + RADIAL_RING_GAP, minR, RADIAL_MIN_RADIUS)

            const step = (2 * Math.PI) / group.length
            // Capas alternas desfasadas media posición para evitar alineaciones radiales
            const offset = i % 2 === 0 ? -Math.PI / 2 : -Math.PI / 2 + step / 2

            group.forEach((device, j) => {
                const angle = j * step + offset
                device.position = [
                    cx + Math.cos(angle) * r,
                    0,
                    cz + Math.sin(angle) * r,
                ]
            })

            r += RADIAL_DEVICE_SPACING / 2
        }
    })
}

function applyRadialLayout(sceneGraph) {
    const { areas } = sceneGraph

    // 1ª pasada: calcular radio de cada área
    const radii = areas.map(area => computeRadiusForArea(area.devices))

    // Cuadrícula de centros de área
    const cols    = Math.ceil(Math.sqrt(areas.length))
    const numRows = Math.ceil(areas.length / cols)

    // Radio máximo por columna y por fila → determina espaciado
    const colMaxR = new Array(cols).fill(0)
    const rowMaxR = new Array(numRows).fill(0)
    areas.forEach((_, i) => {
        colMaxR[i % cols]             = Math.max(colMaxR[i % cols],             radii[i])
        rowMaxR[Math.floor(i / cols)] = Math.max(rowMaxR[Math.floor(i / cols)], radii[i])
    })

    // Centro X acumulado por columna
    const colCX = new Array(cols).fill(0)
    let cumX = 0
    for (let c = 0; c < cols; c++) {
        colCX[c] = cumX + colMaxR[c]
        cumX    += colMaxR[c] * 2 + AREA_GAP
    }

    // Centro Z acumulado por fila
    const rowCZ = new Array(numRows).fill(0)
    let cumZ = 0
    for (let r = 0; r < numRows; r++) {
        rowCZ[r] = cumZ + rowMaxR[r]
        cumZ    += rowMaxR[r] * 2 + AREA_GAP
    }

    // 2ª pasada: asignar bounds y colocar dispositivos
    areas.forEach((area, i) => {
        const col = i % cols
        const row = Math.floor(i / cols)
        const cx  = colCX[col]
        const cz  = rowCZ[row]
        const r   = radii[i]

        // bounds incluye radius → AreaPlane puede renderizar círculo
        area.bounds = { x: cx - r, z: cz - r, width: r * 2, depth: r * 2, radius: r }

        if (!area.devices.length) return

        const sorted = [...area.devices].sort(
            (a, b) => (ROLE_LAYER[a.role] ?? 3) - (ROLE_LAYER[b.role] ?? 3)
        )
        placeDevicesRadial(sorted, cx, cz)
    })

    console.info('[IVE LayoutEngine] Radial →', areas.length, 'areas')
    return sceneGraph
}

// ═════════════════════════════════════════════════════════════════════════════
//  API PÚBLICA
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Aplica el layout indicado al SceneGraph.
 * MUTA device.position y area.bounds.
 *
 * @param {import('../core/types/scene').SceneGraph} sceneGraph
 * @param {'grid'|'radial'} [mode='radial']
 */
export function applyLayout(sceneGraph, mode = 'radial') {
    return mode === 'grid'
        ? applyGridLayout(sceneGraph)
        : applyRadialLayout(sceneGraph)
}

/**
 * Centro geométrico de todas las áreas (target de OrbitControls).
 * Requiere applyLayout() previo.
 *
 * @param {import('../core/types/scene').SceneGraph} graph
 * @returns {[number, number, number]}
 */
export function computeSceneCenter(graph) {
    const areas = graph.areas.filter(a => a.bounds)
    if (!areas.length) return [0, 0, 0]
    const maxX = Math.max(...areas.map(a => a.bounds.x + a.bounds.width))
    const maxZ = Math.max(...areas.map(a => a.bounds.z + a.bounds.depth))
    return [maxX / 2, 0, maxZ / 2]
}

/**
 * Zoom inicial para encuadrar toda la escena.
 *
 * Usa la proyección isométrica real (cámara en dirección [-1,-1,-1]/√3):
 *   anchura aparente en pantalla = (maxX + maxZ) / √2
 *
 * Calibrado para canvas ~1920 px de ancho.
 * CameraControls refina este valor con el tamaño real del canvas
 * al montar el primer sceneGraph (efecto [sceneGraph]).
 *
 * @param {import('../core/types/scene').SceneGraph} graph
 * @returns {number}
 */
export function computeFitZoom(graph) {
    const areas = graph.areas.filter(a => a.bounds)
    if (!areas.length) return 10
    const maxX      = Math.max(...areas.map(a => a.bounds.x + a.bounds.width))
    const maxZ      = Math.max(...areas.map(a => a.bounds.z + a.bounds.depth))
    // Extensión proyectada en la pantalla isométrica
    const projected = Math.max((maxX + maxZ) / Math.SQRT2, 10)
    const zoom      = Math.max(2, Math.min(20, 1500 / projected))
    console.info(`[IVE Layout] Projected: ${projected.toFixed(0)} u → zoom: ${zoom.toFixed(1)}`)
    return zoom
}
