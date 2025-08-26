<div 
    x-data="{
        allOptions: @js($this->filter_options),
        options: [],
        counts: {},
        isOpen: false,
        openedWithKeyboard: false,
        openUpwards: false,
        selectedOptions: [],
        selectedLabels: [],
        setLabelText() {
            const count = this.selectedOptions.length;
            if (count === 0) return '{{ $this->placeholder !== '' ? $this->placeholder : trans('statamic-livewire-filters::ui.please_select') }}';
            return this.selectedLabels.join(', ');
        },
        handleOptionToggle(option) {
            if (option.checked) {
                this.selectedOptions.push(option.value)
            } else {
                this.selectedOptions = this.selectedOptions.filter(
                    (opt) => opt !== option.value,
                )
            }
        },
        getFilteredOptions(query) {
            this.options = Object.entries(this.allOptions).filter(([value, label]) =>
                label.toLowerCase().includes(query.toLowerCase())
            )
            if (this.options.length === 0) {
                this.$refs.noResultsMessage.classList.remove('hidden')
            } else {
                this.$refs.noResultsMessage.classList.add('hidden')
            }
        },
        updateSelectedLabels() {
            this.selectedLabels = this.selectedOptions.map(value => {
                return this.allOptions[value] || value;
            });
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
    x-init="options = Object.entries(allOptions); $watch('selectedOptions', () => updateSelectedLabels());"
    x-on:counts-updated="counts = Object.values($event.detail)[0];"
    x-on:keydown.esc.window="isOpen = false, openedWithKeyboard = false"
    x-modelable="selectedOptions"
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
            <div class="flex gap-x-2 items-center">
                <svg x-cloak x-on:click.stop="selectedOptions = []; resetSearch();" x-show="selectedOptions.length > 0" xmlns="http://www.w3.org/2000/svg" fill="none" class="size-5 cursor-pointer" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>                
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5" x-bind:class="isOpen || openedWithKeyboard ? 'rotate-180' : ''"> 
                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                </svg>
            </div>
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.5" class="absolute left-4 top-1/2 size-5 -translate-y-1/2 text-neutral-600/50 dark:text-neutral-300/50" aria-hidden="true" >
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input 
                    type="text" 
                    class="w-full rounded-t-lf bg-lf-input-bg border-b-(length:--lf-border-width) border-lf-border py-2.5 pl-11 pr-4 text-lf text-lf-text focus:outline-hidden focus-visible:ring-lf-accent disabled:cursor-not-allowed disabled:opacity-75" 
                    name="searchField" 
                    aria-label="Search" 
                    x-on:input="getFilteredOptions($el.value)" 
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
                
                <template x-for="[value, label] in options" x-bind:key="value">
                    <li role="option">
                        <label 
                            class="flex items-center gap-2 px-4 py-2 text-sm text-neutral-600" 
                            x-bind:for="'checkboxOption' + value"
                        >
                            <div class="relative flex items-center">
                                <input type="checkbox" 
                                    class="form-checkbox w-4 h-4 text-lf-accent bg-lf-input-bg border-lf-border rounded focus:ring-lf-accent focus:ring-1" 
                                    x-on:change="handleOptionToggle($el)" 
                                    x-on:keydown.enter.prevent="$el.checked = ! $el.checked; handleOptionToggle($el)"
                                    x-bind:checked="selectedOptions.includes(value)"
                                    x-bind:value="value" 
                                    x-bind:id="'checkboxOption' + value"
                                />
                            </div>
                            <span>
                                <span x-text="label"></span>
                                @if (config('statamic-livewire-filters.enable_filter_values_count') === true)
                                <span class="text-gray-500 ml-1" x-show="counts && counts[value] !== undefined" x-text="'(' + (counts[value]) + ')'"></span>
                                @endif
                            </span>
                        </label>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
