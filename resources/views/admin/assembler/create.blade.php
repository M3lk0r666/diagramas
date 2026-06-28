<x-admin-layout title="Nuevo proyecto | Ensamblador" :breadcrumbs="[
    ['name' => 'Dashboard', 'href' => route('dashboard')],
    ['name' => 'Ensamblador', 'href' => route('admin.assembler.index')],
    ['name' => 'Nuevo proyecto'],
]">

    <div class="max-w-lg mx-auto py-10 px-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Nuevo proyecto de diagrama</h1>

            <form method="POST" action="{{ route('admin.assembler.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        Nombre del proyecto *
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                           placeholder="Ej: Diagrama Global Campus Norte"
                           class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        Cliente *
                    </label>
                    <select name="client_id" required
                            class="w-full border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">— Seleccionar cliente —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type selector --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Tipo de diagrama *
                    </label>
                    <div class="grid grid-cols-2 gap-3" id="type-grid">
                        {{-- PNG Assembler --}}
                        <label class="relative cursor-pointer" onclick="selectType('png')">
                            <input type="radio" name="type" value="png"
                                   id="type-png" class="sr-only" {{ old('type', 'png') === 'png' ? 'checked' : '' }}>
                            <div id="card-png" class="type-card border-2 rounded-xl p-4 transition-all
                                {{ old('type', 'png') === 'png' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-gray-200' }}">
                                <i class="ri-image-2-line text-2xl text-blue-500 block mb-2"></i>
                                <p class="text-sm font-semibold text-gray-800">Ensamblador PNG</p>
                                <p class="text-xs text-gray-500 mt-0.5 leading-tight">
                                    Compone imágenes de topología ya generadas en un lienzo libre.
                                </p>
                                <span id="check-png" class="absolute top-2 right-2 text-blue-500 text-lg
                                    {{ old('type', 'png') === 'png' ? '' : 'hidden' }}">
                                    <i class="ri-checkbox-circle-fill"></i>
                                </span>
                            </div>
                        </label>

                        {{-- Vectorial --}}
                        <label class="relative cursor-pointer" onclick="selectType('vectorial')">
                            <input type="radio" name="type" value="vectorial"
                                   id="type-vectorial" class="sr-only" {{ old('type') === 'vectorial' ? 'checked' : '' }}>
                            <div id="card-vectorial" class="type-card border-2 rounded-xl p-4 transition-all
                                {{ old('type') === 'vectorial' ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200' : 'border-gray-200' }}">
                                <i class="ri-node-tree text-2xl text-indigo-500 block mb-2"></i>
                                <p class="text-sm font-semibold text-gray-800">Diagrama Vectorial</p>
                                <p class="text-xs text-gray-500 mt-0.5 leading-tight">
                                    Nodos SVG de switches con conexiones automáticas desde la base de datos.
                                </p>
                                <span id="check-vectorial" class="absolute top-2 right-2 text-indigo-500 text-lg
                                    {{ old('type') === 'vectorial' ? '' : 'hidden' }}">
                                    <i class="ri-checkbox-circle-fill"></i>
                                </span>
                            </div>
                        </label>
                    </div>
                    @error('type')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('admin.assembler.index') }}"
                       class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" id="submit-btn"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Crear y abrir editor
                    </button>
                </div>

                <script>
                const typeStyles = {
                    png:      { card: 'border-blue-500 bg-blue-50 ring-2 ring-blue-200',     check: 'text-blue-500'   },
                    vectorial:{ card: 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200', check: 'text-indigo-500' },
                };
                function selectType(type) {
                    document.getElementById('type-' + type).checked = true;
                    ['png','vectorial'].forEach(t => {
                        const card  = document.getElementById('card-' + t);
                        const check = document.getElementById('check-' + t);
                        if (t === type) {
                            card.className  = 'type-card border-2 rounded-xl p-4 transition-all ' + typeStyles[t].card;
                            check.className = 'absolute top-2 right-2 text-lg ' + typeStyles[t].check;
                        } else {
                            card.className  = 'type-card border-2 rounded-xl p-4 transition-all border-gray-200';
                            check.className = 'absolute top-2 right-2 text-lg hidden';
                        }
                    });
                    // Update submit button color
                    const btn = document.getElementById('submit-btn');
                    btn.className = btn.className.replace(/bg-\w+-600|hover:bg-\w+-700/g, '');
                    const color = type === 'vectorial' ? 'indigo' : 'blue';
                    btn.className += ` bg-${color}-600 hover:bg-${color}-700`;
                }
                </script>
            </form>
        </div>
    </div>

</x-admin-layout>
