/**
 * IVE — CameraControls
 *
 * Wrapper de OrbitControls (drei) adaptado para vista isométrica.
 *
 * Interacciones:
 *   - Drag izquierdo  → rotar (con límites para mantener vista legible)
 *   - Drag derecho    → pan en plano XZ
 *   - Scroll / pinch  → zoom (OrthographicCamera: modifica camera.zoom)
 *
 * Sincronización con el store (Zustand):
 *   - cameraTarget → controls.target  (se actualiza cuando carga la escena)
 *   - cameraZoom   → camera.zoom      (se actualiza cuando carga la escena)
 *   - cameraResetPending → animación suave hacia los nuevos valores (Fase 9)
 *
 * Fase 9 — Animación de cámara:
 *   Antes: controls.reset() hacía un salto instantáneo a la nueva posición.
 *   Ahora: useFrame interpola (lerp) target y zoom hacia los valores del store
 *          usando una función de amortiguación exponencial independiente del framerate.
 *   La animación usa state.invalidate() para mantener el loop hasta converger.
 */
import { useRef, useEffect } from 'react'
import { useThree, useFrame } from '@react-three/fiber'
import { OrbitControls }      from '@react-three/drei'
import { Vector3 }            from 'three'
import { useIveStore }        from '@ive/core/store/useIveStore'

// ── Constantes de restricción isométrica ─────────────────────────────────────
const MIN_POLAR  = Math.PI / 8       // ~22.5° — muy cerca del cénit
const MAX_POLAR  = Math.PI / 2.1     // ~85.7° — casi horizonte
const MIN_ZOOM   = 2
const MAX_ZOOM   = 100
const STIFFNESS  = 10               // Factor de amortiguación (mayor = más rápido)
const CONVERGE_D = 0.08             // Distancia mínima para considerar "llegado"

// Ángulos canónicos para vistas fijas
// Vista iso: cámara en [D,D,D] → azimuth=π/4, polar=acos(1/√3)≈54.74°
const ISO_PHI   = Math.acos(1 / Math.sqrt(3))   // ≈ 54.74°
const ISO_THETA = Math.PI / 4                    // 45°
// Vista frontal: cámara a ras de horizonte desde +Z
const FRONT_PHI   = Math.PI / 2 * 0.88          // ≈ 79.2° (ligeramente por encima del horizonte)
const FRONT_THETA = 0                            // vista desde +Z

