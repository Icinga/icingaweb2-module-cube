<?php

namespace Icinga\Module\Cube;

use Icinga\Module\Cube\Common\IcingaDb;
use ipl\Sql\Select;

class ServiceDbQuery {

    use IcingaDb;

    public function getResult($urlDimensions, $slices = null)
    {
        $select = (new Select())
            ->from('service s')
            ->join(
                "service_state state",
                "state.service_id = s.id"
            );

        $columns = [];
        foreach ($urlDimensions as $dimension) {
            $select
                ->join(
                    "service_customvar {$dimension}_junction",
                    "{$dimension}_junction.service_id = s.id"
                )
                ->join(
                    "customvar {$dimension}",
                    "{$dimension}.id = {$dimension}_junction.customvar_id AND {$dimension}.name = \"{$dimension}\""
                );

            $columns[$dimension] = $dimension . '.value';
        }

        $groupByValues = $columns;
        $columns['cnt'] = 'SUM(1)';
        $columns['count_ok'] = 'SUM(CASE WHEN state.soft_state = 0 THEN  1 ELSE 0 END)';
        $columns['count_warning'] = 'SUM(CASE WHEN state.soft_state = 1 THEN  1 ELSE 0 END)';
        $columns['count_warning_unhandled'] = 'SUM(CASE WHEN state.soft_state = 1 AND state.is_handled = "n" THEN  1 ELSE 0 END)';
        $columns['count_critical'] = 'SUM(CASE WHEN state.soft_state = 2 THEN  1 ELSE 0 END)';
        $columns['count_critical_unhandled'] = 'SUM(CASE WHEN state.soft_state = 2 AND state.is_handled = "n" THEN  1 ELSE 0 END)';
        $columns['count_unknown'] = 'SUM(CASE WHEN state.soft_state = 3 THEN  1 ELSE 0 END)';
        $columns['count_unknown_unhandled'] = 'SUM(CASE WHEN state.soft_state = 3 AND state.is_handled = "n" THEN  1 ELSE 0 END)';
        // dimension is the last key here
        $groupByValues[$dimension] .= ' WITH ROLLUP';

        $select
            ->columns($columns)
            ->groupBy($groupByValues);

        if (!empty($slices)) {
            foreach ($slices as $key => $value) {
                $select
                    ->where("{$key}.value = '\"{$value}\"'");
            }
        }

        return $this->getDb()->select($select)->fetchAll();
    }
}
