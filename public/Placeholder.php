<?php

namespace FpDbTest;

class Placeholder
{
    public PlaceholderEnum $type;
    public bool $conditional;

    public function __construct(array $params = [])
    {
        foreach ($params as $key => $param) {
            if (property_exists(self::class, $key)) {
                $this->{$key} = $param;
            }
        }
    }
}