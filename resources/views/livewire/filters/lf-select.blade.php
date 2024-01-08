<div>
    <select 
        id="{{ $field }}" 
        class="form-select bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2"
        wire:model.live="selected"
    >
        <option selected value="">{{ __('statamic-livewire-filters::ui.all') }}</option>
        @foreach ($this->filter_options as $value => $label)
            <option 
                value="{{ $value }}">
                {{ $label }}
            </option>
        @endforeach
    </select>
    @unless ($selected == '')
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>