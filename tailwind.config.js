module.exports = {
  content: ['./templates/**/*.twig'],
  theme: {
    extend: {
      aspectRatio: {
        21: '21',
      },
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
      },
    },
  },
  plugins: [
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
};
