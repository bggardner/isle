<?php

namespace ISLE\DataModels;

class Transaction extends Node
{
    static public function validate($node)
    {
        if (!($node['id'] ?? 0)) {
            $last_type = \ISLE\Service::executeStatement(
                '
  SELECT `type`
  FROM `' . \ISLE\Settings::get('table_prefix') . 'transactions`
  WHERE `asset` = ?
  ORDER BY `ts` DESC
  LIMIT 1
                ',
                [['value' => $node['asset'], 'type' => \PDO::PARAM_INT]]
            )->fetchColumn();
            switch ($last_type) {
                case TransactionType::CHECK_OUT:
                    if ($node['type'] != TransactionType::CHECK_IN) {
                        throw new \Exception('Asset must be checked in first');
                    }
                    break;
                case TransactionType::RESTRICT:
                    if ($node['type'] != TransactionType::UNRESTRICT) {
                        throw new \Exception('Asset must be unrestricted first');
                    }
                    break;
                default:
            }
        }
        if ($node['type'] != TransactionType::CHECK_OUT) {
            $node['location'] = \ISLE\Service::executeStatement(
                '
  SELECT `location`
  FROM `' . \ISLE\Settings::get('table_prefix') . 'assets`
  WHERE `id` = ?
                ',
                [['value' => $node['asset'], 'type' => \PDO::PARAM_INT]]
            )->fetchColumn();
        }
        $node['returning'] = in_array(
            $node['type'],
            [
                TransactionType::RESTRICT,
                TransactionType::UNRESTRICT
            ]) ? null : $node['returning'] ?? null;
        return $node;
    }
}
