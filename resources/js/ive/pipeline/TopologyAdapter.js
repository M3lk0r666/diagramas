/**
 * IVE — Topology Adapter
 *
 * Responsabilidad única: convertir el JSON crudo del backend
 * en un SceneGraph tipado.
 *
 * El renderer NUNCA toca el JSON del backend directamente.
 * Este adapter es el único punto de acoplamiento con el formato del backend.
 * Si el backend cambia, solo este archivo cambia.
 *
 * Input (JSON de IsoTopologyController):
 * {
 *   batches: [
 *     {
 *       id: number,
 *       name: string,
 *       color: string,
 *       switches: [{ id, sys_name, management_ip, system_type, system_mac, role, ... }],
 *       connections: [{ src_id, dst_id, src_port, dst_port, role }]
 *     }
 *   ],
 *   inter_area_connections: [{ src_id, dst_id, src_port, dst_port, role }]
 * }
 *
 * Output: SceneGraph (ver core/types/scene.js)
 */

// ── Mapa de roles del backend → DeviceRole interno ───────────────────────────
const ROLE_MAP = {
    core:         'core',
    backbone:     'backbone',
    distribution: 'distribution',
    dist:         'distribution',
    distrib:      'distribution',
    access:       'access',
    stack:        'access',
}

function normalizeRole(raw) {
    return ROLE_MAP[String(raw ?? '').toLowerCase()] ?? 'unknown'
}

// ── Adaptadores individuales ─────────────────────────────────────────────────

/**
 * @param {Object} sw - Switch crudo del backend
 * @returns {import('../core/types/scene').DeviceNode}
 */
function adaptDevice(sw) {
    return {
        id:         String(sw.id),
        type:       'device',
        deviceType: 'switch',
        role:       normalizeRole(sw.role),
        label:      sw.sys_name || `SW-${sw.id}`,
        ip:         sw.management_ip  ?? null,
        model:      sw.system_type    ?? null,
        mac:        sw.system_mac     ?? null,
        position:   [0, 0, 0],   // ← Layout Engine lo asignará en Fase 6
        meta:       sw,
    }
}

/**
 * @param {Object}  conn      - Conexión cruda
 * @param {boolean} interArea - true si es inter-área
 * @returns {import('../core/types/scene').ConnectionEdge}
 */
function adaptConnection(conn, interArea) {
    const src = String(conn.src_id ?? conn.source_id ?? '')
    const dst = String(conn.dst_id ?? conn.target_id ?? conn.dest_id ?? '')
    return {
        id:         `${src}-${dst}`,
        sourceId:   src,
        targetId:   dst,
        sourcePort: conn.src_port  ?? conn.source_port  ?? null,
        targetPort: conn.dst_port  ?? conn.target_port  ?? conn.dest_port ?? null,
        interArea,
        role:       normalizeRole(conn.role),
    }
}

/**
 * @param {Object} batch - Batch crudo del backend
 * @returns {import('../core/types/scene').AreaNode}
 */
function adaptArea(batch) {
    return {
        id:          String(batch.id),
        type:        'area',
        label:       batch.name,
        color:       batch.color ?? '#6366f1',
        devices:     (batch.switches ?? []).map(adaptDevice),
        connections: (batch.connections ?? []).map(c => adaptConnection(c, false)),
    }
}

// ── Función principal ────────────────────────────────────────────────────────

/**
 * Convierte el JSON del backend en un SceneGraph.
 *
 * @param {Object} raw - JSON crudo del endpoint Laravel
 * @param {Object} [meta] - Metadata adicional (client, timestamp, etc.)
 * @returns {import('../core/types/scene').SceneGraph}
 */
export function adaptTopology(raw, meta = {}) {
    if (!raw || typeof raw !== 'object') {
        console.warn('[IVE TopologyAdapter] Raw data is null or not an object')
        return { areas: [], interAreaConnections: [], meta }
    }

    const areas = (raw.batches ?? []).map(adaptArea)
    const interAreaConnections = (raw.inter_area_connections ?? [])
        .map(c => adaptConnection(c, true))

    const totalDevices = areas.reduce((n, a) => n + a.devices.length, 0)
    const totalEdges   = areas.reduce((n, a) => n + a.connections.length, 0)
        + interAreaConnections.length

    console.info(
        `[IVE TopologyAdapter] Adapted: ${areas.length} areas, `
        + `${totalDevices} devices, ${totalEdges} edges`
    )

    return {
        areas,
        interAreaConnections,
        meta: {
            ...meta,
            adaptedAt: new Date().toISOString(),
            stats: { areas: areas.length, devices: totalDevices, edges: totalEdges },
        },
    }
}
