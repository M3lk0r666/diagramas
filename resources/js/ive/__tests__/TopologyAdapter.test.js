/**
 * IVE Fase 11 — Tests unitarios: TopologyAdapter
 *
 * Cubre:
 *   • Entradas nulas / vacías
 *   • Normalización de roles (ROLE_MAP completo)
 *   • Stringificación de IDs
 *   • Label fallback (sys_name → SW-{id})
 *   • Color fallback
 *   • Adaptación de conexiones intra/inter-área
 *   • Nombres de campo alternativos (source_id / target_id)
 *   • meta.stats correcto
 *
 * No requiere DOM (environment: 'node').
 */
import { describe, it, expect } from 'vitest'
import { adaptTopology } from '../pipeline/TopologyAdapter'

// ── Factories ─────────────────────────────────────────────────────────────────

const makeSwitch = (overrides = {}) => ({
    id:            1,
    sys_name:      'SW-Core-01',
    management_ip: '10.0.0.1',
    system_type:   'Cisco Catalyst 9300',
    system_mac:    'aa:bb:cc:dd:ee:ff',
    role:          'core',
    ...overrides,
})

const makeConn = (overrides = {}) => ({
    src_id:   1,
    dst_id:   2,
    src_port: 'Gi1/0/1',
    dst_port: 'Gi1/0/2',
    role:     'core',
    ...overrides,
})

const makeBatch = (overrides = {}) => ({
    id:          1,
    name:        'Edificio A',
    color:       '#6366f1',
    switches:    [makeSwitch()],
    connections: [],
    ...overrides,
})

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('adaptTopology — entradas inválidas', () => {
    it('devuelve grafo vacío para null', () => {
        const result = adaptTopology(null)
        expect(result.areas).toEqual([])
        expect(result.interAreaConnections).toEqual([])
    })

    it('devuelve grafo vacío para string', () => {
        expect(adaptTopology('texto').areas).toEqual([])
    })

    it('devuelve grafo vacío para número', () => {
        expect(adaptTopology(42).areas).toEqual([])
    })

    it('maneja objeto vacío sin batches', () => {
        const result = adaptTopology({})
        expect(result.areas).toEqual([])
        expect(result.interAreaConnections).toEqual([])
    })

    it('maneja batches vacío', () => {
        expect(adaptTopology({ batches: [] }).areas).toEqual([])
    })
})

describe('adaptTopology — área y dispositivo', () => {
    it('adapta un área con un dispositivo', () => {
        const result = adaptTopology({ batches: [makeBatch()] })
        expect(result.areas).toHaveLength(1)
        const area = result.areas[0]
        expect(area.id).toBe('1')
        expect(area.label).toBe('Edificio A')
        expect(area.color).toBe('#6366f1')
        expect(area.devices).toHaveLength(1)
    })

    it('stringifica el ID del área', () => {
        const result = adaptTopology({ batches: [makeBatch({ id: 99 })] })
        expect(result.areas[0].id).toBe('99')
    })

    it('stringifica el ID del dispositivo', () => {
        const result = adaptTopology({ batches: [makeBatch({ switches: [makeSwitch({ id: 42 })] })] })
        expect(result.areas[0].devices[0].id).toBe('42')
    })

    it('usa sys_name como label del dispositivo', () => {
        const result = adaptTopology({ batches: [makeBatch()] })
        expect(result.areas[0].devices[0].label).toBe('SW-Core-01')
    })

    it('usa SW-{id} cuando sys_name es null', () => {
        const result = adaptTopology({ batches: [makeBatch({ switches: [makeSwitch({ sys_name: null })] })] })
        expect(result.areas[0].devices[0].label).toBe('SW-1')
    })

    it('usa SW-{id} cuando sys_name es cadena vacía', () => {
        const result = adaptTopology({ batches: [makeBatch({ switches: [makeSwitch({ sys_name: '' })] })] })
        expect(result.areas[0].devices[0].label).toBe('SW-1')
    })

    it('posición inicial es [0,0,0] (pre-layout)', () => {
        const result = adaptTopology({ batches: [makeBatch()] })
        expect(result.areas[0].devices[0].position).toEqual([0, 0, 0])
    })

    it('usa color por defecto #6366f1 cuando batch.color falta', () => {
        const result = adaptTopology({ batches: [makeBatch({ color: undefined })] })
        expect(result.areas[0].color).toBe('#6366f1')
    })

    it('preserva management_ip en device.ip', () => {
        const result = adaptTopology({ batches: [makeBatch()] })
        expect(result.areas[0].devices[0].ip).toBe('10.0.0.1')
    })

    it('device.ip es null cuando management_ip falta', () => {
        const result = adaptTopology({ batches: [makeBatch({ switches: [makeSwitch({ management_ip: undefined })] })] })
        expect(result.areas[0].devices[0].ip).toBeNull()
    })

    it('área sin switches → devices vacío', () => {
        const result = adaptTopology({ batches: [makeBatch({ switches: [] })] })
        expect(result.areas[0].devices).toEqual([])
    })
})

