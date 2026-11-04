<div>
    {{--
        Sends filter-updated from the browser, pooled with the wire:model commit: one request instead of two.
        The input's own wire:model listener has already updated $wire.selected, so it is exactly what
        LivewireFilter::dispatchFilterUpdated() would send, values that aren't rendered options included.
    --}}
    <div
        class="grid grid-cols-1 gap-y-4"
        @if ($this->dispatchesFromView())
        x-on:change="if ($event.target.type === 'checkbox') {
            $wire.syncedByView = true;
            $wire.$dispatchTo('livewire-collection', 'filter-updated', {
                field: @js($field),
                condition: @js($condition),
                payload: Array.from($wire.selected ?? []),
                modifier: @js($modifier),
            });
        }"
        @endif
    >
        @foreach($this->filter_options as $value => $label)
        <div class="flex items-center">
            <input 
                type="checkbox" 
                class="form-checkbox size-4 text-lf-accent bg-lf-input-bg border-lf-border rounded-lf-sm focus:ring-lf-accent focus:ring-1"
                id="{{ $field }}-{{ $value }}"
                wire:model.live="selected"
                value="{{ $value }}"
                @checked(in_array((string) $value, array_map('strval', array_filter($selected, 'is_scalar')), true))
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