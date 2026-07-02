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
            ->where('translations.welcome.privacy_title', 'アクセス履歴に配慮した設計')
        );
});

it('shows the network route positioning on the homepage', function () {
    $response = $this
        ->withSession(['locale' => 'ja'])
        ->get('/');

    $response
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('translations.welcome.network_title', '主要ルートを、ひと目で選べる')
            ->where('translations.welcome.network_note', '表示は現在の提供方針に合わせたルート表現です。提供地域や入口は運用状況により変わる場合があります。')
        );
});
