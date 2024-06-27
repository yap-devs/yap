<?php

namespace App\Services;

use App\Models\User;
use App\Models\VmessServer;

class ClashService
{
    public function __construct(
        private readonly User $user
    )
    {
    }

    public function genConf()
    {
        if (!$this->user->uuid) {
            $this->user->uuid = fake()->uuid();
            $this->user->save();
        }

        $template = yaml_parse_file(resource_path('clash-conf-template.yaml'));

        $proxies = VmessServer::all();

        dd($template);
    }
}
