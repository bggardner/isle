<?php

namespace ISLE\DataModels;

abstract class Enum
{
    public static function getValues()
    {
        $constants = (new \ReflectionClass(get_called_class()))->getConstants();
        return array_combine(
            array_map(
                function($constant) { return ucfirst(strtolower(str_replace('_', '-', $constant))); },
                array_keys($constants)
            ),
            array_values($constants)
        );
    }
}
