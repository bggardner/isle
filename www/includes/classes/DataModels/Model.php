<?php

namespace ISLE\DataModels;

class Model extends Node
{
    static public function validate($node)
    {
        try {
            $node['description'] = \ISLE\Validate::stringLength(trim($node['description']), 16);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'Description', $e->getMessage()));
        }
        try {
            $node['model'] = \ISLE\Validate::stringLength(trim($node['model']), 1);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'Model', $e->getMessage()));
        }
        $node['series'] = trim($node['series'] ?? null) ?: null;
        $node['url'] = trim($node['url'] ?? null) ?: null;
        $node['url'] = is_null($node['url']) ? null : \ISLE\Validate::url($node['url']);
        $node['attachments'] = is_array($node['attachments'] ?? null) ? array_unique($node['attachments']) : [];
        parent::validateAttributes($node);
        $node['categories'] = ($node['categories'] ?? []);
        $node['categories'] = is_array($node['categories']) ? array_unique($node['categories']) : [];
        return $node;
    }
}
