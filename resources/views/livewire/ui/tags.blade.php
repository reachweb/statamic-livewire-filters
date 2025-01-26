<div class="flex justify-between items-start">
    <div class="flex flex-wrap gap-2">
        @foreach ($this->tags as $tag)
        <button
            type="button"
            wire:key="{{ $tag['field'].$tag['value'] }}"
            class="py-2.5 px-5 me-2 mb-2 text-sm font-medium inline-flex items-center text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 
                hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 cursor-default transition-opacity duration-500"
            wire:loading.class="opacity-40"
            >
                <span class="whitespace-nowrap">{{ $tag['fieldLabel'] }}{{ trans('statamic-livewire-filters::ui.'.$tag['condition']) }} {{ $tag['optionLabel'] }}</span>
                <div class="ml-3 cursor-pointer" wire:click="removeOption('{{ $tag['field'] }}', '{{ $tag['value'] }}')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </div>
        </button>
        @endforeach
    </div>
    @if ($this->tags->count() > 0)
    <button
        type="button"
        class="py-2.5 px-5 me-2 mb-2 text-sm font-medium inline-flex items-center text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 
            hover:bg-gray-100 hover:text-blue-700 focus:z-10 focus:ring-4 focus:ring-gray-100 cursor-default transition-opacity duration-500"
        wire:click="clearAll()"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
        </svg>          
        <span class="ml-3 whitespace-nowrap">{{ trans('statamic-livewire-filters::ui.clear_all') }}</span>
    </button>
    @endif
</div>
