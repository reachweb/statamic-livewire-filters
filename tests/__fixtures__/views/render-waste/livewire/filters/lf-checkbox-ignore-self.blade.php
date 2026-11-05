<div wire:ignore.self x-data="{ open: (1 > 0) }">
    @foreach($this->filter_options as $value => $label)
    <span data-opt="{{ $value }}" class="{{ in_array($value, $selected) ? 'active' : '' }}">{{ $label }}</span>
    @endforeach
</div>
