/**
 * IVE — DeviceBox (switch 1U visual)
 *
 * Renderiza en espacio LOCAL (origen = base del dispositivo).
 * La posición en el mundo la gestiona DeviceNode.
 *
 * Orientación isométrica:
 *   Cámara en [80,80,80] → ve caras +X, +Y, +Z.
 *   Panel frontal (puertos + LED) va en cara +Z para que sea visible.
 *
 * Fase 10 — Optimización:
 *
 *   CHASSIS_GEO (estático, exportado)
 *     BoxGeometry compartida entre:
 *       • InstancedChassisLayer  → visual de todos los chasis (1 draw call)
 *       • DeviceNode hit box     → target invisible de raycast/click
 *       • DeviceBox chassis      → sólo para dispositivo SELECCIONADO (con emissive)
 *     BVH se construye lazily en ensureBVH() tras el primer mount, cuando el
 *     parche main.jsx ya está en el prototipo de BufferGeometry.
 *
 *   PORT_GEO (estático)
 *     12 BoxGeometry de puertos mergeadas en 1 sola BufferGeometry.
 *     Reduce draw calls de puertos de 12N → N (178 menos para 179 devices).
 *
 *   showChassis (prop, default false)
 *     Si false, el chasis lo dibuja InstancedChassisLayer; aquí lo omitimos.
 *     Si true (solo cuando isSelected), se dibuja con emissive completo.
 *
 * Fase 12 — Animaciones de selección:
 *   • SpinRing   → borde punteado rectangular que gira infinitamente al seleccionar
 *                  (reemplaza PulseRing)
 *   • PulseLED   → LED titila al seleccionar
 */
import { useRef, useEffect, useMemo } from 'react'
import { useFrame }                   from '@react-three/fiber'
import { BoxGeometry }                from 'three'
import { Line }                       from '@react-three/drei'
import { mergeGeometries }            from 'three/examples/jsm/utils/BufferGeometryUtils.js'

// ── Dimensiones (exportadas para DeviceLabel, DeviceNode e InstancedChassisLayer) ─
export const BOX_W = 3.2    // Ancho
export const BOX_H = 0.55   // Alto  (muy plano — 1U rack)
export const BOX_D = 1.6    // Profundidad

// ── Paleta de roles ───────────────────────────────────────────────────────────
export const ROLE_ACCENT = {
    core:         '#ef4444',
    backbone:     '#f97316',
    distribution: '#8b5cf6',
    access:       '#3b82f6',
    unknown:      '#6b7280',
}

const CHASSIS_COLOR = '#1e293b'   // Slate-900
const FRONT_COLOR   = '#0f172a'   // Slate-950 (panel frontal)
const PORT_COLOR    = '#0f4c75'   // Azul oscuro visible contra el panel negro

const PORTS_PER_ROW = 6
const PORT_ROWS     = 2
const PORT_W        = 0.25
const PORT_H        = 0.11
const PORT_GAP_X    = 0.40
const PORT_GAP_Y    = 0.17

// ── Geometrías estáticas (una instancia por proceso, compartidas) ─────────────

/**
 * Chasis principal — compartido entre InstancedChassisLayer, hit box de DeviceNode
 * y DeviceBox (para el dispositivo seleccionado).
 * BVH se calcula después del mount (ver ensureBVH).
 */
export const CHASSIS_GEO = new BoxGeometry(BOX_W, BOX_H, BOX_D)

/**
 * 12 puertos mergeados en una sola BufferGeometry.
 * Comparte posición relativa al origen de DeviceBox.
 */
export const PORT_GEO = (() => {
    const template = new BoxGeometry(PORT_W, PORT_H, 0.04)
    const geos     = Array.from({ length: PORT_ROWS * PORTS_PER_ROW }, (_, i) => {
        const row = Math.floor(i / PORTS_PER_ROW)
        const col = i % PORTS_PER_ROW
        const geo = template.clone()
        geo.translate(
            (col - (PORTS_PER_ROW - 1) / 2) * PORT_GAP_X,
            BOX_H / 2 + (row - (PORT_ROWS - 1) / 2) * PORT_GAP_Y,
            BOX_D / 2 + 0.02,
        )
        return geo
    })
    const merged = mergeGeometries(geos, false)
    template.dispose()
    geos.forEach(g => g.dispose())
    return merged
})()

// ── BVH lazy (se ejecuta UNA sola vez tras el primer mount) ──────────────────
/**
 * Construye el BVH sobre CHASSIS_GEO y PORT_GEO.
 * Se llama desde useEffect del primer DeviceBox que monta, momento en el que
 * los parches de main.jsx ya están aplicados sobre BufferGeometry.prototype.
 */
let _bvhReady = false
function ensureBVH() {
    if (_bvhReady) return
    if (typeof CHASSIS_GEO.computeBoundsTree !== 'function') return  // parche no listo aún
    _bvhReady = true
    CHASSIS_GEO.computeBoundsTree()
    PORT_GEO.computeBoundsTree()
}

// ── Borde "marching ants" al seleccionar ─────────────────────────────────────
/**
 * SpinRing — rectángulo de línea punteada con animación de "serpiente continua"
 * (marching ants).
 *
 * Técnica: el rectángulo NO rota. En cambio, animamos `material.dashOffset`
 * en useFrame para que los guiones parezcan desplazarse continuamente a lo
 * largo del perímetro del rectángulo, como los punteados de selección de
 * herramientas gráficas.
 *
 *   dashOffset -= delta * SPEED   →  los guiones avanzan en la dirección
 *                                    de recorrido de los vértices
 *
 * Perím. aprox. = 2*(BOX_W*1.5 + BOX_D*1.7) ≈ 15 u
 * Period de dash = dashSize + gapSize = 0.40 + 0.28 = 0.68 u
 * Guiones totales ≈ 22 → un ciclo completo con SPEED=8 tarda ≈ 1.9 s.
 */
