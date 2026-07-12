<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\FileUploadController;
use App\Http\Controllers\Admin\SwitchController;
use App\Http\Controllers\Admin\ConnectionController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\CustomTopologyController;
use App\Http\Controllers\Admin\GoJsTopologyController;
use App\Http\Controllers\Admin\AreaTopologyController;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\ClientManagerController;
use App\Http\Controllers\Admin\DiagramAssemblerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PortMappingController;
use App\Http\Controllers\Admin\IsoTopologyController;
use App\Http\Controllers\Admin\IsoIndexController;
use App\Http\Controllers\Admin\IveController;
use App\Http\Controllers\Admin\ClientHubController;
use App\Http\Controllers\Admin\SwitchPortController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Hub de clientes (selector + inventario por cliente)
Route::get('/clientes/hub', [ClientHubController::class, 'index'])->name('hub.index');
Route::get('/clientes/hub/{client}/inventario', [ClientHubController::class, 'inventario'])->name('hub.inventario');

// Inventario central
Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');

// Gestión de clientes (áreas + switches)
Route::get('/clientes-manager', [ClientManagerController::class, 'index'])->name('clients.manage.index');
Route::get('/clientes-manager/{client}', [ClientManagerController::class, 'show'])->name('clients.manage.show');
Route::get('/clientes-manager/{client}/batches/{batch}', [ClientManagerController::class, 'batchShow'])    ->name('clients.manage.batch');
Route::delete('/clientes-manager/{client}/batches/{batch}', [ClientManagerController::class, 'destroyBatch']) ->name('clients.manage.batch.destroy');
Route::delete('/clientes-manager/{client}/batches/{batch}/switches/{switch}', [ClientManagerController::class, 'destroySwitch'])->name('clients.manage.switch.destroy');

// Guía de archivos
Route::get('/guia-archivos', fn () => view('admin.guide.index'))->name('guide.index');

// Carga de archivos
Route::get('/client/subir', [FileUploadController::class, 'index'])->name('client.upload');
Route::post('/upload', [FileUploadController::class, 'store'])->name('upload.store');
Route::get('/batches/{batch}', [FileUploadController::class, 'show'])->name('batches.show');
Route::get('/batches/{batch}/status', [FileUploadController::class, 'status'])->name('batches.status');

// Switches
Route::get('/switches-demo/faceplate', \App\Http\Controllers\Admin\SwitchFaceplateDemoController::class)->name('switches.faceplate.demo');
Route::get('/switches', [SwitchController::class, 'index'])->name('switches.index');
Route::get('/switches/{switch}', [SwitchController::class, 'show'])->name('switches.show');
Route::get('/switches/{switch}/ports-diagram', [SwitchController::class, 'portsDiagram'])->name('switches.ports-diagram');
Route::get('/switches/{switch}/config', [SwitchController::class, 'downloadConfig'])->name('switches.config.download');
Route::delete('/switches/{switch}', [SwitchController::class, 'destroy'])->name('switches.destroy');
Route::patch('/switches/{switch}/ports/description', [SwitchPortController::class, 'updateDescription'])->name('switches.ports.description');
Route::post('/switches/{switch}/diagram/generate', [SwitchController::class, 'generateSwitchDiagram'])->name('switches.diagram.generate');
Route::get('/switches/{switch}/diagram/image', [SwitchController::class, 'switchDiagramImage'])->name('switches.diagram.image');

// Clientes
Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

// Topología general
Route::get('/topology', [ConnectionController::class, 'index'])->name('topology.index');
Route::get('/topology/full', [ConnectionController::class, 'full'])->name('topology.full');
Route::get('/topology/icons/{filename}', [ConnectionController::class, 'serveIcon'])->name('topology.icon');

// Topología personalizada
Route::get('/topology/custom/create', [CustomTopologyController::class, 'create'])->name('topology.custom.create');
Route::post('/topology/custom/build', [CustomTopologyController::class, 'build'])->name('topology.custom.build');
Route::get('/topology/custom', [CustomTopologyController::class, 'show'])->name('topology.custom.show');
Route::post('/topology/custom/generate', [CustomTopologyController::class, 'generate'])->name('topology.custom.generate');
Route::get('/topology/custom/image/{key}', [CustomTopologyController::class, 'image'])->name('topology.custom.image');

// Topología GoJS
Route::get('/topology/gojs/create', [GoJsTopologyController::class, 'create'])->name('topology.gojs.create');
Route::get('/topology/gojs/blank', [GoJsTopologyController::class, 'blank'])->name('topology.gojs.blank');
Route::post('/topology/gojs/build', [GoJsTopologyController::class, 'build'])->name('topology.gojs.build');
Route::get('/topology/gojs', [GoJsTopologyController::class, 'show'])->name('topology.gojs.show');
Route::post('/topology/gojs/export', [GoJsTopologyController::class, 'export'])->name('topology.gojs.export');
Route::get('/topology/gojs/image/{key}', [GoJsTopologyController::class, 'image'])->name('topology.gojs.image');

