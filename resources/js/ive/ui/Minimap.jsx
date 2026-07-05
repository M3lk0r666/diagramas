/**
 * IVE — Minimap interactivo
 *
 * Mapa 2D de la escena en la esquina inferior-izquierda.
 * Renderizado con Canvas 2D (no WebGL) para máxima eficiencia.
 *
 * Funcionalidades:
 *   • Áreas como círculos (radial) o rectángulos (grid) coloreados
 *   • Nombre del área centrado en cada forma (7 px, canvas text)
 *   • Highlight del área al hacer hover (relleno + borde más brillante)
 *   • Tooltip HTML con el nombre completo al pasar el ratón
 *   • Click en área → cámara vuela suavemente hacia el centro del área
 *     (usa el lerp exponencial de Fase 9 en CameraControls)
 *   • Dispositivos como puntos coloreados por rol
 *   • Dispositivo seleccionado resaltado con anillo blanco
 *
 * Patrón de rendimiento:
 *   • El canvas se redibuja solo cuando cambia: sceneGraph | selectedNode |
 *     darkMode | hoveredAreaId
 *   • El tooltip y el cursor se actualizan de forma imperativa (sin setState)
 *     en cada mousemove → cero re-renders por movimiento del ratón
 *   • Solo setState para hoveredAreaId (cuando se entra/sale de un área)
 *     para disparar el redibujado del canvas con el highlight
 */
import { useEffect, useRef, useState, useCallback } from 'react'
import { useIveStore }                               from '@ive/core/store/useIveStore'

// ── Dimensiones del canvas 2D ─────────────────────────────────────────────────
const MAP_W = 200
const MAP_H = 148
const PAD   = 8

