<div
    x-data="{ options: @js($this->filter_options), pick: (v) => v.length > 0 }"
    wire:ignore
    class="custom">
    <template x-for="[value, label] in Object.entries(options)"><span x-text="label"></span></template>
</div>
