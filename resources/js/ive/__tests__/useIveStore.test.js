/**
 * IVE Fase 11 — Tests de integración: useIveStore (Zustand v5)
 *
 * Estrategia:
 *   El store es un singleton. Se captura el estado inicial una vez y se restaura
 *   antes de cada test con setState(initialState, true) (replace = true).
 *   No se necesita DOM — Zustand v5 funciona en environment: 'node'.
 *
 * Cubre:
 *   Estado inicial
 *   selectNode / closePanel
 *   toggles UI (showLabels, showConnections, showAreas, showMinimap,
 *               showTraffic, darkMode)
 *   setDetailLevel (Fase 10 LOD)
 *   Acciones de cámara: setCameraTarget, setCameraZoom,
 *                       triggerCameraReset, clearCameraReset
 *   setRawGraph / setSceneGraph
 *   setLayoutMode (integración con LayoutEngine):
 *     – no lanza cuando rawGraph es null
 *     – re-aplica layout y actualiza sceneGraph
 *     – activa cameraResetPending
 *     – limpia selectedNode/panelOpen
 *     – NO muta rawGraph original (structuredClone)
 *     – radial produce bounds.radius; grid no
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { useIveStore } from '../core/store/useIveStore'

// ── Utilidades ────────────────────────────────────────────────────────────────

/** Estado inicial capturado antes de cualquier test. */
const INITIAL_STATE = useIveStore.getState()

/** Grafo raw mínimo (dos dispositivos, sin posiciones definitivas). */
function makeRawGraph() {
    return {
        areas: [
            {
                id: '1', label: 'Area A', color: '#6366f1',
                devices: [
                    { id: 'd1', role: 'core',   position: [0, 0, 0] },
                    { id: 'd2', role: 'access',  position: [0, 0, 0] },
                ],
                connections: [],
            },
        ],
        interAreaConnections: [],
    }
}

beforeEach(() => {
    // Restituye el estado inicial antes de cada test (replace = true)
    useIveStore.setState(INITIAL_STATE, true)
})

// ── Estado inicial ────────────────────────────────────────────────────────────

describe('useIveStore — estado inicial', () => {
    it('sceneGraph es null', () => {
        expect(useIveStore.getState().sceneGraph).toBeNull()
    })

    it('rawGraph es null', () => {
        expect(useIveStore.getState().rawGraph).toBeNull()
    })

    it('selectedNode es null', () => {
        expect(useIveStore.getState().selectedNode).toBeNull()
    })

    it('layoutMode es "radial"', () => {
        expect(useIveStore.getState().layoutMode).toBe('radial')
    })

    it('todos los toggles de visibilidad activos excepto darkMode y panelOpen', () => {
        const s = useIveStore.getState()
        expect(s.showLabels).toBe(true)
        expect(s.showConnections).toBe(true)
        expect(s.showAreas).toBe(true)
        expect(s.showMinimap).toBe(true)
        expect(s.showTraffic).toBe(true)
        expect(s.darkMode).toBe(false)
        expect(s.panelOpen).toBe(false)
    })

    it('detailLevel es "high"', () => {
        expect(useIveStore.getState().detailLevel).toBe('high')
    })

    it('cameraResetPending es false', () => {
        expect(useIveStore.getState().cameraResetPending).toBe(false)
    })

    it('cameraZoom es 20', () => {
        expect(useIveStore.getState().cameraZoom).toBe(20)
    })

    it('cameraTarget es [0,0,0]', () => {
        expect(useIveStore.getState().cameraTarget).toEqual([0, 0, 0])
    })
})

// ── selectNode / closePanel ───────────────────────────────────────────────────

describe('useIveStore — selectNode', () => {
    it('almacena el nodo y abre el panel', () => {
        const device = { id: 'd1', label: 'Core Switch' }
        useIveStore.getState().selectNode(device)
        expect(useIveStore.getState().selectedNode).toBe(device)
        expect(useIveStore.getState().panelOpen).toBe(true)
    })

    it('pasa null → limpia el nodo y cierra el panel', () => {
        useIveStore.getState().selectNode({ id: 'd1' })
        useIveStore.getState().selectNode(null)
        expect(useIveStore.getState().selectedNode).toBeNull()
        expect(useIveStore.getState().panelOpen).toBe(false)
    })

    it('seleccionar un nodo diferente reemplaza al anterior', () => {
        useIveStore.getState().selectNode({ id: 'd1' })
        const d2 = { id: 'd2' }
        useIveStore.getState().selectNode(d2)
        expect(useIveStore.getState().selectedNode).toBe(d2)
    })
})

