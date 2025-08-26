<div>
    <div class="grid grid-cols-1 gap-y-4">
        <div class="relative mb-3">
            <input 
                id="{{ $field }}" 
                type="range" 
                min="{{ $min }}"
                max="{{ $max }}"
                step="{{ $step }}"
                class="w-full h-1 mb-6 bg-gray-200 rounded-lf appearance-none cursor-pointer"
                wire:model.live="selected"
            >
            <span class="text-sm text-lf-text absolute start-0 -bottom-1">{{ $min }}</span>
            <span class="text-sm text-lf-text absolute left-1/2 -translate-x-1/2 -bottom-1">{{ __('statamic-livewire-filters::ui.value') }}: {{ $selected }}</span>
            <span class="text-sm text-lf-text absolute end-0 -bottom-1">{{ $max }}</span>
        </div>
    </div>
</div>


