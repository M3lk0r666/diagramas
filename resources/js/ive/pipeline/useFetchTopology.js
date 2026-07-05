/**
 * IVE — Hook de carga de datos
 *
 * Pipeline:
 *   fetch(endpoint)
 *     → JSON crudo
 *     → adaptTopology()       → rawGraph (sin posiciones)
 *     → store.setRawGraph()   → guardado para cambios de layout posteriores
 *     → structuredClone()     → clon fresco
 *     → applyLayout(mode)     → posiciones asignadas
 *     → store.setSceneGraph() → publicado al renderer
 *
 * Helpers de cámara (computeSceneCenter / computeFitZoom) viven en
 * LayoutEngine para poder reutilizarse desde setLayoutMode() del store.
 */
import { useEffect, useState } from 'react'
import { adaptTopology }       from './TopologyAdapter'
import { applyLayout, computeSceneCenter, computeFitZoom } from './LayoutEngine'
import { useIveStore }         from '@ive/core/store/useIveStore'

/**
 * @param {string|undefined} endpoint - URL del endpoint JSON
 * @returns {'idle'|'loading'|'ready'|'error'} status
 */
export function useFetchTopology(endpoint) {
    const [status, setStatus] = useState('idle')

    useEffect(() => {
        if (!endpoint) return

        let cancelled = false
        setStatus('loading')

        fetch(endpoint, {
            headers: {
                'Accept':           'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status} – ${res.url}`)
                return res.json()
            })
            .then(json => {
                if (cancelled) return

                const store = useIveStore.getState()

                // 1. Adapter: JSON crudo → SceneGraph tipado (sin posiciones)
                const rawGraph = adaptTopology(json, { endpoint })

                // 2. Guardar el grafo limpio para poder re-layoutear sin re-fetch
                store.setRawGraph(rawGraph)

                // 3. Clonar y aplicar layout con el modo actual del store
                const graph = structuredClone(rawGraph)
                applyLayout(graph, store.layoutMode)

                // 4. Zoom y target para encuadrar la escena
                store.setCameraZoom(computeFitZoom(graph))
                store.setCameraTarget(computeSceneCenter(graph))

                // 5. Publicar al renderer
                store.setSceneGraph(graph)

                // 6. Animar cámara al centro de la escena recién cargada.
                //    Mismo mecanismo que el botón "Centrar" y setLayoutMode():
                //    activa cameraResetPending → CameraControls inicia el lerp
                //    (exponential-decay) hacia cameraZoom + cameraTarget actuales.
                store.triggerCameraReset()

                if (!cancelled) setStatus('ready')
            })
            .catch(err => {
                if (cancelled) return
                console.error('[IVE useFetchTopology]', err)
                setStatus('error')
            })

        return () => { cancelled = true }
    }, [endpoint])

    return status
}
