<div>
    <div class="grid grid-cols-1">
        <div class="flex items-center">
            <input 
                type="text" 
                class="form-input bg-lf-input-bg border-(length:--lf-border-width) border-lf-border text-lf-text text-lf rounded-lf focus:ring-lf-accent focus:border-lf-accent block w-full p-lf"
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


