<?php

namespace App\Support;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Model;

class ReactionSummary
{
    /** @var list<ReactionType> */
    private const TYPES = [
        ReactionType::LIKE,
        ReactionType::CELEBRATE,
        ReactionType::SUPPORT,
        ReactionType::INSIGHTFUL,
    ];

    public static function apply($query)
    {
        $counts = [];
        foreach (self::TYPES as $type) {
            $counts['reactions as reaction_'.$type->value.'_count'] = fn ($reactions) => $reactions->where('type', $type->value);
        }

        $query->withCount($counts);
        if (auth()->check()) {
            $query->with(['reactions' => fn ($reactions) => $reactions->where('user_id', auth()->id())]);
        }

        return $query;
    }

    public static function load(Model $target): Model
    {
        $counts = $target->reactions()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        foreach (self::TYPES as $type) {
            $target->setAttribute('reaction_'.$type->value.'_count', (int) ($counts[$type->value] ?? 0));
        }

        $target->setRelation('reactions', $target->reactions()->where('user_id', auth()->id())->get());

        return $target;
    }

    public static function toArray(Model $target): array
    {
        $counts = [];
        foreach (self::TYPES as $type) {
            $counts[$type->value] = (int) ($target->getAttribute('reaction_'.$type->value.'_count') ?? 0);
        }

        $current = $target->relationLoaded('reactions') ? $target->reactions->first()?->type?->value : null;

        return ['total' => array_sum($counts), 'counts' => $counts, 'current_user' => $current];
    }
}
