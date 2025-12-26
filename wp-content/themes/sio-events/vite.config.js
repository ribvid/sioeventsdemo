import {defineConfig} from 'vite'
import laravel from 'laravel-vite-plugin'
import {wordpressPlugin, wordpressThemeJson} from '@roots/vite-plugin';

export default defineConfig({
    base: '/Users/vidribic/Development/sites/sioevents/wp-content/themes/sio-events/public/build/',
    plugins: [
        laravel({
            input: [
                // app.css is imported via import.meta.glob in app.js to support CUBE CSS folder imports
                // 'resources/css/app.css',

                'resources/js/app.js',
                'resources/css/editor.css',
                'resources/js/editor.js',
            ],
            refresh: true,
        }),

        wordpressPlugin(),

        // Generate the theme.json file in the public/build/assets directory
        // based on the Tailwind config and the theme.json file from base theme folder
        wordpressThemeJson({
            disableTailwindColors: false,
            disableTailwindFonts: false,
            disableTailwindFontSizes: false,
        }),
    ],
    resolve: {
        alias: {
            '@scripts': '/resources/js',
            '@styles': '/resources/css',
            '@fonts': '/resources/fonts',
            '@images': '/resources/images',
        },
    },
})
