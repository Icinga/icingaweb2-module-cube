<?php

namespace Icinga\Module\Cube;

use Icinga\Module\Cube\Common\IcingaDb;
use ipl\Sql\Select;

class HostDbQuery {

    use IcingaDb;


    public function getResult($urlDimensions, $slices = null)
    {
        $select = (new Select())
            ->from('host h')
            ->join(
                "host_state state",
                "state.host_id = h.id"
            );

        $columns = [];
        foreach ($urlDimensions as $dimension) {
            $select
                ->join(
                    "host_customvar {$dimension}_junction",
                    "{$dimension}_junction.host_id = h.id"
                )
                ->join(
                    "customvar {$dimension}",
                    "{$dimension}.id = {$dimension}_junction.customvar_id AND {$dimension}.name = \"{$dimension}\""
                );

            $columns[$dimension] = $dimension . '.value';
        }

        $groupByValues = $columns;
        $columns['cnt'] = 'SUM(1)';
        $columns['count_up'] = 'SUM(CASE WHEN state.soft_state = 0 THEN  1 ELSE 0 END)';
        $columns['count_down'] = 'SUM(CASE WHEN state.soft_state = 1 THEN  1 ELSE 0 END)';
        $columns['count_down_unhandled'] = 'SUM(CASE WHEN state.soft_state = 1 AND state.is_handled = "n" THEN  1 ELSE 0 END)';
        // dimension is the last key here
        $groupByValues[$dimension] .= ' WITH ROLLUP';

        $select
            ->columns($columns)
            ->groupBy($groupByValues);

        if (! empty($slices)) {
            foreach ($slices as $key => $value) {
                $select
                    ->where("{$key}.value = '\"{$value}\"'");
            }
        }

        return $this->getDb()->select($select)->fetchAll();
    }


}
