<div>
    <select 
        id="sort-{{ $collection }}" 
        class="form-select bg-lf-input-bg border-(length:--lf-border-width) border-lf-border text-lf-text text-lf rounded-lf focus:ring-lf-accent focus:border-lf-accent block w-full p-lf"
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