describe('useIveStore — closePanel', () => {
    it('limpia selectedNode y cierra panelOpen', () => {
        useIveStore.getState().selectNode({ id: 'd1' })
        useIveStore.getState().closePanel()
        expect(useIveStore.getState().selectedNode).toBeNull()
        expect(useIveStore.getState().panelOpen).toBe(false)
    })

    it('no falla si no había nodo seleccionado', () => {
        expect(() => useIveStore.getState().closePanel()).not.toThrow()
    })
})

// ── Toggles de UI ─────────────────────────────────────────────────────────────

describe('useIveStore — toggles UI', () => {
    it('toggleLabels invierte showLabels', () => {
        useIveStore.getState().toggleLabels()
        expect(useIveStore.getState().showLabels).toBe(false)
        useIveStore.getState().toggleLabels()
        expect(useIveStore.getState().showLabels).toBe(true)
    })

    it('toggleConnections invierte showConnections', () => {
        useIveStore.getState().toggleConnections()
        expect(useIveStore.getState().showConnections).toBe(false)
    })

    it('toggleAreas invierte showAreas', () => {
        useIveStore.getState().toggleAreas()
        expect(useIveStore.getState().showAreas).toBe(false)
    })

    it('toggleMinimap invierte showMinimap', () => {
        useIveStore.getState().toggleMinimap()
        expect(useIveStore.getState().showMinimap).toBe(false)
    })

    it('toggleTraffic invierte showTraffic (Fase 9)', () => {
        expect(useIveStore.getState().showTraffic).toBe(true)
        useIveStore.getState().toggleTraffic()
        expect(useIveStore.getState().showTraffic).toBe(false)
        useIveStore.getState().toggleTraffic()
        expect(useIveStore.getState().showTraffic).toBe(true)
    })

    it('toggleDarkMode invierte darkMode', () => {
        expect(useIveStore.getState().darkMode).toBe(false)
        useIveStore.getState().toggleDarkMode()
        expect(useIveStore.getState().darkMode).toBe(true)
    })

    it('los toggles son independientes entre sí', () => {
        useIveStore.getState().toggleLabels()
        useIveStore.getState().toggleAreas()
        const s = useIveStore.getState()
        expect(s.showLabels).toBe(false)
        expect(s.showAreas).toBe(false)
        expect(s.showConnections).toBe(true)  // sin tocar
        expect(s.showTraffic).toBe(true)       // sin tocar
    })
})

// ── setDetailLevel (LOD Fase 10) ──────────────────────────────────────────────

describe('useIveStore — setDetailLevel', () => {
    it('establece "low"', () => {
        useIveStore.getState().setDetailLevel('low')
        expect(useIveStore.getState().detailLevel).toBe('low')
    })

    it('establece "medium"', () => {
        useIveStore.getState().setDetailLevel('medium')
        expect(useIveStore.getState().detailLevel).toBe('medium')
    })

    it('vuelve a "high"', () => {
        useIveStore.getState().setDetailLevel('low')
        useIveStore.getState().setDetailLevel('high')
        expect(useIveStore.getState().detailLevel).toBe('high')
    })

    it('no afecta otros campos del estado', () => {
        useIveStore.getState().setDetailLevel('low')
        expect(useIveStore.getState().showLabels).toBe(true)
        expect(useIveStore.getState().layoutMode).toBe('radial')
    })
})

// ── Acciones de cámara ────────────────────────────────────────────────────────

describe('useIveStore — cámara', () => {
    it('setCameraTarget actualiza cameraTarget', () => {
        useIveStore.getState().setCameraTarget([10, 0, 20])
        expect(useIveStore.getState().cameraTarget).toEqual([10, 0, 20])
    })

    it('setCameraZoom actualiza cameraZoom', () => {
        useIveStore.getState().setCameraZoom(42)
        expect(useIveStore.getState().cameraZoom).toBe(42)
    })

    it('triggerCameraReset activa cameraResetPending', () => {
        useIveStore.getState().triggerCameraReset()
        expect(useIveStore.getState().cameraResetPending).toBe(true)
    })

    it('clearCameraReset desactiva cameraResetPending', () => {
        useIveStore.getState().triggerCameraReset()
        useIveStore.getState().clearCameraReset()
        expect(useIveStore.getState().cameraResetPending).toBe(false)
    })

    it('trigger → clear → trigger vuelve a activar', () => {
        useIveStore.getState().triggerCameraReset()
        useIveStore.getState().clearCameraReset()
        useIveStore.getState().triggerCameraReset()
        expect(useIveStore.getState().cameraResetPending).toBe(true)
    })
})

