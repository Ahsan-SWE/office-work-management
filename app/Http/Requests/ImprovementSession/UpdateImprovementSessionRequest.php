<?php

namespace App\Http\Requests\ImprovementSession;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImprovementSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'improvement_plan' => ['nullable', 'string', 'max:10000'],
            'team_leader_notes' => ['nullable', 'string', 'max:10000'],
            'employee_visible_notes' => ['nullable', 'string', 'max:10000'],
            'follow_up_date' => ['nullable', 'date'],
        ];
    }
}