// ── Colores de rol para los puntos ───────────────────────────────────────────
const ROLE_DOT = {
    core:         '#ef4444',
    backbone:     '#f97316',
    distribution: '#8b5cf6',
    access:       '#3b82f6',
    unknown:      '#6b7280',
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Formatea un ID de área en nombre legible: "edificio-a_01" → "Edificio A 01" */
function formatAreaName(raw) {
    return String(raw ?? '')
        .replace(/[-_]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase())
}

/** Trunca con elipsis si supera el máximo de caracteres */
function truncate(str, max) {
    return str.length > max ? str.slice(0, max - 1) + '…' : str
}

// ── Componente ────────────────────────────────────────────────────────────────
export function Minimap() {
    const sceneGraph = useIveStore(s => s.sceneGraph)
    const selected   = useIveStore(s => s.selectedNode)
    const darkMode   = useIveStore(s => s.darkMode)

    // Acciones de cámara (para navegación al hacer click)
    const setCameraTarget = useIveStore(s => s.setCameraTarget)
    const setCameraZoom   = useIveStore(s => s.setCameraZoom)
    const triggerReset    = useIveStore(s => s.triggerCameraReset)

    const canvasRef  = useRef(null)
    const tooltipRef = useRef(null)

    /**
     * Regiones de colisión de áreas, reconstruidas en cada dibujo.
     * Forma: Array<{ area, worldCX, worldCZ, diameter,
     *                isCircle: true,  cx, cz, r   (círculo)
     *                isCircle: false, rx, rz, rw, rh (rect) }>
     */
    const areaRegions     = useRef([])

    /** Ref paralelo al estado para evitar setState redundantes en mousemove */
    const hoveredIdRef    = useRef(null)
    const [hoveredAreaId, setHoveredAreaId] = useState(null)

    // ── Redibujado del canvas ────────────────────────────────────────────────
    useEffect(() => {
        if (!sceneGraph || !canvasRef.current) return

        const canvas = canvasRef.current
        const ctx    = canvas.getContext('2d')
        const areas  = sceneGraph.areas.filter(a => a.bounds)
        if (!areas.length) return

        // Bounding box de la escena entera
        const minX = Math.min(...areas.map(a => a.bounds.x))
        const minZ = Math.min(...areas.map(a => a.bounds.z))
        const maxX = Math.max(...areas.map(a => a.bounds.x + a.bounds.width))
        const maxZ = Math.max(...areas.map(a => a.bounds.z + a.bounds.depth))

        const sceneW = maxX - minX || 1
        const sceneH = maxZ - minZ || 1
        const drawW  = MAP_W - PAD * 2
        const drawH  = MAP_H - PAD * 2
        const scale  = Math.min(drawW / sceneW, drawH / sceneH)
        const offX   = PAD + (drawW - sceneW * scale) / 2
        const offZ   = PAD + (drawH - sceneH * scale) / 2

        const toX = wx => offX + (wx - minX) * scale
        const toZ = wz => offZ + (wz - minZ) * scale

        // ── Fondo ──────────────────────────────────────────────────
        ctx.clearRect(0, 0, MAP_W, MAP_H)
        ctx.fillStyle = darkMode ? 'rgba(15,23,42,0.93)' : 'rgba(255,255,255,0.93)'
        ctx.beginPath()
        ctx.roundRect(0, 0, MAP_W, MAP_H, 8)
        ctx.fill()
        ctx.strokeStyle = darkMode ? '#334155' : '#e5e7eb'
        ctx.lineWidth   = 1
        ctx.stroke()

        // ── Áreas + regiones de colisión ───────────────────────────
        const regions = []

        areas.forEach(area => {
            const { x, z, width, depth, radius } = area.bounds
            const worldCX  = x + width  / 2
            const worldCZ  = z + depth  / 2
            const isHov    = area.id === hoveredAreaId
            const diameter = radius ? radius * 2 : Math.max(width, depth)

            ctx.fillStyle   = area.color + (isHov ? '44' : '26')
            ctx.strokeStyle = area.color + (isHov ? 'cc' : '80')
            ctx.lineWidth   = isHov ? 1.8 : 0.8

            if (radius) {
                // ── Área radial → círculo ─────────────────────────
                const cx = toX(worldCX)
                const cz = toZ(worldCZ)
                const r  = Math.max(radius * scale, 2)

                ctx.beginPath()
                ctx.arc(cx, cz, r, 0, Math.PI * 2)
                ctx.fill()
                ctx.stroke()

                regions.push({ area, isCircle: true, cx, cz, r, worldCX, worldCZ, diameter })

                // Nombre centrado en el círculo
                const label = truncate(formatAreaName(area.label ?? area.name ?? area.id), 9)
                ctx.save()
                ctx.font         = `${isHov ? 'bold ' : ''}6.5px system-ui,sans-serif`
                ctx.fillStyle    = darkMode ? '#94a3b8' : '#4b5563'
                ctx.textAlign    = 'center'
                ctx.textBaseline = 'middle'
                ctx.fillText(label, cx, cz)
                ctx.restore()

            } else {
                // ── Área grid → rectángulo ────────────────────────
                const rx = toX(x)
                const rz = toZ(z)
                const rw = Math.max(width  * scale, 2)
                const rh = Math.max(depth  * scale, 2)

                ctx.fillRect(rx, rz, rw, rh)
                ctx.strokeRect(rx, rz, rw, rh)

                regions.push({ area, isCircle: false, rx, rz, rw, rh, worldCX, worldCZ, diameter })

                // Nombre centrado en el rectángulo
                const label = truncate(formatAreaName(area.label ?? area.name ?? area.id), 9)
                ctx.save()
                ctx.font         = `${isHov ? 'bold ' : ''}6.5px system-ui,sans-serif`
                ctx.fillStyle    = darkMode ? '#94a3b8' : '#4b5563'
                ctx.textAlign    = 'center'
                ctx.textBaseline = 'middle'
                ctx.fillText(label, rx + rw / 2, rz + rh / 2)
                ctx.restore()
            }
        })

        areaRegions.current = regions

        // ── Dispositivos (puntos) ──────────────────────────────────
        sceneGraph.areas.forEach(area => {
            area.devices.forEach(device => {
                if (!device.position) return
                const [dx, , dz]  = device.position
                const mx          = toX(dx)
                const mz          = toZ(dz)
                const isSelected  = selected?.id === device.id

                ctx.fillStyle = ROLE_DOT[device.role] ?? ROLE_DOT.unknown
                ctx.beginPath()
                ctx.arc(mx, mz, isSelected ? 3 : 1.5, 0, Math.PI * 2)
                ctx.fill()

                if (isSelected) {
                    ctx.strokeStyle = '#ffffff'
                    ctx.lineWidth   = 1
                    ctx.stroke()
                }
            })
        })

        // ── Etiqueta "MAPA" ────────────────────────────────────────
        ctx.fillStyle    = darkMode ? '#475569' : '#9ca3af'
        ctx.font         = '9px system-ui, sans-serif'
        ctx.textAlign    = 'right'
        ctx.textBaseline = 'alphabetic'
        ctx.fillText('MAPA', MAP_W - PAD, MAP_H - 4)

    }, [sceneGraph, selected, darkMode, hoveredAreaId])

    // ── Hit test (consulta areaRegions.current sin deps) ─────────────────────
    const hitTest = useCallback((mx, my) => {
        for (const r of areaRegions.current) {
            const hit = r.isCircle
                ? Math.hypot(mx - r.cx, my - r.cz) <= r.r
                : mx >= r.rx && mx <= r.rx + r.rw && my >= r.rz && my <= r.rz + r.rh
            if (hit) return r
        }
        return null
    }, [])   // areaRegions es un ref → no hay stale closure

    // ── Mousemove: tooltip + cursor (imperativo, cero re-renders) ────────────
    const handleMouseMove = useCallback((e) => {
        if (!canvasRef.current) return
        const rect = canvasRef.current.getBoundingClientRect()
        const mx   = (e.clientX - rect.left) * (MAP_W / rect.width)
        const my   = (e.clientY - rect.top)  * (MAP_H / rect.height)
        const hit  = hitTest(mx, my)

        // Tooltip
        if (tooltipRef.current) {
            if (hit) {
                const raw  = hit.area.label ?? hit.area.name ?? hit.area.id
                const name = formatAreaName(raw)
                tooltipRef.current.textContent   = name
                tooltipRef.current.style.display = 'block'
                // Posición en px relativos al contenedor (con borde mínimo)
                const tipX = Math.min(mx + 10, MAP_W - tooltipRef.current.offsetWidth - 4)
                const tipY = Math.max(my - 30, 4)
                tooltipRef.current.style.left = `${tipX}px`
                tooltipRef.current.style.top  = `${tipY}px`
            } else {
                tooltipRef.current.style.display = 'none'
            }
        }

        // Cursor
        canvasRef.current.style.cursor = hit ? 'pointer' : 'default'

        // hoveredAreaId — solo setState si cambia (para redibujado del canvas)
        const id = hit?.area?.id ?? null
        if (id !== hoveredIdRef.current) {
            hoveredIdRef.current = id
            setHoveredAreaId(id)
        }
    }, [hitTest])

    const handleMouseLeave = useCallback(() => {
        if (tooltipRef.current) tooltipRef.current.style.display = 'none'
        if (hoveredIdRef.current !== null) {
            hoveredIdRef.current = null
            setHoveredAreaId(null)
        }
    }, [])

    // ── Click: navegar cámara al área ────────────────────────────────────────
    const handleClick = useCallback((e) => {
        if (!canvasRef.current) return
        const rect = canvasRef.current.getBoundingClientRect()
        const mx   = (e.clientX - rect.left) * (MAP_W / rect.width)
        const my   = (e.clientY - rect.top)  * (MAP_H / rect.height)
        const hit  = hitTest(mx, my)
        if (!hit) return

        const { worldCX, worldCZ, diameter } = hit

        // Zoom heurístico: área más pequeña → más zoom
        // Umbral: 600 / diámetro (world units), clamp [10, 75]
        const targetZoom = Math.min(Math.max(600 / Math.max(diameter, 6), 10), 75)

        // Phase 9: setCameraTarget + setCameraZoom + triggerCameraReset
        // se batchean en React 18 → CameraControls los anima con lerp exponencial
        setCameraTarget([worldCX, 0, worldCZ])
        setCameraZoom(targetZoom)
        triggerReset()
    }, [hitTest, setCameraTarget, setCameraZoom, triggerReset])

    // ── Render ────────────────────────────────────────────────────────────────
    const tooltipStyle = {
        display:       'none',
        position:      'absolute',
        pointerEvents: 'none',
        background:    '#111827',
        color:         '#f9fafb',
        fontSize:      11,
        fontWeight:    500,
        fontFamily:    'system-ui, sans-serif',
        padding:       '3px 8px',
        borderRadius:  4,
        whiteSpace:    'nowrap',
        boxShadow:     '0 2px 8px rgba(0,0,0,.30)',
        zIndex:        5,
        lineHeight:    '18px',
    }

    return (
        <div
            style={{
                position:   'absolute',
                bottom:     16,
                left:       16,
                zIndex:     20,
                filter:     'drop-shadow(0 2px 6px rgba(0,0,0,.18))',
                userSelect: 'none',
            }}
            onMouseMove={handleMouseMove}
            onMouseLeave={handleMouseLeave}
            onClick={handleClick}
        >
            <canvas
                ref={canvasRef}
                width={MAP_W}
                height={MAP_H}
                style={{ display: 'block', borderRadius: 8 }}
            />

            {/* Tooltip HTML — gestionado de forma imperativa para evitar re-renders */}
            <div ref={tooltipRef} style={tooltipStyle} />
        </div>
    )
}
