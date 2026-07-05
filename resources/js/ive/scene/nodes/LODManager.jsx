/**
 * IVE — LODManager (Fase 10)
 *
 * Componente dentro del Canvas que detecta el nivel de detalle (LOD) adecuado
 * según el zoom actual de la cámara ortográfica y lo publica en el store.
 *
 * Niveles:
 *   'high'   → zoom ≥ 14  — vista cercana: labels, todos los detalles visibles
 *   'medium' → 6 ≤ zoom < 14  — vista media: labels visibles, geometría completa
 *   'low'    → zoom < 6   — vista lejana: labels ocultos automáticamente
 *
 * Implementación:
 *   • useFrame lee camera.zoom en cada frame (no el store, que sólo refleja el
 *     target de la animación, no el zoom real durante damping de OrbitControls)
 *   • Usa una ref local para evitar actualizaciones redundantes del store cuando
 *     el nivel no cambia (evita re-renders innecesarios de los subscribers)
 *   • La llamada a setDetailLevel es O(1) y sólo ocurre al cambiar de nivel
 *
 * LOD en la escena:
 *   • DeviceLabel → se oculta en 'low' (aporta el mayor ahorro de geometría Troika)
 *   • Resto de geometría → sin cambio (las formas básicas son baratas de renderizar)
 */
import { useRef }        from 'react'
import { useFrame }      from '@react-three/fiber'
import { useIveStore }   from '@ive/core/store/useIveStore'

const THRESHOLD_LOW    = 6     // < 6 → 'low'   (vista general toda la topología)
const THRESHOLD_MEDIUM = 14    // < 14 → 'medium'

export function LODManager() {
    const setDetailLevel = useIveStore(s => s.setDetailLevel)
    const lastLevel      = useRef('high')

    useFrame(({ camera }) => {
        const z     = camera.zoom
        const level = z < THRESHOLD_LOW    ? 'low'
                    : z < THRESHOLD_MEDIUM ? 'medium'
                    : 'high'

        if (level !== lastLevel.current) {
            lastLevel.current = level
            setDetailLevel(level)
        }
    })

    return null
}
