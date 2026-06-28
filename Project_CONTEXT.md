# Project_CONTEXT.md — Diagramas de Red (Switch Topology)

> **Última actualización:** 2026-06-06 — Limpieza de código legacy completada.

---

## Objetivo del proyecto

Aplicación web interna para **visualizar la topología de red de switches Extreme Networks**. El flujo es: subir archivos `.txt` exportados desde los switches → parsear las secciones de cada archivo → almacenar los datos en BD → resolver las conexiones entre equipos cruzando MACs vía EDP → mostrar el grafo de red interactivo y un listado detallado por switch.

---

## Arquitectura

```
Usuario → Upload (multi-file) → FileUploadController
                                    ↓
                          UploadBatch (lote)
                                    ↓  (por cada archivo)
                        ProcessSwitchFileJob  (queue)
                          ↙                 ↘
              SwitchParserService    ConnectionResolverService
                   ↓                          ↓
            Switche (BD)           SwitcheConnection (BD)
                                              ↓
                         SwitchController / ConnectionController
                                              ↓
                              Vistas Blade + vis-network (grafo)
```

- **Stack**: Laravel 12, PHP 8.2, Livewire 3, Tailwind CSS 3, Vite, Flowbite
- **Auth**: Laravel Jetstream + Fortify (2FA incluido, Sanctum)
- **UI components**: WireUI 2.5, Rappasoft Livewire Tables
- **Queue**: worker sincrónico por defecto (`php artisan queue:listen`)
- **Frontend gráfico**: vis-network (CDN) para el grafo de topología
- **BD**: MySQL/SQLite (configurable en `.env`)

---

## Módulos activos

### 1. Upload (`FileUploadController`)
- `GET /admin/` → pantalla de carga con últimos 5 lotes
- `POST /admin/upload` → valida archivos `.txt` (máx 5 MB c/u), crea `UploadBatch`, despacha un `ProcessSwitchFileJob` por archivo
- `GET /admin/batches/{batch}` → progreso en tiempo real (polling JSON)
- `GET /admin/batches/{batch}/status` → endpoint JSON de estado del lote

### 2. Parser (`SwitchParserService`)
Parsea el texto crudo de cada archivo dividiéndolo en secciones por comando (`show switch`, `show version detail`, `show vlan`, `show iproute`, `show edp ports all`, `show ports no-refresh`). Extrae:
- Info del switch: `sys_name`, `sys_location`, `system_mac`, `system_type`, `sys_contact`
- Versión y serial: `firmware_version`, `serial_number`
- VLANs, rutas IP, puertos EDP (vecinos), puertos activos
- Secciones raw completas como JSON

### 3. Job (`ProcessSwitchFileJob`)
Lee el archivo de Storage, llama a `SwitchParserService`, crea el registro `Switche`. Si falla, registra el error en el lote. Al finalizar el último archivo del lote, llama a `ConnectionResolverService`.

### 4. Resolver de conexiones (`ConnectionResolverService`)
Una vez completado el lote, cruza los datos EDP de cada switch contra las MACs conocidas en BD (normaliza 8 bytes → 6 bytes de EDP format) y crea los registros `SwitcheConnection`.

### 5. Switches (`SwitchController`)
- `GET /admin/switches` → listado paginado (solo `parse_status = ok`)
- `GET /admin/switches/{switch}` → detalle con todas las secciones y conexiones

### 6. Topología (`ConnectionController`)
- `GET /admin/topology` → prepara nodos y aristas para vis-network + tabla de conexiones

---

## Estructura de archivos activa

```
app/
├── Http/Controllers/Admin/
│   ├── FileUploadController.php
│   ├── SwitchController.php
│   └── ConnectionController.php
├── Jobs/
│   └── ProcessSwitchFileJob.php
├── Services/
│   ├── SwitchParserService.php
│   └── ConnectionResolverService.php
├── Models/
│   ├── Switche.php
│   ├── SwitcheConnection.php
│   ├── UploadBatch.php
│   └── User.php
resources/views/admin/
├── dashboard.blade.php
├── upload/
│   ├── index.blade.php
│   └── show.blade.php
├── switches/
│   ├── index.blade.php
│   └── show.blade.php
└── topology/
    └── index.blade.php
database/migrations/
├── 0001_01_01_* (users, cache, jobs — Laravel base)
├── 2026_01_09_* (Jetstream 2FA, Sanctum tokens)
├── 2026_04_23_133512_create_upload_batches_table.php
├── 2026_04_23_133705_create_switches_table.php
├── 2026_04_23_133753_create_switche_connections_table.php
└── 2026_06_06_000000_drop_legacy_tables.php  ← ejecutar: php artisan migrate
routes/
├── admin.php    ← rutas del módulo principal
├── web.php      ← redirect / → /admin, ruta dashboard Jetstream
└── api.php
```

