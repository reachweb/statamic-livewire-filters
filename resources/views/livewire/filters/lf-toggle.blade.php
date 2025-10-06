<div>
    <label class="flex items-center cursor-pointer">
        <div class="relative">
            <input
                type="checkbox"
                class="sr-only peer"
                wire:model.live="selected"
            >
            <div class="w-11 h-6 bg-lf-border rounded-full peer peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-lf-accent peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-lf-accent">
            </div>
        </div>
        <span class="ms-3 text-lf-text">{{ $label }}</span>
    </label>
</div>
