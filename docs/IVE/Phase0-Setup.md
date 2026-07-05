# IVE — Fase 0: Infrastructure Setup

## Objetivo

Instalar y configurar el stack técnico del IVE sin tocar el backend existente.
Al finalizar esta fase, se puede navegar a `/admin/ive/{client}` y ver un canvas React Three Fiber
con una escena isométrica básica (luces + grid + 4 cubos de prueba).

---

## Stack instalado

| Paquete | Versión objetivo | Rol |
|---|---|---|
| `react` / `react-dom` | ^18.3 | Framework UI |
| `three` | ^0.169 | Motor 3D |
| `@react-three/fiber` | ^8.17 | React renderer para Three.js |
| `@react-three/drei` | ^9.115 | Helpers RTF (cámara, grid, etc.) |
| `@react-three/postprocessing` | ^2.16 | Post-efectos (Bloom, DOF) — Fase 12 |
| `zustand` | ^5.0 | Estado global |
| `troika-three-text` | ^0.52 | Labels 3D nativos — Fase 5 |
| `three-mesh-bvh` | ^0.8 | Raycasting optimizado — Fase 10 |
| `@react-spring/three` | ^9.7 | Animaciones — Fase 9 |
| `@vitejs/plugin-react` | ^4.3 | HMR + JSX transform |

> **Nota:** Las versiones son mínimas compatibles. npm resolverá las últimas
> compatibles al ejecutar `npm install`.

---

## Comandos de instalación

```bash
# Desde la raíz del proyecto
npm run ive:install

# o manualmente:
npm install react react-dom three \
    @react-three/fiber @react-three/drei @react-three/postprocessing \
    zustand troika-three-text three-mesh-bvh @react-spring/three

npm install -D @vitejs/plugin-react

# Verificar que Vite compila
npm run build
```

---

## Estructura de archivos creados

```
resources/js/ive/
├── main.jsx                          Entry point — monta React en #ive-root
├── App.jsx                           Root: Canvas + Toolbar
│
├── core/
│   ├── store/
│   │   └── useIveStore.js            Estado global (Zustand)
│   └── types/
│       └── scene.js                  Tipos JSDoc del Scene Graph
│
├── pipeline/
│   └── TopologyAdapter.js            JSON backend → SceneGraph
│
├── scene/
│   ├── IveScene.jsx                  Orquestador de la escena 3D
│   ├── camera/
│   │   └── IsometricCamera.jsx       OrthographicCamera isométrica
│   └── environment/
│       └── SceneEnvironment.jsx      Luces + Grid
│
└── ui/
    └── Toolbar.jsx                   UI HTML sobre el canvas

app/Http/Controllers/Admin/
└── IveController.php                 Controller mínimo (solo render view)

resources/views/admin/ive/
└── global.blade.php                  Shell Blade — monta React

routes/admin.php                      +2 líneas: ruta IVE
jsconfig.json                         Alias @ive/ para el IDE
docs/IVE/Phase0-Setup.md             Esta documentación
```

---

## Arquitectura del Pipeline (preview Fase 3)

```
Laravel (IsoTopologyController)
    ↓  JSON
TopologyAdapter.js          ← ÚNICA pieza que conoce el formato del backend
    ↓  SceneGraph
useIveStore (Zustand)
    ↓
IveScene.jsx
    ├── IsometricCamera
    ├── SceneEnvironment
    ├── DeviceLayer (Fase 4)
    └── ConnectionLayer (Fase 7)
```

---

## Restricción arquitectónica activa

El renderer **NUNCA** consume el JSON del backend directamente.
Solo consume el `SceneGraph` tipado que produce el `TopologyAdapter`.

---

## Estado actual (Fase 0)

- [x] Vite configurado con plugin React y entry point IVE
- [x] Estructura de carpetas completa
- [x] Zustand store con slices de estado
- [x] TopologyAdapter skeleton con normalización de roles
- [x] `IsometricCamera` con OrthographicCamera isométrica
- [x] `SceneEnvironment` con luces y grid infinito
- [x] `IveScene` con 4 cubos placeholder
- [x] `Toolbar` HTML mínima sobre el canvas
- [x] Blade shell con mount point `#ive-root`
- [x] Ruta `/admin/ive/{client}`
- [x] Link en sidebar bajo sección "IVE"
- [x] `jsconfig.json` con alias `@ive/`

## Lo que NO hace Fase 0 (pendiente en fases posteriores)

- [ ] Fase 3: Fetch real de datos del backend → TopologyAdapter → store
- [ ] Fase 4: Renderer de DeviceNodes (modelos GLB, InstancedMesh)
- [ ] Fase 5: Labels con Troika-three-text
- [ ] Fase 6: Layout Engine (posicionamiento automático)
- [ ] Fase 7: Connection Router (líneas Bezier / Manhattan)
- [ ] Fase 8: Toolbar completa (labels, zoom, dark mode, export)
- [ ] Fase 9: Animaciones (selection ring, pulse, flow)
- [ ] Fase 10: Optimización (BVH, LOD, Frustum Culling)

---

## Notas de desarrollo

### React Fast Refresh (HMR)
La directiva `@viteReactRefresh` no se puede agregar al layout base
(es un archivo existente — restricción arquitectónica del proyecto).
El canvas renderiza correctamente en dev y prod, pero los cambios en
componentes React requieren recargar la página manualmente en dev.

### Three.js r185 vs r169
El documento IVE.md especifica Three.js r185.
npm resolverá `three@^0.169` a la versión más reciente disponible.
Si el registro npm ya tiene r185 (`0.185.x`), se instalará automáticamente.

### `frameloop="demand"`
El Canvas usa `frameloop="demand"` para no renderizar frames vacíos.
En Fase 9 (animaciones), algunas deberán llamar `invalidate()` del store
o cambiar a `frameloop="always"` según el comportamiento deseado.
