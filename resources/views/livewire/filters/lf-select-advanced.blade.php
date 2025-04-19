<div 
    x-data="{
        allOptions: Object.entries(JSON.parse('{{ json_encode($this->filter_options) }}')).map(([value, label]) => ({ value, label })),
        options: [],
        counts: {},
        isOpen: false,
        openedWithKeyboard: false,
        selected: '',
        selectedLabel: '',
        setLabelText() {
            if (this.selected === '') return '{{ $this->placeholder !== '' ? $this->placeholder : trans('statamic-livewire-filters::ui.all') }}';
            return this.selectedLabel;
        },
        setSelectedOption(option) {
            this.selected = option
            this.isOpen = false
            this.openedWithKeyboard = false
        },
        getFilteredOptions(query) {
            this.options = this.allOptions.filter((option) =>
                option.label.toLowerCase().includes(query.toLowerCase()),
            )
            if (this.options.length === 0) {
                this.$refs.noResultsMessage.classList.remove('hidden')
            } else {
                this.$refs.noResultsMessage.classList.add('hidden')
            }
        },
        updateSelectedLabel() {
            const option = this.allOptions.find(opt => opt.value === this.selected);
            this.selectedLabel = option ? option.label : this.selected;
        },
        resetSearch() {
            this.$refs.searchField.value = '';
            this.getFilteredOptions('');
        },
    }" 
    x-init="options = allOptions; $watch('selected', () => updateSelectedLabel());"
    x-on:counts-updated="counts = Object.values($event.detail)[0];"
    x-on:keydown.esc.window="isOpen = false, openedWithKeyboard = false"
    x-modelable="selected"
    wire:model.live="selected"
    wire:ignore
    class="w-full flex flex-col"
>
    <div class="relative w-full">

        <button 
            type="button" 
            role="combobox" 
            class="inline-flex w-full items-center justify-between whitespace-nowrap bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 px-4 py-2 hover:bg-gray-100 transition-colors duration-150" 
            aria-haspopup="listbox" 
            aria-controls="itemsListbox" 
            x-on:click="isOpen = ! isOpen" 
            x-on:keydown.down.prevent="openedWithKeyboard = true" 
            x-on:keydown.enter.prevent="openedWithKeyboard = true" 
            x-on:keydown.space.prevent="openedWithKeyboard = true" 
            x-bind:aria-label="setLabelText()" 
            x-bind:aria-expanded="isOpen || openedWithKeyboard"
        >
            <span class="w-full text-sm text-start overflow-hidden text-ellipsis whitespace-nowrap" x-text="setLabelText()"></span>
            <span class="inline-flex gap-x-2 items-center">
                <svg x-cloak x-on:click.stop="selected = ''; resetSearch();" x-show="selected !== ''" xmlns="http://www.w3.org/2000/svg" fill="none" class="size-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>                  
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5" x-bind:class="isOpen || openedWithKeyboard ? 'rotate-180' : ''"> 
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                </svg>
            </span>

        </button>

        <div 
            x-cloak
            x-show="isOpen || openedWithKeyboard"
            x-on:click.outside="isOpen = false, openedWithKeyboard = false" 
            x-transition
            class="absolute z-10 left-0 top-12 w-full border border-gray-300 bg-gray-50 rounded-lg overflow-hidden"
        >
            @if ($this->searchable)
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.5" class="absolute left-4 top-1/2 size-5 -translate-y-1/2 text-neutral-600/50 dark:text-neutral-300/50" aria-hidden="true" >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input 
                    type="text" 
                    class="w-full rounded-t-lg border-b border-gray-300 bg-gray-50 py-2.5 pl-11 pr-4 text-sm text-neutral-600 focus:outline-hidden focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-75" 
                    name="searchField" 
                    aria-label="Search" 
                    x-on:input="getFilteredOptions($el.value)" 
                    x-ref="searchField" 
                    placeholder="{{ __('statamic-livewire-filters::ui.search') }}" 
                />
            </div>
            @endif

            <ul 
                class="flex max-h-56 w-full flex-col overflow-hidden overflow-y-auto py-1.5" 
                role="listbox" 
                x-on:keydown.down.prevent="$focus.wrap().next()" 
                x-on:keydown.up.prevent="$focus.wrap().previous()" 
                x-trap="openedWithKeyboard"
            >
                @if ($this->searchable)
                <li class="hidden px-4 py-2 text-sm text-on-surface" x-ref="noResultsMessage">
                    <span>{{ __('statamic-livewire-filters::ui.no_results') }}</span>
                </li>
                @endif
                
                <template x-for="(option, index) in options" x-bind:key="option.value">
                    <li 
                        class="inline-flex justify-between gap-6 bg-gray-50 px-4 py-2 text-sm text-gray-600 hover:bg-gray-900/5 hover:text-gray-900 focus-visible:bg-gray-900/5 focus-visible:text-gray-900 focus-visible:outline-hidden cursor-pointer" 
                        role="option" 
                        x-on:click="setSelectedOption(option.value)" 
                        x-on:keydown.enter="setSelectedOption(option.value)" 
                        x-bind:id="'option-' + index" 
                        tabindex="0"
                    >
                        <span>
                            <span x-bind:class="selected == option.value ? 'font-bold' : null" x-text="option.label"></span>
                            @if (config('statamic-livewire-filters.enable_filter_values_count') === true)
                            <span class="text-gray-500 ml-1" x-show="counts && counts[option.value] !== undefined" x-text="'(' + (counts[option.value]) + ')'"></span>
                            @endif
                        </span> 
                        <span class="sr-only" x-text="selected == option.value ? 'selected' : null"></span>
                        <svg x-cloak x-show="selected == option.value" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5">
                        </svg>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
