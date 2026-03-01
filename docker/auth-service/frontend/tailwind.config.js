/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.html',
    './src/**/*.{js,jsx,ts,tsx}'
  ],
  theme: {
    extend: {
      colors: {
        'brand-bg': '#0f0f1a',      // page background
        'brand-surface': '#1a1a2e', // card background
        'brand-border': '#2d2d4e',  // border color
        'brand-purple': '#c9b8e8',  // accent/button
        'brand-muted': '#888888',   // labels/secondary text
      },
    },
  },
  plugins: [],
};