export function CameraControls() {
    const ref    = useRef()
    const { camera, invalidate, size } = useThree()

    const cameraTarget       = useIveStore(s => s.cameraTarget)
    const cameraZoom         = useIveStore(s => s.cameraZoom)
    const cameraResetPending = useIveStore(s => s.cameraResetPending)
    const clearCameraReset   = useIveStore(s => s.clearCameraReset)
    const sceneGraph         = useIveStore(s => s.sceneGraph)
    const viewMode           = useIveStore(s => s.viewMode)

    // Refs para el lerp (mutables, no provocan re-renders)
    const lerpTarget = useRef(null)   // Vector3 destino | null cuando no anima
    const lerpZoom   = useRef(null)   // number  destino | null cuando no anima
    // Guarda que el auto-encuadre inicial solo se aplica una vez
    const hasAutoFit = useRef(false)

    // ── Sincronizar target cuando la escena carga (sin animación) ────────────
    // Si cameraResetPending ya está activo (mismo batch de estado), dejamos
    // que el useEffect de reset haga la animación. Revisamos el store actual.
    useEffect(() => {
        if (!ref.current) return
        const { cameraResetPending: pending } = useIveStore.getState()
        if (pending) return   // la animación lo manejará
        ref.current.target.set(...cameraTarget)
        ref.current.update()
        invalidate()
    }, [cameraTarget])   // eslint-disable-line

    // ── Sincronizar zoom cuando la escena carga (sin animación) ─────────────
    useEffect(() => {
        const { cameraResetPending: pending } = useIveStore.getState()
        if (pending) return   // la animación lo manejará
        camera.zoom = cameraZoom
        camera.updateProjectionMatrix()
        if (ref.current) ref.current.update()
        invalidate()
    }, [cameraZoom])   // eslint-disable-line

    // ── Reset animado (botón "Centrar" + cambio de layout) ──────────────────
    useEffect(() => {
        if (!cameraResetPending || !ref.current) return
        // Establecer destinos del lerp con los valores actuales del store
        lerpTarget.current = new Vector3(...cameraTarget)
        lerpZoom.current   = cameraZoom
        clearCameraReset()
        invalidate()   // arrancar la animación (primer frame)
    }, [cameraResetPending])   // eslint-disable-line

    // ── Auto-encuadre exacto al cargar la escena (usa px reales del canvas) ─
    //
    // Se declara DESPUÉS de [cameraResetPending] para que React lo ejecute
    // al final del mismo commit y pueda sobreescribir los destinos del lerp
    // con valores geométricamente precisos (vs. el heurístico de computeFitZoom).
    //
    // Geometría isométrica (cámara en dirección [-1,-1,-1]/√3):
    //   right    = [1,0,-1]/√2      → semi-ancho  = (maxX + maxZ) / (2√2)
    //   screenUp = [-1,2,-1]/√6     → semi-alto   = (maxX + maxZ) / (2√6)
    //
    // Para que la escena llene FILL% del canvas sin desbordarse se necesita:
    //   zoom ≤ (canvas_px / 2 × FILL) / half_extent   → en ancho Y en alto
    useEffect(() => {
        if (!sceneGraph || hasAutoFit.current || !ref.current) return
        hasAutoFit.current = true

        const areas = sceneGraph.areas.filter(a => a.bounds)
        if (!areas.length) return

        const maxX   = Math.max(...areas.map(a => a.bounds.x + a.bounds.width))
        const maxZ   = Math.max(...areas.map(a => a.bounds.z + a.bounds.depth))

        // Semi-extensiones proyectadas en la pantalla isométrica
        const halfW  = (maxX + maxZ) / (2 * Math.SQRT2)          // ancho
        const halfH  = (maxX + maxZ) / (2 * Math.sqrt(6))        // alto

        const FILL   = 0.82       // dejar 18 % de margen alrededor
        const zW     = (size.width  / 2 * FILL) / halfW
        const zH     = (size.height / 2 * FILL) / halfH
        const zoom   = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, Math.min(zW, zH)))
        const cx     = maxX / 2
        const cz     = maxZ / 2

        // Sobreescribir los destinos del lerp con valores exactos.
        // (El efecto [cameraResetPending] puede haberlos fijado antes con el
        //  zoom aproximado; este efecto, al ejecutarse después, lo corrige.)
        lerpTarget.current = new Vector3(cx, 0, cz)
        lerpZoom.current   = zoom

        // Sincronizar el store para que el botón "Centrar" y setLayoutMode
        // usen siempre los valores correctos.
        const store = useIveStore.getState()
        store.setCameraZoom(zoom)
        store.setCameraTarget([cx, 0, cz])
        // Re-activar el reset para que el efecto [cameraResetPending] en la
        // siguiente render confirme los nuevos valores en el store y los aplique.
        store.triggerCameraReset()

        invalidate()
    }, [sceneGraph])   // eslint-disable-line

    // ── Modos de vista fija (iso / front) ────────────────────────────────────
    //
    // Al cambiar a un modo fijo:
    //   1. Se bloquean los ángulos polar y azimutal de OrbitControls.
    //   2. Se desactiva la rotación de cámara (solo pan + zoom).
    //   3. Se cambia screenSpacePanning a true (pan siempre en plano de pantalla).
    //   4. La cámara se mueve instantáneamente al ángulo canónico del modo.
    //
    // Al volver a '3d', se restauran todos los límites originales.
    useEffect(() => {
        if (!ref.current) return
        const controls = ref.current

        if (viewMode === '3d') {
            // ── Restaurar libertad de rotación ──────────────────────────────
            controls.minPolarAngle   = MIN_POLAR
            controls.maxPolarAngle   = MAX_POLAR
            controls.minAzimuthAngle = -Infinity
            controls.maxAzimuthAngle = Infinity
            controls.enableRotate    = true
            controls.screenSpacePanning = false
        } else {
            // ── Bloquear en ángulo canónico ─────────────────────────────────
            const phi   = viewMode === 'iso' ? ISO_PHI   : FRONT_PHI
            const theta = viewMode === 'iso' ? ISO_THETA : FRONT_THETA

            controls.minPolarAngle   = phi
            controls.maxPolarAngle   = phi
            controls.minAzimuthAngle = theta
            controls.maxAzimuthAngle = theta
            controls.enableRotate    = false
            controls.screenSpacePanning = true   // pan intuitivo en vistas planas

            // Mover la cámara al ángulo canónico sin animación
            // (OrthographicCamera: la distancia r no afecta el zoom, solo la dirección)
            const target = controls.target.clone()
            const r = camera.position.distanceTo(target) || 80 * Math.sqrt(3)

            camera.position.set(
                target.x + r * Math.sin(phi) * Math.sin(theta),
                target.y + r * Math.cos(phi),
                target.z + r * Math.sin(phi) * Math.cos(theta),
            )
            camera.lookAt(target)
            camera.updateProjectionMatrix()
            controls.update()
            invalidate()
        }
    }, [viewMode])   // eslint-disable-line

    // ── Loop de animación (lerp exponencial — independiente del framerate) ───
    useFrame((state, delta) => {
        if (!ref.current || !lerpTarget.current) return

        // Alpha usando aproximación de exponential decay: 1 − e^(−k·dt)
        const alpha = 1 - Math.exp(-STIFFNESS * delta)

        // Interpolar target de OrbitControls
        ref.current.target.lerp(lerpTarget.current, alpha)

        // Interpolar zoom de la cámara ortográfica
        const zoomDiff  = lerpZoom.current - camera.zoom
        camera.zoom    += zoomDiff * alpha
        camera.updateProjectionMatrix()

        ref.current.update()
        state.invalidate()

        // Convergencia: ¿llegamos?
        const distTarget = ref.current.target.distanceTo(lerpTarget.current)
        const distZoom   = Math.abs(camera.zoom - lerpZoom.current)

        if (distTarget < CONVERGE_D && distZoom < 0.1) {
            // Fijar valores exactos y detener la animación
            ref.current.target.copy(lerpTarget.current)
            camera.zoom = lerpZoom.current
            camera.updateProjectionMatrix()
            ref.current.update()
            lerpTarget.current = null
            lerpZoom.current   = null
        }
    })

    return (
        <OrbitControls
            ref={ref}
            makeDefault={false}         // La cámara ya fue configurada por IsometricCamera
            enableDamping               // Inercia suave al soltar
            dampingFactor={0.08}
            minPolarAngle={MIN_POLAR}
            maxPolarAngle={MAX_POLAR}
            minZoom={MIN_ZOOM}
            maxZoom={MAX_ZOOM}
            screenSpacePanning={false}  // Pan en plano XZ (más natural en isométrico)
        />
    )
}
