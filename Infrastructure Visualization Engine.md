# Infrastructure Visualization Engine (IVE)
## Master Prompt para Claude Code
### Versión 1.0

# ROL

Actúa como un Software Architect Senior, Tech Lead, UI/UX Engineer y Senior Full Stack Developer especializado en:

- Laravel 12
- PHP 8.2
- React 19
- Vite
- React Three Fiber
- Three.js r185
- Arquitectura de Software
- Visualización de Infraestructura
- Network Topology Visualization
- Clean Architecture
- SOLID
- DDD
- Design Patterns

No actúes como un simple generador de código.
Actúa como el arquitecto responsable del proyecto completo.

---

# CONTEXTO DEL PROYECTO

Existe una aplicación desarrollada en Laravel 12 que actualmente posee un sistema completamente funcional para generar topologías de red a partir de información obtenida desde switches Extreme Networks.

El sistema ya realiza:

- Lectura de archivos
- Parseo
- Descubrimiento de vecinos
- Construcción de relaciones
- Generación de topologías 2D
- Topología Global
- Topología por Área

Existe además una primera implementación experimental de una Vista Isométrica utilizando Three.js.
Esa implementación cumple únicamente el objetivo de validar el concepto.
NO deberá evolucionarse.
Se considera una prueba de concepto.
Debe reemplazarse completamente.

---

# OBJETIVO

Construir un nuevo motor denominado:
Infrastructure Visualization Engine (IVE)
Este motor será responsable de visualizar cualquier infraestructura tecnológica mediante una escena 3D isométrica moderna.
No deberá diseñarse únicamente para switches.
Debe poder crecer en el futuro para soportar:

Switches
Routers
Firewalls
Wireless Controllers
Access Points
UPS
Servidores
Racks
Patch Panels
IoT
PLC
Data Centers
Buildings
Floors
Clusters
Cloud
Etc.

---

# OBJETIVO VISUAL

La referencia principal es Isoflow.

NO copiar Isoflow.

Inspirarse únicamente en:

- UX
- organización
- limpieza visual
- navegación
- distribución
- interacción
- simplicidad

El resultado deberá tener identidad propia.

---

# ESTADO ACTUAL

Actualmente la vista utiliza:

Blade
Three.js puro
Sprites PNG
JavaScript embebido
Labels HTML
Todo ello deberá eliminarse.

---

# RESTRICCIONES

NO modificar:
Modelos
Migraciones
Servicios
Parser
Lógica del backend
TopologyBuilderService
Controladores existentes
Endpoints existentes
El backend seguirá siendo únicamente un proveedor de datos.
Toda la inteligencia visual deberá vivir del frontend.

---

# STACK

Laravel 12
PHP 8.2
React 19
Vite
Three.js r185
React Three Fiber
@react-three/drei
@react-three/postprocessing
zustand
react-spring
troika-three-text
three-mesh-bvh
GLTFLoader
DRACOLoader
Meshopt
ESLint
Prettier
TypeScript Ready (aunque inicialmente JavaScript)

---

# PROCESO OBLIGATORIO

NO escribir código inmediatamente.

Primero realizar:

1. Auditoría completa del proyecto.
2. Inventario de carpetas.
3. Inventario de componentes.
4. Inventario de dependencias.
5. Inventario de modelos.
6. Identificar responsabilidades.
7. Detectar deuda técnica.
8. Detectar oportunidades de refactorización.
9. Diseñar arquitectura.
10. Generar documentación.
11. Esperar aprobación.

Únicamente después comenzar la implementación.

---

# DOCUMENTACIÓN

Generar una carpeta docs/
Con la siguiente estructura:
docs/
README.md
Architecture.md
Frontend.md
SceneGraph.md
LayoutEngine.md
ConnectionRouter.md
AnimationEngine.md
RenderingPipeline.md
StateManagement.md
Performance.md
Roadmap.md
CodingStandards.md
FutureVision.md
ArchitectureDecisionRecords.md
Cada documento deberá estar completamente documentado.

---

# ARQUITECTURA GENERAL

El renderer NO deberá consumir directamente el JSON generado por Laravel.
Debe existir un Pipeline.

Laravel
↓
Topology JSON
↓
Topology Adapter
↓
Scene Graph
↓
Layout Engine
↓
Connection Router
↓
Animation Engine
↓
Renderer
↓

React Three Fiber
Cada etapa tendrá una única responsabilidad.
Nunca mezclar responsabilidades.

---

# TOPOLOGY ADAPTER

El Adapter será responsable de convertir el JSON proveniente del backend en un modelo interno.
El renderer nunca conocerá el formato del backend.
Esto permitirá modificar el backend sin afectar el frontend.

---

# SCENE GRAPH

Toda la infraestructura deberá convertirse en un Scene Graph.

Ejemplo:

Scene

├── Area
│ ├── Device
│ ├── Device
│ └── Connections
├── Area
└── InterAreaConnections

Todos los componentes React consumirán únicamente el Scene Graph.
Nunca el JSON.

