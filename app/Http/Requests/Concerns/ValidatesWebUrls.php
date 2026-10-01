<?php

namespace App\Http\Requests\Concerns;

use Closure;

trait ValidatesWebUrls
{
    protected function webUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $parts = parse_url((string) $value);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if (! in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
                $fail("The {$attribute} must be a valid web URL.");
            }
        };
    }
}
