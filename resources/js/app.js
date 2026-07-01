document.addEventListener('alpine:init', () => {
    Alpine.data('liveFreshness', (lastPolledAt) => ({
        lastPolledAt,
        secondsAgo: 0,
        interval: null,

        init() {
            this.tick();
            this.interval = setInterval(() => this.tick(), 1000);
            this.$cleanup(() => clearInterval(this.interval));
        },

        tick() {
            if (! this.lastPolledAt) {
                this.secondsAgo = null;
                return;
            }

            this.secondsAgo = Math.max(0, Math.floor((Date.now() - new Date(this.lastPolledAt).getTime()) / 1000));
        },

        get label() {
            if (this.secondsAgo === null) {
                return 'Aguardando primeira atualização';
            }

            if (this.secondsAgo < 60) {
                return `Atualizado há ${this.secondsAgo}s`;
            }

            return `Atualizado há ${Math.floor(this.secondsAgo / 60)}min`;
        },
    }));
});
