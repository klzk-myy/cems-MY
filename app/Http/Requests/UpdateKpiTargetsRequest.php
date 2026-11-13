<?php

namespace App\Http\Requests;

use App\Enums\KpiMetric;
use App\Models\Bi\KpiTarget;
use App\Models\Branch;
use Illuminate\Validation\Validator;

/**
 * Validates bulk KPI target edits from the admin page.
 *
 * Authorization is enforced on the route (role:manage_thresholds +
 * mfa.verified + password.confirm) and re-checked in the controller; this
 * request validates shape and whitelists the compound keys so the form
 * cannot mint arbitrary metrics, branches or periods.
 */
class UpdateKpiTargetsRequest extends AuthorizedFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'targets' => ['sometimes', 'array'],
            'targets.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'targets.*.numeric' => 'KPI targets must be numbers.',
            'targets.*.min' => 'KPI targets must not be negative.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $metrics = array_column(KpiMetric::cases(), 'value');
            $periods = [KpiTarget::PERIOD_DAY, KpiTarget::PERIOD_MONTH];
            $branchIds = Branch::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach (array_keys($this->input('targets', [])) as $key) {
                $parts = explode('|', (string) $key);

                if (count($parts) !== 3) {
                    $validator->errors()->add("targets.{$key}", 'Malformed target key.');

                    continue;
                }

                [$metric, $branch, $period] = $parts;

                if (! in_array($metric, $metrics, true)) {
                    $validator->errors()->add("targets.{$key}", "Unknown metric: {$metric}");
                }

                if ($branch !== 'all' && ! in_array((int) $branch, $branchIds, true)) {
                    $validator->errors()->add("targets.{$key}", "Unknown branch: {$branch}");
                }

                if (! in_array($period, $periods, true)) {
                    $validator->errors()->add("targets.{$key}", "Unknown period: {$period}");
                }
            }
        });
    }
}
