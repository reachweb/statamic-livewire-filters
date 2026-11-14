<div>
    <select 
        id="{{ $field }}" 
        class="form-select bg-lf-input-bg border-(length:--lf-border-width) border-lf-border text-lf-text text-lf rounded-lf focus:ring-lf-accent focus:border-lf-accent block w-full p-lf"
        wire:model.live="selected"
    >
        <option value="" @selected($selected === '')>{{ $this->placeholder !== '' ? $this->placeholder : __('statamic-livewire-filters::ui.all') }}</option>
        {{-- Range list bounds without a bucket: shown as chosen but not offered, so the first option can clear them. --}}
        @if (is_string($selected) && isset($this->unmatched_range_option[$selected]))
            <option wire:key="{{ $field }}-unmatched-range" value="{{ $selected }}" selected disabled hidden>{{ $this->unmatched_range_option[$selected] }}</option>
        @endif
        @php($counts = $this->counts)
        @php($disabled = array_flip($this->disabled_options))
        @foreach ($this->visible_options as $value => $label)
            <option wire:key="{{ $field }}-{{ $value }}" value="{{ $value }}" @selected(is_scalar($selected) && (string) $value === (string) $selected) @disabled(isset($disabled[$value]))>
                {{ $label }}
                @if ($counts[$value] !== null)
                <span class="text-gray-500 ml-1">({{ $counts[$value] }})</span>
                @endif
            </option>
        @endforeach
    </select>
</div>