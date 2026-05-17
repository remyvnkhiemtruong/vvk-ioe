<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class SpreadsheetTable
{
    public static function read(string $path, array $fieldAliases, int $minimumMatches = 3): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $headerIndex = self::detectHeaderIndex($rows, $fieldAliases, $minimumMatches);
        $headers = array_map(fn ($value) => self::normalizeHeader((string) $value), $rows[$headerIndex] ?? []);

        $dataRows = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $dataRows[] = [
                'row_number' => $index + 1,
                'values' => $row,
            ];
        }

        return [
            'header_row' => $headerIndex + 1,
            'headers' => $headers,
            'mapping' => self::guessMapping($headers, $fieldAliases),
            'rows' => $dataRows,
        ];
    }

    public static function normalizeHeader(string $header): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($header)));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
    }

    public static function cleanValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return in_array($value, ['', '?', '-', '—', '–'], true) ? null : $value;
    }

    public static function mapRow(array $headers, array $row, array $mapping, array $fields): array
    {
        $assoc = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $assoc[$header] = self::cleanValue($row[$index] ?? null);
        }

        return collect($fields)
            ->mapWithKeys(fn ($field) => [$field => $assoc[$mapping[$field] ?? ''] ?? null])
            ->all();
    }

    public static function parseDate(?string $value): ?string
    {
        $value = self::cleanValue($value);

        if (! $value) {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    public static function gradeFromText(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        preg_match('/(10|11|12)/', $value, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    public static function booleanFromText(?string $value): ?bool
    {
        $value = self::normalizeHeader((string) $value);

        return match ($value) {
            'co', 'yes', 'true', '1' => true,
            'khong', 'no', 'false', '0' => false,
            default => null,
        };
    }

    public static function activeStatusFromText(?string $value, string $activeDefault = 'active'): string
    {
        $value = self::normalizeHeader((string) $value);

        if ($value === '') {
            return $activeDefault;
        }

        return str_contains($value, 'dang') ? 'active' : 'inactive';
    }

    public static function emptyPayload(array $payload): bool
    {
        foreach ($payload as $value) {
            if (self::cleanValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private static function detectHeaderIndex(array $rows, array $fieldAliases, int $minimumMatches): int
    {
        $bestIndex = null;
        $bestScore = 0;

        foreach ($rows as $index => $row) {
            $headers = array_map(fn ($value) => self::normalizeHeader((string) $value), $row);
            $score = count(self::guessMapping($headers, $fieldAliases));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        if ($bestIndex === null || $bestScore < $minimumMatches) {
            throw new InvalidArgumentException('Không tìm thấy dòng tiêu đề phù hợp trong file Excel.');
        }

        return $bestIndex;
    }

    private static function guessMapping(array $headers, array $fieldAliases): array
    {
        $mapping = [];

        foreach ($fieldAliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = self::normalizeHeader($alias);

                foreach ($headers as $header) {
                    if ($header === '') {
                        continue;
                    }

                    if ($header === $normalizedAlias || str_contains($header, $normalizedAlias)) {
                        $mapping[$field] = $header;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }
}
