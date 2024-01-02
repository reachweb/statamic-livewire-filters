<div class="grid grid-cols-1 gap-y-4">
    @foreach($this->filter_options as $value => $label)
    <div class="flex items-center">
        <input 
            type="checkbox" 
            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 "
            id="{{ $field }}-{{ $value }}"
            wire:model="selected"
            value="{{ $value }}"
        >
        <label 
            for="{{ $field }}-{{ $value }}" 
            class="ml-2 text-gray-900"
        >
            {{ $label }}
        </label>
    </div>
    @endforeach
</div>