module.exports = {
  content: ['./templates/**/*.twig'],
  darkMode: 'class',
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
        // Social media brand colors
        facebook: '#1877F2',
        whatsapp: '#25D366',
        linkedin: '#0A66C2',
        instagram: '#E4405F',
        bluesky: '#0085FF',
        youtube: '#FF0000',
        tiktok: '#00f2ea',
        x: '#000000',
      },
    },
  },
  plugins: [
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/typography'),
  ],
};
