<div>
    @foreach($this->filter_options as $value => $label)
    <label><input type="checkbox" wire:model.live="selected" value="{{ $value }}"> <span data-opt="{{ $value }}" class="{{ in_array($value, $selected) ? 'active' : '' }}">{{ $label }}</span></label>
    @endforeach
    @error('selected') <p class="err">{{ $message }}</p> @enderror
</div>
