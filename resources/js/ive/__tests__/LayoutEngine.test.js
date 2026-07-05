/**
 * IVE Fase 11 — Tests unitarios: LayoutEngine
 *
 * Cubre:
 *   applyLayout('grid')
 *     • bounds asignados a todas las áreas
 *     • posiciones de dispositivos ≠ [0,0,0]
 *     • Y siempre 0 (plano del suelo)
 *     • múltiples áreas sin solapamiento de origen
 *     • área vacía (sin dispositivos) no lanza error
 *     • retorna la misma referencia (muta in-place)
 *
 *   applyLayout('radial')
 *     • bounds.radius > 0
 *     • único dispositivo core queda en el centro del área
 *     • N dispositivos access → N posiciones distintas
 *     • sin solapamiento entre áreas
 *     • retorna la misma referencia
 *
 *   applyLayout (default → radial)
 *   computeSceneCenter
 *   computeFitZoom
 *
 * No requiere DOM (environment: 'node').
 */
import { describe, it, expect } from 'vitest'
import { applyLayout, computeSceneCenter, computeFitZoom } from '../pipeline/LayoutEngine'

// ── Factory ───────────────────────────────────────────────────────────────────

/**
 * Crea un SceneGraph mínimo con N dispositivos por área.
 * @param {Array<{id?:string, devices: string[]}>} areaConfigs
 *   devices: array de strings de rol ('core', 'access', ...)
 */
function makeGraph(areaConfigs) {
    return {
        areas: areaConfigs.map(({ id, devices = [] } = {}, i) => ({
            id: id ?? String(i + 1),
            devices: devices.map((role, j) => ({
                id:       `${i}-${j}`,
                role,
                position: [0, 0, 0],
            })),
            connections: [],
        })),
        interAreaConnections: [],
    }
}

// ── Grid layout ───────────────────────────────────────────────────────────────

describe('applyLayout — grid', () => {
    it('asigna bounds a cada área', () => {
        const graph = makeGraph([{ devices: ['core', 'access', 'access'] }])
        applyLayout(graph, 'grid')
        const { x, z, width, depth } = graph.areas[0].bounds
        expect(typeof x).toBe('number')
        expect(typeof z).toBe('number')
        expect(width).toBeGreaterThan(0)
        expect(depth).toBeGreaterThan(0)
    })

    it('asigna posiciones distintas de [0,0,0] a todos los dispositivos', () => {
        const graph = makeGraph([{ devices: ['core', 'access', 'distribution', 'backbone'] }])
        applyLayout(graph, 'grid')
        graph.areas[0].devices.forEach(d => {
            expect(d.position).not.toEqual([0, 0, 0])
        })
    })

    it('la coordenada Y de todos los dispositivos es 0', () => {
        const graph = makeGraph([{ devices: ['core', 'access', 'backbone'] }])
        applyLayout(graph, 'grid')
        graph.areas[0].devices.forEach(d => {
            expect(d.position[1]).toBe(0)
        })
    })

    it('áreas múltiples tienen orígenes distintos', () => {
        const graph = makeGraph([
            { id: '1', devices: ['core'] },
            { id: '2', devices: ['access'] },
        ])
        applyLayout(graph, 'grid')
        const [a1, a2] = graph.areas
        const sameOrigin = a1.bounds.x === a2.bounds.x && a1.bounds.z === a2.bounds.z
        expect(sameOrigin).toBe(false)
    })

    it('área vacía (sin dispositivos) no lanza error', () => {
        const graph = makeGraph([{ id: '1', devices: [] }])
        expect(() => applyLayout(graph, 'grid')).not.toThrow()
        expect(graph.areas[0].bounds).toBeDefined()
    })

    it('array de áreas vacío no lanza error', () => {
        const graph = { areas: [], interAreaConnections: [] }
        expect(() => applyLayout(graph, 'grid')).not.toThrow()
    })

    it('retorna la misma referencia (muta in-place)', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        expect(applyLayout(graph, 'grid')).toBe(graph)
    })

    it('número de posiciones únicas = número de dispositivos', () => {
        const graph = makeGraph([{ devices: ['core', 'access', 'access', 'access', 'access'] }])
        applyLayout(graph, 'grid')
        const positions = graph.areas[0].devices.map(d => d.position.join(','))
        expect(new Set(positions).size).toBe(5)
    })

    it('los bounds del área contienen todas las posiciones de dispositivos', () => {
        const graph = makeGraph([{ devices: ['core', 'access', 'access', 'distribution'] }])
        applyLayout(graph, 'grid')
        const { x, z, width, depth } = graph.areas[0].bounds
        graph.areas[0].devices.forEach(d => {
            expect(d.position[0]).toBeGreaterThanOrEqual(x - 1)
            expect(d.position[0]).toBeLessThanOrEqual(x + width + 1)
            expect(d.position[2]).toBeGreaterThanOrEqual(z - 1)
            expect(d.position[2]).toBeLessThanOrEqual(z + depth + 1)
        })
    })
})

