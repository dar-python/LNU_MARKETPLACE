<?php

namespace App\Http\Requests;

use App\Models\PostReport;
use App\Models\UserReport;
use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ShowReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('postReport') ?? $this->route('userReport');
        $userId = (int) ($this->user()?->id ?? 0);

        return ($report instanceof PostReport || $report instanceof UserReport)
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
