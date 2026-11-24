<?php

namespace App\Support;

class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        $parts = explode('.', (string) ($amount ?? 0));

        return ((int) $parts[0] * 100) + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
