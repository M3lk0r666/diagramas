/**
 * IVE — Estado global (Zustand)
 *
 * Slices:
 *   scene    → rawGraph, sceneGraph, selectedNode
 *   layout   → layoutMode, re-apply al cambiar
 *   camera   → target, zoom, resetPending
 *   ui       → toggles de visibilidad, darkMode, panelOpen
 */
import { create } from 'zustand'
import { applyLayout, computeSceneCenter, computeFitZoom } from '@ive/pipeline/LayoutEngine'

export const useIveStore = create((set) => ({

    // ── Scene ────────────────────────────────────────────────
    /** Grafo adaptado SIN posiciones (salida de TopologyAdapter). */
    rawGraph:     null,

    /** Grafo con posiciones asignadas por LayoutEngine (renderizable). */
    sceneGraph:   null,

    /** @type {import('../types/scene').DeviceNode|null} */
    selectedNode: null,

    // ── Layout ───────────────────────────────────────────────
    /** @type {'radial'|'grid'} */
    layoutMode: 'radial',

    // ── Cámara ───────────────────────────────────────────────
    cameraTarget:       [0, 0, 0],
    cameraZoom:         20,
    cameraResetPending: false,

    // ── UI ───────────────────────────────────────────────────
    showLabels:      true,
    showConnections: true,
    showAreas:       true,
    showMinimap:     true,
    showTraffic:     true,    // Fase 9: partículas de tráfico en arcos inter-área
    darkMode:        false,
    panelOpen:       false,

    // Fase 10 — LOD (calculado por LODManager en cada frame según camera.zoom)
    /** @type {'high'|'medium'|'low'} */
    detailLevel:     'high',

    // Fase 12 — Modo de cámara
    /** @type {'3d'|'iso'|'front'} */
    viewMode: '3d',

    // ── Acciones — Scene ─────────────────────────────────────
    setRawGraph:   (graph) => set({ rawGraph:   graph }),
    setSceneGraph: (graph) => set({ sceneGraph: graph }),

    selectNode: (node) => set({ selectedNode: node, panelOpen: !!node }),

    // ── Acciones — Layout ────────────────────────────────────
    setLayoutMode: (mode) => set(s => {
        if (!s.rawGraph) return { layoutMode: mode }
        const cloned    = structuredClone(s.rawGraph)
        applyLayout(cloned, mode)
        return {
            layoutMode:         mode,
            sceneGraph:         cloned,
            cameraZoom:         computeFitZoom(cloned),
            cameraTarget:       computeSceneCenter(cloned),
            cameraResetPending: true,
            selectedNode:       null,
            panelOpen:          false,
        }
    }),

    // ── Acciones — Cámara ────────────────────────────────────
    setCameraTarget: (t) => set({ cameraTarget: t }),
    setCameraZoom:   (z) => set({ cameraZoom:   z }),

    triggerCameraReset: () => set({ cameraResetPending: true }),
    clearCameraReset:   () => set({ cameraResetPending: false }),

    // ── Acciones — UI ────────────────────────────────────────
    toggleLabels:      () => set(s => ({ showLabels:      !s.showLabels })),
    toggleConnections: () => set(s => ({ showConnections: !s.showConnections })),
    toggleAreas:       () => set(s => ({ showAreas:       !s.showAreas })),
    toggleMinimap:     () => set(s => ({ showMinimap:     !s.showMinimap })),
    toggleTraffic:     () => set(s => ({ showTraffic:     !s.showTraffic })),
    toggleDarkMode:    () => set(s => ({ darkMode:        !s.darkMode })),
    setDetailLevel:    (level) => set({ detailLevel: level }),
    setViewMode:       (mode)  => set({ viewMode:    mode  }),

    closePanel: () => set({ panelOpen: false, selectedNode: null }),
}))
