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
use App\Models\Notification;
use App\Models\Payment;
use App\Models\VolunteerReview;
use App\Services\PaymobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'payment_method' => $data['payment_method'],
            'service_fee' => (int) round(($data['service_fee'] ?? 0) * 100),
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
            'status' => ['nullable', 'string', 'in:all,history,pending,active,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HelpRequest::query()
            ->where('requester_id', $request->user()->id)
            ->with(['requester', 'volunteer'])
            ->latest();

        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'history') {
                $query->whereIn('status', ['completed', 'cancelled']);
            } else {
                $query->where('status', $validated['status']);
            }
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

        $volunteer = $request->user();
        $helpRequest->volunteer_id = $volunteer->id;
        $helpRequest->accepted_at = now();

        if ($helpRequest->requiresOnlinePayment()) {
            $helpRequest->status = 'pending_payment';
        } else {
            $helpRequest->status = 'active';
        }

        $helpRequest->save();
        $helpRequest->load(['requester', 'volunteer']);

        Notification::create([
            'user_id' => $helpRequest->requester_id,
            'type' => 'volunteer_accepted',
            'title' => 'Volunteer Accepted!',
            'body' => $helpRequest->requiresOnlinePayment()
                ? 'Please complete payment to confirm your booking.'
                : "Volunteer {$volunteer->name} has accepted your request.",
            'severity' => 'info',
            'notifiable_type' => HelpRequest::class,
            'notifiable_id' => $helpRequest->id,
            'metadata' => [
                'help_request_id' => $helpRequest->id,
                'volunteer_id' => $volunteer->id,
                'volunteer_name' => $volunteer->name,
                'payment_method' => $helpRequest->payment_method,
                'service_fee' => $helpRequest->service_fee,
                'requires_payment' => $helpRequest->requiresOnlinePayment(),
            ],
        ]);

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

        if (!in_array($helpRequest->status, ['active', 'confirmed'], true)
            || (int) $helpRequest->volunteer_id !== (int) $request->user()->id) {
            return $this->errorResponse('Only assigned active/confirmed requests can be completed.', [], 422);
        }

        $helpRequest->status = 'completed';
        $helpRequest->completed_at = now();

        // ── Compute settlement fields ──
        $gross = (int) $helpRequest->service_fee;
        $feePct = (int) config('athar.platform_fee_percentage', 30);
        $fee = (int) round($gross * $feePct / 100);
        $net = $gross - $fee;
        $helpRequest->fee_amount_cents = $fee;
        $helpRequest->net_amount_cents = $net;

        // Cash payments are cleared immediately on completion.
        // Card payments are cleared when Paymob callback marks them paid (handled in PaymobService).
        if ($helpRequest->payment_method === 'cash') {
            $helpRequest->cleared_at = now();
        }

        $helpRequest->save();

        $helpRequest->load(['requester', 'volunteer']);

        Notification::create([
            'user_id' => $helpRequest->requester_id,
            'type' => 'service_completed',
            'title' => 'Service Completed',
            'body' => 'Your volunteer has finished the service. Please rate your experience.',
            'severity' => 'info',
            'notifiable_type' => HelpRequest::class,
            'notifiable_id' => $helpRequest->id,
            'metadata' => [
                'help_request_id' => $helpRequest->id,
                'volunteer_id' => $helpRequest->volunteer_id,
                'volunteer_name' => $helpRequest->volunteer?->name,
                'action' => 'rate_experience',
            ],
        ]);

        broadcast(new HelpRequestUpdated($helpRequest))->toOthers();

        return $this->successResponse(new HelpRequestResource($helpRequest));
    }

    public function payForService(Request $request, int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->with('volunteer')->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        if ((int) $helpRequest->requester_id !== (int) $request->user()->id) {
            return $this->errorResponse('Forbidden.', [], 403);
        }

        if ($helpRequest->status !== 'pending_payment') {
            return $this->errorResponse('This request does not require payment at this time.', [], 422);
        }

        if ($helpRequest->payment_method === 'cash') {
            $helpRequest->status = 'active';
            $helpRequest->save();
            $helpRequest->load(['requester', 'volunteer']);

            return $this->successResponse(
                new HelpRequestResource($helpRequest),
                'Cash payment confirmed. Service is now active.',
            );
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $serviceFeeEgp = $helpRequest->service_fee / 100;
        $amountCents = $helpRequest->service_fee;

        if ($amountCents <= 0) {
            $helpRequest->status = 'active';
            $helpRequest->save();
            $helpRequest->load(['requester', 'volunteer']);

            return $this->successResponse(
                new HelpRequestResource($helpRequest),
                'No fee required. Service is now active.',
            );
        }

        try {
            $payment = Payment::create([
                'help_request_id' => $helpRequest->id,
                'user_id' => $helpRequest->requester_id,
                'payment_method' => 'card',
                'amount_cents' => $amountCents,
                'total_amount' => $serviceFeeEgp,
                'currency' => 'EGP',
                'order_reference' => PaymobService::generateOrderReference(),
                'status' => 'pending',
                'raw_request_json' => [
                    'help_request_id' => $helpRequest->id,
                    'billing' => $validated,
                ],
            ]);

            $paymobService = app(PaymobService::class);
            $result = $paymobService->generateCardCheckoutUrl($payment, $validated);

            return $this->successResponse([
                'payment_id' => $payment->id,
                'help_request_id' => $helpRequest->id,
                'payment_method' => 'card',
                'amount_cents' => $amountCents,
                'amount_egp' => $serviceFeeEgp,
                'currency' => 'EGP',
                'paymob_order_id' => $result['paymob_order_id'],
                'payment_token' => $result['payment_key'],
                'checkout_url' => $result['checkout_url'],
                'status' => $payment->fresh()->status,
            ], 'Payment session created. Proceed to checkout.');

        } catch (\Throwable $e) {
            Log::error('Pay for service failed', [
                'help_request_id' => $helpRequest->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Unable to process payment at the moment.', [], 500);
        }
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

    public function rateVolunteer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return $this->errorResponse('Help request not found.', [], 404);
        }

        if ((int) $helpRequest->requester_id !== (int) $request->user()->id) {
            return $this->errorResponse('Forbidden.', [], 403);
        }

        if ($helpRequest->status !== 'completed') {
            return $this->errorResponse('You can only rate completed requests.', [], 422);
        }

        if (!$helpRequest->volunteer_id) {
            return $this->errorResponse('No volunteer assigned to this request.', [], 422);
        }

        $review = VolunteerReview::query()->updateOrCreate(
            [
                'help_request_id' => $helpRequest->id,
                'reviewer_id' => $request->user()->id,
            ],
            [
                'volunteer_id' => $helpRequest->volunteer_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return $this->successResponse([
            'id' => $review->id,
            'help_request_id' => $helpRequest->id,
            'volunteer_id' => $helpRequest->volunteer_id,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
        ], 'Review submitted successfully.', 201);
    }

    private function canAccessHelpRequest(int $userId, HelpRequest $helpRequest): bool
    {
        return (int) $helpRequest->requester_id === $userId
            || (int) $helpRequest->volunteer_id === $userId;
    }
}
