{{-- Off-canvas filters panel for deferred mode (defer_filters="true"). Closing without applying discards the queued changes. --}}
<div
    x-data="{
        open: false,
        active: 0,
        pending: 0,
        close(discard = true) {
            if (discard && this.pending > 0) { Livewire.dispatch('discard-filters') }
            this.open = false
        },
    }"
    x-on:entries-updated.window="active = $event.detail?.active ?? 0"
    x-on:pending-filters-updated.window="pending = $event.detail?.count ?? 0"
    x-on:lf-close-drawer.window="close(false)"
    x-on:keydown.escape.window="open && close()"
>
    <button
        type="button"
        x-on:click="open = true"
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
        x-show="open"
        x-on:click="close()"
        x-transition.opacity
        class="fixed inset-0 z-40 bg-black/50"
        aria-hidden="true"
    ></div>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('statamic-livewire-filters::ui.filters') }}"
        class="fixed inset-y-0 left-0 z-50 flex flex-col w-[90vw] max-w-md bg-white shadow-2xl"
    >
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
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

        <div class="flex-1 overflow-y-auto p-4">
            {!! $slot !!}
        </div>

        <div class="p-4 border-t border-gray-200">
            @include('statamic-livewire-filters::livewire.ui.apply-button', ['class' => null])
        </div>
    </div>
</div>
