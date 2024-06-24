<?php

namespace App\Services;

use Grpc\BaseStub;

class V2rayService extends BaseStub
{
    public function __construct($hostname, $opts = null, $channel = null)
    {
        if (is_null($opts)) {
            $opts = [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ];
        }

        parent::__construct($hostname, $opts, $channel);
    }

    public function AlterInboundRequest($tag, $operation)
    {
        $request = new \V2ray\Core\App\Proxyman\Command\AlterInboundRequest();
        $request->setTag($tag);
        $request->setOperation($operation);

        [$response, $status] = $this->_simpleRequest(
            '/v2ray.core.app.proxyman.command.Command/AlterInboundRequest',
            $request,
            ['\V2ray\Core\App\Proxyman\Command\AlterInboundResponse', 'decode']
        );

        return [$response, $status];
    }
}
