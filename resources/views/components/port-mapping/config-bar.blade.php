{{--
    Componente: Barra de configuración de capacidades
    Selectores de origen (tipo + fibra) y destino (cobre + fibra) + botones de acción.
    Los IDs son los que espera port-mapping.js.
--}}
<div class="config-bar">
    <label>Origen:</label>
    <select id="selOriginType">
        <option value="24">1 switch de 24</option>
        <option value="48" selected>1 switch de 48</option>
        <option value="2x24">2 switches de 24 (stack)</option>
    </select>
    <select id="selOriginFiber">
        <option value="0">sin fibra</option>
        <option value="4" selected>+ 4 SFP</option>
        <option value="6">+ 6 SFP</option>
    </select>

    <span class="sep">|</span>

    <label>Destino:</label>
    <select id="selDestType">
        <option value="24">24 puertos</option>
        <option value="48" selected>48 puertos</option>
    </select>
    <select id="selDestFiber">
        <option value="0">sin fibra</option>
        <option value="4" selected>+ 4 SFP</option>
        <option value="6">+ 6 SFP</option>
    </select>

    <button class="pm-btn" id="btnApply">Aplicar</button>

    <div class="pm-spacer"></div>

    {{-- Botones de respaldo JSON --}}
    <button class="pm-btn" id="btnSave" title="Exportar estado como archivo JSON">
        <i class="ri-download-line"></i> JSON
    </button>
    <button class="pm-btn" id="btnLoad" title="Cargar estado desde archivo JSON">
        <i class="ri-upload-line"></i> Cargar JSON
    </button>
    <input type="file" id="fileLoad" accept=".json" style="display:none">

    <button class="pm-btn primary" id="btnPng">
        <i class="ri-image-line"></i> Exportar PNG
    </button>
</div>
