/**
 * IVE — TrafficDot (Fase 9)
 *
 * Partícula que se desplaza a lo largo de un arco Bezier inter-área
 * simulando tráfico de red.
 *
 * Recibe los puntos Vector3[] del ConnectionRouter (ya calculados como
 * curva cúbica de Bezier) y los usa como spline de desplazamiento vía
 * CatmullRomCurve3, que produce interpolación suave entre los 24 puntos.
 *
 * Patrón de animación:
 *   - useFrame actualiza la posición del mesh en cada frame
 *   - state.invalidate() solicita el siguiente frame (frameloop="demand")
 *   - La partícula inicia en una posición aleatoria para que los arcos
 *     no estén todos sincronizados visualmente
 *
 * Performance:
 *   - Un único mesh (esfera 6×6) por arco
 *   - useFrame exit-early si el mesh no existe
 *   - Se desmonta automáticamente cuando showConnections=false
 */
import { useRef, useMemo }  from 'react'
import { useFrame }         from '@react-three/fiber'
import { CatmullRomCurve3 } from 'three'

/**
 * @param {{
 *   points: import('three').Vector3[],
 *   color:  string,
 * }} props
 */
export function TrafficDot({ points, color }) {
    const meshRef    = useRef()
    const progress   = useRef(Math.random())              // inicio escalonado (fijo al montar)
    const speed      = useRef(0.05 + Math.random() * 0.06) // velocidad aleatoria fija al montar

    // Crear spline a partir de los puntos Bezier pre-calculados
    const curve = useMemo(
        () => new CatmullRomCurve3(points, false, 'catmullrom', 0.5),
        [points],
    )

    useFrame((state, delta) => {
        if (!meshRef.current) return
        progress.current = (progress.current + delta * speed.current) % 1
        const pos = curve.getPoint(progress.current)
        meshRef.current.position.copy(pos)
        state.invalidate()   // mantener el loop mientras el componente vive
    })

    return (
        <mesh ref={meshRef}>
            <sphereGeometry args={[0.15, 6, 6]} />
            <meshBasicMaterial color={color} transparent opacity={0.88} />
        </mesh>
    )
}
