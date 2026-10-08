<?php

namespace App\Filament\Resources\UserResource\Schemas;

use App\Models\User;
use App\Models\UserPackage;
use App\Services\SubscriptionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Account overview')->columns(2)->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('balance')->money('USD'),
                TextEntry::make('created_at')->label('Joined')->dateTime(),
                TextEntry::make('email_verified_at')->label('Email verified')->dateTime()->placeholder('Not verified'),
                TextEntry::make('deleted_at')->label('Account removed')->dateTime()->placeholder('Active account'),
            ]),
            Section::make('VPN access')->description('Account eligibility and route permissions. This does not verify live connectivity.')->columns(2)->schema([
                TextEntry::make('access_status')->label('Eligibility')
                    ->state(fn (User $record): string => ! $record->trashed() && $record->is_valid ? 'Eligible' : 'Unavailable')
                    ->badge()->color(fn (User $record): string => ! $record->trashed() && $record->is_valid ? 'success' : 'danger'),
                TextEntry::make('access_reason')->label('Reason')->state(fn (User $record): string => static::accessReason($record)),
                TextEntry::make('route_scope')->label('Route scope')
                    ->state(fn (User $record): string => $record->is_low_priority ? 'Low-priority routes only' : 'All enabled routes'),
                TextEntry::make('available_packages')->label('Available packages')
                    ->state(fn (User $record): int => $record->packages->filter(fn (UserPackage $package): bool => $package->isAvailable())->count()),
                TextEntry::make('queued_packages')->label('Queued packages')
                    ->state(fn (User $record): int => $record->packages()->queued()->count()),
                TextEntry::make('routes')->label('Eligible routes')->columnSpanFull()
                    ->state(fn (User $record): string => $record->trashed() || ! $record->is_valid
                        ? 'None'
                        : app(SubscriptionService::class)->serversFor($record)->pluck('name')->implode(', '))
                    ->placeholder('No enabled routes in this scope'),
            ]),
            Section::make('AI service')->columns(2)->schema([
                TextEntry::make('sub2api_key_status')->label('Key status')->badge()->placeholder('No key'),
                TextEntry::make('sub2api_last_synced_at')->label('Last imported usage')->dateTime()->placeholder('Not recorded'),
                TextEntry::make('ai_availability')->label('Status policy')->columnSpanFull()
                    ->state(fn (): string => config('services.sub2api.enabled')
                        ? 'AI has separate balance thresholds; VPN package eligibility does not activate an AI key.'
                        : 'AI integration is disabled.'),
            ]),
            Section::make('Technical details')->collapsed()->columns(2)->schema([
                TextEntry::make('uuid')->label('Subscription UUID')->copyable()->placeholder('Not recorded'),
                TextEntry::make('last_settled_at')->label('Last balance deduction')->dateTime()->placeholder('Not recorded'),
                TextEntry::make('traffic_unpaid')->label('Unsettled traffic')->formatStateUsing(fn (mixed $state): string => number_format((float) $state / 1073741824, 2).' GiB'),
                TextEntry::make('github_nickname')->label('GitHub account')->placeholder('Not linked'),
                TextEntry::make('github_created_at')->label('GitHub account created')->dateTime()->placeholder('Not recorded'),
            ]),
        ]);
    }

    private static function accessReason(User $record): string
    {
        return match (true) {
            $record->trashed() => 'Account removed',
            (float) $record->balance > 0 => 'Positive balance',
            $record->packages->contains(fn (UserPackage $package): bool => $package->isAvailable()) => 'Available package',
            (bool) $record->is_valid => 'GitHub account allowance',
            default => 'No positive balance, available package, or GitHub allowance',
        };
    }
}