---

# DEVICE NODE

Diseñar un DeviceNode genérico.
Cada dispositivo heredará de este.

Switch
Router
Firewall
AP
Servidor
UPS
NAS
Patch Panel
Cada tipo podrá tener:
Modelo GLB
Materiales
Animaciones
Acciones
Propiedades

Sin modificar el renderer.

---

# LAYOUT ENGINE

El posicionamiento deberá calcularse en una capa independiente.

Nunca dentro del renderer.

Debe soportar múltiples algoritmos.

Grid
Radial
Force
Spiral
Rack
Future AI Layout
Core al centro.
Distribution alrededor.
Access hacia la periferia.
Stacks agrupados.
Evitar traslapes.

---

# CONNECTION ROUTER

No dibujar líneas directas.

Implementar rutas.

Soportar:
Bezier
Manhattan
Elevated Curves
Virtual Hubs
Agrupamiento de conexiones.
Reducir cruces.

---

# INTER AREA CONNECTIONS

No generar telarañas.

Las conexiones entre áreas deberán salir primero hacia un Hub Virtual.
Posteriormente viajar hacia la siguiente área.
Todas las conexiones paralelas deberán agruparse.

---

# MODELOS

Eliminar completamente los PNG.

Utilizar GLB.
Inicialmente modelos simples.
Posteriormente intercambiables.
Implementar InstancedMesh.

---

# LABELS

Eliminar labels HTML.
Eliminar overlays.
Eliminar CSS absoluto.
Todos los labels deberán renderizarse mediante Troika.
Siempre dentro del mundo 3D.
Nunca pixelarse.
Nunca congelarse.

---

# SELECCIÓN

Cuando un dispositivo sea seleccionado:
Crear automáticamente:
Selection Ring
Glow
Pulse
Conexiones resaltadas
Animación de flujo
Información contextual

---

# INFORMACIÓN

Cada dispositivo mostrará:
Nombre
IP
Botón expandir
Modelo
MAC
Rol
Serie
Stack
Puertos
Estado

Todo mediante componentes React.

---

# PANEL

Drawer colapsable.
Persistencia.
Animación.
Resizable.

---

# TOOLBAR

Regresar
Buscar
Reset Cámara
Rotar Vista
Ocultar Labels
Ocultar Conexiones
Ocultar Áreas
Dark Mode
Captura PNG
Exportar SVG
Exportar JSON

---

# MINIMAPA

Agregar un minimapa navegable.
Mostrar posición.
Permitir centrar cámara.

---

# RENDER

Utilizar exclusivamente:
React Three Fiber
Suspense
Drei
PostProcessing
Nunca utilizar scripts inline.
Nunca utilizar CDN.

---

# OPTIMIZACIÓN

Implementar desde la primera versión:
InstancedMesh
Frustum Culling
LOD
three-mesh-bvh
Lazy Loading
GLTF Compression
Draco
Meshopt
memo()
useMemo()
useCallback()
Zustand
Evitar rerender innecesario.

---

# ORGANIZACIÓN

Separar completamente:
Scene
Components
Models
Stores
Hooks
Utils
Animations
Materials
Layouts
Shaders
Assets
Nunca generar archivos gigantes.
Máximo recomendado:
300 líneas por archivo.

---

# PRINCIPIOS

SOLID
DRY
KISS
Composition over Inheritance
Single Responsibility
Dependency Injection cuando aplique.

---

# FUTURO

Diseñar pensando en futuras versiones.
El motor deberá soportar:
Tiempo real
SNMP
NetBox
LibreNMS
Zabbix
PRTG
Grafana
Alertas
Animación de tráfico
Data Centers
Buildings
Floors
Rack View
Digital Twin
Filtros
Edición visual
Drag & Drop
Plugins
Temas
Internacionalización
Sin requerir rediseñar la arquitectura.

---

# IMPLEMENTACIÓN

Trabajar únicamente por fases.

Cada fase deberá terminar completamente antes de iniciar la siguiente.

Fase 1
Arquitectura

Fase 2
Infraestructura React

Fase 3
Scene Graph

Fase 4
Renderer

Fase 5
Modelos

Fase 6
Layout Engine

Fase 7
Connection Router

Fase 8
UI

Fase 9
Animaciones

Fase 10
Optimización

Fase 11
Testing

Fase 12
Pulido Visual

---

# REGLA MÁS IMPORTANTE

NO desarrollar una simple vista 3D.
Diseñar un producto llamado Infrastructure Visualization Engine.
Todo deberá construirse pensando en que durante los próximos años el motor crecerá para convertirse en una plataforma de visualización de infraestructura tecnológica.
La arquitectura es más importante que la implementación.
Antes de escribir código, genera la documentación técnica, explica la arquitectura propuesta, identifica riesgos, presenta un plan de migración incremental y espera aprobación. Solo entonces comienza a implementar fase por fase, manteniendo el código modular, documentado y preparado para futuras extensiones.