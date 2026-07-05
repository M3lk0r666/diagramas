/**
 * IVE — App root
 *
 * Capas (de atrás hacia adelante):
 *   1. Canvas RTF (WebGL) — IveScene + ScreenshotCapture
 *   2. Minimap (HTML canvas 2D, esquina inferior-izquierda)
 *   3. DevicePanel (drawer HTML derecho)
 *   4. Toolbar (barra HTML superior)
 */
import { Suspense, useEffect }  from 'react'
import { Canvas }               from '@react-three/fiber'
import { useThree }             from '@react-three/fiber'
import { useIveStore }          from '@ive/core/store/useIveStore'
import { IveScene }             from './scene/IveScene'
import { Toolbar }              from './ui/Toolbar'
import { DevicePanel }          from './ui/DevicePanel'
import { Minimap }              from './ui/Minimap'
import { useFetchTopology }     from './pipeline/useFetchTopology'
import { captureRef }           from './pipeline/captureRef'

const FILL = { position: 'absolute', inset: 0, width: '100%', height: '100%' }

/**
 * Registra la función de captura PNG en captureRef.
 * Vive DENTRO del Canvas para acceder a gl, scene y camera mediante useThree().
 * Con frameloop="demand", forzamos un render antes de llamar a toBlob().
 */
function ScreenshotCapture({ clientName }) {
    const { gl, scene, camera } = useThree()

    useEffect(() => {
        captureRef.current = () => {
            // Forzar un frame antes de leer el buffer
            gl.render(scene, camera)
            gl.domElement.toBlob(blob => {
                if (!blob) return
                const url  = URL.createObjectURL(blob)
                const a    = document.createElement('a')
                const name = (clientName ?? 'ive').replace(/\s+/g, '-').toLowerCase()
                a.href     = url
                a.download = `ive-${name}-${Date.now()}.png`
                document.body.appendChild(a)
                a.click()
                document.body.removeChild(a)
                URL.revokeObjectURL(url)
            }, 'image/png')
        }
        return () => { captureRef.current = null }
    }, [gl, scene, camera, clientName])

    return null
}

export default function App({ config = {} }) {
    const status    = useFetchTopology(config.endpoints?.global)
    const darkMode  = useIveStore(s => s.darkMode)
    const showMinimap = useIveStore(s => s.showMinimap)

    const bg = darkMode ? '#0f172a' : '#f1f5f9'

    return (
        <div style={{ position: 'relative', width: '100%', height: '100%', background: bg, overflow: 'hidden' }}>

            {/* ── Canvas WebGL ────────────────────────────────── */}
            <Canvas
                style={FILL}
                gl={{
                    antialias:              true,
                    logarithmicDepthBuffer: true,
                    powerPreference:        'high-performance',
                }}
                dpr={[1, 2]}
                frameloop="demand"
            >
                <Suspense fallback={null}>
                    <IveScene />
                </Suspense>
                <ScreenshotCapture clientName={config.clientName} />
            </Canvas>

            {/* ── UI HTML (sobre el canvas) ────────────────────── */}
            {showMinimap && <Minimap />}
            <DevicePanel />
            <Toolbar config={config} status={status} />

        </div>
    )
}
