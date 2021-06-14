<?php

namespace ISLE\DataModels;

class User extends Node
{
    const DEFAULT = ['id' => 0, 'name' => 'User', 'role' => Role::DOES_NOT_EXIST];

    public static function validate($node) {
        try {
            \ISLE\Validate::stringLength(trim($node['name']), 1);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'User name', $e->getMessage()));
        }
        \ISLE\Validate::email(trim($node['email']));
        try {
            \ISLE\Validate::stringLength($node['password'], 8);
        } catch (\Exception $e) {
            throw new \Exception(str_replace('String', 'Password', $e->getMessage()));
        }
        $node['hash'] = password_hash($node['password'], PASSWORD_DEFAULT);
        if (!in_array($node['role'], Role::getValues())) {
            throw new \Exception('Unrecognized role: ' . htmlspecialchars($node['role']));
        }
        return $node;
    }
}
