/**
 * IVE — DeviceModel
 *
 * Renderer de modelos GLB para dispositivos.
 * Se activa automáticamente cuando ModelRegistry.js tiene una ruta para
 * el deviceType correspondiente.
 *
 * ESTADO ACTUAL: componente stub — listo para recibir GLBs de Blender.
 *
 * CUANDO TENGAS TUS MODELOS:
 *   1. Agregar la ruta en ModelRegistry.js
 *   2. Este componente se activará automáticamente
 *   3. El fallback (DeviceBox) se mostrará mientras el GLB carga (Suspense)
 *
 * Características implementadas:
 *   - useGLTF con caché automático de drei
 *   - scene.clone() para múltiples instancias del mismo modelo
 *   - Escala configurable por tipo de dispositivo
 *   - Interacción heredada del grupo padre (DeviceNode)
 *
 * Fase 10: migrar a InstancedMesh para topologías con cientos de dispositivos.
 */
import { useMemo }   from 'react'
import { useGLTF }   from '@react-three/drei'

/**
 * Escalas por tipo de dispositivo (ajustar según el modelo Blender).
 * 1.0 = modelo a escala real (1 unidad Blender = 1 unidad IVE).
 * @type {Record<string, number>}
 */
const TYPE_SCALE = {
    switch:      1.0,
    router:      1.0,
    firewall:    1.2,
    ap:          0.8,
    server:      1.0,
    ups:         1.1,
    patch_panel: 1.0,
    unknown:     1.0,
}

/**
 * @param {{
 *   device: import('../../core/types/scene').DeviceNode,
 *   path:   string,
 * }} props
 */
export function DeviceModel({ device, path }) {
    const { scene } = useGLTF(path)

    // Clonar la escena para permitir múltiples instancias del mismo GLB
    const cloned = useMemo(() => {
        const clone = scene.clone(true)
        // Habilitar sombras en todos los meshes del modelo
        clone.traverse(child => {
            if (child.isMesh) {
                child.castShadow    = false   // cambiar a true en Fase 12
                child.receiveShadow = false
            }
        })
        return clone
    }, [scene])

    const scale = TYPE_SCALE[device.deviceType] ?? 1.0

    return (
        <group scale={[scale, scale, scale]}>
            <primitive object={cloned} />
        </group>
    )
}

// Pre-registro para useGLTF.preload() — se llama desde ModelRegistry.preloadModels()
// DeviceModel.preload = (path) => useGLTF.preload(path)
