function deleteProfileImage() {
    if (confirm("Are you sure you want to remove your profile image?")) {
        document.getElementById("delete-image-form").submit();
    }
}

function twoFactorSetup() {
    return {
        otpCountdown: 30,
        showSecret: false,
        copied: false,
        secret: "",
        _countdownTimer: null,
        _copiedTimer: null,

        init() {
            this.secret = this.$el.dataset.secret || "";
            this.tickCountdown();
            this._countdownTimer = setInterval(
                () => this.tickCountdown(),
                1000,
            );
        },

        destroy() {
            if (this._countdownTimer) {
                clearInterval(this._countdownTimer);
            }
            if (this._copiedTimer) {
                clearTimeout(this._copiedTimer);
            }
        },

        tickCountdown() {
            const second = Math.floor(Date.now() / 1000);
            const remaining = 30 - (second % 30);
            this.otpCountdown = remaining === 0 ? 30 : remaining;
        },

        toggleSecret() {
            this.showSecret = !this.showSecret;
        },

        async copySecret() {
            if (!this.secret || !navigator.clipboard) {
                return;
            }

            await navigator.clipboard.writeText(this.secret);
            this.copied = true;

            if (this._copiedTimer) {
                clearTimeout(this._copiedTimer);
            }

            this._copiedTimer = setTimeout(() => {
                this.copied = false;
            }, 1400);
        },
    };
}

window.deleteProfileImage = deleteProfileImage;

document.addEventListener("alpine:init", () => {
    window.Alpine.data("twoFactorSetup", twoFactorSetup);
});
