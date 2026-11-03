<div>
    <div class="grid grid-cols-1 gap-y-4">
        @php($counts = $this->counts)
        @php($disabled = array_flip($this->disabled_options))
        @foreach($this->visible_options as $value => $label)
        <div class="flex items-center" wire:key="{{ $field }}-{{ $value }}">
            <input 
                type="checkbox" 
                class="form-checkbox size-4 text-lf-accent bg-lf-input-bg border-lf-border rounded-lf-sm focus:ring-lf-accent focus:ring-1"
                id="{{ $field }}-{{ $value }}"
                wire:model.live="selected"
                value="{{ $value }}"
                @disabled(isset($disabled[$value]))
            >
            <label
                for="{{ $field }}-{{ $value }}"
                class="ml-2 text-lf-text{{ isset($disabled[$value]) ? ' opacity-50' : '' }}"
            >
                {{ $label }}
                @if ($counts[$value] !== null)
                <span class="text-lf-muted ml-1">({{ $counts[$value] }})</span>
                @endif
            </label>
        </div>
        @endforeach
    </div>
    @unless ($selected == [])
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>