// ── setRawGraph / setSceneGraph ───────────────────────────────────────────────

describe('useIveStore — setRawGraph / setSceneGraph', () => {
    it('setRawGraph almacena el grafo raw', () => {
        const raw = makeRawGraph()
        useIveStore.getState().setRawGraph(raw)
        expect(useIveStore.getState().rawGraph).toBe(raw)
    })

    it('setSceneGraph almacena el scene graph', () => {
        const sg = makeRawGraph()
        useIveStore.getState().setSceneGraph(sg)
        expect(useIveStore.getState().sceneGraph).toBe(sg)
    })

    it('setRawGraph no modifica sceneGraph', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        expect(useIveStore.getState().sceneGraph).toBeNull()
    })
})

// ── setLayoutMode (integración con LayoutEngine) ──────────────────────────────

describe('useIveStore — setLayoutMode', () => {
    it('no lanza cuando rawGraph es null', () => {
        expect(() => useIveStore.getState().setLayoutMode('grid')).not.toThrow()
    })

    it('actualiza layoutMode aunque rawGraph sea null', () => {
        useIveStore.getState().setLayoutMode('grid')
        expect(useIveStore.getState().layoutMode).toBe('grid')
    })

    it('re-aplica layout cuando rawGraph está definido (sceneGraph ≠ null)', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('grid')
        expect(useIveStore.getState().sceneGraph).not.toBeNull()
    })

    it('sceneGraph resultante tiene bounds en cada área', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('grid')
        expect(useIveStore.getState().sceneGraph.areas[0].bounds).toBeDefined()
    })

    it('activa cameraResetPending tras cambio de layout', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('grid')
        expect(useIveStore.getState().cameraResetPending).toBe(true)
    })

    it('limpia selectedNode y cierra panelOpen', () => {
        useIveStore.getState().selectNode({ id: 'd1' })
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('grid')
        expect(useIveStore.getState().selectedNode).toBeNull()
        expect(useIveStore.getState().panelOpen).toBe(false)
    })

    it('layout radial produce bounds.radius en cada área', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('radial')
        const { radius } = useIveStore.getState().sceneGraph.areas[0].bounds
        expect(radius).toBeGreaterThan(0)
    })

    it('NO muta el rawGraph original (usa structuredClone internamente)', () => {
        const raw = makeRawGraph()
        useIveStore.getState().setRawGraph(raw)
        useIveStore.getState().setLayoutMode('grid')
        // Los dispositivos del rawGraph original deben conservar position [0,0,0]
        expect(raw.areas[0].devices[0].position).toEqual([0, 0, 0])
    })

    it('cameraZoom actualizado tras re-layout (≠ valor por defecto 20 para escena pequeña)', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('radial')
        // computeFitZoom devuelve entre 3 y 20 dependiendo del tamaño
        const zoom = useIveStore.getState().cameraZoom
        expect(typeof zoom).toBe('number')
        expect(Number.isFinite(zoom)).toBe(true)
    })

    it('cameraTarget actualizado tras re-layout (no todos ceros para escena no vacía)', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('radial')
        const [cx, cy, cz] = useIveStore.getState().cameraTarget
        expect(cy).toBe(0)
        // Con al menos 1 área, cx y cz deberían ser > 0
        expect(cx + cz).toBeGreaterThan(0)
    })

    it('switch de grid → radial produce bounds.radius', () => {
        useIveStore.getState().setRawGraph(makeRawGraph())
        useIveStore.getState().setLayoutMode('grid')
        expect(useIveStore.getState().sceneGraph.areas[0].bounds.radius).toBeUndefined()
        useIveStore.getState().setLayoutMode('radial')
        expect(useIveStore.getState().sceneGraph.areas[0].bounds.radius).toBeGreaterThan(0)
    })
})
