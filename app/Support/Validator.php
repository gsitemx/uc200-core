<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rulesForField = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);

            foreach ($rulesForField as $rule) {
                if ($rule === 'required' && trim((string) $value) === '') {
                    $errors[$field][] = 'required';
                }

                if ($rule === 'email' && trim((string) $value) !== '' && ! filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'email';
                }

                if (str_starts_with((string) $rule, 'in:') && trim((string) $value) !== '') {
                    $allowed = explode(',', substr((string) $rule, 3));
                    if (! in_array((string) $value, $allowed, true)) {
                        $errors[$field][] = 'in';
                    }
                }

                if (str_starts_with((string) $rule, 'max:') && strlen((string) $value) > (int) substr((string) $rule, 4)) {
                    $errors[$field][] = 'max';
                }
            }
        }

        return $errors;
    }
}
