<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    use ApiResponse;

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ticket = SupportTicket::query()->create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        return $this->successResponse([
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'created_at' => optional($ticket->created_at)->toIso8601String(),
        ], null, 201);
    }
}
