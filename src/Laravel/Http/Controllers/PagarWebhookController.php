<?php

namespace OrionSuite\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use OrionSuite\Payments\PagarClient;

class PagarWebhookController extends Controller
{
    public function __invoke(Request $request, PagarClient $client): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('Pagar-Signature');
        $eventId = $request->header('Pagar-Event-Id');

        if (! $eventId) {
            return response()->json(['error' => 'Missing Pagar-Event-Id'], 400);
        }

        if (! $client->validateWebhook($rawBody, $signature, $eventId)) {
            Log::warning('Pagar webhook signature validation failed', [
                'eventId' => $eventId,
                'signature' => $signature,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $eventName = $payload['event'] ?? 'unknown';

        Log::info('Pagar webhook received successfully', [
            'eventId' => $eventId,
            'event' => $eventName,
        ]);

        // Disparo de evento genérico do Laravel
        Event::dispatch('pagar.webhook.'.$eventName, [$payload, $eventId]);
        Event::dispatch('pagar.webhook.received', [$payload, $eventId]);

        return response()->json(['status' => 'success', 'eventId' => $eventId], 200);
    }
}
