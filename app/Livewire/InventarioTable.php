<?php

namespace App\Livewire;

use App\Models\Switche;
use App\Models\UploadBatch;
use App\Services\TopologyBuilderService;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;

class InventarioTable extends DataTableComponent
{
    /** ID del cliente que se filtra */
    public int $clientId;

    // ── Configuración general ───────────────────────────────────────────

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setAdditionalSelects(['switches.stack_members']);
        $this->setDefaultSort('sys_name', 'asc');
        $this->setPerPageAccepted([25, 50, 100, 250]);
        $this->setPerPage(50);
        $this->setColumnSelectEnabled();
        $this->setEmptyMessage('No hay switches registrados para este cliente.');
        $this->setSearchDebounce(300);

        // Estilos alineados con el diseño existente
        $this->setTableAttributes(['class' => 'w-full text-sm']);
        $this->setTheadAttributes(['class' => 'bg-blue-50 border-b border-gray-100 text-gray-500 uppercase tracking-wide text-[11px]']);
        $this->setThAttributes(fn (Column $column) => ['class' => 'px-4 py-2.5 text-left font-semibold whitespace-nowrap']);
        $this->setTbodyAttributes(['class' => 'divide-y divide-gray-50']);
        $this->setTrAttributes(fn ($row, $selected) => ['class' => 'hover:bg-orange-50/40 transition']);
        $this->setTdAttributes(fn (Column $column, $row) => ['class' => 'px-4 py-2.5']);
    }

    // ── Query ───────────────────────────────────────────────────────────

    public function builder(): Builder
    {
        return Switche::with(['batch'])
            ->whereHas('batch', fn ($q) => $q->where('client_id', $this->clientId))
            ->where('parse_status', 'ok');
    }

    // ── Columnas ────────────────────────────────────────────────────────

    public function columns(): array
    {
        return [
            Column::make('Área', 'batch.name')
                ->sortable(fn (Builder $q, string $dir) =>
                    $q->leftJoin('upload_batches as ub', 'switches.upload_batch_id', '=', 'ub.id')
                      ->orderBy('ub.name', $dir)
                      ->select('switches.*')
                )
                ->searchable(fn (Builder $q, string $term) =>
                    $q->orWhereHas('batch', fn ($b) => $b->where('name', 'like', "%{$term}%"))
                ),

            Column::make('Hostname', 'sys_name')
                ->sortable()
                ->searchable()
                ->format(fn ($value, $row) =>
                    $row?->id
                        ? '<a href="'.url('/admin/switches/'.$row->id).'"
                               class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">'
                          .e($value ?? '—').'</a>'
                        : e($value ?? '—')
                )
                ->html(),

            Column::make('IP Gestión', 'management_ip')
                ->sortable()
                ->searchable()
                ->format(fn ($v) => '<span class="font-mono text-gray-600 text-xs">'.e($v ?? '—').'</span>')
                ->html(),

            Column::make('Modelo', 'system_type')
                ->sortable()
                ->searchable()
                ->format(fn ($v) => '<span class="text-gray-600 text-xs">'.e($v ?? '—').'</span>')
                ->html(),

            Column::make('Serie', 'serial_number')
                ->sortable()
                ->searchable()
                ->format(fn ($v, $row) => $row ? $this->renderSerial($row) : e($v ?? '—'))
                ->html(),

            Column::make('MAC', 'system_mac')
                ->sortable()
                ->searchable()
                ->format(fn ($v, $row) => $row ? $this->renderMac($row) : e($v ?? '—'))
                ->html(),

            Column::make('Firmware', 'firmware_version')
                ->sortable()
                ->format(fn ($v) => '<span class="text-xs text-gray-500">'.e($v ?? '—').'</span>')
                ->html(),

            Column::make('Arreglo', 'is_stacked')
                ->sortable()
                ->format(fn ($v, $row) => $row ? $this->renderRole($row) : '—')
                ->html(),
        ];
    }

    // ── Filtros ─────────────────────────────────────────────────────────

    public function filters(): array
    {
        $areas = UploadBatch::where('client_id', $this->clientId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return [
            SelectFilter::make('Área', 'area')
                ->options(['' => 'Todas las áreas'] + $areas)
                ->filter(fn (Builder $q, $v) => $v ? $q->where('upload_batch_id', $v) : null),

            SelectFilter::make('Arreglo', 'arreglo')
                ->options(['' => 'Todos', '1' => 'Stack', '0' => 'Estándar'])
                ->filter(fn (Builder $q, $v) => $v !== '' ? $q->where('is_stacked', (bool) $v) : null),
        ];
    }

    // ── Exportación CSV (acción Livewire) ───────────────────────────────

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $switches = $this->builder()->get([
            'id', 'sys_name', 'system_type', 'serial_number', 'system_mac',
            'management_ip', 'firmware_version', 'is_stacked', 'ip_routes',
            'stack_members', 'upload_batch_id',
        ])->load('batch');

        $filename = 'inventario-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($switches) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

            fputcsv($handle, [
                'Área', 'Hostname', 'IP Gestión', 'Modelo',
                'Serie', 'MAC', 'Firmware', 'Default Route', 'Arreglo',
            ]);

            foreach ($switches as $s) {
                $defaultRoute = collect($s->ip_routes ?? [])
                    ->first(fn ($r) => ($r['destination'] ?? '') === 'Default Route');

                $role = $s->is_stacked
                    ? 'Stack'
                    : ucfirst(TopologyBuilderService::detectRoleStatic($s->sys_name ?? ''));

                $serial = ($s->is_stacked && !empty($s->stack_members))
                    ? collect($s->stack_members)
                        ->map(fn ($m) => 'S'.($m['slot'] ?? '?').': '.($m['serial_number'] ?? '—'))
                        ->join(' | ')
                    : ($s->serial_number ?? '—');

                $mac = ($s->is_stacked && !empty($s->stack_members))
                    ? collect($s->stack_members)
                        ->map(fn ($m) => 'S'.($m['slot'] ?? '?').': '.($m['mac'] ?? '—'))
                        ->join(' | ')
                    : ($s->system_mac ?? '—');

                fputcsv($handle, [
                    $s->batch?->name          ?? '—',
                    $s->sys_name              ?? '—',
                    $s->management_ip         ?? '—',
                    $s->system_type           ?? '—',
                    $serial,
                    $mac,
                    $s->firmware_version      ?? '—',
                    $defaultRoute['gateway']  ?? '—',
                    $role,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Helpers privados ────────────────────────────────────────────────

    private function renderSerial(Switche $row): string
    {
        if ($row->is_stacked && !empty($row->stack_members)) {
            return collect($row->stack_members)
                ->map(fn ($m) =>
                    '<span class="block font-mono text-gray-500 text-xs leading-5">'
                    . '<span class="text-gray-400 text-[10px]">S'.($m['slot'] ?? '?').':</span> '
                    . e($m['serial_number'] ?? '—')
                    . '</span>'
                )
                ->join('');
        }
        return '<span class="font-mono text-gray-500 text-xs">'.e($row->serial_number ?? '—').'</span>';
    }

    private function renderMac(Switche $row): string
    {
        if ($row->is_stacked && !empty($row->stack_members)) {
            return collect($row->stack_members)
                ->map(fn ($m) =>
                    '<span class="block font-mono text-gray-500 text-xs leading-5">'
                    . '<span class="text-gray-400 text-[10px]">S'.($m['slot'] ?? '?').':</span> '
                    . e($m['mac'] ?? '—')
                    . '</span>'
                )
                ->join('');
        }
        return '<span class="font-mono text-gray-500 text-xs">'.e($row->system_mac ?? '—').'</span>';
    }

    private function renderRole(Switche $row): string
    {
        $roleLabels = [
            'core'     => 'Core',
            'backbone' => 'Backbone',
            'dist'     => 'Dist',
            'access'   => 'Access',
            'stack'    => 'Stack',
        ];
        $roleColors = [
            'core'     => 'text-blue-700 bg-blue-50',
            'backbone' => 'text-purple-700 bg-purple-50',
            'dist'     => 'text-teal-700 bg-teal-50',
            'access'   => 'text-sky-700 bg-sky-50',
            'stack'    => 'text-amber-700 bg-amber-50',
        ];

        $role  = $row->is_stacked ? 'stack' : TopologyBuilderService::detectRoleStatic($row->sys_name ?? '');
        $label = $roleLabels[$role] ?? ucfirst($role);
        $color = $roleColors[$role] ?? 'text-gray-600 bg-gray-50';

        return '<span class="text-[11px] font-semibold px-2 py-0.5 rounded-full '.$color.'">'.$label.'</span>';
    }
}
