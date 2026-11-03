<div wire:ignore.children class="x">
    @foreach($this->filter_options as $value => $label)
    <span data-opt="{{ $value }}">{{ $label }}</span>
    @endforeach
</div>
