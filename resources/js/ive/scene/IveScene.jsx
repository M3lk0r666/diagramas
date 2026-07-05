/**
 * IVE — Escena principal
 *
 * FASE 4 (estado actual):
 *   ✓ IsometricCamera (OrthographicCamera)
 *   ✓ CameraControls (OrbitControls con restricciones isométricas)
 *   ✓ SceneEnvironment (luces + grid)
 *   ✓ AreaPlane por área (suelo coloreado)
 *   ✓ DeviceBox por dispositivo (caja con color de rol, clic para seleccionar)
 *   ✓ ConnectionLayer (líneas entre dispositivos)
 *   ✓ Deselección al clic en fondo
 *
 * TODO Fase 5: DeviceBox → modelos GLB + troika labels
 * TODO Fase 7: ConnectionLayer → curvas Bezier / Manhattan
 */
import { useEffect }               from 'react'
import { useThree }                from '@react-three/fiber'
import { useIveStore }             from '@ive/core/store/useIveStore'
import { IsometricCamera }         from './camera/IsometricCamera'
import { CameraControls }          from './camera/CameraControls'
import { SceneEnvironment }        from './environment/SceneEnvironment'
import { DeviceNode }              from './nodes/DeviceNode'
import { AreaPlane }               from './areas/AreaPlane'
import { ConnectionLayer }         from './connections/ConnectionLayer'
import { InstancedChassisLayer }   from './nodes/InstancedChassisLayer'
import { LODManager }              from './nodes/LODManager'

/**
 * Fuerza un frame de render cuando cambia cualquier toggle de visibilidad.
 * frameloop="demand" no detecta cambios de estado Zustand originados desde
 * botones HTML externos al canvas — sin esto los toggles necesitan que muevas
 * el mouse para que el canvas se redibuje.
 */
function StoreInvalidator() {
    const { invalidate }  = useThree()
    const showLabels      = useIveStore(s => s.showLabels)
    const showConnections = useIveStore(s => s.showConnections)
    const showAreas       = useIveStore(s => s.showAreas)
    const showTraffic     = useIveStore(s => s.showTraffic)
    const darkMode        = useIveStore(s => s.darkMode)
    const detailLevel     = useIveStore(s => s.detailLevel)
    const viewMode        = useIveStore(s => s.viewMode)
    const selectedNode    = useIveStore(s => s.selectedNode)
    useEffect(() => { invalidate() }, [showLabels, showConnections, showAreas, showTraffic, darkMode, detailLevel, viewMode, selectedNode, invalidate])
    return null
}

/** Cambia el fondo del canvas Three.js cuando cambia el modo oscuro. */
function SceneBackground() {
    const darkMode = useIveStore(s => s.darkMode)
    return <color attach="background" args={[darkMode ? '#0f172a' : '#f1f5f9']} />
}

// Wireframes de espera mientras el fetch no ha terminado
function LoadingPlaceholder() {
    return (
        <>
            {[-4, 0, 4].map(x =>
                [0, 6].map(z => (
                    <mesh key={`${x}-${z}`} position={[x, 0.6, z]}>
                        <boxGeometry args={[2, 1.2, 2]} />
                        <meshStandardMaterial color="#e5e7eb" wireframe />
                    </mesh>
                ))
            )}
        </>
    )
}

// Plano invisible gigante para capturar clic en "fondo vacío" → deseleccionar
function BackgroundPlane() {
    const closePanel = useIveStore(s => s.closePanel)
    return (
        <mesh
            position={[0, -0.05, 0]}
            rotation={[-Math.PI / 2, 0, 0]}
            onClick={() => closePanel()}
        >
            <planeGeometry args={[20000, 20000]} />
            <meshBasicMaterial transparent opacity={0} depthWrite={false} />
        </mesh>
    )
}

export function IveScene() {
    const sceneGraph = useIveStore(s => s.sceneGraph)
    const showAreas  = useIveStore(s => s.showAreas)

    return (
        <>
            {/* ── Cámara ──────────────────────────────────────── */}
            <IsometricCamera />
            <CameraControls />

            {/* ── Fondo del canvas ────────────────────────────── */}
            <SceneBackground />

            {/* ── Ambiente ────────────────────────────────────── */}
            <SceneEnvironment />

            {/* ── Plano de fondo (deselección) ────────────────── */}
            <BackgroundPlane />

            {/* ── Invalidador de toggles externos ─────────────── */}
            <StoreInvalidator />

            {/* ── LOD dinámico (Fase 10) ───────────────────────── */}
            <LODManager />

            {/* ── Contenido dinámico ──────────────────────────── */}
            {sceneGraph ? (
                <>
                    {/* Chasis instanciados — 1 draw call para todos (Fase 10) */}
                    <InstancedChassisLayer />

                    {/* Áreas */}
                    {sceneGraph.areas.map(area => (
                        <group key={area.id}>
                            {showAreas && <AreaPlane area={area} />}
                            {area.devices.map(device => (
                                <DeviceNode key={device.id} device={device} />
                            ))}
                        </group>
                    ))}

                    {/* Conexiones */}
                    <ConnectionLayer />
                </>
            ) : (
                <LoadingPlaceholder />
            )}
        </>
    )
}
