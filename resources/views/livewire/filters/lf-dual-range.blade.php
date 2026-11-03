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
            {{-- Read through $wire: a changed x-data value would re-initialize the slider on morph --}}
            bins: $wire.histogramBins,
            boundsMin: $wire.dynamicMin ?? {{ $min }},
            boundsMax: $wire.dynamicMax ?? {{ $max }},
            get maxBinCount() {
                return Math.max(1, ...this.bins.map((bin) => bin.count));
            },
            binStyle(bin) {
                const span = this.boundsMax - this.boundsMin;
                const selected = bin.to > this.displayMin && bin.from < this.displayMax;
                return {
                    left: ((bin.from - this.boundsMin) / span * 100) + '%',
                    width: ((bin.to - bin.from) / span * 100) + '%',
                    height: (bin.count / this.maxBinCount * 100) + '%',
                    backgroundColor: selected ? 'var(--noui-connect, #2563eb)' : 'var(--noui-bg-dark, #e4e4e4)',
                };
            },
            init() {
                if (!window.noUiSlider) {
                    console.error('noUiSlider is not loaded');
                    return;
                }
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

                // Handle dynamic bounds updates
                $wire.on('range-bounds-updated', (bounds) => {
                    slider.noUiSlider.updateOptions({
                        range: {
                            'min': bounds.min,
                            'max': bounds.max
                        }
                    }, false);
                    this.boundsMin = bounds.min;
                    this.boundsMax = bounds.max;
                    if (bounds.bins) {
                        this.bins = bounds.bins;
                    }
                    slider.noUiSlider.set([bounds.selectedMin, bounds.selectedMax], false);
                    bounds.disabled ? slider.noUiSlider.disable() : slider.noUiSlider.enable();
                });
            }
        }"

        class="w-full my-8"
    >
        <div class="relative">
            @if ($histogram)
            <div wire:ignore x-show="bins.length" class="relative h-10 w-[93%] mx-auto pointer-events-none" style="opacity: 0.35" aria-hidden="true">
                <template x-for="bin in bins" :key="`${bin.from}-${bin.to}-${bin.count}`">
                    <div class="absolute" style="bottom: 0; border-left: 1px solid transparent; background-clip: padding-box" :style="binStyle(bin)"></div>
                </template>
            </div>
            @endif
            <div wire:ignore x-ref="slider" class="w-[93%] mx-auto"></div>
            <div class="flex justify-center mt-3">
                <span class="font-bold" x-text="displayMin"></span>
                <span class="mx-2">{{ __('statamic-livewire-filters::ui.to') }}</span>
                <span class="font-bold" x-text="displayMax"></span>
            </div>
        </div>
    </div>
</div>
