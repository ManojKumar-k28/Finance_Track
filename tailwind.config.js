/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#e6f0ff',
          100: '#c2d9ff',
          200: '#9dc2ff',
          300: '#78a9ff',
          400: '#5593ff',
          500: '#0466C8',
          600: '#0052a3',
          700: '#003d7a',
          800: '#002952',
          900: '#001429',
        },
        success: {
          50: '#e6fff2',
          100: '#b3ffdc',
          200: '#80ffc5',
          300: '#4dffaf',
          400: '#1aff98',
          500: '#0CCE6B',
          600: '#00a653',
          700: '#007d3f',
          800: '#00532a',
          900: '#002a15',
        },
        warning: {
          50: '#fff9e6',
          100: '#ffeeb3',
          200: '#ffe480',
          300: '#ffd94d',
          400: '#ffcf1a',
          500: '#FF9F1C',
          600: '#cc7a00',
          700: '#995c00',
          800: '#663d00',
          900: '#331f00',
        },
        danger: {
          50: '#ffe6e6',
          100: '#ffb3b3',
          200: '#ff8080',
          300: '#ff4d4d',
          400: '#ff1a1a',
          500: '#E63946',
          600: '#cc0000',
          700: '#990000',
          800: '#660000',
          900: '#330000',
        },
      },
    },
  },
  plugins: [],
};