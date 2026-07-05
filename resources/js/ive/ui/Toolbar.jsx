/**
 * IVE — Toolbar (Fase 8)
 *
 * Barra HTML superior sobre el canvas.
 * Todas las acciones son sin estado propio: leen y mutan el store.
 *
 * Grupos:
 *   [Brand]  → logo · cliente · stats · estado
 *   [Vista]  → Labels | Conexiones | Áreas | Radial | Grid
 *   [Accs]   → 🌙 Dark | 📸 PNG | {} JSON | ⊙ Centrar | ⊟ Mapa
 */
import { useIveStore }  from '@ive/core/store/useIveStore'
import { captureRef }   from '@ive/pipeline/captureRef'

// ── Helpers de estilo (reactivos al dark mode) ────────────────────────────────

function makeStyles(dark) {
    const bg      = dark ? '#1e293b' : '#ffffff'
    const border  = dark ? '#334155' : '#e5e7eb'
    const text    = dark ? '#cbd5e1' : '#4b5563'
    const active  = dark ? '#818cf8' : '#6366f1'
    const activeBg= dark ? '#1e274a' : '#f0f4ff'

    const BASE = {
        background:  'none',
        border:      'none',
        cursor:      'pointer',
        padding:     '7px 12px',
        fontSize:    12,
        fontWeight:  500,
        display:     'flex',
        alignItems:  'center',
        gap:         5,
        transition:  'background .15s, color .15s',
        whiteSpace:  'nowrap',
    }

    return {
        CARD: {
            background:    bg,
            border:        `1px solid ${border}`,
            borderRadius:  8,
            boxShadow:     dark
                ? '0 1px 3px rgba(0,0,0,.4)'
                : '0 1px 3px rgba(0,0,0,.08)',
            display:       'flex',
            alignItems:    'center',
            pointerEvents: 'auto',
            overflow:      'hidden',
            userSelect:    'none',
        },

        BTN: {
            ...BASE,
            color:       text,
            borderLeft:  `1px solid ${border}`,
        },

        BTN_ACTIVE: {
            ...BASE,
            color:       active,
            background:  activeBg,
            borderLeft:  `1px solid ${border}`,
        },

        BTN_FIRST: {
            ...BASE,
            color:      text,
            borderLeft: 'none',
        },

        BRAND_TEXT:   { fontSize: 12, fontWeight: 600, color: active },
        DIVIDER:      { color: dark ? '#475569' : '#d1d5db' },
        CLIENT_TEXT:  { color: dark ? '#e2e8f0' : '#111827', fontWeight: 500 },
        STATS_TEXT:   { color: dark ? '#64748b' : '#9ca3af', fontWeight: 400 },
    }
}

// ── Indicadores de carga ──────────────────────────────────────────────────────

const STATUS_INFO = {
    idle:    { label: '',           color: '#9ca3af' },
    loading: { label: 'Cargando…', color: '#f59e0b' },
    ready:   { label: '',           color: '#10b981' },
    error:   { label: '⚠ Error',   color: '#ef4444' },
}

// ── Exportar JSON de topología ────────────────────────────────────────────────

function downloadJSON(clientName) {
    const { sceneGraph } = useIveStore.getState()
    if (!sceneGraph) return

    const blob = new Blob(
        [JSON.stringify(sceneGraph, null, 2)],
        { type: 'application/json' },
    )
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    const name = (clientName ?? 'ive').replace(/\s+/g, '-').toLowerCase()
    a.href     = url
    a.download = `ive-${name}-${Date.now()}.json`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
}

// ── Componente ────────────────────────────────────────────────────────────────

const BAR = {
    position:      'absolute',
    top:           12,
    left:          12,
    right:         12,
    zIndex:        30,
    display:       'flex',
    alignItems:    'center',
    gap:           8,
    pointerEvents: 'none',
    flexWrap:      'wrap',
}

