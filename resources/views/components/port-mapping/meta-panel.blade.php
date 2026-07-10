{{--
    Componente: Panel de metadatos en dos columnas
    IP compartida arriba (se conserva tras migración).
    Columna izquierda: datos del switch origen.
    Columna derecha:   datos del switch destino.
    Responsive: una columna en móvil (<760px, controlado por CSS del prototipo).
    Todos los inputs son editables en vivo — JS actualiza el estado sin reiniciar el mapeo.
--}}
<div class="meta-panel">

    {{-- IP compartida: la misma IP se reutiliza en el switch de reemplazo --}}
    <div class="meta-ip">
        <label for="inpIp">IP (se conserva en el destino):</label>
        <input type="text" id="inpIp" placeholder="192.168.1.61" style="width:170px">
    </div>

    {{-- Columna izquierda: ORIGEN --}}
    <div class="meta-col">
        <h4>DATOS ORIGEN</h4>
        <div class="row">
            <label for="inpOriginModel">Modelo:</label>
            <input type="text" id="inpOriginModel" placeholder="X440-G2-24p" style="width:190px">
        </div>
        <div class="row">
            <label id="lblSerial1">Serial:</label>
            <input type="text" id="inpOriginSerial1" placeholder="2236G-01026" style="width:140px">
            {{-- Serial 2 solo visible en modo stack (2×24) — JS lo controla --}}
            <label id="lblSerial2" style="display:none">Serial 2:</label>
            <input type="text" id="inpOriginSerial2" placeholder="2236G-01027"
                   style="width:140px; display:none">
        </div>
    </div>

    {{-- Columna derecha: DESTINO --}}
    <div class="meta-col">
        <h4>DATOS DESTINO</h4>
        <div class="row">
            <label for="inpDestModel">Modelo:</label>
            <input type="text" id="inpDestModel" placeholder="X440-G2-48p-10G4" style="width:190px">
        </div>
        <div class="row">
            <label for="inpDestSerial">Serial:</label>
            <input type="text" id="inpDestSerial" placeholder="2236N-40876" style="width:140px">
        </div>
    </div>

</div>
