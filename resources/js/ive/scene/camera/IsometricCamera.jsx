/**
 * IVE — Cámara Ortográfica Isométrica
 *
 * Configura la cámara una vez al montar.
 * Posición [D, D, D] + lookAt([0,0,0]) = vista isométrica clásica (35.26°).
 *
 * IMPORTANTE: el zoom y el target posteriores los gestiona CameraControls
 * (OrbitControls). Este componente solo establece el estado inicial.
 */
import { useEffect, useRef } from 'react'
import { OrthographicCamera } from '@react-three/drei'

const DISTANCE     = 80   // Distancia de la cámara al origen
const INITIAL_ZOOM = 20   // Zoom de arranque — useFetchTopology lo ajustará

export function IsometricCamera() {
    const ref = useRef()

    useEffect(() => {
        if (!ref.current) return
        // lookAt manual — OrthographicCamera de drei no lo aplica por defecto
        ref.current.lookAt(0, 0, 0)
        ref.current.updateProjectionMatrix()
    }, [])

    return (
        <OrthographicCamera
            ref={ref}
            makeDefault
            position={[DISTANCE, DISTANCE, DISTANCE]}
            zoom={INITIAL_ZOOM}
            near={0.1}
            far={20000}
        />
    )
}
