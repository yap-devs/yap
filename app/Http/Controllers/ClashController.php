<?php

namespace App\Http\Controllers;

use App\Models\User;

class ClashController extends Controller
{
    public function index(string $uuid)
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        $yaml_file = storage_path("clash-config/$user->uuid.yaml");

        abort_if(!file_exists($yaml_file), 404);

        // 64 TiB, 1989-06-04
        $userinfo = "upload=$user->traffic_uplink download=$user->traffic_downlink total=70368744177664 expire=612894867";

        return response()->download($yaml_file, 'yap.yaml', [
            'Content-Type' => 'application/x-yaml',
            'Subscription-Userinfo' => $userinfo,
            'Profile-Update-Interval' => '12',  // 12 hours
            'Profile-Web-Page-Url' => route('profile.edit')
        ]);
    }
}
