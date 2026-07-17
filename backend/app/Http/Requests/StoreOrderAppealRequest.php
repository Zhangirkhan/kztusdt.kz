<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExchangeOrder;
use App\Models\OrderAppeal;
use App\Services\OrderAppealService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrderAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ExchangeOrder|null $order */
        $order = $this->route('order');
        $side = $this->appealSide();

        $reasons = $order instanceof ExchangeOrder
            ? app(OrderAppealService::class)->allowedReasons($order, $side)
            : [];

        return [
            'reason' => ['required', 'string', Rule::in($reasons)],
            'description' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    private function appealSide(): string
    {
        return OrderAppeal::SIDE_CLIENT;
    }
}
