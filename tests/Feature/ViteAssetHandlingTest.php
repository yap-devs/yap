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
