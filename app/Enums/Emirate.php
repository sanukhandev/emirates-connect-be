<?php

namespace App\Enums;

enum Emirate: string
{
    case ABU_DHABI = 'abu-dhabi';
    case DUBAI = 'dubai';
    case SHARJAH = 'sharjah';
    case AJMAN = 'ajman';
    case UMM_AL_QUWAIN = 'umm-al-quwain';
    case RAS_AL_KHAIMAH = 'ras-al-khaimah';
    case FUJAIRAH = 'fujairah';

    public function label(): string
    {
        return str($this->value)->replace('-', ' ')->title()->toString();
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $emirate): array => [
            'value' => $emirate->value,
            'label' => $emirate->label(),
        ], self::cases());
    }
}
