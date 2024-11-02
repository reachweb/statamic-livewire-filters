<div>
    @script
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('update-url', ({ newUrl }) => {
                history.pushState(null, '', newUrl);
            });
        });
    </script>
    @endscript
</div>
