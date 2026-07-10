@props(['port', 'flip' => false])

{{-- Jack individual del faceplate (usado por switch-faceplate.blade.php) --}}
<button type="button"
        data-sw-jack
        data-id="{{ $port['id'] }}"
        data-type="{{ $port['type'] }}"
        data-status="{{ $port['status']->value }}"
        data-status-label="{{ $port['status']->label() }}"
        data-vlan="{{ $port['vlan'] }}"
        data-desc="{{ $port['description'] }}"
        data-speed="{{ $port['speed'] !== '' ? $port['speed'] . (is_numeric($port['speed']) ? ' Mbps' : '') : '' }}"
        data-duplex="{{ $port['duplex'] !== '' ? ucfirst(strtolower($port['duplex'])) . '-Duplex' : '' }}"
        class="sw-jack {{ $flip ? 'sw-flip' : '' }} {{ $port['status']->cssClass() }}"
        title="Puerto {{ $port['id'] }}{{ $port['description'] !== '' ? ' — ' . $port['description'] : '' }}">&#8983;</button>
