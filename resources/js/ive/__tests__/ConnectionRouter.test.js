/**
 * IVE Fase 12 — Tests unitarios: ConnectionRouter (actualizado)
 *
 * Comportamiento nuevo (Fase 12):
 *   - Líneas RECTAS (2 puntos) — no curvas Bezier
 *   - Color determinista por hash del ID (ya no es el color del área)
 *   - Incluye sourceId, targetId, sourcePort, targetPort en cada ruta
 *
 * routeIntraConnections:
 *   • Sin conexiones → array vacío
 *   • Una ruta por conexión
 *   • Campos requeridos: key, points (Vector3[]), color, lineWidth, opacity,
 *     sourceId, targetId, sourcePort, targetPort
 *   • Exactamente 2 puntos (línea recta)
 *   • Y ≥ 0 en todos los puntos (sobre el suelo)
 *   • Color es un string (formato hsl)
 *   • Dispositivos no encontrados → ruta omitida
 *
 * routeInterConnections:
 *   • Sin conexiones → array vacío
 *   • Una ruta POR CONEXIÓN individual (sin agregación por par)
 *   • N conexiones entre el mismo par → N rutas
 *   • Campos requeridos: key, points (Vector3[]), color, lineWidth, opacity,
 *     sourceId, targetId, sourcePort, targetPort
 *   • Exactamente 2 puntos (línea recta)
 *   • Dispositivo inexistente → omitido
 *   • Conexión intra-área → omitida
 *
 * No requiere DOM (environment: 'node').
 */
import { describe, it, expect } from 'vitest'
import { Vector3 } from 'three'
import { routeIntraConnections, routeInterConnections } from '../pipeline/ConnectionRouter'

// ── Factory ───────────────────────────────────────────────────────────────────

/**
 * Construye un SceneGraph con dos áreas pre-posicionadas.
 *   area-a  (x:0..20,  z:0..20)  → devices: d1 core, d2 access, d3 access
 *   area-b  (x:40..60, z:0..20)  → devices: d4 distribution, d5 access
 *
 * @param {Object} opts
 * @param {[string,string,string?,string?][]} opts.intraConns  [srcId, dstId, srcPort?, dstPort?] en area-a
 * @param {[string,string,string?,string?][]} opts.interConns  [srcId, dstId, srcPort?, dstPort?] entre áreas
 */
function makeGraph({ intraConns = [], interConns = [] } = {}) {
    return {
        areas: [
            {
                id:     'area-a',
                color:  '#6366f1',
                bounds: { x: 0, z: 0, width: 20, depth: 20 },
                devices: [
                    { id: 'd1', role: 'core',   position: [0,  0, 0]  },
                    { id: 'd2', role: 'access',  position: [8,  0, 0]  },
                    { id: 'd3', role: 'access',  position: [0,  0, 8]  },
                ],
                connections: intraConns.map(([src, dst, sp, dp]) => ({
                    id: `${src}-${dst}`, sourceId: src, targetId: dst,
                    sourcePort: sp ?? null, targetPort: dp ?? null,
                    interArea: false, role: 'core',
                })),
            },
            {
                id:     'area-b',
                color:  '#f97316',
                bounds: { x: 40, z: 0, width: 20, depth: 20 },
                devices: [
                    { id: 'd4', role: 'distribution', position: [50, 0, 10] },
                    { id: 'd5', role: 'access',        position: [55, 0, 10] },
                ],
                connections: [],
            },
        ],
        interAreaConnections: interConns.map(([src, dst, sp, dp]) => ({
            id: `${src}-${dst}`, sourceId: src, targetId: dst,
            sourcePort: sp ?? null, targetPort: dp ?? null,
            interArea: true, role: 'core',
        })),
    }
}

// ── routeIntraConnections ─────────────────────────────────────────────────────

