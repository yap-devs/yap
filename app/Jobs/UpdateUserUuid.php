<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VmessServer;
use App\Services\ClashService;
use App\Services\V2rayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class UpdateUserUuid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    )
    {
        //
    }

    /**
     * Handles the logic for generating a new UUID for the user and updating relevant data in the system.
     *
     * This method performs the following steps:
     * - Generates a new UUID using the Str::uuid() method.
     * - Retrieves the old UUID from the user object.
     * - Creates a new instance of the ClashService class with the user object.
     * - Retrieves all VmessServer records from the database.
     * - Iterates over each VmessServer record and performs the following steps:
     *   - Checks if the user is low priority and the VmessServer is not for low priority. If true, skips to the next iteration.
     *   - Creates a new instance of the V2rayService class with the internal server of the VmessServer.
     *   - Removes the user's email from the V2rayService.
     *   - Adds a new user with the new UUID and the user's email to the V2rayService.
     *   - Stores the VmessServer record in an array for later use.
     * - Updates the user's UUID in the database.
     * - Deletes the clash profile associated with the old UUID.
     * - Generates a new clash profile with the updated VmessServer records.
     * - Updates the user's UUID in the database once again.
     *
     * @see ClashService
     * @see V2rayService
     * @see User
     * @see VmessServer
     */
    public function handle(): void
    {
        $new_uuid = (string)Str::uuid();

        $clash = new ClashService($this->user);
        $vmess_servers = VmessServer::all();

        // add new uuid to vmess servers
        // remove old uuid from vmess servers
        $servers = [];
        /** @var VmessServer $vmess_server */
        foreach ($vmess_servers as $vmess_server) {
            if ($this->user->is_low_priority && !$vmess_server->for_low_priority) {
                continue;
            }

            $v2ray = new V2rayService($vmess_server->internal_server);
            $v2ray->removeUser($this->user->email);
            $v2ray->addUser($this->user->email, $new_uuid);

            $servers[] = $vmess_server;
        }

        // unlink old clash profile
        $clash->delConf();
        // update user uuid in database
        $this->user->update(['uuid' => $new_uuid]);
        // create new clash profile
        $clash->genConf($servers);
    }
}
