<?php

it('renders the homepage successfully', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});

it('keeps the china travel and privacy positioning on the homepage', function () {
    $response = $this
        ->withSession(['locale' => 'ja'])
        ->get('/');

    $response
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('translations.welcome.hero_points.0', '中国出張・旅行向け')
            ->where('translations.welcome.privacy_title', 'アクセス履歴への不安を減らす設計')
        );
});
