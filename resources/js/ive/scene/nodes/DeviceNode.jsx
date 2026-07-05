/**
 * IVE — DeviceNode (contenedor inteligente)
 *
 * Fase 12 — Tooltip expandible:
 *   Al seleccionar un dispositivo aparece una tarjeta HTML compacta
 *   posicionada a la derecha y encima del modelo. Sin distanceFactor
 *   para evitar el escalado incorrecto en OrthographicCamera.
 *   Contiene: badge de rol · nombre · IP siempre visibles.
 *   Botón "▼ Más info" despliega modelo y MAC.
 *
 * Fase 9 — Animación de escala en hover/select.
 * Fase 10 — Hit box invisible, LOD para labels.
 */
import { useState, useEffect, useRef, Suspense } from 'react'
import { useFrame }                from '@react-three/fiber'
import { Html }                    from '@react-three/drei'
import { MathUtils }               from 'three'
import { useIveStore }             from '@ive/core/store/useIveStore'
import { DeviceBox, BOX_W, BOX_H, CHASSIS_GEO, ROLE_ACCENT } from './DeviceBox'
import { DeviceLabel }             from './DeviceLabel'
import { DeviceModel }             from './DeviceModel'
import { getModelPath }            from '../models/ModelRegistry'

const SCALE_DEFAULT  = 1.00
const SCALE_HOVERED  = 1.04
const SCALE_SELECTED = 1.08
const LERP_SPEED     = 0.18

// ── Tooltip expandible ─────────────────────────────────────────────────────────
/**
 * Tarjeta HTML anclada al dispositivo seleccionado.
 *
 * SIN distanceFactor: con OrthographicCamera el cálculo de escala de drei
 * puede dar valores muy grandes. Sin el prop, el div se renderiza a su tamaño
 * CSS natural (pixeles fijos), lo cual es el comportamiento correcto para un
 * tooltip de información.
 */
function DeviceTooltip({ device }) {
    const [expanded, setExpanded] = useState(false)
    const accent = ROLE_ACCENT[device.role] ?? ROLE_ACCENT.unknown

    return (
        <Html
            position={[BOX_W * 0.65, BOX_H + 0.3, 0]}
            zIndexRange={[200, 50]}
            style={{ pointerEvents: 'none' }}
        >
            {/* Wrapper centrado hacia arriba desde el punto de anclaje */}
            <div style={{ transform: 'translate(4px, -50%)' }}>
                <div style={{
                    background:    '#ffffff',
                    border:        `2px solid ${accent}`,
                    borderRadius:  7,
                    padding:       '5px 9px',
                    width:         160,
                    boxShadow:     '0 3px 12px rgba(0,0,0,0.18)',
                    fontFamily:    'system-ui, -apple-system, sans-serif',
                    fontSize:      11,
                    lineHeight:    1.45,
                    pointerEvents: 'auto',
                    userSelect:    'none',
                }}>
                    {/* Encabezado: badge rol + nombre */}
                    <div style={{ display:'flex', alignItems:'center', gap:5, marginBottom:3 }}>
                        <span style={{
                            background:    accent,
                            color:         '#fff',
                            borderRadius:  3,
                            padding:       '1px 4px',
                            fontSize:      9,
                            fontWeight:    700,
                            textTransform: 'uppercase',
                            letterSpacing: '0.04em',
                            flexShrink:    0,
                        }}>
                            {device.role}
                        </span>
                        <span style={{
                            fontWeight:   700,
                            color:        '#0f172a',
                            fontSize:     11,
                            overflow:     'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace:   'nowrap',
                            maxWidth:     105,
                        }}>
                            {device.label}
                        </span>
                    </div>

                    {/* IP siempre visible */}
                    {device.ip && (
                        <div style={{ color:'#475569', fontSize:10 }}>
                            IP: {device.ip}
                        </div>
                    )}

                    {/* Detalles expandibles */}
                    {expanded && (
                        <div style={{ borderTop:`1px solid ${accent}22`, marginTop:4, paddingTop:4 }}>
                            {device.model && (
                                <div style={{ color:'#64748b', fontSize:10 }}>
                                    Modelo: {device.model}
                                </div>
                            )}
                            {device.mac && (
                                <div style={{ color:'#64748b', fontSize:9, fontFamily:'monospace', marginTop:1 }}>
                                    MAC: {device.mac}
                                </div>
                            )}
                            {!device.model && !device.mac && (
                                <div style={{ color:'#94a3b8', fontSize:10, fontStyle:'italic' }}>
                                    Sin datos adicionales
                                </div>
                            )}
                        </div>
                    )}

                    {/* Botón expandir/colapsar */}
                    <button
                        onClick={(e) => { e.stopPropagation(); setExpanded(v => !v) }}
                        style={{
                            marginTop:   5,
                            display:     'block',
                            width:       '100%',
                            background:  expanded ? '#f1f5f9' : accent,
                            color:       expanded ? '#475569' : '#fff',
                            border:      'none',
                            borderRadius: 4,
                            padding:     '3px 0',
                            fontSize:    10,
                            cursor:      'pointer',
                            fontWeight:  600,
                            textAlign:   'center',
                            lineHeight:  1.4,
                        }}
                    >
                        {expanded ? '▲ Ver menos' : '▼ Más info'}
                    </button>
                </div>
            </div>
        </Html>
    )
}

