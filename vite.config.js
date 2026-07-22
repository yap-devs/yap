import { sentryVitePlugin } from '@sentry/vite-plugin';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const sentrySourceMapsEnabled = mode === 'production' && [
        env.SENTRY_AUTH_TOKEN,
        env.SENTRY_ORG,
        env.SENTRY_PROJECT,
        env.SENTRY_RELEASE,
    ].every((value) => Boolean(value?.trim()));
    const sentryPlugins = sentrySourceMapsEnabled
        ? sentryVitePlugin({
            authToken: env.SENTRY_AUTH_TOKEN,
            org: env.SENTRY_ORG,
            project: env.SENTRY_PROJECT,
            release: {
                name: env.SENTRY_RELEASE,
                setCommits: false,
            },
            sourcemaps: {
                assets: './public/build/**',
                filesToDeleteAfterUpload: './public/build/**/*.map',
            },
        })
        : [];

    return {
        build: {
            sourcemap: sentrySourceMapsEnabled ? 'hidden' : false,
        },
        plugins: [
            laravel({
                input: [
                    'resources/js/app.jsx',
                    'resources/css/filament/admin/theme.css',
                ],
                refresh: true,
            }),
            react(),
            ...sentryPlugins,
        ],
    };
});
