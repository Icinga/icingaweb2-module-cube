<?php

namespace Icinga\Module\Cube;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Cube\Common\IcingaDb;
use Icinga\Module\Icingadb\Model\Service;

class ServiceDbQuery
{

    use IcingaDb;

    /**
     * @param $urlDimensions
     * @param null $slices
     * @return \Generator
     * @throws ConfigurationError
     */
    public function getResult($urlDimensions, $slices = null)
    {
        $query = Service::on($this->getDb())
            ->with('state');

        $query->disableDefaultSort();

        $columns = [];

        foreach ($urlDimensions as $dimension) {
            $dimensionJunction = $dimension . '_junction';

            $query->getSelectBase()
                ->join(
                    "service_customvar {$dimensionJunction}",
                    "{$dimensionJunction}.service_id = service.id"
                )
                ->join(
                    "customvar {$dimension}",
                    "{$dimension}.id = {$dimensionJunction}.customvar_id 
                    AND {$dimension}.name = \"{$dimension}\""
                );

            $columns[$dimension] = $dimension . '.value';
        }

        $groupByValues = $columns;
        $columns['cnt'] = 'SUM(1)';
        $columns['count_ok'] = 'SUM(CASE WHEN service_state.soft_state = 0 THEN  1 ELSE 0 END)';
        $columns['count_warning'] = 'SUM(CASE WHEN service_state.soft_state = 1 THEN  1 ELSE 0 END)';
        $columns['count_warning_unhandled'] = 'SUM(CASE WHEN service_state.soft_state = 1 
        AND service_state.is_handled = "n" THEN  1 ELSE 0 END)';
        $columns['count_critical'] = 'SUM(CASE WHEN service_state.soft_state = 2 THEN  1 ELSE 0 END)';
        $columns['count_critical_unhandled'] = 'SUM(CASE WHEN service_state.soft_state = 2 
        AND service_state.is_handled = "n" THEN  1 ELSE 0 END)';
        $columns['count_unknown'] = 'SUM(CASE WHEN service_state.soft_state = 3 THEN  1 ELSE 0 END)';
        $columns['count_unknown_unhandled'] = 'SUM(CASE WHEN service_state.soft_state = 3 
        AND service_state.is_handled = "n" THEN  1 ELSE 0 END)';

        // dimension is the last key here
        if (isset($dimension)) {
            $groupByValues[$dimension] .= ' WITH ROLLUP';
        }

        if (!empty($slices)) {
            foreach ($slices as $key => $value) {
                $query->getSelectBase()
                    ->where("{$key}.value = '\"{$value}\"'");
            }
        }

        $query->getSelectBase()->columns($columns);

        if (! empty($groupByValues)) {
            $query->getSelectBase()
                ->groupBy($groupByValues);
        }

        foreach ($query as $row) {
            yield $row;
        }
    }
}
