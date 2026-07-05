/**
 * IVE — AreaPlane (Fase 12 rev.2)
 *
 * Siempre renderiza un RECTÁNGULO, independientemente del layout (radial o grid).
 * En layout radial, bounds.width = bounds.depth = radius*2, por lo que el
 * rectángulo es un cuadrado circunscrito al círculo de dispositivos.
 *
 * Nombre del área:
 *   Renderizado como texto Troika plano sobre el suelo (rotation X = -90°)
 *   en la esquina "base" del área (borde inferior en vista isométrica).
 *   En coordenadas locales del grupo centrado en el área, el borde inferior
 *   visible desde la cámara isométrica [80,80,80] es el de mayor Z.
 *   Posición: [0, 0.08, +depth/2 * 0.78] — centrado en X, desplazado al borde.
 */
import { useMemo }    from 'react'
import { Line, Text } from '@react-three/drei'

/**
 * @param {{ area: import('@ive/core/types/scene').AreaNode }} props
 */
export function AreaPlane({ area }) {
    const { bounds, color, label } = area
    if (!bounds) return null

    const { x, z, width, depth } = bounds
    const cx = x + width  / 2
    const cz = z + depth  / 2

    // ── Borde rectangular ────────────────────────────────────────────────────
    const hw = width  / 2
    const hd = depth  / 2

    const border = useMemo(() => [
        [-hw, 0, -hd],
        [ hw, 0, -hd],
        [ hw, 0,  hd],
        [-hw, 0,  hd],
        [-hw, 0, -hd],   // cerrar el loop
    ], [hw, hd])

    // ── Tamaño adaptativo del label ──────────────────────────────────────────
    // Ocupa un ~16% de la dimensión menor del área, acotado entre 2 y 14 u.
    const labelSize = Math.max(2, Math.min(14, Math.min(width, depth) * 0.16))

    // maxWidth = 85% del ancho del área (para que el texto no salga del borde)
    const maxWidth = width * 0.85

    // ── Posición del label: borde inferior (mayor +Z en vista isométrica) ──
    const labelZ = hd * 0.78   // 78% hacia el borde inferior

    return (
        <group position={[cx, 0, cz]}>

            {/* ── Suelo rectangular ───────────────────────────── */}
            <mesh rotation={[-Math.PI / 2, 0, 0]} receiveShadow={false}>
                <planeGeometry args={[width, depth]} />
                <meshStandardMaterial
                    color={color}
                    opacity={0.08}
                    transparent
                    depthWrite={false}
                    roughness={1}
                />
            </mesh>

            {/* ── Borde rectangular ───────────────────────────── */}
            <Line
                points={border}
                color={color}
                lineWidth={1.5}
                opacity={0.50}
                transparent
            />

            {/* ── Nombre del área en el borde inferior ────────── */}
            {/*
             * rotation [-π/2, 0, 0]: el texto queda plano sobre el plano XZ.
             * position Y=0.08 evita z-fighting con el suelo.
             * position Z=labelZ: desplazado hacia el borde "inferior" de la vista
             * isométrica (cámara en [80,80,80] → mayor Z = más abajo en pantalla).
             */}
            <Text
                position={[0, 0.08, labelZ]}
                rotation={[-Math.PI / 2, 0, 0]}
                fontSize={labelSize}
                maxWidth={maxWidth}
                color={color}
                fillOpacity={0.35}
                anchorX="center"
                anchorY="middle"
                textAlign="center"
                fontWeight="bold"
                depthOffset={-2}
            >
                {label ?? ''}
            </Text>

        </group>
    )
}
