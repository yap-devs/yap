<?php

test('inertia root view only loads the app vite entrypoint', function () {
    $view = file_get_contents(resource_path('views/app.blade.php'));

    expect($view)
        ->toContain("@vite('resources/js/app.jsx')")
        ->not->toContain('resources/js/Pages/');
});

test('vite preload failures trigger a full page reload', function () {
    $entrypoint = file_get_contents(resource_path('js/app.jsx'));

    expect($entrypoint)
        ->toContain("addEventListener('vite:preloadError'")
        ->toContain('event.preventDefault();')
        ->toContain('window.location.reload();');
});

test('inertia asset loading exceptions recover with a full page reload', function () {
    $entrypoint = file_get_contents(resource_path('js/app.jsx'));

    expect($entrypoint)
        ->toContain("router.on('exception'")
        ->toContain('event.preventDefault();')
        ->toContain('isStaleAssetError(event.detail.exception)')
        ->toContain('reloadForStaleAssets()');
});

test('initial inertia asset loading failures are recovered', function () {
    $entrypoint = file_get_contents(resource_path('js/app.jsx'));

    expect($entrypoint)
        ->toContain('.catch((error) => {')
        ->toContain('isStaleAssetError(error)')
        ->toContain('reloadForStaleAssets()');
});

test('inertia network interruptions are handled before they reach sentry', function () {
    $entrypoint = file_get_contents(resource_path('js/app.jsx'));

    expect($entrypoint)
        ->toContain('isNetworkError(event.detail.exception)')
        ->toContain('window.YAP_TRANSLATIONS?.common?.network_error')
        ->toContain('showToast(');
});

test('sentry receives runtime release and environment context', function () {
    $view = file_get_contents(resource_path('views/app.blade.php'));
    $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));

    expect($view)
        ->toContain('name="sentry-environment"')
        ->toContain('name="sentry-release"')
        ->and($bootstrap)
        ->toContain("document.querySelector('meta[name=\"sentry-environment\"]')")
        ->toContain("document.querySelector('meta[name=\"sentry-release\"]')")
        ->toContain('environment: sentryEnvironment')
        ->toContain('release: sentryRelease');
});

test('production builds upload hidden source maps and remove local map files', function () {
    $vite_config = file_get_contents(base_path('vite.config.js'));

    expect($vite_config)
        ->toContain("from '@sentry/vite-plugin'")
        ->toContain("sourcemap: sentrySourceMapsEnabled ? 'hidden' : false")
        ->toContain("assets: './public/build/**'")
        ->toContain("filesToDeleteAfterUpload: './public/build/**/*.map'")
        ->toContain('authToken: env.SENTRY_AUTH_TOKEN')
        ->not->toContain('VITE_SENTRY_AUTH_TOKEN');
});
