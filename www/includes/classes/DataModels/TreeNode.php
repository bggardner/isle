<?php

namespace ISLE\DataModels;

abstract class TreeNode extends Node
{
    static public function validate($node)
    {
        try {
            \ISLE\Validate::stringLength(trim($node['name']), 1);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'Name', $e->getMessage()));
        }
        $node['parent'] = max(1, intval($node['parent']));
        return $node;
    }
}
