{{-- Updates from the browser event without a request; no Alpine state, so a server morph (old open tabs) can't go stale. --}}
<div
    x-data
    x-on:entries-updated.window="
        const count = Number($event.detail.count);
        $el.firstElementChild.hidden = ! (count > 0);
        if ($event.detail.entriesLabel !== undefined) $el.firstElementChild.textContent = count + ' ' + $event.detail.entriesLabel;
    "
>
    <span @if (! ($count > 0)) hidden @endif>{{ $count }} {{ trans_choice('statamic-livewire-filters::ui.entries', (int) $count) }}</span>
</div>
