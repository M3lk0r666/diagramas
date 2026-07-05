/**
 * IVE — ConnectionLayer (Fase 7 + Fase 9 + Fase 12)
 *
 * Orquesta el renderizado de todas las conexiones usando el ConnectionRouter.
 *
 * Intra-área  → líneas rectas (color por hash)
 * Inter-área  → líneas rectas desde dispositivo a dispositivo (color por hash)
 *
 * Fase 12 — Mejoras de selección:
 *   • isActive: la línea se resalta (más brillante / opaca) cuando el dispositivo
 *     seleccionado es uno de sus extremos.
 *   • TrafficDot: solo se anima en conexiones del dispositivo seleccionado.
 *     Si no hay selección → sin partículas de tráfico.
 *
 * Lee el SceneGraph del store directamente (no recibe props de datos).
 * El ConnectionRouter hace todos los cálculos de geometría.
 */
import { useMemo }        from 'react'
import { useIveStore }    from '@ive/core/store/useIveStore'
import { ConnectionLine } from './ConnectionLine'
import { TrafficDot }     from './TrafficDot'
import {
    routeIntraConnections,
    routeInterConnections,
} from '@ive/pipeline/ConnectionRouter'

export function ConnectionLayer() {
    const sceneGraph      = useIveStore(s => s.sceneGraph)
    const showConnections = useIveStore(s => s.showConnections)
    const showTraffic     = useIveStore(s => s.showTraffic)
    const selectedNode    = useIveStore(s => s.selectedNode)
    const detailLevel     = useIveStore(s => s.detailLevel)

    // Etiquetas de puerto: visibles salvo cuando el zoom es muy pequeño (low)
    const showPortLabels  = detailLevel !== 'low'

    // ── Rutas intra-área ──────────────────────────────────────────────────
    const intraRoutes = useMemo(
        () => (sceneGraph ? routeIntraConnections(sceneGraph) : []),
        [sceneGraph],
    )

    // ── Rutas inter-área ──────────────────────────────────────────────────
    const interRoutes = useMemo(
        () => (sceneGraph ? routeInterConnections(sceneGraph) : []),
        [sceneGraph],
    )

    if (!sceneGraph || !showConnections) return null

    const selId = selectedNode?.id ?? null

    /**
     * Una ruta está "activa" cuando el dispositivo seleccionado participa
     * en ella como origen o destino.
     */
    const isActive = (route) =>
        selId !== null && (route.sourceId === selId || route.targetId === selId)

    return (
        <group name="connections">

            {/* ── Intra-área ──────────────────────────────────── */}
            {intraRoutes.map(r => (
                <group key={r.key}>
                    <ConnectionLine
                        points={r.points}
                        color={r.color}
                        lineWidth={r.lineWidth}
                        opacity={r.opacity}
                        isActive={isActive(r)}
                        sourcePort={r.sourcePort}
                        targetPort={r.targetPort}
                        showPortLabels={showPortLabels}
                    />
                    {/* Tráfico: solo si hay selección y esta línea pertenece al nodo */}
                    {showTraffic && isActive(r) && (
                        <TrafficDot points={r.points} color={r.color} />
                    )}
                </group>
            ))}

            {/* ── Inter-área ──────────────────────────────────── */}
            {interRoutes.map(r => (
                <group key={r.key}>
                    <ConnectionLine
                        points={r.points}
                        color={r.color}
                        lineWidth={r.lineWidth}
                        opacity={r.opacity}
                        isActive={isActive(r)}
                        sourcePort={r.sourcePort}
                        targetPort={r.targetPort}
                        showPortLabels={showPortLabels}
                    />
                    {showTraffic && isActive(r) && (
                        <TrafficDot points={r.points} color={r.color} />
                    )}
                </group>
            ))}

        </group>
    )
}
