<?php

namespace App\Services;

use App\Enums\BusinessStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $query = $filters['q'] ?? null;
        $type = $filters['type'] ?? 'all';
        $perPage = (int) ($filters['per_page'] ?? 20);
        $unions = [];

        if ($type !== 'businesses') {
            $unions[] = $this->users($filters, $query);
        }

        if ($type !== 'users') {
            $unions[] = $this->businesses($filters, $query);
        }

        $combined = array_shift($unions);
        foreach ($unions as $union) {
            $combined->unionAll($union);
        }

        return DB::query()
            ->fromSub($combined, 'search_results')
            ->orderByDesc('search_relevance')
            ->orderByDesc('is_verified')
            ->orderBy('search_name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    private function users(array $filters, ?string $query): Builder
    {
        $name = "COALESCE(NULLIF(profiles.display_name, ''), users.name)";
        $builder = DB::table('users')
            ->join('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.account_status', UserStatus::ACTIVE->value)
            ->selectRaw("users.id, 'user' AS type, {$name} AS search_name, {$name} AS display_name,
                profiles.headline, profiles.avatar_path, NULL AS name, NULL AS slug, NULL AS description,
                NULL AS logo_path, profiles.industry, profiles.emirate,
                (profiles.verification_status = ?) AS is_verified", [VerificationStatus::APPROVED->value]);

        $this->applyCommonFilters($builder, 'profiles', $filters);
        $this->applyKeyword($builder, $query, [
            'profiles.display_name', 'users.name', 'profiles.headline', 'profiles.bio',
            'profiles.job_title', 'profiles.company_name', 'profiles.industry', 'profiles.emirate',
        ], $name);

        return $builder;
    }

    private function businesses(array $filters, ?string $query): Builder
    {
        $builder = DB::table('businesses')
            ->where('businesses.status', BusinessStatus::ACTIVE->value)
            ->selectRaw("businesses.id, 'business' AS type, businesses.name AS search_name, NULL AS display_name,
                businesses.tagline AS headline, NULL AS avatar_path, businesses.name, businesses.slug,
                businesses.description, businesses.logo_path, businesses.industry, businesses.emirate,
                (businesses.verification_status = ?) AS is_verified", [VerificationStatus::APPROVED->value]);

        $this->applyCommonFilters($builder, 'businesses', $filters);
        $this->applyKeyword($builder, $query, [
            'businesses.name', 'businesses.slug', 'businesses.tagline', 'businesses.description',
            'businesses.industry', 'businesses.emirate',
        ], 'businesses.name');

        return $builder;
    }

    private function applyCommonFilters(Builder $builder, string $table, array $filters): void
    {
        if (isset($filters['industry'])) {
            $builder->where("{$table}.industry", $filters['industry']);
        }

        if (isset($filters['emirate'])) {
            $builder->where("{$table}.emirate", $filters['emirate']);
        }

        if (array_key_exists('verified', $filters)) {
            $column = "{$table}.verification_status";
            $approved = VerificationStatus::APPROVED->value;
            $filters['verified']
                ? $builder->where($column, $approved)
                : $builder->where(function (Builder $query) use ($column, $approved): void {
                    $query->where($column, '!=', $approved)->orWhereNull($column);
                });
        }
    }

    private function applyKeyword(Builder $builder, ?string $query, array $fields, string $nameField): void
    {
        if ($query === null) {
            $builder->selectRaw('0 AS search_relevance');

            return;
        }

        $literal = $this->escapeLike(mb_strtolower($query));
        $contains = "%{$literal}%";
        $prefix = "{$literal}%";
        $bindings = [$query, $prefix, $contains];
        $score = "CASE
            WHEN LOWER({$nameField}) = LOWER(?) THEN 100
            WHEN LOWER({$nameField}) LIKE LOWER(?) ESCAPE '!' THEN 80
            WHEN LOWER({$nameField}) LIKE LOWER(?) ESCAPE '!' THEN 60";

        foreach (array_slice($fields, 2) as $field) {
            $score .= " WHEN LOWER({$field}) LIKE LOWER(?) ESCAPE '!' THEN 40";
            $bindings[] = $contains;
        }

        $score .= ' ELSE 20 END AS search_relevance';
        $builder->selectRaw($score, $bindings)->where(function (Builder $where) use ($fields, $contains): void {
            foreach ($fields as $index => $field) {
                $where->{$index === 0 ? 'whereRaw' : 'orWhereRaw'}("{$field} LIKE ? ESCAPE '!'", [$contains]);
            }
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
