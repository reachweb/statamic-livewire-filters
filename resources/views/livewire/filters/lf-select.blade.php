<div>
    <select 
        id="{{ $field }}" 
        class="form-select bg-lf-input-bg border-(length:--lf-border-width) border-lf-border text-lf-text text-lf rounded-lf focus:ring-lf-accent focus:border-lf-accent block w-full p-lf"
        wire:model.live="selected"
    >
        <option selected value="">{{ $this->placeholder !== '' ? $this->placeholder : __('statamic-livewire-filters::ui.all') }}</option>
        @foreach ($this->filter_options as $value => $label)
            <option 
                value="{{ $value }}">
                {{ $label }}
                @if ($this->counts[$value] !== null)
                <span class="text-gray-500 ml-1">({{ $this->counts[$value] }})</span>
                @endif
            </option>
        @endforeach
    </select>
</div>