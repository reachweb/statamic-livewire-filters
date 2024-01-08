<div>
    <div class="grid grid-cols-1">
        <div class="relative">
            <div class="absolute inset-y-0 start-0 flex z-1 items-center ps-3.5 pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
            </div>
            <input 
                type="text" 
                class="form-input bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 ps-10"
                id="{{ $field }}"
                wire:model.live.debounce.300ms="selected"
                data-flatpickr
            >
        </div>
    </div>
    @unless ($selected == '')
    <div class="mt-4">
        @include('statamic-livewire-filters::livewire.ui.clear-filters-button')
    </div>
    @endunless
</div>

@script
<script>
    flatpickr($wire.$el.querySelector('[data-flatpickr]'), {
        minDate: '{{ $this->filter_options['earliest_date'] ?? ''}}',
        maxDate: '{{ $this->filter_options['latest_date'] ?? ''}}',
    })
</script>
@endscript


