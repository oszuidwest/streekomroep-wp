/** @type {import('tailwindcss').Config} */
export default {
  content: ['./templates/**/*.twig', './src/**/*.php', './static/**/*.js'],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        round: ['Nunito', 'sans-serif'],
      },
      maxWidth: {
        960: '60rem',
      },
      colors: {
        roze: {
          DEFAULT: '#e6007e',
        },
        groen: {
          DEFAULT: '#00de01',
        },
        blauw: {
          DEFAULT: '#009fe3',
        },
        facebook: { DEFAULT: '#0D6FEE', dark: '#4391F4' },
        whatsapp: { DEFAULT: '#0F883B', dark: '#25D366' },
        linkedin: { DEFAULT: '#0A66C2', dark: '#3193F4' },
        instagram: '#E4405F',
        bluesky: '#0085FF',
        youtube: '#FF0000',
        tiktok: '#00f2ea',
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
};
