<?php

namespace Icinga\Module\Cube;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Cube\Common\IcingaDb;
use Icinga\Module\Icingadb\Model\Host;

class HostDbQuery
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
        $query = Host::on($this->getDb())
            ->with('state');

        $query->disableDefaultSort();

        $columns = [];

        foreach ($urlDimensions as $dimension) {
            $dimensionJunction = $dimension . '_junction';

            $query->getSelectBase()
                ->join(
                    "host_customvar {$dimensionJunction}",
                    "{$dimensionJunction}.host_id = host.id"
                )
                ->join(
                    "customvar {$dimension}",
                    "{$dimension}.id = {$dimensionJunction}.customvar_id 
                    AND {$dimension}.name = \"{$dimension}\""
                );

            $columns[$dimension] = $dimension . '.value';
        }

        $groupByValues = $columns;

        // dimension is the last key here
        $columns['cnt'] = 'SUM(1)';
        $columns['count_up'] = 'SUM(CASE WHEN host_state.soft_state = 0 THEN  1 ELSE 0 END)';
        $columns['count_down'] = 'SUM(CASE WHEN host_state.soft_state = 1 THEN  1 ELSE 0 END)';
        $columns['count_down_unhandled'] = 'SUM(CASE WHEN host_state.soft_state = 1 
        AND host_state.is_handled = "n" THEN  1 ELSE 0 END)';

        if (! empty($slices)) {
            foreach ($slices as $key => $value) {
                $query->getSelectBase()
                    ->where("{$key}.value = '\"{$value}\"'");
            }
        }

        if (isset($dimension)) {
            $groupByValues[$dimension] .= ' WITH ROLLUP';
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

    /**
     * @param $slices $row->name
     * @return \Generator
     * @throws ConfigurationError
     */
    public function getHostNames($slices)
    {

        $query = Host::on($this->getDb());

        foreach ($slices as $dimension => $value) {
            $dimensionJunction = $dimension . '_junction';

            $query->getSelectBase()
                ->join(
                    "host_customvar {$dimensionJunction}",
                    "{$dimensionJunction}.host_id = h.id"
                )
                ->join(
                    "customvar {$dimension}",
                    "{$dimension}.id = {$dimensionJunction}.customvar_id 
                    AND {$dimension}.name = \"{$dimension}\""
                );
        }

        foreach ($slices as $dimension => $value) {
            $query->getSelectBase()
                ->where("{$dimension}.value = '\"{$value}\"'");
        }

        foreach ($query as $row) {
            yield $row->name;
        }
    }
}
