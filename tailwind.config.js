const colors = require('tailwindcss/colors')

module.exports = {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/views/**/*.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                primary: colors.blue,
                danger: colors.rose,
                success: colors.green,
                warning: colors.yellow,
                nexus: {
                    bg: 'var(--nexus-bg)',
                    surface: 'var(--nexus-surface)',
                    'surface-alt': 'var(--nexus-surface-alt)',
                    border: 'var(--nexus-border)',
                    text: 'var(--nexus-text)',
                    muted: 'var(--nexus-text-muted)',
                    primary: 'var(--nexus-primary)',
                    'primary-text': 'var(--nexus-primary-text)',
                    link: 'var(--nexus-link)',
                    success: 'var(--nexus-success)',
                    warning: 'var(--nexus-warning)',
                    danger: 'var(--nexus-danger)',
                },
            },
            fontFamily: {
                sans: ['var(--nexus-font-sans)', 'sans-serif'],
            },
            maxWidth: {
                content: 'var(--nexus-content-max)',
            },
        },
    },
}
