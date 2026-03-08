/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./index.html", "./src/**/*.{js,jsx,ts,tsx}"],
    theme: {
        extend: {
            colors: {
                "brand-bg": "#0f0f1a",
                "brand-surface": "#1a1a2e",
                "brand-border": "#2d2d4e",
                "brand-purple": "#c9b8e8",
                "brand-muted": "#888888",
            },
        },
    },
    plugins: [],
};
