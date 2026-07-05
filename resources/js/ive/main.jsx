/**
 * IVE — Infrastructure Visualization Engine
 * Entry point: monta el motor React dentro del div #ive-root
 * que sirve el Blade view admin.ive.global
 *
 * Fase 10 — Optimización BVH:
 *   Parcheamos los prototipos de Three.js ANTES de montar React para que
 *   TODOS los objetos creados durante el render usen raycast acelerado.
 *
 *   • BufferGeometry.prototype.computeBoundsTree  → construye el BVH
 *   • BufferGeometry.prototype.disposeBoundsTree  → libera la memoria del BVH
 *   • Mesh.prototype.raycast                      → usa el BVH si está disponible,
 *     de lo contrario cae al raycast estándar de Three.js (retrocompatible)
 *
 *   Los imports se resuelven antes que el código de módulo (ES modules hoisting),
 *   pero este bloque de asignación sí se ejecuta antes del mount de React, por lo
 *   que todos los hooks de tipo useEffect en los componentes ya encuentran las
 *   funciones en el prototipo cuando quieran llamar a computeBoundsTree().
 */
import { BufferGeometry, Mesh }                                          from 'three'
import { computeBoundsTree, disposeBoundsTree, acceleratedRaycast }      from 'three-mesh-bvh'
import { createRoot }                                                     from 'react-dom/client'
import App                                                                from './App'

// ── Parches BVH (una sola vez, antes del primer render) ──────────────────────
BufferGeometry.prototype.computeBoundsTree = computeBoundsTree
BufferGeometry.prototype.disposeBoundsTree = disposeBoundsTree
Mesh.prototype.raycast                     = acceleratedRaycast

const mountPoint = document.getElementById('ive-root')

if (!mountPoint) {
    console.error('[IVE] Mount point #ive-root not found in DOM.')
} else {
    // El Blade view inyecta la config en data-config como JSON
    const config = JSON.parse(mountPoint.dataset.config || '{}')
    createRoot(mountPoint).render(<App config={config} />)
}
