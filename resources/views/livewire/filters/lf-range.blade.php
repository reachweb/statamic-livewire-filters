<div>
    <div
        class="grid grid-cols-1 gap-y-4"
        x-data="{
            init() {
                // Sync the displayed value when the bounds change — the true selection
                // is preserved server-side, only its display is clamped into the bounds
                $wire.on('range-bounds-updated', (bounds) => {
                    $refs.range.value = bounds.selected;
                    $refs.selectedValue.textContent = bounds.selected;
                });
            }
        }"
    >
        <div class="relative mb-3">
            <input
                id="{{ $field }}"
                type="range"
                x-ref="range"
                min="{{ $dynamicMin ?? $min }}"
                max="{{ $dynamicMax ?? $max }}"
                step="{{ $step }}"
                class="w-full h-1 mb-6 bg-gray-200 rounded-lf appearance-none cursor-pointer"
                wire:model.live="selected"
                @disabled($boundsDisabled)
            >
            <span class="text-sm text-lf-text absolute start-0 -bottom-1">{{ $dynamicMin ?? $min }}</span>
            <span class="text-sm text-lf-text absolute left-1/2 -translate-x-1/2 -bottom-1">{{ __('statamic-livewire-filters::ui.value') }}: <span x-ref="selectedValue">{{ $selected }}</span></span>
            <span class="text-sm text-lf-text absolute end-0 -bottom-1">{{ $dynamicMax ?? $max }}</span>
        </div>
    </div>
</div>


