<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'companion_id' => ['required', 'integer', 'exists:companions,id'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'cancellation' => ['sometimes', 'boolean'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'special_instructions' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companionId = (int) $this->input('companion_id');
            $newStart = $this->date('scheduled_start');
            $newEnd = $this->date('scheduled_end');

            if (!$newStart || !$newEnd) {
                return;
            }

            $bookingParam = $this->route('booking');
            $ignoreId = null;
            if (is_object($bookingParam) && property_exists($bookingParam, 'id')) {
                $ignoreId = $bookingParam->id;
            } elseif (is_numeric($bookingParam)) {
                $ignoreId = (int) $bookingParam;
            }

            $overlapQuery = Booking::query()
                ->where('companion_id', $companionId)
                ->where('cancellation', false)
                ->where('scheduled_start', '<', $newEnd)
                ->where('scheduled_end', '>', $newStart);

            if (!is_null($ignoreId)) {
                $overlapQuery->whereKeyNot($ignoreId);
            }

            if ($overlapQuery->exists()) {
                $validator->errors()->add('scheduled_start', 'This companion has another booking that overlaps with the requested time range.');
            }
        });
    }
}
