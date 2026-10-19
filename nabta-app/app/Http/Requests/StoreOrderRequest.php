<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\Payment\PaymobService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side mirror of validateOrderForm() from assets/js/nabta-shop.js.
 * The browser copy is a convenience for the customer; this one is the real rule set.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:3', 'max:120'],
            'customer_phone' => ['required', 'string', 'regex:/^01[0125][0-9]{8}$/'],
            'customer_alt_phone' => ['nullable', 'string', 'regex:/^01[0125][0-9]{8}$/'],
            'governorate_id' => [
                'required',
                Rule::exists('governorates', 'id')->where('is_active', true),
            ],
            'city' => ['required', 'string', 'min:2', 'max:120'],
            'address_line' => ['required', 'string', 'min:10', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Online payment is only offered while Paymob is fully configured.
            'payment_method' => [
                'required',
                Rule::in(app(PaymobService::class)->isConfigured()
                    ? [Order::PAYMENT_COD, Order::PAYMENT_PAYMOB]
                    : [Order::PAYMENT_COD]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'من فضلك اكتب الاسم بالكامل',
            'customer_name.min' => 'من فضلك اكتب الاسم بالكامل',
            'customer_phone.required' => 'من فضلك اكتب رقم الموبايل',
            'customer_phone.regex' => 'رقم موبايل مصري غير صحيح (مثال: 01012345678)',
            'customer_alt_phone.regex' => 'رقم الموبايل البديل غير صحيح',
            'governorate_id.required' => 'اختر المحافظة',
            'governorate_id.exists' => 'اختر المحافظة',
            'city.required' => 'اكتب المدينة أو المنطقة',
            'city.min' => 'اكتب المدينة أو المنطقة',
            'address_line.required' => 'اكتب العنوان بالتفصيل (شارع، رقم العقار، الدور)',
            'address_line.min' => 'اكتب العنوان بالتفصيل (شارع، رقم العقار، الدور)',
            'payment_method.required' => 'اختر طريقة الدفع',
            'payment_method.in' => 'طريقة الدفع المختارة غير متاحة حالياً',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'customer_phone' => $this->cleanPhone($this->input('customer_phone')),
            'customer_alt_phone' => $this->cleanPhone($this->input('customer_alt_phone')),
            // Older cached checkout pages post no method; they only ever offered COD.
            'payment_method' => $this->input('payment_method', Order::PAYMENT_COD),
        ], fn ($v) => $v !== null));
    }

    /**
     * Egyptian customers type numbers with spaces, dashes, a +2 prefix and
     * sometimes Arabic-Indic digits. Normalise before validating rather than
     * rejecting a number that is perfectly valid.
     */
    private function cleanPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $western = strtr(trim($value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $digits = preg_replace('/\D+/', '', $western) ?? '';

        if (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
