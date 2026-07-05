/**
 * IVE — DevicePanel
 *
 * Drawer HTML lateral derecho con información del nodo seleccionado.
 * Se abre/cierra con CSS transition (sin dependencias extra de animación).
 *
 * Fase 9: reemplazar transición CSS con @react-spring/three para animación suave.
 */
import { useIveStore } from '@ive/core/store/useIveStore'

// ── Colores de rol ────────────────────────────────────────────────────────────
const ROLE_META = {
    core:         { label: 'Core',         color: '#ef4444', bg: '#fef2f2' },
    backbone:     { label: 'Backbone',     color: '#f97316', bg: '#fff7ed' },
    distribution: { label: 'Distribución', color: '#8b5cf6', bg: '#f5f3ff' },
    access:       { label: 'Acceso',       color: '#3b82f6', bg: '#eff6ff' },
    unknown:      { label: '—',            color: '#6b7280', bg: '#f9fafb' },
}

// ── Estilos ───────────────────────────────────────────────────────────────────
const PANEL_BASE = {
    position:         'absolute',
    top:              56,            // Debajo del toolbar (~56px)
    right:            0,
    width:            288,
    height:           'calc(100% - 56px)',
    background:       '#ffffff',
    borderLeft:       '1px solid #e5e7eb',
    boxShadow:        '-4px 0 24px rgba(0,0,0,.07)',
    display:          'flex',
    flexDirection:    'column',
    zIndex:           40,
    transition:       'transform 0.25s cubic-bezier(.4,0,.2,1)',
    overflowY:        'auto',
}

const HEADER_STYLE = {
    display:        'flex',
    alignItems:     'center',
    justifyContent: 'space-between',
    padding:        '12px 16px',
    borderBottom:   '1px solid #f3f4f6',
    background:     '#f9fafb',
    flexShrink:     0,
}

const CLOSE_BTN = {
    background:   'none',
    border:       'none',
    cursor:       'pointer',
    padding:      '4px 6px',
    borderRadius: 6,
    color:        '#9ca3af',
    fontSize:     18,
    lineHeight:   1,
    display:      'flex',
    alignItems:   'center',
}

export function DevicePanel() {
    const selectedNode = useIveStore(s => s.selectedNode)
    const panelOpen    = useIveStore(s => s.panelOpen)
    const closePanel   = useIveStore(s => s.closePanel)
    const darkMode     = useIveStore(s => s.darkMode)

    const isOpen = panelOpen && !!selectedNode

    const panelStyle = darkMode ? {
        ...PANEL_BASE,
        background:  '#1e293b',
        borderLeft:  '1px solid #334155',
        boxShadow:   '-4px 0 24px rgba(0,0,0,.35)',
    } : PANEL_BASE

    const headerStyle = darkMode ? {
        ...HEADER_STYLE,
        background:  '#0f172a',
        borderBottom:'1px solid #334155',
    } : HEADER_STYLE

    return (
        <div style={{
            ...panelStyle,
            transform: isOpen ? 'translateX(0)' : 'translateX(100%)',
            pointerEvents: isOpen ? 'auto' : 'none',
        }}>
            {/* ── Header ──────────────────────────────────────── */}
            <div style={headerStyle}>
                <span style={{ fontSize: 13, fontWeight: 600, color: darkMode ? '#e2e8f0' : '#374151', display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span style={{ color: '#6366f1', fontSize: 15 }}>◈</span>
                    Detalles del dispositivo
                </span>
                <button style={{ ...CLOSE_BTN, color: darkMode ? '#64748b' : '#9ca3af' }} onClick={closePanel} title="Cerrar">✕</button>
            </div>

            {/* ── Contenido ───────────────────────────────────── */}
            {selectedNode && <PanelBody node={selectedNode} darkMode={darkMode} />}
        </div>
    )
}

function PanelBody({ node, darkMode = false }) {
    const roleMeta = ROLE_META[node.role] ?? ROLE_META.unknown
    const meta     = node.meta ?? {}

    return (
        <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 16 }}>

            {/* Nombre + rol */}
            <div>
                <div style={{ fontSize: 16, fontWeight: 700, color: darkMode ? '#f1f5f9' : '#111827', marginBottom: 6, wordBreak: 'break-all' }}>
                    {node.label}
                </div>
                <span style={{
                    display:      'inline-block',
                    padding:      '2px 10px',
                    borderRadius: 12,
                    fontSize:     11,
                    fontWeight:   700,
                    color:        roleMeta.color,
                    background:   roleMeta.bg,
                    border:       `1px solid ${roleMeta.color}33`,
                    textTransform:'uppercase',
                    letterSpacing:'0.05em',
                }}>
                    {roleMeta.label}
                </span>
            </div>

            {/* Campos */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 0 }}>
                <Field label="IP de Gestión"  value={node.ip}    darkMode={darkMode} />
                <Field label="Modelo"         value={node.model} darkMode={darkMode} />
                <Field label="MAC"            value={node.mac}   darkMode={darkMode} monospace />
                <Field label="Stacked"        value={meta.is_stacked ? 'Sí' : 'No'} darkMode={darkMode} />
                <Field label="ID interno"     value={node.id}    darkMode={darkMode} />
            </div>

        </div>
    )
}

function Field({ label, value, monospace = false, darkMode = false }) {
    if (!value && value !== 0) return null
    return (
        <div style={{
            display:      'flex',
            flexDirection:'column',
            padding:      '9px 0',
            borderBottom: `1px solid ${darkMode ? '#1e293b' : '#f3f4f6'}`,
        }}>
            <span style={{ fontSize: 10, fontWeight: 600, color: darkMode ? '#475569' : '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 2 }}>
                {label}
            </span>
            <span style={{ fontSize: 13, color: darkMode ? '#cbd5e1' : '#111827', fontFamily: monospace ? 'monospace' : 'inherit', wordBreak: 'break-all' }}>
                {value}
            </span>
        </div>
    )
}