// Topología por Áreas
Route::get('/areas', [AreaTopologyController::class, 'index'])->name('areas.index');
Route::get('/areas/clients/{client}', [AreaTopologyController::class, 'areas'])->name('areas.client');
Route::get('/areas/clients/{client}/batches/{batch}', [AreaTopologyController::class, 'show'])->name('areas.show');
Route::get('/areas/clients/{client}/batches/{batch}/topology', [AreaTopologyController::class, 'topology'])->name('areas.topology');
Route::get('/areas/clients/{client}/global', [AreaTopologyController::class, 'global'])->name('areas.global');
Route::post('/areas/clients/{client}/rebuild-connections', [AreaTopologyController::class, 'rebuildConnections'])->name('areas.rebuild-connections');

// Ensamblador de Diagramas Global
Route::get('/assembler', [DiagramAssemblerController::class, 'index'])->name('assembler.index');
Route::get('/assembler/create', [DiagramAssemblerController::class, 'create'])->name('assembler.create');
Route::post('/assembler', [DiagramAssemblerController::class, 'store'])->name('assembler.store');
Route::get('/assembler/{project}', [DiagramAssemblerController::class, 'edit'])->name('assembler.edit');
Route::put('/assembler/{project}', [DiagramAssemblerController::class, 'update'])->name('assembler.update');
Route::delete('/assembler/{project}', [DiagramAssemblerController::class, 'destroy'])->name('assembler.destroy');
Route::post('/assembler/{project}/export', [DiagramAssemblerController::class, 'export'])->name('assembler.export');
Route::get('/assembler/{project}/library', [DiagramAssemblerController::class, 'library'])->name('assembler.library');
Route::post('/assembler/{project}/autolayout', [DiagramAssemblerController::class, 'autolayout'])->name('assembler.autolayout');
Route::get('/assembler/{project}/vectorial', [DiagramAssemblerController::class, 'vectorialEditor'])->name('assembler.vectorial');
Route::get('/assembler/{project}/graph', [DiagramAssemblerController::class, 'graph'])->name('assembler.graph');

// Vista Isométrica 3D
Route::get('/iso', [IsoIndexController::class, 'index'])->name('iso.index');
Route::get('/iso/{client}', [IsoTopologyController::class, 'global'])->name('iso.global');
Route::get('/iso/{client}/{batch}', [IsoTopologyController::class, 'area'])->name('iso.area');

// Infrastructure Visualization Engine (IVE)
Route::get('/ive',                [IveController::class, 'index'])      ->name('ive.index');
Route::get('/ive/{client}',       [IveController::class, 'global'])     ->name('ive.global');
Route::get('/ive/{client}/data',  [IveController::class, 'dataGlobal']) ->name('ive.data.global');

// ── Mapeo de Puertos ──────────────────────────────────────────────────────────
// CRUD básico (index, create, show, store, update, destroy)
// Los nombres quedan con prefijo admin. → admin.port-mapping.*
Route::get('/port-mapping',                           [PortMappingController::class, 'index'])   ->name('port-mapping.index');
Route::get('/port-mapping/create',                    [PortMappingController::class, 'create'])  ->name('port-mapping.create');
Route::post('/port-mapping',                          [PortMappingController::class, 'store'])   ->name('port-mapping.store');
Route::get('/port-mapping/{portMapping}',             [PortMappingController::class, 'show'])    ->name('port-mapping.show');
Route::put('/port-mapping/{portMapping}',             [PortMappingController::class, 'update'])  ->name('port-mapping.update');
Route::delete('/port-mapping/{portMapping}',          [PortMappingController::class, 'destroy']) ->name('port-mapping.destroy');
// Punto de extensión: precargar estado desde análisis de switch existente
Route::post('/port-mapping/preload-from-switch',      [PortMappingController::class, 'loadFromSwitchAnalysis'])->name('port-mapping.preload');

// Diagrama exportado (PNG)
Route::get('/batches/{batch}/diagram', [FileUploadController::class, 'diagram'])->name('batches.diagram');
Route::get('/batches/{batch}/diagram/image', [FileUploadController::class, 'diagramImage'])->name('batches.diagram.image');
Route::post('/batches/{batch}/diagram/regenerate', [FileUploadController::class, 'regenerateDiagram'])->name('batches.diagram.regenerate');
Route::get('/batches/{batch}/diagram/clusters/{filename}', [FileUploadController::class, 'clusterImage'])->name('batches.diagram.cluster.image');
