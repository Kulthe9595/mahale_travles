<?php

namespace App\Http\Requests;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripRequest extends FormRequest
{
    /** When true, end details are always required (used by the bill preview). */
    protected bool $requireEndDetails = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $needsEnd = $this->requireEndDetails || $this->input('status') === TripStatus::Completed->value;
        $endRule = $needsEnd ? 'required' : 'nullable';

        $rules = [
            'customer_id' => ['required', 'exists:customers,id'],
            'car_id' => ['required', 'exists:cars,id'],
            'driver_id' => ['nullable', 'exists:drivers,id'],
            'pickup' => ['nullable', 'string', 'max:200'],
            'drop' => ['nullable', 'string', 'max:200'],
            'start_at' => ['required', 'date'],
            'end_at' => [$endRule, 'date', 'after_or_equal:start_at'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'start_km' => [$endRule, 'integer', 'min:0', 'max:9999999'],
            'end_km' => [$endRule, 'integer', 'min:0', 'max:9999999', 'gte:start_km'],
            'status' => [$this->requireEndDetails ? 'nullable' : 'required', Rule::enum(TripStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'billing' => ['array'],
            'billing.gst_enabled' => ['boolean'],
            'billing.package_mode' => ['nullable', 'in:fixed,per_day'],
            'billing.included_km' => ['nullable', 'integer', 'min:0'],
            'billing.included_hours' => ['nullable', 'integer', 'min:0'],
            'billing.other_taxable_label' => ['nullable', 'string', 'max:60'],
        ];

        foreach (Trip::MONEY_KEYS as $key) {
            $rules["billing.$key"] = ['nullable', 'numeric', 'min:0', 'max:10000000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'end_km.gte' => 'End km cannot be less than start km.',
            'end_at.after_or_equal' => 'Trip end cannot be before trip start.',
        ];
    }

    /** Attributes for Trip::fill(). */
    public function tripAttributes(): array
    {
        $data = $this->validated();
        $billing = $data['billing'] ?? [];
        $billing['gst_enabled'] = (bool) ($billing['gst_enabled'] ?? false);
        unset($data['billing']);

        return $data + ['billing_inputs' => $billing];
    }
}