function SpinRing({ accent }) {
    const lineRef = useRef()   // ← ref al LineSegments2 de drei

    // Vértices del rectángulo ligeramente mayor que el chasis (estáticos)
    const pts = useMemo(() => {
        const hw = BOX_W * 0.75
        const hd = BOX_D * 0.85
        return [
            [-hw, 0, -hd],
            [ hw, 0, -hd],
            [ hw, 0,  hd],
            [-hw, 0,  hd],
            [-hw, 0, -hd],   // cerrar el bucle
        ]
    }, [])

    useFrame((state, delta) => {
        const mat = lineRef.current?.material
        if (!mat) return
        mat.dashOffset -= delta * 8   // velocidad de desplazamiento (u/s)
        state.invalidate()
    })

    return (
        <group position={[0, 0.06, 0]}>
            <Line
                ref={lineRef}
                points={pts}
                color={accent}
                lineWidth={2.2}
                dashed
                dashScale={1.0}
                dashSize={0.40}
                gapSize={0.28}
                opacity={0.95}
                transparent
            />
        </group>
    )
}

// ── LED de estado animado ─────────────────────────────────────────────────────
function PulseLED({ color, isActive }) {
    const matRef = useRef()

    useFrame((state) => {
        if (!matRef.current || !isActive) return
        const t = state.clock.getElapsedTime()
        matRef.current.emissiveIntensity = 2.0 + Math.sin(t * 4.0) * 0.8
        state.invalidate()
    })

    return (
        <mesh position={[-(BOX_W / 2 - 0.28), BOX_H - 0.12, BOX_D / 2 + 0.07]}>
            <sphereGeometry args={[0.07, 10, 10]} />
            <meshStandardMaterial
                ref={matRef}
                color={isActive ? color : '#22c55e'}
                emissive={isActive ? color : '#22c55e'}
                emissiveIntensity={isActive ? 2.5 : 1.5}
                roughness={0.1}
                metalness={0.0}
            />
        </mesh>
    )
}

// ── Componente principal ──────────────────────────────────────────────────────
/**
 * @param {{
 *   device:      import('../../core/types/scene').DeviceNode,
 *   isSelected:  boolean,
 *   isHovered:   boolean,
 *   showChassis: boolean,   — false = InstancedChassisLayer lo dibuja
 * }} props
 */
export function DeviceBox({ device, isSelected = false, isHovered = false, showChassis = false }) {
    const accent = ROLE_ACCENT[device.role] ?? ROLE_ACCENT.unknown

    // BVH lazy — sólo el primer DeviceBox que monta lo inicializa
    useEffect(() => { ensureBVH() }, [])

    return (
        <group>

            {/* ── 1. Chasis principal ─────────────────────────────────────────
                Dibujado sólo cuando isSelected (con emissive de acento).
                Para el resto lo renderiza InstancedChassisLayer (1 draw call). */}
            {showChassis && (
                <mesh position={[0, BOX_H / 2, 0]}>
                    <primitive object={CHASSIS_GEO} attach="geometry" />
                    <meshStandardMaterial
                        color={CHASSIS_COLOR}
                        emissive={accent}
                        emissiveIntensity={isSelected ? 0.20 : isHovered ? 0.10 : 0.04}
                        roughness={0.45}
                        metalness={0.60}
                    />
                </mesh>
            )}

            {/* ── 2. Stripe de rol en la tapa (cara +Y) ───────── */}
            <mesh position={[0, BOX_H + 0.015, 0]}>
                <boxGeometry args={[BOX_W + 0.02, 0.03, BOX_D + 0.02]} />
                <meshStandardMaterial
                    color={accent}
                    emissive={accent}
                    emissiveIntensity={isSelected ? 0.9 : 0.45}
                    roughness={0.3}
                    metalness={0.1}
                />
            </mesh>

            {/* ── 3. Panel frontal oscuro (cara +Z) ───────────── */}
            <mesh position={[0, BOX_H / 2, BOX_D / 2 - 0.03]}>
                <boxGeometry args={[BOX_W - 0.08, BOX_H - 0.06, 0.06]} />
                <meshStandardMaterial color={FRONT_COLOR} roughness={0.6} metalness={0.4} />
            </mesh>

            {/* ── 4. Puertos (PORT_GEO = 12 boxes mergeados → 1 draw call) ── */}
            <mesh>
                <primitive object={PORT_GEO} attach="geometry" />
                <meshStandardMaterial
                    color={PORT_COLOR}
                    emissive={PORT_COLOR}
                    emissiveIntensity={isSelected ? 0.8 : 0.35}
                    roughness={0.6}
                    metalness={0.3}
                />
            </mesh>

            {/* ── 5. LED de estado — animado al seleccionar ────── */}
            <PulseLED color={accent} isActive={isSelected} />

            {/* ── 6. Placa base (color de rol, semitransparente) ── */}
            <mesh position={[0, 0.02, 0]}>
                <boxGeometry args={[BOX_W + 0.3, 0.04, BOX_D + 0.3]} />
                <meshStandardMaterial
                    color={accent}
                    opacity={isSelected ? 0.50 : 0.18}
                    transparent
                    depthWrite={false}
                />
            </mesh>

            {/* ── 7. Borde punteado giratorio (selección activa) ── */}
            {isSelected && <SpinRing accent={accent} />}

        </group>
    )
}
