/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./index.html", "./src/**/*.{js,jsx,ts,tsx}"],
    theme: {
        extend: {
            colors: {
                "brand-bg": "#f9fafb",
                "brand-surface": "#ffffff",
                "brand-border": "#e5e7eb",
                "brand-purple": "#6366f1",
                "brand-muted": "#6b7280",
            },
        },
    },
    plugins: [],
};
