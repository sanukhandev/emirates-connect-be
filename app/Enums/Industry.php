<?php

namespace App\Enums;

enum Industry: string
{
    case TECHNOLOGY = 'technology';
    case RETAIL = 'retail';
    case HOSPITALITY = 'hospitality';
    case REAL_ESTATE = 'real-estate';
    case CONSTRUCTION = 'construction';
    case HEALTHCARE = 'healthcare';
    case EDUCATION = 'education';
    case FINANCE = 'finance';
    case PROFESSIONAL_SERVICES = 'professional-services';
    case LOGISTICS = 'logistics';
    case MANUFACTURING = 'manufacturing';
    case MEDIA = 'media';
    case MARKETING = 'marketing';
    case FOOD_AND_BEVERAGE = 'food-and-beverage';
    case AUTOMOTIVE = 'automotive';
    case TRAVEL = 'travel';
    case OTHER = 'other';

    public function label(): string
    {
        return str($this->value)->replace('-', ' ')->title()->toString();
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $industry): array => [
            'value' => $industry->value,
            'label' => $industry->label(),
        ], self::cases());
    }
}
