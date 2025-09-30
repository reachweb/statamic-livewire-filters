<div 
    x-data="{
        allOptions: @js($this->filter_options),
        options: [],
        counts: {},
        isOpen: false,
        openedWithKeyboard: false,
        openUpwards: false,
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
            this.options = Object.entries(this.allOptions).filter(([value, label]) =>
                label.toLowerCase().includes(query.toLowerCase())
            )
            if (this.options.length === 0) {
                this.$refs.noResultsMessage?.classList.remove('hidden')
            } else {
                this.$refs.noResultsMessage?.classList.add('hidden')
            }
        },
        updateSelectedLabel() {
            this.selectedLabel = this.allOptions[this.selected] || this.selected;
        },
        resetSearch() {
            if (this.$refs.searchField) {
                this.$refs.searchField.value = '';
                this.getFilteredOptions('');
            }
        },
        checkPosition() {
            this.$nextTick(() => this.openUpwards = (window.innerHeight - this.$refs.dropdownButton.getBoundingClientRect().bottom) < this.$refs.dropdown.getBoundingClientRect().height);
        },
    }" 
    x-init="options = Object.entries(allOptions); $watch('selected', () => updateSelectedLabel());"
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
            class="inline-flex w-full items-center justify-between whitespace-nowrap bg-lf-input-bg border-(length:--lf-border-width) border-lf-border text-lf-text text-lf rounded-lf p-lf hover:bg-lf-subtle transition-colors duration-150 aria-expanded:border-lf-accent aria-expanded:ring-1 aria-expanded:ring-lf-accent"
            aria-haspopup="listbox" 
            aria-controls="itemsListbox" 
            x-on:click="isOpen = ! isOpen; checkPosition()" 
            x-on:keydown.down.prevent="openedWithKeyboard = true"
            x-on:keydown.enter.prevent="openedWithKeyboard = true" 
            x-on:keydown.space.prevent="openedWithKeyboard = true" 
            x-bind:aria-label="setLabelText()" 
            x-bind:aria-expanded="isOpen || openedWithKeyboard"
            x-ref="dropdownButton"
        >
            <span class="w-full text-lf leading-6 text-start overflow-hidden text-ellipsis whitespace-nowrap" x-text="setLabelText()"></span>
            <span class="inline-flex gap-x-2 items-center">
                <svg x-cloak x-on:click.stop="selected = ''; resetSearch();" x-show="selected !== ''" xmlns="http://www.w3.org/2000/svg" fill="none" class="size-5 cursor-pointer" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-label="{{ __('statamic-livewire-filters::ui.clear') }}">
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
            x-ref="dropdown"
            class="absolute z-30 left-0 w-full bg-lf-input-bg border-(length:--lf-border-width) border-lf-border rounded-lf overflow-hidden"
            x-bind:class="openUpwards ? 'bottom-full' : 'top-12'"
        >
            @if ($this->searchable)
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.5" class="absolute left-4 top-1/2 size-5 -translate-y-1/2 text-lf-muted" aria-hidden="true" >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input
                    type="text"
                    class="w-full rounded-t-lf bg-lf-input-bg border-(length:--lf-border-width) border-lf-border py-2.5 pl-11 pr-4 text-lf text-lf-text focus:outline-hidden focus-visible:ring-lf-accent disabled:cursor-not-allowed disabled:opacity-75"
                    name="searchField"
                    aria-label="{{ __('statamic-livewire-filters::ui.search_options') }}"
                    x-on:input.debounce.150ms="getFilteredOptions($el.value)"
                    x-ref="searchField"
                    placeholder="{{ __('statamic-livewire-filters::ui.search') }}"
                />
            </div>
            @endif

            <ul 
                class="flex max-h-64 w-full flex-col overflow-hidden overflow-y-auto py-1.5" 
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
                
                <template x-for="([value, label], index) in options" x-bind:key="value">
                    <li 
                        class="inline-flex justify-between gap-6 bg-lf-input-bg px-4 py-2 text-lf text-lf-text hover:bg-lf-subtle focus-visible:bg-lf-subtle focus-visible:outline-hidden cursor-pointer" 
                        role="option" 
                        x-on:click="setSelectedOption(value)" 
                        x-on:keydown.enter="setSelectedOption(value)" 
                        x-bind:id="'option-' + index" 
                        tabindex="0"
                    >
                        <span>
                            <span x-bind:class="selected == value ? 'font-bold' : null" x-text="label"></span>
                            @if (config('statamic-livewire-filters.enable_filter_values_count') === true)
                            <span class="text-lf-muted ml-1" x-show="counts && counts[value] !== undefined" x-text="'(' + (counts[value]) + ')'"></span>
                            @endif
                        </span> 
                        <span class="sr-only" x-text="selected == value ? 'selected' : null"></span>
                        <svg x-cloak x-show="selected == value" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" class="size-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5">
                        </svg>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
