<?php

namespace ISLE\DataModels;

class Asset extends Node
{
    static public function validate($node)
    {
        try {
            $node['serial'] = \ISLE\Validate::stringLength(trim($node['serial']), 1);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'Serial', $e->getMessage()));
        }
        $node['attachments'] = is_array($node['attachments'] ?? null) ? array_unique($node['attachments']) : [];
        parent::validateAttributes($node);
        return $node;
    }
}
