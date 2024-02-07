<?php

namespace FpDbTest;

use http\Exception\RuntimeException;

enum PlaceholderEnum: string
{
    case Integer = '?d';
    case Float = '?f';
    case Array = '?a';
    case ID = '?#';
    case Any = '?';

    public function getValue(int|float|array|null|bool|string $arg): string
    {
        return (string)(match ($this) {
            self::Any => static function () use ($arg) {
                return self::getAnyValue($arg);
            },
            self::Integer, self::Float => static function () use ($arg) {
                return self::getNumericValue($arg);
            },
            self::Array => static function () use ($arg) {
                return self::getArrayValue($arg);
            },
            self::ID => static function () use ($arg) {
                if (is_array($arg)) {
                    return self::getArrayValue($arg, true);
                }

                return "`{$arg}`";
            },
        })();
    }

    private static function getAnyValue(string|int|float|null $arg): float|int|string
    {
        if (is_string($arg)) {
            return "'$arg'";
        }

        return self::getNumericValue($arg);
    }

    private static function getArrayValue(array $arg, bool $isID = false): string
    {
        if (!array_is_list($arg)) {
            $response = '';

            foreach ($arg as $key => $value) {
                $response .= "`{$key}` = " . self::getAnyValue($value);

                if ($key !== array_key_last($arg)) {
                    $response .= ', ';
                }
            }
        } else {
            $response = $isID ? '' : '(';

            foreach ($arg as $key => $value) {
                $response .= ($isID ? "`{$value}`" : self::getAnyValue($value));
                if ($key !== array_key_last($arg)) {
                    $response .= ', ';
                }
            }

            $response .= ($isID ? '' : ')');
        }

        return $response;
    }

    private static function getNumericValue(int|float|bool|null $arg): float|int|string
    {
        return match (true) {
            is_null($arg) => 'NULL',
            is_numeric($arg) => $arg,
            is_bool($arg) => (int)$arg,
            default => throw new RuntimeException(
                sprintf('Wrong type provided for %s', $arg)
            )
        };
    }
}