export function Toolbar({ config = {}, status = 'idle' }) {
    const showLabels      = useIveStore(s => s.showLabels)
    const showConnections = useIveStore(s => s.showConnections)
    const showAreas       = useIveStore(s => s.showAreas)
    const showMinimap     = useIveStore(s => s.showMinimap)
    const showTraffic     = useIveStore(s => s.showTraffic)
    const dm              = useIveStore(s => s.darkMode)
    const layoutMode      = useIveStore(s => s.layoutMode)
    const viewMode        = useIveStore(s => s.viewMode)
    const sceneGraph      = useIveStore(s => s.sceneGraph)

    const toggleLabels      = useIveStore(s => s.toggleLabels)
    const toggleConnections = useIveStore(s => s.toggleConnections)
    const toggleAreas       = useIveStore(s => s.toggleAreas)
    const toggleMinimap     = useIveStore(s => s.toggleMinimap)
    const toggleTraffic     = useIveStore(s => s.toggleTraffic)
    const toggleDarkMode    = useIveStore(s => s.toggleDarkMode)
    const setLayoutMode     = useIveStore(s => s.setLayoutMode)
    const setViewMode       = useIveStore(s => s.setViewMode)
    const triggerReset      = useIveStore(s => s.triggerCameraReset)

    const S          = makeStyles(dm)
    const stats      = sceneGraph?.meta?.stats
    const statusInfo = STATUS_INFO[status] ?? STATUS_INFO.idle

    return (
        <div style={BAR}>

            {/* ── Brand + stats ────────────────────────────────── */}
            <div style={{ ...S.CARD, padding: '7px 14px', gap: 8 }}>
                <span style={{ fontSize: 15, ...S.BRAND_TEXT }}>⬡</span>
                <span style={S.BRAND_TEXT}>IVE</span>
                {config.clientName && (
                    <>
                        <span style={S.DIVIDER}>·</span>
                        <span style={{ fontSize: 12, ...S.CLIENT_TEXT }}>{config.clientName}</span>
                    </>
                )}
                {stats && (
                    <span style={{ fontSize: 12, ...S.STATS_TEXT }}>
                        {stats.areas}A · {stats.devices}D · {stats.edges}C
                    </span>
                )}
                {statusInfo.label && (
                    <span style={{ fontSize: 11, fontWeight: 400, color: statusInfo.color }}>
                        {statusInfo.label}
                    </span>
                )}
            </div>

            {/* ── Spacer ───────────────────────────────────────── */}
            <div style={{ flex: 1, minWidth: 8 }} />

            {/* ── Vista ────────────────────────────────────────── */}
            <div style={S.CARD}>
                <button style={showLabels ? S.BTN_ACTIVE : S.BTN_FIRST}
                    onClick={toggleLabels}
                    title={showLabels ? 'Ocultar labels' : 'Mostrar labels'}>
                    <span style={{ fontSize: 13 }}>🏷</span> Labels
                </button>

                <button style={showConnections ? S.BTN_ACTIVE : S.BTN}
                    onClick={toggleConnections}
                    title={showConnections ? 'Ocultar conexiones' : 'Mostrar conexiones'}>
                    <span style={{ fontSize: 13 }}>⎯</span> Conex.
                </button>

                <button style={showAreas ? S.BTN_ACTIVE : S.BTN}
                    onClick={toggleAreas}
                    title={showAreas ? 'Ocultar áreas' : 'Mostrar áreas'}>
                    <span style={{ fontSize: 13 }}>◻</span> Áreas
                </button>

                <button style={layoutMode === 'radial' ? S.BTN_ACTIVE : S.BTN}
                    onClick={() => setLayoutMode('radial')}
                    title="Layout radial: Core → Distribution → Access">
                    <span style={{ fontSize: 13 }}>⬡</span> Radial
                </button>

                <button style={layoutMode === 'grid' ? S.BTN_ACTIVE : S.BTN}
                    onClick={() => setLayoutMode('grid')}
                    title="Layout en cuadrícula">
                    <span style={{ fontSize: 13 }}>⊞</span> Grid
                </button>

                <button
                    style={viewMode === 'iso' ? S.BTN_ACTIVE : S.BTN}
                    onClick={() => setViewMode(viewMode === 'iso' ? '3d' : 'iso')}
                    title={viewMode === 'iso' ? 'Volver a vista libre 3D' : 'Vista isométrica fija (solo pan)'}>
                    <span style={{ fontSize: 13 }}>⬟</span> Iso
                </button>

                <button
                    style={viewMode === 'front' ? S.BTN_ACTIVE : S.BTN}
                    onClick={() => setViewMode(viewMode === 'front' ? '3d' : 'front')}
                    title={viewMode === 'front' ? 'Volver a vista libre 3D' : 'Vista frontal/elevación (solo pan)'}>
                    <span style={{ fontSize: 13 }}>⊡</span> Frente
                </button>
            </div>

            {/* ── Acciones ─────────────────────────────────────── */}
            <div style={S.CARD}>
                <button style={dm ? S.BTN_ACTIVE : S.BTN_FIRST}
                    onClick={toggleDarkMode}
                    title={dm ? 'Modo claro' : 'Modo oscuro'}>
                    <span style={{ fontSize: 13 }}>{dm ? '☀' : '🌙'}</span>
                </button>

                <button style={S.BTN}
                    onClick={() => captureRef.current?.()}
                    title="Capturar PNG">
                    <span style={{ fontSize: 13 }}>📸</span> PNG
                </button>

                <button style={S.BTN}
                    onClick={() => downloadJSON(config.clientName)}
                    title="Exportar topología JSON">
                    <span style={{ fontSize: 12, fontFamily: 'monospace' }}>{'{}'}</span> JSON
                </button>

                <button style={S.BTN}
                    onClick={triggerReset}
                    title="Centrar cámara">
                    <span style={{ fontSize: 13 }}>⊙</span> Centrar
                </button>

                <button style={showMinimap ? S.BTN_ACTIVE : S.BTN}
                    onClick={toggleMinimap}
                    title={showMinimap ? 'Ocultar mapa' : 'Mostrar mapa'}>
                    <span style={{ fontSize: 12 }}>⊟</span> Mapa
                </button>

                <button style={showTraffic ? S.BTN_ACTIVE : S.BTN}
                    onClick={toggleTraffic}
                    title={showTraffic ? 'Ocultar tráfico' : 'Mostrar tráfico'}>
                    <span style={{ fontSize: 13 }}>⚡</span> Tráfico
                </button>
            </div>

        </div>
    )
}
