<div>
    <select 
        id="{{ $field }}" 
        class="form-select bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2"
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