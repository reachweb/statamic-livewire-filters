<div>
    <select 
        id="sort-{{ $collection }}" 
        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2"
        wire:model="selected"
    >
        <option selected value="">Default</option>
        @foreach ($this->sortOptions as $option)
            <option 
                value="{{ $option['value'] }}|{{ $option['dir'] }}">
                {{ $option['label'] }} {{ $option['dir'] }}
            </option>
        @endforeach
    </select>
</div>