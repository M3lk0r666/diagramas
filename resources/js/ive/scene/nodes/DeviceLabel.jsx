/**
 * IVE — DeviceLabel (Troika-three-text)
 *
 * Label 3D nativo que vive dentro del mundo Three.js.
 * Usa <Text> de @react-three/drei (wrapper de troika-three-text).
 *
 * Ventajas sobre labels HTML:
 *   ✓ Nunca se pixela (resolución independiente del zoom)
 *   ✓ Nunca se congela (no hay reflow de CSS)
 *   ✓ Escala correctamente con la cámara
 *   ✓ Vive en el mundo 3D (oclusión, depth)
 *
 * Billboard: el grupo rota para SIEMPRE mirar a la cámara.
 * Funciona correctamente con OrthographicCamera + OrbitControls.
 *
 * Posición: relativa al grupo padre DeviceNode (origen = base del dispositivo).
 */
import { Billboard, Text } from '@react-three/drei'
import { useIveStore }     from '@ive/core/store/useIveStore'

/** Altura sobre la tapa del dispositivo */
const LABEL_Y = 0.95

/** Color por rol para el texto del label */
const ROLE_TEXT_COLOR = {
    core:         '#ef4444',
    backbone:     '#f97316',
    distribution: '#8b5cf6',
    access:       '#3b82f6',
    unknown:      '#6b7280',
}

/**
 * @param {{
 *   device:    import('../../core/types/scene').DeviceNode,
 *   boxHeight: number,   — alto del DeviceBox para posicionar sobre él
 * }} props
 */
export function DeviceLabel({ device, boxHeight = 0.55 }) {
    const showLabels   = useIveStore(s => s.showLabels)
    const detailLevel  = useIveStore(s => s.detailLevel)
    const selectedNode = useIveStore(s => s.selectedNode)
    const isSelected   = selectedNode?.id === device.id

    // Fase 10 LOD: ocultar labels en vista lejana para ahorrar llamadas a Troika
    if (!showLabels || detailLevel === 'low') return null

    const labelColor = isSelected
        ? '#111827'
        : ROLE_TEXT_COLOR[device.role] ?? '#374151'

    const labelY = boxHeight + LABEL_Y

    return (
        <Billboard
            position={[0, labelY, 0]}
            follow={true}
            lockX={false}
            lockY={false}
            lockZ={false}
        >
            {/* Nombre del dispositivo */}
            <Text
                fontSize={0.55}
                color={labelColor}
                anchorX="center"
                anchorY="bottom"
                outlineWidth={0.06}
                outlineColor="#ffffff"
                outlineOpacity={0.9}
                maxWidth={7}
                overflowWrap="break-word"
                textAlign="center"
                renderOrder={10}
            >
                {device.label}
            </Text>

            {/* IP — solo visible cuando está seleccionado */}
            {isSelected && device.ip && (
                <Text
                    position={[0, -0.62, 0]}
                    fontSize={0.38}
                    color="#6b7280"
                    anchorX="center"
                    anchorY="bottom"
                    outlineWidth={0.05}
                    outlineColor="#ffffff"
                    outlineOpacity={0.85}
                    renderOrder={10}
                >
                    {device.ip}
                </Text>
            )}
        </Billboard>
    )
}
