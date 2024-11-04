<div>
    <div 
        x-data="dualRange({
            initialMin: @entangle('selectedMin'),
            initialMax: @entangle('selectedMax'),
            step: {{ $step }},
            min: {{ $min }},
            max: {{ $max }},
            format: '{{ $format }}',
            minRange: {{ $minRange }}
        })"
        class="w-full my-8"
    >
        <div class="relative">
            <div x-ref="slider" class="w-[93%] mx-auto"></div>
            <div class="flex justify-center mt-3">
                <span class="font-bold" x-text="min"></span>
                <span class="mx-2">{{ __('statamic-livewire-filters::ui.to') }}</span>
                <span class="font-bold" x-text="max"></span>
            </div>
        </div>
    </div>
</div>

@script
<script>
Alpine.data('dualRange', ({ initialMin, initialMax, step, min, max, format, minRange }) => ({
    min: initialMin,
    max: initialMax,
    init() {
        const slider = this.$refs.slider;

        console.log(window);
        
        window.noUiSlider.create(slider, {
            start: [this.min, this.max],
            connect: true,
            step: step,
            range: {
                'min': min,
                'max': max
            },
            format: {
                to: (value) => {
                    return format === 'date' 
                        ? new Date(value).getFullYear()
                        : Math.round(value);
                },
                from: (value) => {
                    return format === 'date'
                        ? new Date(value, 0).getTime()
                        : parseFloat(value);
                }
            }
        });

        slider.noUiSlider.on('update', (values) => {
            this.min = values[0];
            this.max = values[1];
        });

        slider.noUiSlider.on('slide', (values, handle) => {
            if (handle === 0 && (values[1] - values[0]) < minRange) {
                slider.noUiSlider.set([values[1] - minRange, values[1]]);
            }
            if (handle === 1 && (values[1] - values[0]) < minRange) {
                slider.noUiSlider.set([values[0], values[0] + minRange]);
            }
        });
    }
}))
</script>
@endscript