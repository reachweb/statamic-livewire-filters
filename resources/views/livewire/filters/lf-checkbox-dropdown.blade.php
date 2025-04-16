<div 
    x-data="{
        options: JSON.parse('{{ json_encode($this->filter_options) }}'),
        isOpen: false,
        openedWithKeyboard: false,
        selectedOptions: [],
        selectedLabels: [],
        setLabelText() {
            const count = this.selectedOptions.length;
            if (count === 0) return '{{ trans('statamic-livewire-filters::ui.please_select') }}';
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
        updateSelectedLabels() {
            this.selectedLabels = this.selectedOptions.map(value => this.options[value] || value);
        },
    }" 
    x-init="$watch('selectedOptions', () => updateSelectedLabels())"
    class="w-full flex flex-col" 
    x-modelable="selectedOptions"
    wire:model.live="selected"
    x-on:keydown.esc.window="isOpen = false, openedWithKeyboard = false"
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5" x-bind:class="isOpen || openedWithKeyboard ? 'rotate-180' : ''"> 
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
            </svg>
        </button>

        <ul 
            x-cloak
            x-show="isOpen || openedWithKeyboard"
            class="absolute z-10 left-0 top-12 flex max-h-56 w-full flex-col overflow-hidden overflow-y-auto border border-gray-300 bg-gray-50 py-1.5 rounded-lg" 
            role="listbox" 
            x-on:click.outside="isOpen = false, openedWithKeyboard = false" 
            x-on:keydown.down.prevent="$focus.wrap().next()" 
            x-on:keydown.up.prevent="$focus.wrap().previous()" 
            x-transition 
            x-trap="openedWithKeyboard"
        >
            @foreach($this->filter_options as $value => $label)
            <li role="option" x-bind:key="{{ $value }}">
                <label 
                    class="flex items-center gap-2 px-4 py-2 text-sm text-neutral-600" 
                    for="checkboxOption{{ $loop->index }}"
                >
                    <div class="relative flex items-center">
                        <input type="checkbox" 
                            class="form-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2" 
                            x-on:change="handleOptionToggle($el)" 
                            x-on:keydown.enter.prevent="$el.checked = ! $el.checked; handleOptionToggle($el)"
                            x-bind:checked="selectedOptions.includes('{{ $value }}')"
                            value="{{ $value }}" 
                            id="checkboxOption{{ $loop->index }}"
                        />
                    </div>
                    <span>
                        {{ $label }}
                        @if ($this->counts[$value] !== null)
                        <span class="text-gray-500 ml-1">({{ $this->counts[$value] }})</span>
                        @endif
                    </span>
                </label>
            </li>
            @endforeach
        </ul>
    </div>
</div>
