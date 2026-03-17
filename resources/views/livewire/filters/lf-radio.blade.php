<div>
    <div class="grid grid-cols-1 gap-y-4">
        @foreach($this->filter_options as $value => $label)
        <div class="flex items-center">
            <input 
                type="radio" 
                class="form-radio size-4 text-lf-accent bg-lf-input-bg border-lf-border focus:ring-lf-accent focus:ring-1"
                id="{{ $field }}-{{ $value }}"
                wire:model.live="selected"
                value="{{ $value }}"
            >
            <label
                for="{{ $field }}-{{ $value }}"
                class="ml-2 text-lf-text"
            >
                {{ $label }}
                @if ($this->counts[$value] !== null)
                <span class="text-lf-muted ml-1">({{ $this->counts[$value] }})</span>
                @endif
            </label>
        </div>
        @endforeach
    </div>
    @unless ($selected == '')
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>


