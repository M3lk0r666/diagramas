<x-admin-layout title="Demo Faceplate | Diagramas" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Switches', 'href' => route('admin.switches.index')],
    ['name' => 'Demo Faceplate'],
]">

    <div class="space-y-4 max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800">Demo — Switch Faceplate</h1>
        <p class="text-sm text-gray-500">
            Datos de prueba (48 RJ45 + 4 SFP+). Sin <code>update-url</code>, la edición de descripción queda deshabilitada.
        </p>

        <x-switch-faceplate :device="$device" :ports="$ports" />
    </div>

</x-admin-layout>
