<?php

namespace App\Http\Controllers\Api;

use App\Events\HelpRequestAssigned;
use App\Events\HelpRequestCreated;
use App\Events\HelpRequestMessageCreated;
use App\Events\HelpRequestUpdated;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActionRequest;
use App\Http\Requests\Api\StoreHelpRequestRequest;
use App\Http\Requests\Api\StoreHelpRequestMessageRequest;
use App\Http\Resources\HelpRequestResource;
use App\Http\Resources\MessageResource;
use App\Models\HelpRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpRequestController extends Controller
{
    use ApiResponse;

    public function store(StoreHelpRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $helpRequest = HelpRequest::create([
            'requester_id' => $user->id,
            'user_id' => $user->id,
            'urgency_level' => $data['urgency'],
            'assistance_type' => $data['assistance_type'],
            'details' => $data['details'] ?? null,
            'from_name' => $data['from_label'],
            'from_lat' => $data['from_lat'],
            'from_lng' => $data['from_lng'],
            'to_name' => $data['to_label'],
            'to_lat' => $data['to_lat'] ?? null,
            'to_lng' => $data['to_lng'] ?? null,
            'status' => 'pending',
        ]);

        $helpRequest->load(['requester', 'volunteer']);

        broadcast(new HelpRequestCreated($helpRequest))->toOthers();

        return $this->successResponse(new HelpRequestResource($helpRequest), null, 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,active,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HelpRequest::query()
            ->where('requester_id', $request->user()->id)
            ->with(['requester', 'volunteer'])
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $helpRequests = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse(
            $this->paginatedData($helpRequests, HelpRequestResource::collection($helpRequests->getCollection()))
        );
    }

    public function cancel(ActionRequest $request, int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        if ((int) $helpRequest->requester_id !== (int) $request->user()->id) {
            return $this->errorResponse('Forbidden.', [], 403);
        }

        if (!in_array($helpRequest->status, ['pending', 'active'], true)) {
            return $this->errorResponse('Only pending or active requests can be cancelled.', [], 422);
        }

        $helpRequest->status = 'cancelled';
        $helpRequest->cancelled_at = now();
        $helpRequest->save();

        $helpRequest->load(['requester', 'volunteer']);

        broadcast(new HelpRequestUpdated($helpRequest))->toOthers();

        return $this->successResponse(new HelpRequestResource($helpRequest));
    }

    public function accept(ActionRequest $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can accept requests.', [], 403);
        }

        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        if ($helpRequest->status !== 'pending') {
            return $this->errorResponse('Only pending requests can be accepted.', [], 422);
        }

        $helpRequest->status = 'active';
        $helpRequest->volunteer_id = $request->user()->id;
        $helpRequest->accepted_at = now();
        $helpRequest->save();

        $helpRequest->load(['requester', 'volunteer']);

        broadcast(new HelpRequestAssigned($helpRequest))->toOthers();

        return $this->successResponse(new HelpRequestResource($helpRequest));
    }

    public function decline(ActionRequest $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can decline requests.', [], 403);
        }

        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        return $this->successResponse([
            'id' => $helpRequest->id,
            'declined' => true,
        ]);
    }

    public function complete(ActionRequest $request, int $id): JsonResponse
    {
        if ($request->user()->role !== 'volunteer') {
            return $this->errorResponse('Only volunteers can complete requests.', [], 403);
        }

        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        if ($helpRequest->status !== 'active' || (int) $helpRequest->volunteer_id !== (int) $request->user()->id) {
            return $this->errorResponse('Only assigned active requests can be completed.', [], 422);
        }

        $helpRequest->status = 'completed';
        $helpRequest->completed_at = now();
        $helpRequest->save();

        $helpRequest->load(['requester', 'volunteer']);

        broadcast(new HelpRequestUpdated($helpRequest))->toOthers();

        return $this->successResponse(new HelpRequestResource($helpRequest));
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        if (!$this->canAccessHelpRequest($request->user()->id, $helpRequest)) {
            return $this->errorResponse('Forbidden.', [], 403);
        }

        $messages = Message::query()
            ->where('help_request_id', $helpRequest->id)
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return $this->successResponse(
            $this->paginatedData($messages, MessageResource::collection($messages->getCollection()))
        );
    }

    public function storeMessage(StoreHelpRequestMessageRequest $request, int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        $sender = $request->user();

        if (!$this->canAccessHelpRequest($sender->id, $helpRequest)) {
            return $this->errorResponse('Forbidden.', [], 403);
        }

        $receiverId = (int) $helpRequest->requester_id === (int) $sender->id
            ? $helpRequest->volunteer_id
            : $helpRequest->requester_id;

        if (!$receiverId) {
            return $this->errorResponse('No receiver available for this request yet.', [], 422);
        }

        $message = Message::query()->create([
            'help_request_id' => $helpRequest->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'message' => $request->validated()['message'],
        ]);

        broadcast(new HelpRequestMessageCreated($message))->toOthers();

        return $this->successResponse(new MessageResource($message), null, 201);
    }

    private function canAccessHelpRequest(int $userId, HelpRequest $helpRequest): bool
    {
        return (int) $helpRequest->requester_id === $userId
            || (int) $helpRequest->volunteer_id === $userId;
    }
}
