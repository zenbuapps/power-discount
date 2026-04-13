<?php
declare(strict_types=1);

namespace PowerDiscount\Persistence;

final class JsonSerializer
{
    public static function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '[]' : $json;
    }

    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
