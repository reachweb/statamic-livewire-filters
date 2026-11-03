<?php

namespace Reach\StatamicLivewireFilters\Http\Livewire;

use Livewire\Component;

class LfCount extends Component
{
    public $count;

    public $view = 'count';

    /** Request-scoped: set when an existing snapshot is hydrated, never at mount. */
    protected bool $hydrated = false;

    public function hydrate(): void
    {
        $this->hydrated = true;
    }

    /**
     * The bundled view updates itself from the browser event, so it advertises no
     * listener and costs no request. A customized view (custom `view=` or a published
     * count.blade.php) keeps the server listener. Livewire only advertises listeners
     * at mount, so the listener is still accepted afterwards: a page rendered before
     * this change still routes the event here and must not get an error.
     */
    protected function getListeners()
    {
        return $this->hydrated || $this->countViewIsCustomized() ? ['entries-updated' => 'updatedCount'] : [];
    }

    public function updatedCount($count)
    {
        $this->count = $count;
    }

    protected function countViewIsCustomized(): bool
    {
        if ($this->view !== 'count') {
            return true;
        }

        try {
            $path = view()->getFinder()->find('statamic-livewire-filters::livewire.ui.count');
        } catch (\Throwable $exception) {
            return true;
        }

        $bundled = realpath(__DIR__.'/../../../resources/views');

        return $bundled === false || ! str_starts_with(realpath($path) ?: $path, $bundled.DIRECTORY_SEPARATOR);
    }

    public function render()
    {
        return view('statamic-livewire-filters::livewire.ui.'.$this->view);
    }
}
