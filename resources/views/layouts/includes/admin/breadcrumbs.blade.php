@if (count($breadcrumbs))
    <nav class="mb-4">
        <ol class="flex flex-wrap">
            @foreach ($breadcrumbs as $item)
                <li class="text-sm leading-normal text-slate-700 {{ !$loop->first ?: '' }}">
                    @isset($item['href'])
                        <a href="{{ $item['href'] }}"
                            class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-700 dark:hover:text-white">
                            <strong>{{ $item['name'] }}</strong>

                            <svg class="rtl:rotate-180 w-3 h-3 text-gray-400 mx-1" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m1 9 4-4-4-4" />
                            </svg>
                        </a>
                    @else
                        <span
                            class="ms-1 text-sm font-medium text-gray-500 md:ms-2 dark:text-gray-400">{{ $item['name'] }}</span>
                    @endisset
                </li>
            @endforeach
        </ol>

        @if (count($breadcrumbs) > 1)
            <h6 class="font-bold mt-2">
                <x-wire-badge outline blue>
                    <h4 class="text-2xl font-bold dark:text-white">{{ end($breadcrumbs)['name'] }}</h4>
                </x-wire-badge>
            </h6>
        @endif
    </nav>
@endif
