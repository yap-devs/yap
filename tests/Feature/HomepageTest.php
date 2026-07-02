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

it('keeps entry point details out of the public homepage', function () {
    $response = $this
        ->withSession(['locale' => 'ja'])
        ->get('/');

    $response
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('translations.welcome.dashboard_preview_title', 'ダッシュボードで接続準備を完結')
            ->where('translations.welcome.dashboard_preview_rows.2.label', 'サーバー')
            ->where('translations.welcome.dashboard_preview_rows.2.value', 'ログイン後に確認')
            ->missing('translations.welcome.network_nodes')
            ->missing('translations.welcome.network_title')
            ->missing('translations.welcome.network_stats')
        );
});

it('uses dashboard-first positioning in simplified chinese', function () {
    $response = $this
        ->withSession(['locale' => 'zh_CN'])
        ->get('/');

    $response
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('translations.welcome.dashboard_preview_title', '在仪表盘完成连接准备')
            ->where('translations.welcome.dashboard_preview_rows.2.label', '服务器')
            ->where('translations.welcome.dashboard_preview_rows.2.value', '登录后查看')
            ->missing('translations.welcome.network_nodes')
        );
});
