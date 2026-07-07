<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientType: string
{
    case Individual = 'individual';
    case LegalEntity = 'legal_entity';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Физ. лицо',
            self::LegalEntity => 'Юр. лицо',
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
