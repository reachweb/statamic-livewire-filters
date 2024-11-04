<div>
    <div 
        x-data="{
            selectedMin: $wire.selectedMin,
            selectedMax: $wire.selectedMax,
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
                            return this.format === 'date' 
                                ? new Date(value).getFullYear()
                                : Math.round(value);
                        },
                        from: (value) => {
                            return this.format === 'date'
                                ? new Date(value, 0).getTime()
                                : parseFloat(value);
                        }
                    }
                });

                slider.noUiSlider.on('set', (values) => {
                    $wire.set('selectedMin', values[0]);
                    $wire.set('selectedMax', values[1]);
                });
                }
        }"

        class="w-full my-8"
    >
        <div class="relative">
            <div wire:ignore x-ref="slider" class="w-[93%] mx-auto"></div>
            <div class="flex justify-center mt-3">
                <span class="font-bold" x-text="$wire.selectedMin"></span>
                <span class="mx-2">{{ __('statamic-livewire-filters::ui.to') }}</span>
                <span class="font-bold" x-text="$wire.selectedMax"></span>
            </div>
        </div>
    </div>
</div>