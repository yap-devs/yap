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
            ->where('translations.welcome.setup_preview_title', '接続までの流れ')
            ->where('translations.welcome.setup_preview_steps.0.title', 'アカウント作成')
            ->where('translations.welcome.setup_preview_steps.2.title', 'Dashboardで確認')
            ->missing('translations.welcome.dashboard_preview_rows')
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
            ->where('translations.welcome.setup_preview_title', '连接准备流程')
            ->where('translations.welcome.setup_preview_steps.0.title', '创建账户')
            ->where('translations.welcome.setup_preview_steps.2.title', '在仪表盘查看')
            ->missing('translations.welcome.dashboard_preview_rows')
            ->missing('translations.welcome.network_nodes')
        );
});