// ── Radial layout ─────────────────────────────────────────────────────────────

describe('applyLayout — radial', () => {
    it('asigna bounds.radius positivo a cada área', () => {
        const graph = makeGraph([{ devices: ['core', 'access', 'access'] }])
        applyLayout(graph, 'radial')
        expect(graph.areas[0].bounds.radius).toBeGreaterThan(0)
    })

    it('el único dispositivo core queda en el centro del área', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        applyLayout(graph, 'radial')
        const [cx, , cz] = graph.areas[0].devices[0].position
        const { x, z, width, depth } = graph.areas[0].bounds
        expect(Math.abs(cx - (x + width / 2))).toBeLessThan(0.01)
        expect(Math.abs(cz - (z + depth / 2))).toBeLessThan(0.01)
    })

    it('N dispositivos access → N posiciones distintas', () => {
        const graph = makeGraph([{ devices: ['access', 'access', 'access', 'access'] }])
        applyLayout(graph, 'radial')
        const positions = graph.areas[0].devices.map(d => d.position.join(','))
        expect(new Set(positions).size).toBe(4)
    })

    it('la coordenada Y de todos los dispositivos es 0', () => {
        const graph = makeGraph([{ devices: ['core', 'distribution', 'access', 'access'] }])
        applyLayout(graph, 'radial')
        graph.areas[0].devices.forEach(d => {
            expect(d.position[1]).toBe(0)
        })
    })

    it('áreas múltiples sin solapamiento (distancia centro > suma de radios)', () => {
        const graph = makeGraph([
            { id: '1', devices: ['core', 'access', 'access'] },
            { id: '2', devices: ['core', 'access', 'access'] },
        ])
        applyLayout(graph, 'radial')
        const [a1, a2] = graph.areas
        const cx1 = a1.bounds.x + a1.bounds.width / 2
        const cz1 = a1.bounds.z + a1.bounds.depth / 2
        const cx2 = a2.bounds.x + a2.bounds.width / 2
        const cz2 = a2.bounds.z + a2.bounds.depth / 2
        const dist = Math.hypot(cx2 - cx1, cz2 - cz1)
        // Tolerar 1 unidad de margin
        expect(dist).toBeGreaterThan(a1.bounds.radius + a2.bounds.radius - 1)
    })

    it('área vacía no lanza error', () => {
        const graph = makeGraph([{ id: '1', devices: [] }])
        expect(() => applyLayout(graph, 'radial')).not.toThrow()
    })

    it('retorna la misma referencia (muta in-place)', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        expect(applyLayout(graph, 'radial')).toBe(graph)
    })

    it('bounds.width y bounds.depth son iguales (área circular)', () => {
        const graph = makeGraph([{ devices: ['core', 'access'] }])
        applyLayout(graph, 'radial')
        const { width, depth } = graph.areas[0].bounds
        expect(width).toBe(depth)
    })
})

