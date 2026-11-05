<div>
    @if ($count > 0)
    <span>
        {{ $count }} {{ trans_choice('statamic-livewire-filters::ui.entries', $count) }}
    </span>
    @endif
</div>

