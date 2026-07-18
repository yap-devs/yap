<?php

use App\Models\Package;

it('renders the homepage successfully', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});

it('presents active package pricing on the public homepage', function () {
    Package::query()->create([
        'name' => 'Public Package',
        'description' => 'Visible package',
        'status' => Package::STATUS_ACTIVE,
        'price' => 0.8,
        'duration_days' => 30,
        'traffic_limit' => 50 * 1024 * 1024 * 1024,
    ]);
    Package::query()->create([
        'name' => 'Small Package',
        'description' => 'Smaller package',
        'status' => Package::STATUS_ACTIVE,
        'price' => 0.18,
        'duration_days' => 7,
        'traffic_limit' => 10 * 1024 * 1024 * 1024,
    ]);
    Package::query()->create([
        'name' => 'Hidden Package',
        'description' => 'Private package',
        'status' => Package::STATUS_HIDDEN,
        'price' => 2,
        'duration_days' => 30,
        'traffic_limit' => 100 * 1024 * 1024 * 1024,
    ]);

    $response = $this->get('/');

    $response
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('unitPrice', config('yap.unit_price'))
            ->has('packages', 2)
            ->where('packages.0.name', 'Small Package')
            ->missing('packages.0.description')
            ->where('packages.0.duration_days', 7)
            ->where('packages.0.traffic_limit', 10 * 1024 * 1024 * 1024)
            ->where('packages.0.price', fn ($price) => (float) $price === 0.18)
            ->where('packages.0.original_price', fn ($price) => (float) $price === 0.2)
            ->where('packages.1.name', 'Public Package')
            ->where('packages.1.original_price', fn ($price) => (float) $price === 1.0)
            ->missing('packages.2'));
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
