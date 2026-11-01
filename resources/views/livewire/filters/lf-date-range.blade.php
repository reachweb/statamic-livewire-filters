@php
    $earliest = $this->filter_options['earliest_date'] ?? null;
    $latest = $this->filter_options['latest_date'] ?? null;
    // Each end's calendar is bounded by the blueprint limits and by the other end.
    $ends = [
        ['property' => 'selectedFrom', 'other' => 'selectedTo', 'bound' => 'maxDate', 'fallback' => $latest, 'suffix' => 'from', 'label' => __('statamic-livewire-filters::ui.start_date')],
        ['property' => 'selectedTo', 'other' => 'selectedFrom', 'bound' => 'minDate', 'fallback' => $earliest, 'suffix' => 'to', 'label' => __('statamic-livewire-filters::ui.end_date')],
    ];
@endphp
<div class="@container">
    <div class="grid grid-cols-1 @xs:grid-cols-2 gap-2">
        @foreach ($ends as $end)
        {{-- Server changes (presets, clearing, URL) can move both ends at once: refresh the
             bound before applying the value, and don't echo the update back. --}}
        <div
            wire:ignore
            class="relative"
            x-data="calendar({
                mode: 'single',
                display: 'popup',
                format: @js($format),
                locale: @js($this->calendarLocale()),
                timezone: @js(config('app.timezone')),
                inputId: @js($field.'-'.$end['suffix']),
                minDate: @js($earliest),
                maxDate: @js($latest),
                value: $wire.{{ $end['property'] }} || undefined,
            })"
            x-init="
                const sync = () => {
                    updateConstraints({ {{ $end['bound'] }}: $wire.{{ $end['other'] }} || @js($end['fallback']) || undefined });
                    const value = $wire.{{ $end['property'] }} || '';
                    if (value === (getSelection()[0]?.toISO() ?? '')) return;
                    $el.lfSyncing = true;
                    value ? setValue(value) : clearSelection();
                    $el.lfSyncing = false;
                };
                sync();
                $watch('$wire.{{ $end['other'] }}', sync);
                $watch('$wire.{{ $end['property'] }}', sync);
            "
            x-on:calendar:change="
                if ($el.lfSyncing) return;
                const value = $event.detail.dates[0] ?? '';
                if (value !== ($wire.{{ $end['property'] }} || '')) $wire.set('{{ $end['property'] }}', value);
            "
        >
            <div class="absolute inset-y-0 start-0 flex z-10 items-center ps-3.5 pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </div>
            <input
                x-ref="rc-input"
                type="text"
                class="form-input bg-lf-input-bg border-(length:--lf-border-width) border-lf-border text-lf-text text-lf rounded-lf focus:ring-lf-accent focus:border-lf-accent block w-full py-lf ps-lf-icon pe-lf"
                aria-label="{{ $end['label'] }}"
                placeholder="{{ $end['label'] }}"
                data-date-range="{{ $end['suffix'] }}"
            >
        </div>
        @endforeach
    </div>
    @if (count($this->presetOptions) > 0)
    <div class="flex flex-wrap gap-2 mt-3">
        @foreach ($this->presetOptions as $preset)
        <button
            type="button"
            wire:click="applyPreset('{{ $preset }}')"
            wire:key="{{ $field }}-preset-{{ $preset }}"
            @class([
                'px-3 py-1.5 text-lf rounded-lf border-(length:--lf-border-width) cursor-pointer transition-colors',
                'bg-lf-accent border-lf-accent text-white' => $this->activePreset === $preset,
                'bg-lf-input-bg border-lf-border text-lf-text hover:bg-lf-subtle' => $this->activePreset !== $preset,
            ])
            aria-pressed="{{ $this->activePreset === $preset ? 'true' : 'false' }}"
        >
            {{ __('statamic-livewire-filters::ui.preset_'.$preset) }}
        </button>
        @endforeach
    </div>
    @endif
    @unless ($selectedFrom === '' && $selectedTo === '')
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>
