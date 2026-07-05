/**
 * IVE — Model Registry
 *
 * Mapa centralizado: deviceType → ruta GLB.
 *
 * ESTADO ACTUAL (Fase 5): todos en null → DeviceBox visual.
 *
 * CUANDO TENGAS TUS MODELOS BLENDER:
 *   1. Exportar desde Blender como .glb (File → Export → glTF 2.0)
 *      Recomendado: activar Draco compression en las opciones de exportación.
 *   2. Colocar en public/ive/models/{type}.glb
 *   3. Cambiar null por la ruta correspondiente aquí.
 *   4. El renderer cambia automáticamente sin tocar ningún otro archivo.
 *
 * Convenciones de exportación Blender recomendadas:
 *   - Escala: 1 unidad Blender = 1 unidad IVE (Three.js)
 *   - Eje Y hacia arriba
 *   - Pivot en la base del modelo (0,0,0 = suelo del dispositivo)
 *   - Textures: embed en el GLB (no archivos externos)
 *   - Draco compression: ON (reduce tamaño ~70%)
 *
 * @type {Record<import('../../core/types/scene').DeviceType, string|null>}
 */
export const MODEL_REGISTRY = {
    // ── Switching ────────────────────────────────────────────
    switch:      null,   // → '/ive/models/switch.glb'
    router:      null,   // → '/ive/models/router.glb'
    firewall:    null,   // → '/ive/models/firewall.glb'

    // ── Wireless ─────────────────────────────────────────────
    ap:          null,   // → '/ive/models/access_point.glb'

    // ── Compute ──────────────────────────────────────────────
    server:      null,   // → '/ive/models/server_1u.glb'

    // ── Power ────────────────────────────────────────────────
    ups:         null,   // → '/ive/models/ups.glb'

    // ── Cabling ──────────────────────────────────────────────
    patch_panel: null,   // → '/ive/models/patch_panel.glb'

    // ── Fallback ─────────────────────────────────────────────
    unknown:     null,
}

/**
 * Retorna la ruta GLB para un tipo de dispositivo, o null si aún no hay modelo.
 *
 * @param {import('../../core/types/scene').DeviceType} deviceType
 * @returns {string|null}
 */
export function getModelPath(deviceType) {
    return MODEL_REGISTRY[deviceType] ?? null
}

/**
 * Precarga los modelos registrados en memoria.
 * Llamar desde App.jsx al iniciar si se desea warm-up del cache de tres.js.
 * (No-op mientras todos los paths sean null.)
 */
export function preloadModels() {
    Object.values(MODEL_REGISTRY).forEach(path => {
        if (path) {
            // useGLTF.preload(path)  ← descomentar cuando haya modelos
        }
    })
}
