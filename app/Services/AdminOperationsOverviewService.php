<?php

namespace App\Services;

use App\Filament\Pages\AccessHealth;
use App\Filament\Resources\AffiliateCommissionResource;
use App\Filament\Resources\Nodes\NodeResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\AffiliateCommission;
use App\Models\Node;
use App\Models\Payment;

class AdminOperationsOverviewService
{
    public function __construct(
        private readonly AdminDashboardReportService $reports,
        private readonly NodeHealthService $nodes,
        private readonly SchedulerStatusService $scheduler,
        private readonly BackupStatusService $backups,
    ) {}

    public function overview(): array
    {
        $access = $this->reports->getAccessHealthBreakdown();
        $packages = $this->reports->getPackageUtilizationBreakdown();
        $attention = [
            ['key' => 'low_balance', 'label' => 'Customers with low balance', 'count' => (int) $access->get('Low balance', 0) + (int) $access->get('Negative balance', 0), 'description' => 'Balance below $1 with no available package. Check access and follow up.', 'icon' => 'heroicon-o-user-group', 'url' => AccessHealth::getUrl()],
            ['key' => 'critical_packages', 'label' => 'Packages running low', 'count' => (int) $packages->get('Critical <10%', 0), 'description' => 'Started subscriptions with less than 10% of their traffic allowance.', 'icon' => 'heroicon-o-cube', 'url' => AccessHealth::getUrl()],
        ];
        if (config('node_agent.enabled')) {
            foreach (['offline' => ['Nodes with stale heartbeats', 'Enabled agents have not checked in recently.'], 'unseen' => ['Nodes awaiting first heartbeat', 'Enabled nodes have not reported yet.'], 'pending' => ['Node configuration pending', 'Recent heartbeat received; the desired revision is not applied yet.']] as $state => [$label, $description]) {
                $attention[] = ['key' => $state, 'label' => $label, 'count' => $this->nodes->filter(Node::query(), $state)->count(), 'description' => $description, 'icon' => 'heroicon-o-server-stack', 'url' => NodeResource::getUrl('index', ['tableFilters' => ['health' => ['value' => $state]]])];
            }
        }
        $waiting = [
            ['key' => 'orders', 'label' => 'Awaiting payment', 'count' => Payment::where('status', Payment::STATUS_CREATED)->count(), 'description' => 'Open recharge orders; payment or expiration is handled automatically.', 'url' => PaymentResource::getUrl('index', ['tableFilters' => ['status' => ['value' => Payment::STATUS_CREATED]]])],
            ['key' => 'commissions', 'label' => 'Commission settlement', 'count' => AffiliateCommission::where('status', AffiliateCommission::STATUS_PENDING)->where('hold_until', '<=', now())->count(), 'description' => 'Hold period ended; scheduled settlement rechecks eligibility.', 'url' => AffiliateCommissionResource::getUrl('index', ['tableFilters' => ['due' => ['isActive' => true]]])],
        ];

        return ['today' => $this->reports->getTodayStats(), 'attention' => $attention, 'waiting' => $waiting,
            'scheduler' => $this->scheduler->overview(), 'backup' => $this->backups->overview()];
    }
}
