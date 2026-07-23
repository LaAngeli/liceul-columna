<?php

namespace App\Support;

/**
 * Token-urile selecției de „familii" (audiența nominală, reach = elevul și părinții): un membru
 * al familiei — elev SAU părinte — identifică familia. Select-ul din formular stochează
 * `student:ID` / `guardian:ID`; la salvare token-urile se expandează în ELEVII vizați (entitatea
 * persistată în pivot), iar reach-ul decide cine din familie vede/primește.
 */
final class FamilyTokens
{
    private const STUDENT = 'student';

    private const GUARDIAN = 'guardian';

    public static function student(int|string $id): string
    {
        return self::STUDENT.':'.$id;
    }

    public static function guardian(int|string $id): string
    {
        return self::GUARDIAN.':'.$id;
    }

    /**
     * Desparte token-urile pe tip. Orice valoare care nu respectă formatul ajunge în `invalid` —
     * validarea de câmp o respinge explicit (un POST fabricat nu se filtrează tăcut).
     *
     * @param  array<int, mixed>  $tokens
     * @return array{students: array<int, int>, guardians: array<int, int>, invalid: array<int, string>}
     */
    public static function parse(array $tokens): array
    {
        $students = [];
        $guardians = [];
        $invalid = [];

        foreach ($tokens as $token) {
            if (! is_string($token) || preg_match('/^(student|guardian):(\d+)$/', $token, $matches) !== 1) {
                $invalid[] = is_scalar($token) ? (string) $token : gettype($token);

                continue;
            }

            if ($matches[1] === self::STUDENT) {
                $students[] = (int) $matches[2];
            } else {
                $guardians[] = (int) $matches[2];
            }
        }

        return [
            'students' => array_values(array_unique($students)),
            'guardians' => array_values(array_unique($guardians)),
            'invalid' => $invalid,
        ];
    }
}