// ── Componente principal ───────────────────────────────────────────────────────
/**
 * @param {{ device: import('../../core/types/scene').DeviceNode }} props
 */
export function DeviceNode({ device }) {
    const [hovered, setHovered] = useState(false)

    const selectNode   = useIveStore(s => s.selectNode)
    const selectedNode = useIveStore(s => s.selectedNode)
    const showLabels   = useIveStore(s => s.showLabels)
    const detailLevel  = useIveStore(s => s.detailLevel)
    const isSelected   = selectedNode?.id === device.id

    const [x, , z] = device.position
    const modelPath = getModelPath(device.deviceType)

    // ── Animación de escala ──────────────────────────────────────────────────
    const groupRef   = useRef()
    const scaleCur   = useRef(SCALE_DEFAULT)
    const animActive = useRef(false)

    useEffect(() => { animActive.current = true }, [isSelected, hovered])

    useFrame((state, delta) => {
        if (!animActive.current || !groupRef.current) return
        const target = isSelected ? SCALE_SELECTED : hovered ? SCALE_HOVERED : SCALE_DEFAULT
        const next   = MathUtils.lerp(scaleCur.current, target, LERP_SPEED)
        scaleCur.current = next
        groupRef.current.scale.setScalar(next)
        if (Math.abs(next - target) > 0.001) {
            state.invalidate()
        } else {
            groupRef.current.scale.setScalar(target)
            scaleCur.current   = target
            animActive.current = false
        }
    })

    // ── Handlers ─────────────────────────────────────────────────────────────
    const handleClick = (e) => {
        e.stopPropagation()
        selectNode(isSelected ? null : device)
    }
    const handlePointerOver = (e) => {
        e.stopPropagation()
        setHovered(true)
        document.body.style.cursor = 'pointer'
    }
    const handlePointerOut = () => {
        setHovered(false)
        document.body.style.cursor = 'default'
    }

    const labelsVisible = showLabels && detailLevel !== 'low'

    return (
        <group
            ref={groupRef}
            position={[x, 0, z]}
            onClick={handleClick}
            onPointerOver={handlePointerOver}
            onPointerOut={handlePointerOut}
        >
            {/* Hit box invisible */}
            <mesh position={[0, BOX_H / 2, 0]}>
                <primitive object={CHASSIS_GEO} attach="geometry" />
                <meshBasicMaterial transparent opacity={0} depthWrite={false} />
            </mesh>

            {/* Visual */}
            {modelPath ? (
                <Suspense fallback={
                    <DeviceBox device={device} isSelected={isSelected} isHovered={hovered} showChassis={isSelected} />
                }>
                    <DeviceModel device={device} path={modelPath} />
                </Suspense>
            ) : (
                <DeviceBox
                    device={device}
                    isSelected={isSelected}
                    isHovered={hovered}
                    showChassis={isSelected}
                />
            )}

            {/* Label Troika */}
            {labelsVisible && <DeviceLabel device={device} boxHeight={BOX_H} />}

            {/* Tooltip HTML — solo cuando seleccionado */}
            {isSelected && <DeviceTooltip device={device} />}
        </group>
    )
}
