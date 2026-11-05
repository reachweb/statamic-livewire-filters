{{--
    Filters panel for deferred mode. Below the breakpoint it is an off-canvas drawer with a
    "Filters (n)" trigger; opening it starts deferring (defer_filters="drawer"), and closing it
    applies (Apply button) or discards (✕, backdrop, Escape) the queued changes. From the
    breakpoint up it is a plain sidebar whose filters apply live, unless defer_filters="true".
--}}
<div
    data-lf-drawer
    x-data="{
        open: false,
        desktop: false,
        active: 0,
        pending: 0,
        pendingStop: null,
        init() {
            // Counts Livewire requests in flight, so a discard can wait for a filter change already on its way.
            if (! window.__lfRequests) {
                const requests = window.__lfRequests = { inFlight: 0, lastActivity: 0 };
                const done = () => { requests.inFlight = Math.max(0, requests.inFlight - 1); requests.lastActivity = performance.now() };
                Livewire.hook('request', ({ succeed, fail }) => {
                    requests.inFlight++;
                    requests.lastActivity = performance.now();
                    succeed(done);
                    fail(done);
                });
            }
            const breakpoint = @js((int) ($breakpoint ?? 1280));
            if (breakpoint < 1) { return }
            const query = window.matchMedia('(min-width: ' + breakpoint + 'px)');
            this.desktop = query.matches;
            query.addEventListener('change', (event) => {
                // Widening past the breakpoint with the drawer open keeps what was chosen.
                if (event.matches) { this.close(true) }
                this.desktop = event.matches;
            });
        },
        show() {
            // Reopened before a discard was sent: the queued changes are still there to apply.
            clearTimeout(this.pendingStop);
            this.pendingStop = null;
            this.open = true;
            Livewire.dispatch('start-deferring');
        },
        close(apply = false) {
            if (this.desktop) {
                if (apply && this.pending > 0) { Livewire.dispatch('apply-filters') }
                return;
            }
            if (! this.open) { return }
            this.open = false;
            if (apply) {
                Livewire.dispatch('stop-deferring', { apply: true });
                return;
            }
            this.discardWhenSettled();
        },
        // A filter change made just before closing reaches the collection one round trip later
        // (or after the bundled text and date filters' 300ms debounce). Discarding first would end
        // deferral and let it apply, so wait until those requests have settled.
        discardWhenSettled() {
            const requests = window.__lfRequests;
            const closedAt = performance.now();
            const check = () => {
                const now = performance.now();
                const settled = now - closedAt >= 350 && requests.inFlight === 0 && now - requests.lastActivity >= 100;
                if (! settled && now - closedAt < 10000) {
                    this.pendingStop = setTimeout(check, 50);
                    return;
                }
                this.pendingStop = null;
                Livewire.dispatch('stop-deferring', { apply: false });
            };
            clearTimeout(this.pendingStop);
            this.pendingStop = setTimeout(check, 50);
        },
    }"
    x-on:entries-updated.window="active = $event.detail?.active ?? 0"
    x-on:pending-filters-updated.window="pending = $event.detail?.count ?? 0"
    x-on:lf-close-drawer="close($event.detail?.apply ?? false)"
    x-on:keydown.escape.window="open && close()"
>
    <button
        type="button"
        x-cloak
        x-show="! desktop"
        x-on:click="show()"
        x-bind:aria-expanded="open"
        class="{{ $class ?? 'w-full flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300' }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75" />
        </svg>
        <span>{{ __('statamic-livewire-filters::ui.filters') }}<span x-cloak x-show="active > 0" x-text="' (' + active + ')'"></span></span>
    </button>

    <div
        x-cloak
        x-show="open && ! desktop"
        x-on:click="close()"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/50"
        aria-hidden="true"
    ></div>

    <div
        x-cloak
        x-show="open || desktop"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        x-bind:role="desktop ? null : 'dialog'"
        x-bind:aria-modal="desktop ? null : 'true'"
        aria-label="{{ __('statamic-livewire-filters::ui.filters') }}"
        x-bind:class="desktop ? '' : 'fixed inset-y-0 left-0 z-50 flex flex-col w-[90vw] max-w-md bg-white shadow-2xl'"
    >
        <div x-show="! desktop" class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold">{{ __('statamic-livewire-filters::ui.filters') }}</h2>
            <button
                type="button"
                x-on:click="close()"
                class="p-2 text-gray-500 hover:text-gray-900"
                aria-label="{{ __('statamic-livewire-filters::ui.close') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div x-bind:class="desktop ? '' : 'flex-1 overflow-y-auto p-4'">
            {!! $slot !!}
        </div>

        {{-- On desktop only shown while changes are queued, which happens with defer_filters="true". --}}
        <div x-show="! desktop || pending > 0" x-bind:class="desktop ? 'pt-4' : 'p-4 border-t border-gray-200'">
            @include('statamic-livewire-filters::livewire.ui.apply-button', ['class' => null])
        </div>
    </div>
</div>
