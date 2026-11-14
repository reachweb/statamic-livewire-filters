<div>
    <div class="grid grid-cols-1 gap-y-4">
        @php($counts = $this->counts)
        @php($disabled = array_flip($this->disabled_options))
        <div class="flex items-center" wire:key="{{ $field }}-all">
            <input
                type="radio"
                class="form-radio size-4 text-lf-accent bg-lf-input-bg border-lf-border focus:ring-lf-accent focus:ring-1"
                id="{{ $field }}-all"
                wire:model.live="selected"
                value=""
                @checked($selected === '' || $selected === null)
            >
            <label for="{{ $field }}-all" class="ml-2 text-lf-text">
                {{ __('statamic-livewire-filters::ui.all') }}
            </label>
        </div>
        @foreach($this->visible_options as $value => $label)
        <div class="flex items-center" wire:key="{{ $field }}-{{ $value }}">
            <input
                type="radio"
                class="form-radio size-4 text-lf-accent bg-lf-input-bg border-lf-border focus:ring-lf-accent focus:ring-1"
                id="{{ $field }}-{{ $value }}"
                wire:model.live="selected"
                value="{{ $value }}"
                @checked(is_scalar($selected) && (string) $value === (string) $selected)
                @disabled(isset($disabled[$value]))
            >
            <label
                for="{{ $field }}-{{ $value }}"
                class="ml-2 text-lf-text{{ isset($disabled[$value]) ? ' opacity-50' : '' }}"
            >
                {{ $label }}
                @if ($counts[$value] !== null)
                <span class="text-lf-muted ml-1">({{ $counts[$value] }})</span>
                @endif
            </label>
        </div>
        @endforeach
    </div>
</div>
