<?php

namespace ISLE\DataModels;

abstract class Node
{
    abstract public static function validate($node);

    protected static function validateAttributes(&$node) {
        $attributes = $node['attributes'] ?? [];
        $node['attribute_values'] = $node['attribute_values'] ?? [];
        $node['attributes'] = [];
        foreach ($attributes as $index => $attribute) {
            $attribute = intval(trim($attribute));
            $value = trim($node['attribute_values'][$index]);
            if ($attribute <= 0) {
                continue;
            }
            if (empty($value)) {
                throw new \Exception('Empty value for attribute ' . htmlspecialchars($node['attribute_names'][$index] ?? ''));
            }
            $node['attributes'][$attribute] = $value;
        }
    }
}
