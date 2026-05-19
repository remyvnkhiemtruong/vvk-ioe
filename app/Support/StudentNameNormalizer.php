<?php

namespace App\Support;

use Illuminate\Support\Str;

class StudentNameNormalizer
{
    public static function normalize(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s+/u', ' ', $name) ?: $name;

        return trim(Str::ascii(Str::lower($name)));
    }
}