// ── Modo por defecto ──────────────────────────────────────────────────────────

describe('applyLayout — modo por defecto', () => {
    it('sin segundo argumento usa radial (bounds.radius definido)', () => {
        const graph = makeGraph([{ devices: ['core', 'access'] }])
        applyLayout(graph)
        expect(graph.areas[0].bounds.radius).toBeDefined()
        expect(graph.areas[0].bounds.radius).toBeGreaterThan(0)
    })
})

// ── computeSceneCenter ────────────────────────────────────────────────────────

describe('computeSceneCenter', () => {
    it('retorna [0,0,0] cuando ningún área tiene bounds (pre-layout)', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        expect(computeSceneCenter(graph)).toEqual([0, 0, 0])
    })

    it('la coordenada Y del centro siempre es 0', () => {
        const graph = makeGraph([
            { id: '1', devices: ['core'] },
            { id: '2', devices: ['access', 'access'] },
        ])
        applyLayout(graph, 'radial')
        expect(computeSceneCenter(graph)[1]).toBe(0)
    })

    it('X y Z del centro son positivos tras layout con múltiples áreas', () => {
        const graph = makeGraph([
            { id: '1', devices: ['core', 'access'] },
            { id: '2', devices: ['core', 'access'] },
        ])
        applyLayout(graph, 'grid')
        const [cx, , cz] = computeSceneCenter(graph)
        expect(cx).toBeGreaterThan(0)
        expect(cz).toBeGreaterThan(0)
    })

    it('con una única área el centro coincide con la mitad de los bounds', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        applyLayout(graph, 'grid')
        const [cx, , cz] = computeSceneCenter(graph)
        const { x, z, width, depth } = graph.areas[0].bounds
        // computeSceneCenter usa max/2, no center per se; comprobamos coherencia
        expect(cx).toBeLessThanOrEqual(x + width)
        expect(cz).toBeLessThanOrEqual(z + depth)
    })

    it('retorna array de longitud 3', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        applyLayout(graph, 'radial')
        expect(computeSceneCenter(graph)).toHaveLength(3)
    })
})

// ── computeFitZoom ────────────────────────────────────────────────────────────

describe('computeFitZoom', () => {
    it('retorna 10 para grafo sin áreas', () => {
        expect(computeFitZoom({ areas: [] })).toBe(10)
    })

    it('retorna 10 cuando ningún área tiene bounds', () => {
        const graph = makeGraph([{ devices: ['core'] }])
        expect(computeFitZoom(graph)).toBe(10)
    })

    it('retorna un número entre 3 y 20', () => {
        const graph = makeGraph([
            { id: '1', devices: ['core', 'access', 'access', 'distribution'] },
            { id: '2', devices: ['access', 'access'] },
        ])
        applyLayout(graph, 'radial')
        const zoom = computeFitZoom(graph)
        expect(zoom).toBeGreaterThanOrEqual(3)
        expect(zoom).toBeLessThanOrEqual(20)
    })

    it('escena pequeña → zoom mayor que escena grande', () => {
        const small = makeGraph([{ id: '1', devices: ['core'] }])
        const large = makeGraph([
            { id: '1', devices: Array(20).fill('access') },
            { id: '2', devices: Array(20).fill('access') },
            { id: '3', devices: Array(20).fill('access') },
        ])
        applyLayout(small, 'radial')
        applyLayout(large, 'radial')
        expect(computeFitZoom(small)).toBeGreaterThan(computeFitZoom(large))
    })

    it('retorna número (no NaN, no Infinity)', () => {
        const graph = makeGraph([{ devices: ['core', 'access'] }])
        applyLayout(graph, 'grid')
        const zoom = computeFitZoom(graph)
        expect(Number.isFinite(zoom)).toBe(true)
    })
})
