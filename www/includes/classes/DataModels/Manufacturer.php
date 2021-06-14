<?php

namespace ISLE\DataModels;

class Manufacturer extends Node
{
    public static function validate($node)
    {
        try {
            \ISLE\Validate::stringLength(trim($node['name']), 1);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'Name', $e->getMessage()));
        }
        return $node;
    }
}
