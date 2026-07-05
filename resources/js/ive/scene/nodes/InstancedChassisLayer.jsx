/**
 * IVE — InstancedChassisLayer (Fase 10)
 *
 * Renderiza el chasis de TODOS los dispositivos como un único InstancedMesh,
 * reduciendo los draw calls del chasis de N → 1.
 *
 * Principios de diseño:
 *
 *   • Sin event handlers  — no participa en el raycast de RTF.
 *     El hit-detection lo gestiona DeviceNode a través de su hit box invisible.
 *
 *   • Dispositivo SELECCIONADO — se escala a 0.0001 (invisible).
 *     DeviceBox lo sustituye con un chasis individual que tiene emissive completo.
 *
 *   • Geometría compartida — usa CHASSIS_GEO de DeviceBox (mismo objeto que el
 *     hit box de DeviceNode), con el BVH ya computado.
 *
 *   • Se actualiza mediante useEffect cuando cambia sceneGraph o selectedNode.
 *     La llamada a invalidate() asegura que frameloop="demand" redibuje tras el update.
 *
 * Draw call math (antes → después):
 *   Antes : 1 chasis/device × N devices = N draw calls
 *   Después: 1 InstancedMesh             = 1 draw call   (ahorro: N-1)
 */
import { useRef, useMemo, useEffect } from 'react'
import { useThree }                   from '@react-three/fiber'
import { Object3D }                   from 'three'
import { useIveStore }                from '@ive/core/store/useIveStore'
import { CHASSIS_GEO, BOX_H }        from './DeviceBox'

const CHASSIS_MATERIAL_COLOR = '#1e293b'   // Slate-900 uniforme — el emissive lo añade DeviceBox

/** Reutilizable para calcular matrices de instancia (nunca montado en el DOM) */
const _dummy = new Object3D()

export function InstancedChassisLayer() {
    const meshRef    = useRef()
    const { invalidate } = useThree()

    const sceneGraph   = useIveStore(s => s.sceneGraph)
    const selectedNode = useIveStore(s => s.selectedNode)

    // Aplanar todos los dispositivos de todas las áreas
    const devices = useMemo(
        () => sceneGraph?.areas.flatMap(a => a.devices) ?? [],
        [sceneGraph],
    )

    // Actualizar matrices de instancia cuando cambia sceneGraph o selectedNode
    useEffect(() => {
        const mesh = meshRef.current
        if (!mesh || devices.length === 0) return

        const selectedId = selectedNode?.id ?? null

        devices.forEach((device, i) => {
            const [x, , z] = device.position
            const isSelected = device.id === selectedId

            _dummy.position.set(x, BOX_H / 2, z)
            // El seleccionado lo renderiza DeviceBox con emissive → lo ocultamos aquí
            _dummy.scale.setScalar(isSelected ? 0.0001 : 1)
            _dummy.updateMatrix()
            mesh.setMatrixAt(i, _dummy.matrix)
        })

        mesh.instanceMatrix.needsUpdate = true
        invalidate()
    }, [devices, selectedNode, invalidate])

    if (devices.length === 0) return null

    return (
        /*
         * args={[geometry, material, count]}
         *   geometry y material se sobreescriben con los children JSX (RTF).
         *   count fija el tamaño del buffer de matrices de instancia.
         *   key={devices.length} fuerza remount si la topología cambia de tamaño.
         */
        <instancedMesh
            ref={meshRef}
            key={devices.length}
            args={[CHASSIS_GEO, undefined, devices.length]}
            frustumCulled={false}   // el InstancedMesh tiene AABB global — evitar culling prematuro
        >
            <meshStandardMaterial
                color={CHASSIS_MATERIAL_COLOR}
                roughness={0.45}
                metalness={0.60}
            />
        </instancedMesh>
    )
}
