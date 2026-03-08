<?php

namespace App\Http\Requests;

use App\Models\ModerationReport;
use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ShowReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');
        $userId = (int) ($this->user()?->id ?? 0);

        return $report instanceof ModerationReport
            && $userId > 0
            && $userId === (int) $report->reporter_user_id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error('Forbidden.', null, 403)
        );
    }
}
