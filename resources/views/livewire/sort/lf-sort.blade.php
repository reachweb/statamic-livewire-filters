<div>
    <select 
        id="sort-{{ $collection }}" 
        class="form-select bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2"
        wire:model.live="selected"
    >
        <option selected value="">Default</option>
        @foreach ($this->sortOptions as $option)
            <option 
                value="{{ $option['value'] }}:{{ $option['dir'] }}">
                {{ $option['label'] }} | {{ Str::upper($option['dir']) }}
            </option>
        @endforeach
    </select>
</div>