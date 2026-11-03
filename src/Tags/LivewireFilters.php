<?php

namespace Reach\StatamicLivewireFilters\Tags;

use Statamic\Tags\Tags;

class LivewireFilters extends Tags
{
    protected static $handle = 'livewire-filters';

    public function head(): string
    {
        if (config('statamic.static_caching.strategy') !== 'full') {
            return '';
        }

        $csrf = json_encode(csrf_token());

        return <<<HTML
<script>
    window.livewireScriptConfig = window.livewireScriptConfig || { csrf: {$csrf} };
    (function () {
        function start() {
            if (window.__livewireStarted) {
                return;
            }
            if (!window.Livewire) {
                setTimeout(start, 50);
                return;
            }
            window.__livewireStarted = true;
            window.Livewire.start();
        }

        document.addEventListener('statamic:csrf.replaced', start, { once: true });
    })();
</script>
HTML;
    }

    public function loadMore(): string
    {
        if (! $this->context->get('has_more_pages')) {
            return '';
        }

        return view('statamic-livewire-filters::livewire.ui.load-more', [
            'auto' => $this->params->bool('auto', false),
            'text' => $this->params->get('text'),
            'class' => $this->params->get('class'),
        ])->render();
    }

    public function applyButton(): string
    {
        return view('statamic-livewire-filters::livewire.ui.apply-button', [
            'class' => $this->params->get('class'),
        ])->render();
    }

    public function drawer(): string
    {
        return view('statamic-livewire-filters::livewire.ui.drawer', [
            'slot' => $this->isPair ? $this->parse() : '',
            'class' => $this->params->get('class'),
        ])->render();
    }
}
