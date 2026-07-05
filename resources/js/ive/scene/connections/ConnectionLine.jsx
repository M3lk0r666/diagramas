/**
 * IVE — ConnectionLine (Fase 12 rev.2)
 *
 * Renderiza una ruta 3D pre-calculada por ConnectionRouter.
 *
 * Etiqueta de puerto (midpoint label):
 *   Visible siempre que la conexión tenga datos de puerto y `showPortLabels`
 *   sea true (pasado desde ConnectionLayer según detailLevel).
 *   Se renderiza en el punto medio de la línea, ligeramente elevada.
 *   Formato: "srcPort → dstPort"  (o solo el que esté disponible).
 *
 * isActive:
 *   La línea se resalta (más opaca y gruesa) cuando el dispositivo seleccionado
 *   es uno de sus extremos. Las etiquetas de puerto también se vuelven más visibles.
 */
import { useMemo }       from 'react'
import { Line, Text }    from '@react-three/drei'
import { Vector3 }       from 'three'

/**
 * @param {{
 *   points:          import('three').Vector3[],
 *   color:           string,
 *   lineWidth:       number,
 *   opacity:         number,
 *   isActive?:       boolean,
 *   sourcePort?:     string|null,
 *   targetPort?:     string|null,
 *   showPortLabels?: boolean,
 * }} props
 */
export function ConnectionLine({
    points,
    color,
    lineWidth      = 1.0,
    opacity        = 0.4,
    isActive       = false,
    sourcePort     = null,
    targetPort     = null,
    showPortLabels = false,
}) {
    // ── Etiqueta en el punto medio ────────────────────────────────────────
    const portLabel = useMemo(() => {
        if (!sourcePort && !targetPort) return null
        const parts = [sourcePort, targetPort].filter(Boolean)
        return parts.length === 2 ? `${parts[0]} ↔ ${parts[1]}` : parts[0]
    }, [sourcePort, targetPort])

    const midPos = useMemo(() => {
        if (!portLabel || !showPortLabels) return null
        const p0 = points[0]
        const p1 = points[points.length - 1]
        if (!p0 || !p1) return null
        const mid = new Vector3().lerpVectors(p0, p1, 0.5)
        mid.y += 0.55   // elevar ligeramente sobre la línea
        return mid
    }, [portLabel, showPortLabels, points])

    // ── Estilo de línea según estado activo ──────────────────────────────
    const effectiveOpacity   = isActive ? Math.min(1, opacity * 2.2) : opacity
    const effectiveLineWidth = isActive ? lineWidth * 2.0             : lineWidth
    // Etiqueta más visible cuando la conexión está activa
    const labelOpacity       = isActive ? 1.0 : 0.70
    const labelSize          = isActive ? 0.60 : 0.50

    return (
        <group>
            <Line
                points={points}
                color={color}
                lineWidth={effectiveLineWidth}
                opacity={effectiveOpacity}
                transparent
            />

            {/* ── Etiqueta de puerto en el punto medio ─── */}
            {midPos && portLabel && (
                <Text
                    position={[midPos.x, midPos.y, midPos.z]}
                    fontSize={labelSize}
                    color={color}
                    fillOpacity={labelOpacity}
                    anchorX="center"
                    anchorY="bottom"
                    outlineWidth={0.06}
                    outlineColor="#ffffff"
                    outlineOpacity={0.85}
                    depthOffset={-1}
                >
                    {portLabel}
                </Text>
            )}
        </group>
    )
}
