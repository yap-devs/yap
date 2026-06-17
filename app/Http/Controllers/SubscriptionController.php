<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SubscriptionController extends Controller
{
    public function clash(string $uuid, SubscriptionService $subscription_service)
    {
        return $this->subscriptionResponse(
            $uuid,
            $subscription_service,
            SubscriptionService::FORMAT_CLASH,
            'application/x-yaml',
            'yaml'
        );
    }

    public function universal(string $uuid, SubscriptionService $subscription_service)
    {
        return $this->subscriptionResponse(
            $uuid,
            $subscription_service,
            SubscriptionService::FORMAT_UNIVERSAL,
            'text/plain; charset=UTF-8',
            'txt'
        );
    }

    private function subscriptionResponse(
        string $uuid,
        SubscriptionService $subscription_service,
        string $format,
        string $content_type,
        string $extension,
    ) {
        $user = User::where('uuid', $uuid)->with('packages')->firstOrFail();

        abort_if(! $user->is_valid, 404);

        $filename = 'yap.'.$extension;

        $content = $subscription_service->content($user, $format);

        if ($content === null) {
            if (Cache::add('subscription_rebuild_pending', true, now()->addMinutes(5))) {
                GenerateClashProfileLink::dispatch();
            }

            abort(404);
        }

        return response($content, 200, [
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'Content-Type' => $content_type,
            'Profile-Update-Interval' => '12',
            'Profile-Web-Page-Url' => route('profile.edit'),
            'Subscription-Userinfo' => $subscription_service->userInfo($user),
        ]);
    }
}