describe('adaptTopology — normalización de roles', () => {
    const cases = [
        ['core',         'core'],
        ['backbone',     'backbone'],
        ['distribution', 'distribution'],
        ['dist',         'distribution'],
        ['distrib',      'distribution'],
        ['access',       'access'],
        ['stack',        'access'],
        ['CORE',         'core'],        // insensible a mayúsculas
        ['whatever',     'unknown'],
        [null,           'unknown'],
        [undefined,      'unknown'],
        ['',             'unknown'],
    ]

    it.each(cases)('normaliza "%s" → "%s"', (raw, expected) => {
        const result = adaptTopology({ batches: [makeBatch({ switches: [makeSwitch({ role: raw })] })] })
        expect(result.areas[0].devices[0].role).toBe(expected)
    })
})

describe('adaptTopology — conexiones intra-área', () => {
    it('adapta una conexión intra-área', () => {
        const result = adaptTopology({ batches: [makeBatch({ connections: [makeConn()] })] })
        const conn = result.areas[0].connections[0]
        expect(conn.id).toBe('1-2')
        expect(conn.sourceId).toBe('1')
        expect(conn.targetId).toBe('2')
        expect(conn.interArea).toBe(false)
        expect(conn.sourcePort).toBe('Gi1/0/1')
        expect(conn.targetPort).toBe('Gi1/0/2')
    })

    it('normaliza el rol de la conexión', () => {
        const result = adaptTopology({ batches: [makeBatch({ connections: [makeConn({ role: 'dist' })] })] })
        expect(result.areas[0].connections[0].role).toBe('distribution')
    })

    it('acepta nombres alternativos source_port / dest_port', () => {
        const conn = { src_id: 1, dst_id: 2, source_port: 'Te1/1', dest_port: 'Te2/1', role: 'core' }
        const result = adaptTopology({ batches: [makeBatch({ connections: [conn] })] })
        const c = result.areas[0].connections[0]
        expect(c.sourcePort).toBe('Te1/1')
        expect(c.targetPort).toBe('Te2/1')
    })
})

describe('adaptTopology — conexiones inter-área', () => {
    it('adapta una conexión inter-área', () => {
        const raw = { batches: [makeBatch()], inter_area_connections: [makeConn({ src_id: 1, dst_id: 99 })] }
        const result = adaptTopology(raw)
        expect(result.interAreaConnections).toHaveLength(1)
        expect(result.interAreaConnections[0].interArea).toBe(true)
    })

    it('acepta source_id / target_id como nombres de campo', () => {
        const conn = { source_id: 10, target_id: 20, role: 'access' }
        const result = adaptTopology({ batches: [], inter_area_connections: [conn] })
        expect(result.interAreaConnections[0].sourceId).toBe('10')
        expect(result.interAreaConnections[0].targetId).toBe('20')
    })

    it('sin inter_area_connections → array vacío', () => {
        const result = adaptTopology({ batches: [makeBatch()] })
        expect(result.interAreaConnections).toEqual([])
    })
})

describe('adaptTopology — meta.stats', () => {
    it('cuenta áreas, dispositivos y aristas correctamente', () => {
        const raw = {
            batches: [
                makeBatch({
                    switches:    [makeSwitch(), makeSwitch({ id: 2 })],
                    connections: [makeConn()],
                }),
                makeBatch({ id: 2, switches: [], connections: [] }),
            ],
            inter_area_connections: [makeConn({ src_id: 1, dst_id: 99 })],
        }
        const result = adaptTopology(raw)
        expect(result.meta.stats.areas).toBe(2)
        expect(result.meta.stats.devices).toBe(2)
        expect(result.meta.stats.edges).toBe(2)   // 1 intra + 1 inter
    })

    it('incluye adaptedAt como ISO date string', () => {
        const result = adaptTopology({ batches: [] })
        expect(typeof result.meta.adaptedAt).toBe('string')
        expect(() => new Date(result.meta.adaptedAt)).not.toThrow()
    })

    it('fusiona meta adicional pasado como segundo argumento', () => {
        const result = adaptTopology({ batches: [] }, { clientId: 'abc' })
        expect(result.meta.clientId).toBe('abc')
    })
})
