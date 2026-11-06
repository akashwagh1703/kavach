export default {
  content: ['./index.html', './src/**/*.{js,jsx}', '../../packages/app-kit/src/**/*.{js,jsx}'],
  corePlugins: { preflight: false },
  theme: {
    extend: {
      colors: {
        navy: '#0B1F44',
        electric: '#2F6FED',
        teal: '#14B8A6',
        mint: '#DDF7EF',
        amber: '#F5A623',
        cloud: '#F4F7FB',
        danger: '#DC2626',
        muted: '#64748B',
        orange: '#F58220',
      },
      fontFamily: { sans: ['Plus Jakarta Sans', 'Inter', 'Noto Sans Devanagari', 'sans-serif'] },
    },
  },
};