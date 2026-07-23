<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortMappingRequest;
use App\Models\PortMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortMappingController extends Controller
{
    // ── Índice: lista de mapeos del usuario autenticado ────────────────────────

    public function index(): View
    {
        $mappings = PortMapping::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('admin.port-mapping.index', compact('mappings'));
    }

    // ── Crear: herramienta visual en blanco ────────────────────────────────────

    public function create(): View
    {
        return view('admin.port-mapping.tool', [
            'portMapping'     => null,
            'portMappingJson' => null,
        ]);
    }

    // ── Ver: herramienta con mapeo precargado ──────────────────────────────────

    public function show(PortMapping $portMapping): View
    {
        Gate::authorize('view', $portMapping);

        $portMappingJson = json_encode([
            'id'            => $portMapping->id,
            'name'          => $portMapping->name,
            'mapping_state' => $portMapping->mapping_state,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('admin.port-mapping.tool', compact('portMapping', 'portMappingJson'));
    }

    // ── Guardar nuevo mapeo (petición fetch desde el JS) ───────────────────────

    public function store(StorePortMappingRequest $request): JsonResponse
    {
        $portMapping = PortMapping::create([
            'user_id'       => auth()->id(),
            'name'          => $request->name,
            'ip'            => $request->ip,
            'origin_config' => $request->origin_config,
            'dest_config'   => $request->dest_config,
            'mapping_state' => $request->mapping_state,
        ]);

        return response()->json([
            'id'      => $portMapping->id,
            'message' => 'Mapeo guardado correctamente.',
        ], 201);
    }

    // ── Actualizar mapeo existente (petición fetch desde el JS) ───────────────

    public function update(StorePortMappingRequest $request, PortMapping $portMapping): JsonResponse
    {
        Gate::authorize('update', $portMapping);

        $portMapping->update([
            'name'          => $request->name,
            'ip'            => $request->ip,
            'origin_config' => $request->origin_config,
            'dest_config'   => $request->dest_config,
            'mapping_state' => $request->mapping_state,
        ]);

        return response()->json(['message' => 'Mapeo actualizado correctamente.']);
    }

    // ── Eliminar mapeo ─────────────────────────────────────────────────────────

    public function destroy(PortMapping $portMapping): RedirectResponse
    {
        Gate::authorize('delete', $portMapping);

        $portMapping->delete();

        return redirect()
            ->route('admin.port-mapping.index')
            ->with('success', 'Mapeo eliminado.');
    }

    // ── EXTRA: Precargar desde análisis de switch ──────────────────────────────
    // Punto de extensión para poblar automáticamente los estados del origen
    // desde el parser de "Port Summary" que ya existe en el portal.
    //
    // TODO: implementar en sprint futuro.
    //   1. Recibir el switch_id (o batch_id) como parámetro.
    //   2. Leer los puertos desde Switche / SwitcheConnection.
    //   3. Mapear:  Link A → state: 'active'
    //               Link R → state: 'nolink'
    //               Link D → state: 'disabled'
    //               Sin info → state: 'unset'
    //   4. Devolver el estado JSON compatible con window.__PORT_MAPPING__.
    //
    public function loadFromSwitchAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'switch_id' => 'required|exists:switches,id',
        ]);

        // TODO: implementar la lógica real aquí.
        return response()->json([
            'message' => 'Función no implementada aún.',
        ], 501);
    }
}