---

## Decisiones técnicas

| Decisión | Razón |
|----------|-------|
| Modelo `Switche` (sin "h" al final) | Evita colisión con palabra reservada `Switch` en PHP |
| JSON columns para vlans, edp_ports, etc. | Arrays variables por equipo; no justifican tablas propias |
| `system_mac` como identificador lógico | Campo más estable para cruzar conexiones EDP |
| EDP → MAC normalización 8→6 bytes | El protocolo EDP reporta MACs con 2 bytes de prefijo `00:00:` |
| Queue para el parseo | Archivos grandes; evita timeout HTTP |
| Lotes (`UploadBatch`) | Permite subir múltiples archivos y ver progreso global |
| vis-network desde CDN | Librería suficiente para grafos básicos |
| `routes/admin.php` separado | Agrupa todas las rutas bajo prefijo `/admin` |

---

## Convenciones utilizadas

- **Rutas**: prefijo `/admin`, nombres con punto (`admin.switches.index`, `admin.batches.show`)
- **Vistas**: `resources/views/admin/{módulo}/{acción}.blade.php`
- **Layout**: `x-admin-layout` como componente principal con slot `header` y `@push('js')`
- **Modelos**: `$fillable` explícito, `$casts` para JSON → array
- **Jobs**: `readonly` properties en constructor, inyección de servicios en `handle()`
- **Idioma**: interfaz en español (`lang/es/`), código en inglés
- **Nomenclatura BD**: snake_case; tablas en plural

---

## Estado actual

- ✅ Upload multi-archivo con lotes y progreso
- ✅ Parser de archivos Extreme Networks completo (6 secciones)
- ✅ Almacenamiento de switches y conexiones en BD
- ✅ Resolución de conexiones vía EDP post-batch
- ✅ Vista de topología básica con vis-network
- ✅ Listado y detalle de switches
- ✅ **Código legacy eliminado** (controllers, jobs, services, models, views y migraciones de enero 2026)
- ⚠️ Pendiente ejecutar `php artisan migrate` para que corra `drop_legacy_tables`
- ❌ Rutas `/admin` sin middleware `auth:sanctum` (solo `/dashboard` está protegido)
- ❌ Sin tests automatizados

---

## Plan de trabajo — Próximas fases

### Fase 1 — JSON de topología por lote
Generar y persistir un JSON estructurado en `upload_batches.topology_json` al completar cada batch. Estructura:
```json
{
  "batch_id": 5,
  "nodes": [{ "id": 1, "name": "PUEBLITO-100.240", "ip": "148.206.100.240", "mac": "...", "type": "access" }],
  "edges": [{ "src_id": 1, "src_port": "49", "dst_id": 99, "dst_port": "2:1", "link_type": "uplink" }]
}
```

### Fase 2 — Generador de diagrama (Python o SVG)
Script que lee el JSON y produce una imagen PNG/SVG con:
- Switch representado con hostname + IP
- Aristas etiquetadas con `puerto_origen → puerto_destino`
- Color diferenciado: enlace de acceso vs uplink a CORE

### Fase 3 — Integración Laravel
- Artisan command `diagram:generate {batchId}`
- Llamada automática al finalizar el batch
- Campo `diagram_path` en `upload_batches`

### Fase 4 — Vista del diagrama
- Ruta `GET /admin/batches/{batch}/diagram`
- Imagen con zoom/pan, botón de descarga
- Botón "Regenerar diagrama"

### Fase 5 — Proteger rutas y hardening
- Aplicar `auth:sanctum` + `verified` al grupo de rutas de `admin.php`
- Revisar validaciones de upload

---

*Generado y actualizado analizando app/, routes/, database/ y resources/*
