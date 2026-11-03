<div x-data="{ note: 'wire:ignore', cmp: (a, b) => a > b }" data-attr='wire:ignore'>
    @foreach($this->filter_options as $value => $label)
    <span data-opt="{{ $value }}">{{ $label }}</span>
    @endforeach
</div>
