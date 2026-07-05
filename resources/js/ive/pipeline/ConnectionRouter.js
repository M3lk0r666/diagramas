/**
 * IVE — Connection Router (Fase 12)
 *
 * Transforma la topología plana del SceneGraph en rutas 3D renderizables.
 * Módulo puro: sin estado, sin React, sin side-effects.
 *
 * Estrategias (actualizadas en Fase 12):
 *   Intra-área  → Líneas RECTAS entre dispositivos del mismo área.
 *                 Color determinista por hash del ID de conexión (diferencia
 *                 visualmente conexiones que se solapan).
 *   Inter-área  → Líneas RECTAS entre las posiciones reales de los dispositivos
 *                 origen y destino (NO entre centros de área).
 *                 Una línea por conexión (NO agrega por par de áreas).
 *
 * Cada ruta incluye sourceId, targetId, sourcePort, targetPort para que
 * ConnectionLayer pueda filtrar por dispositivo seleccionado y ConnectionLine
 * pueda renderizar etiquetas de puerto.
 */
import { Vector3 } from 'three'

// ── Constantes ────────────────────────────────────────────────────────────────

const LINE_Y = 0.12   // Elevación mínima sobre el suelo (evita z-fighting)

// ── Color por hash (determinista, visual) ─────────────────────────────────────

/**
 * Genera un color HSL determinista a partir del ID de la conexión.
 * El tono varía ampliamente para que líneas adyacentes sean distinguibles.
 *
 * @param {string} id
 * @returns {string}  "hsl(H, 65%, 55%)"
 */
function connectionColor(id) {
    let h = 0
    for (let i = 0; i < id.length; i++) {
        h = (h * 31 + id.charCodeAt(i)) | 0
    }
    const hue = ((h >>> 0) * 2654435761) >>> 22   // Fibonacci hashing → mejor distribución
    return `hsl(${hue % 360}, 65%, 55%)`
}

// ── API pública ───────────────────────────────────────────────────────────────

/**
 * Enruta conexiones INTRA-ÁREA como líneas rectas.
 * Una línea por conexión. Color único por conexión.
 *
 * @param {import('../core/types/scene').SceneGraph} sceneGraph
 * @returns {Array<{
 *   key:        string,
 *   points:     THREE.Vector3[],
 *   color:      string,
 *   lineWidth:  number,
 *   opacity:    number,
 *   sourceId:   string,
 *   targetId:   string,
 *   sourcePort: string|null,
 *   targetPort: string|null,
 * }>}
 */
export function routeIntraConnections(sceneGraph) {
    const routes = []

    sceneGraph.areas.forEach(area => {
        // Mapa local: id → device (sólo los de esta área)
        const localMap = {}
        area.devices.forEach(d => { localMap[d.id] = d })

        area.connections.forEach(conn => {
            const src = localMap[conn.sourceId]
            const dst = localMap[conn.targetId]
            if (!src?.position || !dst?.position) return

            routes.push({
                key:        conn.id,
                points:     [
                    new Vector3(src.position[0], LINE_Y, src.position[2]),
                    new Vector3(dst.position[0], LINE_Y, dst.position[2]),
                ],
                color:      connectionColor(conn.id),
                lineWidth:  1.0,
                opacity:    0.45,
                sourceId:   conn.sourceId,
                targetId:   conn.targetId,
                sourcePort: conn.sourcePort ?? null,
                targetPort: conn.targetPort ?? null,
            })
        })
    })

    return routes
}

/**
 * Enruta conexiones INTER-ÁREA como líneas rectas entre posiciones reales.
 * Una línea por conexión individual (sin agregación por par de áreas).
 * El color es determinista por ID de conexión.
 *
 * @param {import('../core/types/scene').SceneGraph} sceneGraph
 * @returns {Array<{
 *   key:        string,
 *   points:     THREE.Vector3[],
 *   color:      string,
 *   lineWidth:  number,
 *   opacity:    number,
 *   sourceId:   string,
 *   targetId:   string,
 *   sourcePort: string|null,
 *   targetPort: string|null,
 * }>}
 */
export function routeInterConnections(sceneGraph) {
    // ── 1. Mapa global: deviceId → { position, areaId } ───────────────────
    const deviceMap = {}   // id → { position: [x,y,z], areaId: string }

    sceneGraph.areas.forEach(area => {
        area.devices.forEach(d => {
            if (!d.position) return
            deviceMap[d.id] = { position: d.position, areaId: area.id }
        })
    })

    // ── 2. Una ruta por conexión inter-área ────────────────────────────────
    const routes = []

    sceneGraph.interAreaConnections.forEach(conn => {
        const srcDev = deviceMap[conn.sourceId]
        const dstDev = deviceMap[conn.targetId]
        if (!srcDev || !dstDev) return
        // Filtrar conexiones intra-área que aparezcan erróneamente aquí
        if (srcDev.areaId === dstDev.areaId) return

        routes.push({
            key:        conn.id,
            points:     [
                new Vector3(srcDev.position[0], LINE_Y, srcDev.position[2]),
                new Vector3(dstDev.position[0], LINE_Y, dstDev.position[2]),
            ],
            color:      connectionColor(conn.id),
            lineWidth:  1.5,
            opacity:    0.55,
            sourceId:   conn.sourceId,
            targetId:   conn.targetId,
            sourcePort: conn.sourcePort ?? null,
            targetPort: conn.targetPort ?? null,
        })
    })

    return routes
}
