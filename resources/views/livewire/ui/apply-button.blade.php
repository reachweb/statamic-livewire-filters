{{-- Deferred mode (defer_filters="true"): applies the queued filter changes, or shows the result count when none are queued. --}}
<button
    type="button"
    x-data="{
        pending: 0,
        count: null,
        labels: @js([
            'apply' => __('statamic-livewire-filters::ui.apply'),
            'results' => __('statamic-livewire-filters::ui.show_results'),
            'zero' => trans_choice('statamic-livewire-filters::ui.show_n_results', 0),
            'one' => trans_choice('statamic-livewire-filters::ui.show_n_results', 1),
            'many' => trans_choice('statamic-livewire-filters::ui.show_n_results', 2, ['total' => '__COUNT__']),
        ]),
        get label() {
            if (this.pending > 0) { return this.labels.apply + ' (' + this.pending + ')' }
            if (this.count === null) { return this.labels.results }
            if (this.count === 0) { return this.labels.zero }
            if (this.count === 1) { return this.labels.one }
            return this.labels.many.replace('__COUNT__', this.count)
        },
        apply() {
            // Inside the drawer, closing it applies; on its own the button applies directly.
            if (this.$el.closest('[data-lf-drawer]')) {
                this.$dispatch('lf-close-drawer', { apply: true })
            } else if (this.pending > 0) {
                Livewire.dispatch('apply-filters')
            }
        },
    }"
    x-on:pending-filters-updated.window="pending = $event.detail?.count ?? 0"
    x-on:entries-updated.window="count = $event.detail?.count ?? null"
    x-on:click="apply()"
    x-text="label"
    class="{{ $class ?? 'w-full px-4 py-3 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-4 focus:ring-gray-300' }}"
>{{ __('statamic-livewire-filters::ui.show_results') }}</button>
