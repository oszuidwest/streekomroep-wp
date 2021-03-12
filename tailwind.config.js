module.exports = {
  purge: [
    './templates/**/*.twig'
  ],
  darkMode: false, // or 'media' or 'class'
  theme: {
    extend: {
      fontFamily: {
        round: ['Nunito', 'sans-serif'],
      },
      maxWidth: {
        '960': '60rem',
      }
    },
  },
  variants: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
}
