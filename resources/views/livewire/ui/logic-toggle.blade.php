@if ($this->logic() !== null)
{{-- Reads the locked condition/modifier through $wire, so it also updates inside wire:ignore views --}}
<div
    x-data="{ get logic() { return $wire.condition === 'contains_all' || ($wire.condition === 'taxonomy' && $wire.modifier === 'all') ? 'all' : 'any' } }"
    role="group"
    class="inline-flex self-start mb-3 rounded-lf border-(length:--lf-border-width) border-lf-border bg-lf-input-bg p-0.5 text-xs"
>
    @foreach (['any', 'all'] as $logic)
    <button
        type="button"
        class="px-2.5 py-1 rounded-lf-sm text-lf-muted hover:text-lf-text transition-colors duration-150 aria-pressed:bg-lf-accent aria-pressed:text-white focus:outline-none focus-visible:ring-1 focus-visible:ring-lf-accent"
        aria-pressed="{{ $this->logic() === $logic ? 'true' : 'false' }}"
        x-bind:aria-pressed="logic === '{{ $logic }}'"
        wire:click="setLogic('{{ $logic }}')"
    >
        {{ __('statamic-livewire-filters::ui.match_'.$logic) }}
    </button>
    @endforeach
</div>
@endif
