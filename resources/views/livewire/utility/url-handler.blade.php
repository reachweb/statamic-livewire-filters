<div>
    @script
    <script>
        document.addEventListener('livewire:initialized', () => {
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

            Livewire.on('update-url', ({ newUrl }) => {
                const currentUrl = new URL(window.location.href);
                const nextUrl = new URL(newUrl, window.location.origin);

                if (normalizeUrl(currentUrl) === normalizeUrl(nextUrl)) {
                    return;
                }

                history.pushState(null, '', nextUrl);
            });
        });
    </script>
    @endscript
</div>