describe('routeIntraConnections', () => {
    it('devuelve array vacío sin conexiones', () => {
        expect(routeIntraConnections(makeGraph())).toEqual([])
    })

    it('devuelve una ruta por conexión', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2'], ['d1', 'd3']] })
        expect(routeIntraConnections(graph)).toHaveLength(2)
    })

    it('cada ruta tiene key, points, color, lineWidth, opacity, sourceId, targetId', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        const [route] = routeIntraConnections(graph)

        expect(route.key).toBeDefined()
        expect(Array.isArray(route.points)).toBe(true)
        expect(route.points.length).toBeGreaterThan(1)
        expect(typeof route.color).toBe('string')
        expect(typeof route.lineWidth).toBe('number')
        expect(typeof route.opacity).toBe('number')
        expect(route.sourceId).toBe('d1')
        expect(route.targetId).toBe('d2')
    })

    it('usa la clave de la conexión como key de ruta', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        const [route] = routeIntraConnections(graph)
        expect(route.key).toBe('d1-d2')
    })

    it('la ruta tiene exactamente 2 puntos (línea recta)', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        expect(routeIntraConnections(graph)[0].points).toHaveLength(2)
    })

    it('los puntos son instancias de Vector3', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        routeIntraConnections(graph)[0].points.forEach(p =>
            expect(p).toBeInstanceOf(Vector3)
        )
    })

    it('todos los puntos tienen Y ≥ 0', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        routeIntraConnections(graph)[0].points.forEach(p =>
            expect(p.y).toBeGreaterThanOrEqual(0)
        )
    })

    it('el color es un string de tipo hsl()', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        const { color } = routeIntraConnections(graph)[0]
        expect(color).toMatch(/^hsl\(\d+, \d+%, \d+%\)$/)
    })

    it('sourcePort y targetPort se propagan desde la conexión', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2', 'Gi0/1', 'Gi0/2']] })
        const [route] = routeIntraConnections(graph)
        expect(route.sourcePort).toBe('Gi0/1')
        expect(route.targetPort).toBe('Gi0/2')
    })

    it('sourcePort y targetPort son null cuando no están definidos', () => {
        const graph = makeGraph({ intraConns: [['d1', 'd2']] })
        const [route] = routeIntraConnections(graph)
        expect(route.sourcePort).toBeNull()
        expect(route.targetPort).toBeNull()
    })

    it('omite ruta cuando el dispositivo origen no existe', () => {
        const graph = makeGraph({ intraConns: [['ghost', 'd2']] })
        expect(routeIntraConnections(graph)).toHaveLength(0)
    })

    it('omite ruta cuando el dispositivo destino no existe', () => {
        const graph = makeGraph({ intraConns: [['d1', 'ghost']] })
        expect(routeIntraConnections(graph)).toHaveLength(0)
    })

    it('no procesa conexiones de area-b (sin conexiones declaradas)', () => {
        // area-b tiene devices pero no connections → 0 rutas desde ese área
        const graph = makeGraph({ intraConns: [['d4', 'd5']] })
        expect(routeIntraConnections(graph)).toHaveLength(0)
    })
})

// ── routeInterConnections ─────────────────────────────────────────────────────

describe('routeInterConnections', () => {
    it('devuelve array vacío sin conexiones inter-área', () => {
        expect(routeInterConnections(makeGraph())).toEqual([])
    })

    it('devuelve UNA ruta por conexión individual (sin agregar por par)', () => {
        const graph = makeGraph({
            interConns: [['d1', 'd4'], ['d2', 'd4'], ['d3', 'd5']],
        })
        // Tres conexiones entre area-a ↔ area-b → tres rutas individuales
        expect(routeInterConnections(graph)).toHaveLength(3)
    })

    it('cada ruta tiene key, points, color, lineWidth, opacity, sourceId, targetId', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        const [route] = routeInterConnections(graph)

        expect(route.key).toBeDefined()
        expect(route.points.length).toBeGreaterThan(1)
        expect(typeof route.color).toBe('string')
        expect(typeof route.lineWidth).toBe('number')
        expect(typeof route.opacity).toBe('number')
        expect(route.sourceId).toBe('d1')
        expect(route.targetId).toBe('d4')
    })

    it('la ruta tiene exactamente 2 puntos (línea recta)', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        expect(routeInterConnections(graph)[0].points).toHaveLength(2)
    })

    it('los puntos son instancias de Vector3', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        routeInterConnections(graph)[0].points.forEach(p =>
            expect(p).toBeInstanceOf(Vector3)
        )
    })

    it('el color es un string de tipo hsl()', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        const { color } = routeInterConnections(graph)[0]
        expect(color).toMatch(/^hsl\(\d+, \d+%, \d+%\)$/)
    })

    it('sourcePort y targetPort se propagan', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4', 'Te1/0/1', 'Te1/0/2']] })
        const [route] = routeInterConnections(graph)
        expect(route.sourcePort).toBe('Te1/0/1')
        expect(route.targetPort).toBe('Te1/0/2')
    })

    it('los puntos arrancan en la posición del dispositivo origen', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        const [route] = routeInterConnections(graph)
        // d1.position = [0,0,0] → p0 = Vector3(0, LINE_Y, 0)
        expect(route.points[0].x).toBeCloseTo(0)
        expect(route.points[0].z).toBeCloseTo(0)
    })

    it('el segundo punto es la posición del dispositivo destino', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        const [route] = routeInterConnections(graph)
        // d4.position = [50,0,10] → p1 = Vector3(50, LINE_Y, 10)
        expect(route.points[1].x).toBeCloseTo(50)
        expect(route.points[1].z).toBeCloseTo(10)
    })

    it('omite conexión cuando el dispositivo origen no pertenece a ningún área', () => {
        const graph = makeGraph({ interConns: [['ghost', 'd4']] })
        expect(routeInterConnections(graph)).toHaveLength(0)
    })

    it('omite conexión cuando ambos dispositivos están en la misma área', () => {
        const graph = makeGraph({ interConns: [['d1', 'd2']] })
        expect(routeInterConnections(graph)).toHaveLength(0)
    })

    it('funciona aunque área no tenga bounds (usa posición de dispositivo)', () => {
        // Sin bounds, el device map aún incluye los dispositivos
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        delete graph.areas[0].bounds   // area-a sin bounds
        // Los dispositivos de area-a siguen teniendo position asignada
        expect(routeInterConnections(graph)).toHaveLength(1)
    })

    it('una única conexión → una sola ruta', () => {
        const graph = makeGraph({ interConns: [['d1', 'd4']] })
        expect(routeInterConnections(graph)).toHaveLength(1)
    })
})
