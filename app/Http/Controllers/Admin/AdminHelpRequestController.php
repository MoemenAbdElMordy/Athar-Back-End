<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHelpRequestRequest;
use App\Models\HelpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminHelpRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheSeconds = max((int) config('athar.admin_list_cache_seconds', 60), 0);
        $refreshRequested = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOL);
        $queryParams = $request->query();
        unset($queryParams['refresh']);

        if ($cacheSeconds > 0 && !$refreshRequested) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_help_requests:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );

            $cachedPayload = Cache::get($cacheKey);
            if (is_array($cachedPayload)) {
                return response()->json($cachedPayload);
            }
        }

        $status = (string) $request->query('status', 'pending');
        $hasReviewsTable = Schema::hasTable('volunteer_reviews');
        $hasPaymentCol = Schema::hasColumn('help_requests', 'payment_method');

        $eagerLoads = [
            'requester:id,name,full_name,email,phone',
            'volunteer:id,name,full_name,email,phone',
            'payment:id,help_request_id,user_id,payment_method,status,success,amount_cents,currency,paymob_transaction_id,paid_at,created_at',
        ];
        if ($hasReviewsTable) {
            $eagerLoads[] = 'volunteerReview:id,help_request_id,rating,comment';
        }

        $query = HelpRequest::query()->with($eagerLoads);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('assistance_type')) {
            $query->where('assistance_type', $request->query('assistance_type'));
        }

        if ($hasPaymentCol && $request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        if ($request->filled('urgency_level')) {
            $query->where('urgency_level', $request->query('urgency_level'));
        }

        $completedStatuses = ['active', 'confirmed', 'completed', 'resolved'];
        $paymentCompletedFilterRaw = $request->query('payment_completed');
        $paymentCompletedFilter = $paymentCompletedFilterRaw === null
            ? null
            : filter_var($paymentCompletedFilterRaw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($paymentCompletedFilter !== null) {
            if ($paymentCompletedFilter) {
                $query->where(function ($builder) use ($completedStatuses): void {
                    $builder
                        ->where(function ($onlineBuilder): void {
                            $onlineBuilder
                                ->whereIn('payment_method', ['card', 'wallet'])
                                ->whereHas('payment', function ($paymentBuilder): void {
                                    $paymentBuilder
                                        ->where('status', 'paid')
                                        ->where('success', true);
                                });
                        })
                        ->orWhere(function ($offlineBuilder) use ($completedStatuses): void {
                            $offlineBuilder
                                ->whereNotIn('payment_method', ['card', 'wallet'])
                                ->where(function ($settledBuilder) use ($completedStatuses): void {
                                    $settledBuilder
                                        ->whereNotNull('cleared_at')
                                        ->orWhereIn('status', $completedStatuses);
                                });
                        });
                });
            } else {
                $query->where(function ($builder) use ($completedStatuses): void {
                    $builder
                        ->where(function ($onlineBuilder): void {
                            $onlineBuilder
                                ->whereIn('payment_method', ['card', 'wallet'])
                                ->where(function ($pendingBuilder): void {
                                    $pendingBuilder
                                        ->whereDoesntHave('payment')
                                        ->orWhereHas('payment', function ($paymentBuilder): void {
                                            $paymentBuilder->where(function ($stateBuilder): void {
                                                $stateBuilder
                                                    ->where('status', '!=', 'paid')
                                                    ->orWhere('success', false);
                                            });
                                        });
                                });
                        })
                        ->orWhere(function ($offlineBuilder) use ($completedStatuses): void {
                            $offlineBuilder
                                ->whereNotIn('payment_method', ['card', 'wallet'])
                                ->whereNull('cleared_at')
                                ->whereNotIn('status', $completedStatuses);
                        });
                });
            }
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('created_at', [$request->query('from'), $request->query('to') . ' 23:59:59']);
        }

        $perPage = min((int) ($request->query('per_page', 15) ?: 15), 100);
        $helpRequests = $query->orderByDesc('id')->paginate($perPage);

        $helpRequests->setCollection(
            $helpRequests->getCollection()->map(fn (HelpRequest $helpRequest): array => $this->formatForDashboard($helpRequest))
        );

        $payload = $helpRequests->toArray();

        if ($cacheSeconds > 0) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_help_requests:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );
            Cache::put($cacheKey, $payload, now()->addSeconds($cacheSeconds));
        }

        return response()->json($payload);
    }

    public function show(int $id): JsonResponse
    {
        $hasReviewsTable = Schema::hasTable('volunteer_reviews');

        $eagerLoads = [
            'requester:id,name,full_name,email,phone',
            'volunteer:id,name,full_name,email,phone',
            'messages',
            'payment:id,help_request_id,user_id,payment_method,status,success,amount_cents,currency,paymob_transaction_id,paid_at,created_at',
        ];
        if ($hasReviewsTable) {
            $eagerLoads[] = 'volunteerReview';
        }

        $helpRequest = HelpRequest::query()->with($eagerLoads)->find($id);

        if (!$helpRequest) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($this->formatForDashboard($helpRequest));
    }

    public function update(UpdateHelpRequestRequest $request, int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $helpRequest->status = $data['status'];
        }

        if (array_key_exists('assigned_admin_id', $data)) {
            $helpRequest->assigned_admin_id = $data['assigned_admin_id'];
        }

        $helpRequest->save();
        $this->bumpCacheVersion();

        return response()->json($helpRequest);
    }

    public function resolve(int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($helpRequest->status !== 'resolved') {
            $helpRequest->status = 'resolved';
            $helpRequest->resolved_at = now();

            if (!$helpRequest->assigned_admin_id) {
                $admin = Auth::guard('web')->user();
                $helpRequest->assigned_admin_id = $admin?->id;
            }

            $helpRequest->save();
            $this->bumpCacheVersion();
        }

        return response()->json(['success' => true, 'help_request' => $helpRequest]);
    }

    private function cacheVersion(): int
    {
        return max((int) Cache::get('admin_help_requests:version', 1), 1);
    }

    private function bumpCacheVersion(): void
    {
        Cache::forever('admin_help_requests:version', $this->cacheVersion() + 1);
    }

    private function formatForDashboard(HelpRequest $helpRequest): array
    {
        $payload = $helpRequest->toArray();
        $payment = $helpRequest->payment;
        $requiresOnlinePayment = in_array((string) $helpRequest->payment_method, ['card', 'wallet'], true);

        $paymentCompleted = $payment
            ? ($payment->status === 'paid' && (bool) $payment->success)
            : (! $requiresOnlinePayment
                && ($helpRequest->cleared_at !== null
                    || in_array((string) $helpRequest->status, ['active', 'confirmed', 'completed', 'resolved'], true)));

        $payload['payment_required'] = $requiresOnlinePayment;
        $payload['payment_completed'] = $paymentCompleted;
        $payload['payment_details'] = [
            'payment_id' => $payment?->id,
            'status' => $payment?->status,
            'success' => $payment?->success,
            'amount_cents' => $payment?->amount_cents,
            'amount_egp' => $payment?->amount_cents !== null ? round(((int) $payment->amount_cents) / 100, 2) : null,
            'currency' => $payment?->currency,
            'paymob_transaction_id' => $payment?->paymob_transaction_id,
            'paid_at' => $payment?->paid_at?->toIso8601String(),
            'created_at' => $payment?->created_at?->toIso8601String(),
        ];

        return $payload;
    }
}
