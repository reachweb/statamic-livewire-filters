{{--
    Updates from the browser event without a request; no Alpine state, so a server morph (old open tabs) can't go stale.
    An event without the label (user code, positional `[count]` or `{ count }`) sets the count on the server instead,
    which renders the translated plural.
--}}
<div
    x-data
    x-on:entries-updated.window="
        const detail = $event.detail;
        const count = Number(Array.isArray(detail) ? detail[0] : detail?.count);
        if (typeof detail?.entriesLabel === 'string') {
            $el.firstElementChild.hidden = ! (count > 0);
            $el.firstElementChild.textContent = count + ' ' + detail.entriesLabel;
        } else {
            $wire.$set('count', count);
        }
    "
>
    <span @if (! ($count > 0)) hidden @endif>{{ $count }} {{ trans_choice('statamic-livewire-filters::ui.entries', (int) $count) }}</span>
</div>
