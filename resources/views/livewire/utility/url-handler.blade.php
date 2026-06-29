<div>
    @script
    <script>
        document.addEventListener('livewire:initialized', () => {
            const historyStateKey = 'statamicLivewireFilters';

            const normalizeUrl = (value) => {
                const url = new URL(value, window.location.origin);
                const normalizedParams = Array.from(url.searchParams.entries())
                    .sort(([leftKey, leftValue], [rightKey, rightValue]) => {
                        if (leftKey === rightKey) {
                            return leftValue.localeCompare(rightValue);
                        }

                        return leftKey.localeCompare(rightKey);
                    });

                return JSON.stringify({
                    origin: url.origin,
                    pathname: url.pathname,
                    hash: url.hash,
                    searchParams: normalizedParams,
                });
            };

            const markedHistoryState = () => ({
                ...(history.state ?? {}),
                [historyStateKey]: true,
            });

            Livewire.on('update-url', ({ newUrl, replace = false }) => {
                const currentUrl = new URL(window.location.href);
                const nextUrl = new URL(newUrl, window.location.origin);

                if (normalizeUrl(currentUrl) === normalizeUrl(nextUrl)) {
                    return;
                }

                // Canonicalize initial page loads in place. This also heals history
                // entries containing paginator property paths from older releases.
                if (replace) {
                    history.replaceState(markedHistoryState(), '', nextUrl);

                    return;
                }

                // Mark both sides of the transition and retain any Livewire state.
                history.replaceState(markedHistoryState(), '', currentUrl);
                history.pushState(markedHistoryState(), '', nextUrl);
            });

            window.addEventListener('popstate', (event) => {
                if (! event.state?.[historyStateKey]) {
                    return;
                }

                window.location.reload();
            });
        });
    </script>
    @endscript
</div>
