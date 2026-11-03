<div>
    {{-- Sends filter-updated from the browser, pooled with the wire:model commit: one request instead of two. --}}
    <div
        class="grid grid-cols-1 gap-y-4"
        x-on:change="if ($event.target.type === 'checkbox') {
            const checked = Array.from($el.querySelectorAll('input[type=checkbox]:checked')).map((input) => input.value);
            const kept = Array.from($wire.selected ?? []).map(String).filter((value) => checked.includes(value));
            $wire.syncedByView = true;
            $wire.$dispatchTo('livewire-collection', 'filter-updated', {
                field: @js($field),
                condition: @js($condition),
                payload: [...kept, ...checked.filter((value) => ! kept.includes(value))],
                modifier: @js($modifier),
            });
        }"
    >
        @foreach($this->filter_options as $value => $label)
        <div class="flex items-center">
            <input 
                type="checkbox" 
                class="form-checkbox size-4 text-lf-accent bg-lf-input-bg border-lf-border rounded-lf-sm focus:ring-lf-accent focus:ring-1"
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
    @unless ($selected == [])
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>