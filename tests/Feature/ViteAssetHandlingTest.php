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
