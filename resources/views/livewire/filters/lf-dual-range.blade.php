<div>
    <div 
        x-data="{
            selectedMin: $wire.selectedMin,
            selectedMax: $wire.selectedMax,
            displayMin: $wire.selectedMin,
            displayMax: $wire.selectedMax,
            min: {{ $min }},
            max: {{ $max }},
            step: {{ $step }},
            format: '{{ $format }}',
            minRange: {{ $minRange }},
            init() {
                const slider = $refs.slider;
                window.noUiSlider.create(slider, {
                    start: [this.selectedMin, this.selectedMax],
                    connect: true,
                    step: this.step,
                    margin: this.minRange,
                    range: {
                        'min': this.min,
                        'max': this.max
                    },
                    format: {
                        to: (value) => {
                            return this.format === 'float' 
                                ? value
                                : Math.round(value);
                        },
                        from: (value) => {
                            return this.format === 'float'
                                ? value
                                : parseFloat(value);
                        }
                    }
                });

                // We are using two events so that the filter doesn't query the server every time the slider is moved
                slider.noUiSlider.on('update', (values) => {
                    this.displayMin = values[0];
                    this.displayMax = values[1]; 
                });
                slider.noUiSlider.on('set', (values) => {
                    $wire.set('selectedMin', values[0]);
                    $wire.set('selectedMax', values[1]);
                });

                // Handle preset values
                $wire.on('dual-range-preset-values', (presetValues) => {
                    slider.noUiSlider.set([presetValues.min, presetValues.max], false);
                });
            }
        }"

        class="w-full my-8"
    >
        <div class="relative">
            <div wire:ignore x-ref="slider" class="w-[93%] mx-auto"></div>
            <div class="flex justify-center mt-3">
                <span class="font-bold" x-text="displayMin"></span>
                <span class="mx-2">{{ __('statamic-livewire-filters::ui.to') }}</span>
                <span class="font-bold" x-text="displayMax"></span>
            </div>
        </div>
    </div>
</div>
