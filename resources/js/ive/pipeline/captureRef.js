/**
 * IVE — Capture Ref
 *
 * Singleton que conecta el Toolbar (HTML, fuera del Canvas) con el
 * renderer WebGL (dentro del Canvas).
 *
 * ScreenshotCapture (dentro del Canvas) asigna la función de captura.
 * El Toolbar llama captureRef.current() para disparar la descarga PNG.
 *
 * Patrón: ref imperativo en lugar de Zustand — las funciones con closures
 * sobre gl/scene/camera no deben vivir en estado serializable.
 */
export const captureRef = { current: null }
