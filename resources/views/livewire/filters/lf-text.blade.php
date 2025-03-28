<div>
    <div class="grid grid-cols-1">
        <div class="flex items-center">
            <input 
                type="text" 
                class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                id="{{ $field }}"
                @if ($placeholder !== '')
                placeholder="{{ $placeholder }}"
                @endif
                wire:model.live.debounce.300ms="selected"
            >
        </div>
    </div>
    @unless ($selected == '')
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>


