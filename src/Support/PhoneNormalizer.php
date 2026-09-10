<?php

namespace OrionSuite\Support;

class PhoneNormalizer
{
    /**
     * Normaliza para formato nacional 9 dígitos (ex: 841234567)
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '258') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Normaliza para formato internacional com prefixo 258 (ex: 258841234567)
     */
    public static function normalizeInternational(string $phone): string
    {
        $clean = self::normalize($phone);
        if (strlen($clean) === 9 && in_array(substr($clean, 0, 2), ['84', '85', '86', '87', '82', '83'], true)) {
            return '258'.$clean;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits;
    }

    /**
     * Detecta o método de pagamento compatível em Moçambique
     */
    public static function detectPaymentMethod(string $phone): ?string
    {
        $clean = self::normalize($phone);

        if (strlen($clean) !== 9) {
            return null;
        }

        $prefix = substr($clean, 0, 2);

        return match ($prefix) {
            '84', '85' => 'MPESA',
            '86', '87' => 'EMOLA',
            '82', '83' => 'MKESH',
            default => null,
        };
    }
}
