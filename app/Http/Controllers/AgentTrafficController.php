<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrafficBatchRequest;
use App\Services\TrafficIngestionService;
use Illuminate\Http\JsonResponse;

class AgentTrafficController extends Controller
{
    public function store(StoreTrafficBatchRequest $request, TrafficIngestionService $service): JsonResponse
    {
        $data = $request->validated();
        $batch = $service->ingest($request->attributes->get('node'), $data['batch_uuid'], $data['records']);

        return response()->json(['batch_uuid' => $batch->batch_uuid, 'accepted' => true]);
    }
}
