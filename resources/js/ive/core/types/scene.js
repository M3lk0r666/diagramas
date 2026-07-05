/**
 * IVE — Tipos del Scene Graph (JSDoc)
 *
 * Estos tipos definen el contrato entre el Topology Adapter y el renderer.
 * El renderer NUNCA recibe el JSON crudo del backend — solo tipos de este archivo.
 *
 * Cuando migremos a TypeScript, estos JSDoc se convierten en interfaces.
 */

// ── Enumeraciones ────────────────────────────────────────────────────────────

/**
 * Tipos de dispositivo soportados en esta versión.
 * En versiones futuras crecerá sin modificar el renderer.
 * @typedef {'switch'|'router'|'firewall'|'ap'|'server'|'ups'|'patch_panel'|'unknown'} DeviceType
 */

/**
 * Rol del dispositivo en la jerarquía de red.
 * Determina posición (Layout Engine) y color.
 * @typedef {'core'|'backbone'|'distribution'|'access'|'unknown'} DeviceRole
 */

// ── Nodos ────────────────────────────────────────────────────────────────────

/**
 * Nodo de dispositivo — unidad atómica de la escena.
 * El Layout Engine es responsable de asignar `position`.
 *
 * @typedef {Object} DeviceNode
 * @property {string}      id          - ID único (string del backend)
 * @property {'device'}    type        - Discriminador de tipo de nodo
 * @property {DeviceType}  deviceType  - Tipo de dispositivo
 * @property {DeviceRole}  role        - Rol en jerarquía de red
 * @property {string}      label       - Nombre visible (sys_name o fallback)
 * @property {string|null} ip          - IP de gestión
 * @property {string|null} model       - Modelo del hardware
 * @property {string|null} mac         - MAC del sistema
 * @property {[number, number, number]} position - [x, y, z] asignado por LayoutEngine
 * @property {Object}      meta        - Datos crudos del backend (solo lectura)
 */

/**
 * Área lógica (batch en el backend).
 * Agrupa DeviceNodes.
 *
 * @typedef {Object} AreaNode
 * @property {string}       id          - ID del batch
 * @property {'area'}       type
 * @property {string}       label       - Nombre del área
 * @property {string}       color       - Color hex asignado por el backend
 * @property {DeviceNode[]} devices     - Dispositivos en esta área
 * @property {ConnectionEdge[]} connections - Conexiones internas del área
 */

// ── Aristas ──────────────────────────────────────────────────────────────────

/**
 * Arista de conexión entre dos dispositivos.
 *
 * @typedef {Object} ConnectionEdge
 * @property {string}      id          - ID derivado (src-dst)
 * @property {string}      sourceId    - ID del DeviceNode origen
 * @property {string}      targetId    - ID del DeviceNode destino
 * @property {string|null} sourcePort  - Puerto de origen (ej. "1/1/1")
 * @property {string|null} targetPort  - Puerto de destino
 * @property {boolean}     interArea   - true si cruza fronteras de área
 * @property {DeviceRole}  role        - Rol del enlace (heredado del nodo de mayor jerarquía)
 */

// ── Scene Graph raíz ─────────────────────────────────────────────────────────

/**
 * Scene Graph completo — la única estructura que consume el renderer.
 *
 * @typedef {Object} SceneGraph
 * @property {AreaNode[]}        areas                - Áreas de la topología
 * @property {ConnectionEdge[]}  interAreaConnections - Conexiones inter-área globales
 * @property {Object}            meta                 - Metadata global (client, timestamp)
 */

export {}  // Hace de este archivo un módulo ES6
