/**
 * IVE — Ambiente de la escena
 *
 * Luces + grid de referencia.
 * Fase 12: reemplazar con HDRI / Environment de drei si se desea.
 */
import { Grid } from '@react-three/drei'

export function SceneEnvironment() {
    return (
        <>
            {/* Luz ambiental suave */}
            <ambientLight intensity={0.85} />

            {/* Luz principal desde arriba-derecha (isométrico: dirección [1, 2, 1]) */}
            <directionalLight
                position={[30, 60, 30]}
                intensity={0.55}
                castShadow={false}
            />

            {/* Relleno lateral izquierdo — reduce contraste duro */}
            <directionalLight
                position={[-20, 30, -20]}
                intensity={0.18}
                color="#dde8ff"
            />

            {/* Grid de suelo */}
            <Grid
                position={[0, -0.01, 0]}
                args={[500, 500]}
                cellSize={5}
                cellThickness={0.5}
                cellColor="#d1d5db"
                sectionSize={25}
                sectionThickness={1}
                sectionColor="#9ca3af"
                fadeDistance={400}
                fadeStrength={1}
                followCamera={false}
                infiniteGrid
            />
        </>
    )
}
