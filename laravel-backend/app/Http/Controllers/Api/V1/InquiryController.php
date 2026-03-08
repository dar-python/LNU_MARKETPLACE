<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecideInquiryRequest;
use App\Models\Inquiry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InquiryController extends Controller
{
    public function decide(DecideInquiryRequest $request, Inquiry $inquiry): JsonResponse
    {
        if (! $inquiry->isPending()) {
            return ApiResponse::error('Only pending inquiries can be decided.', [
                'status' => ['The inquiry has already been decided.'],
            ], 422);
        }

        $status = (string) $request->validated('status');
        $decidedAt = now();

        $inquiry->fill([
            'status' => $status,
            'decided_at' => $decidedAt,
            'decided_by' => (int) $request->user()->id,
            'inquiry_status' => $this->legacyInquiryStatus($status),
            'responded_at' => $decidedAt,
        ]);
        $inquiry->save();

        $inquiry = $inquiry->fresh();

        return ApiResponse::success($this->decisionMessage($status), [
            'inquiry' => $this->serializeInquiry($inquiry),
        ]);
    }

    private function decisionMessage(string $status): string
    {
        return $status === Inquiry::STATUS_ACCEPTED
            ? 'Inquiry accepted.'
            : 'Inquiry declined.';
    }

    private function legacyInquiryStatus(string $status): string
    {
        return $status === Inquiry::STATUS_ACCEPTED ? 'resolved' : 'closed';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInquiry(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'listing_id' => (int) $inquiry->listing_id,
            'sender_user_id' => (int) $inquiry->sender_user_id,
            'recipient_user_id' => (int) $inquiry->recipient_user_id,
            'subject' => $inquiry->subject,
            'message' => $inquiry->message,
            'status' => (string) $inquiry->status,
            'decided_at' => $inquiry->decided_at?->toJSON(),
            'decided_by' => $inquiry->decided_by === null ? null : (int) $inquiry->decided_by,
            'created_at' => $inquiry->created_at?->toJSON(),
            'updated_at' => $inquiry->updated_at?->toJSON(),
        ];
    }
}
