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
            ->where('translations.welcome.network_title', '使える入口を、わかりやすく')
            ->where('translations.welcome.network_note', '表示は現在提供している入口と接続方法の説明です。提供地域は運用状況により変わる場合があります。')
            ->where('translations.welcome.network_stats.0.value', '海外滞在者 / 中国本土ユーザー')
            ->where('translations.welcome.network_stats.1.value', 'Clash / Shadowrocket / Stash')
            ->where('translations.welcome.network_nodes.0.code', 'JP')
            ->where('translations.welcome.network_nodes.1.code', 'HK')
            ->where('translations.welcome.network_nodes.4.code', 'US')
            ->where('translations.welcome.network_nodes', fn ($nodes): bool => ! collect($nodes)
                ->pluck('code')
                ->intersect(['CN', 'KR'])
                ->isNotEmpty())
        );
});
