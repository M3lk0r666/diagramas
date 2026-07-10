{{--
    Componente: Faceplate de switch (contenedor)
    Proporciona la estructura del panel. Los jacks (puertos individuales)
    son generados dinámicamente por JS en port-mapping.js mediante buildPortsArea().

    Props:
      @param string $side      'o' (origen) | 'd' (destino)
      @param int    $unit      Índice de unidad en stack (0-based). Null para destino.
      @param int    $copper    Cantidad de puertos de cobre
      @param int    $fiber     Cantidad de puertos SFP (0 si ninguno)
      @param bool   $isStack   true si es stack (muestra nota de bloque)
--}}
@props([
    'side'    => 'o',
    'unit'    => 0,
    'copper'  => 48,
    'fiber'   => 4,
    'isStack' => false,
])

@php
    $panelId  = $side === 'o' ? 'panel-o-' . $unit : 'panelDest';
    $areaId   = $side === 'o' ? 'area-o-'  . $unit : 'areaDest';
    $titleId  = $side === 'o' ? 'title-o-' . $unit : 'titleDest';
    $subId    = $side === 'o' ? 'sub-o-'   . $unit : 'subDest';
    $titleTxt = $side === 'o'
        ? 'SWITCH ORIGEN' . ($isStack ? ' ' . ($unit + 1) : '') . ' (' . $copper . ($fiber ? ' + ' . $fiber . ' SFP' : '') . ')'
        : 'SWITCH DESTINO (' . $copper . ($fiber ? ' + ' . $fiber . ' SFP' : '') . ')';
@endphp

<div class="panel" id="{{ $panelId }}">
    <div class="panel-title" id="{{ $titleId }}">{{ $titleTxt }}</div>
    <div class="panel-sub {{ $side === 'o' ? 'origin-sub' : '' }}" id="{{ $subId }}"></div>

    {{-- Los jacks se insertan aquí por JS vía buildPortsArea() --}}
    <div class="ports-area" id="{{ $areaId }}"></div>

    {{-- Nota de bloque en stacks: muestra el rango del destino --}}
    @if($isStack)
    @php
        $start = $unit * $copper + 1;
        $end   = $start + $copper - 1;
    @endphp
    <div class="block-note" id="block-note-{{ $unit }}">
        → Destino {{ $start }}–{{ $end }} · el orden se mantiene
    </div>
    @endif
</div>
