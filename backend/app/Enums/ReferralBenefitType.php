<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralBenefitType: string
{
    case FeeDiscount = 'fee_discount';

    public function label(): string
    {
        return match ($this) {
            self::FeeDiscount => 'Скидка на комиссию',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
