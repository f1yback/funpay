<?php

namespace FpDbTest;

use mysqli;
use RuntimeException;

class Database implements DatabaseInterface
{
    private const SKIP_MYSQL_CONDITION = 'SKIP_MYSQL_CONDITION';

    private mysqli $mysqli;
    private array $parsedPlaceholders = [];
    private array $parsedQuery = [];
    private array $rawQuery = [];
    private array $args = [];
    private array $argsMap = [];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $this->args = $args;
        $this->parsePlaceholders($query);
        $this->checkValidArgumentsCount(count($this->parsedPlaceholders), count($args));

        foreach ($this->parsedPlaceholders as $placeholderPos => $placeholder) {
            $query = $this->rebuildQuery($placeholder, $placeholderPos);
        }

        return $query;
    }

    public function skip(): string
    {
        return self::SKIP_MYSQL_CONDITION;
    }

    private function checkValidArgumentsCount(int $specsCount, int $argCount): void
    {
        if ($specsCount !== $argCount) {
            throw new RuntimeException(sprintf('Expect %d arguments count, got: %d', $specsCount, $argCount));
        }
    }

    private function parsePlaceholders(string $query): void
    {
        $this->parsedQuery = explode(' ', $query);
        $this->rawQuery = $this->parsedQuery;
        $this->parsedPlaceholders = array_filter(
            $this->parsedQuery,
            static fn($val) => str_contains($val, '?')
        );

        $this->argsMap = array_flip(array_keys($this->parsedPlaceholders));

        if ($this->parsedPlaceholders) {
            foreach ($this->parsedPlaceholders as $key => $placeholder) {
                $this->parsedPlaceholders[$key] = new Placeholder([
                    'type' => $this->resolvePlaceholder($placeholder),
                    'conditional' => str_contains($placeholder, '}')
                ]);
            }
        }
    }

    private function resolvePlaceholder(string $placeholder): PlaceholderEnum
    {
        $enum = PlaceholderEnum::tryFrom($placeholder);

        if (!$enum) {
            foreach (PlaceholderEnum::cases() as $case) {
                if (str_contains($placeholder, $case->value)) {
                    return $case;
                }
            }
        }

        return $enum;
    }

    private function rebuildQuery(Placeholder $placeholder, int $placeholderPos): string
    {
        if ($placeholder->conditional && $this->args[$this->argsMap[$placeholderPos]] === self::SKIP_MYSQL_CONDITION) {
            $splicePositions = [];
            $index = $placeholderPos;

            while (count($splicePositions) < 2) {
                if (str_contains($this->rawQuery[$index], '{')) {
                    $splicePositions[0] = $index;
                    $index++;
                    continue;
                }

                if (str_contains($this->rawQuery[$index], '}')) {
                    $splicePositions[1] = $index;
                    $index--;
                    continue;
                }

                if (isset($splicePositions[1])) {
                    $index--;
                } else {
                    $index++;
                }

                if ($index <= 0 || $index >= array_key_last($this->rawQuery)) {
                    throw new RuntimeException('Out of range');
                }
            }

            $this->parsedQuery = array_splice($this->parsedQuery, 0, $splicePositions[0] + 1);
        } else {
            $this->parsedQuery[$placeholderPos] = $placeholder->type->getValue($this->args[$this->argsMap[$placeholderPos]]);
        }

        return trim(implode(' ', $this->parsedQuery));
    }
}
