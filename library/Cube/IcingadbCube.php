<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use ipl\Orm\Query;
use ipl\Sql\Select;

abstract class IcingadbCube extends Cube
{
    use Database;

    use Auth;
    /** @var Select The inner query fetching all required data */
    protected $innerQuery;

    /** @var Select The rollup query, creating grouped sums over innerQuery */
    protected $rollupQuery;

    /** @var Select The outer query, orders respecting NULL values, rollup first */
    protected $fullQuery;

    /** @var array Key/value array containing our chosen facts and the corresponding SQL expression */
    protected $factColumns = array();

    /**
     * A DbCube must provide a list of all available columns
     *
     * This is a key/value array with the key being the fact name / column alias
     * and
     *
     * @return array
     */
    abstract public function getAvailableFactColumns();

    /**
     * @return Query
     */
    abstract public function prepareInnerQuery();

    public function innerQuery()
    {
        if ($this->innerQuery === null) {
            $this->innerQuery = $this->prepareInnerQuery();
        }

        return $this->innerQuery;
    }

    public function finalizeQuery()
    {
        $query = $this->prepareInnerQuery();
        $columns = [];
        foreach ($this->dimensions as $name => $dimension) {
            $dimension->addToCube($this);
            if ($this->hasSlice($name)) {
                $query->getSelectBase()
                    ->where($dimension->getColumnExpression($this) . "= '\"{$this->slices[$name]}\"'");
            } else {
                $columns[$name] = "TRIM(BOTH '\"' FROM {$dimension->getColumnExpression($this)})";
            }
        }

        $c = [];

        foreach ($columns + $this->factColumns as $k => $v) {
            $c[$k] = $v;
        }

        return $query->getSelectBase()->columns($c)->groupBy($columns);
    }

    protected function rollupQuery()
    {
        if ($this->rollupQuery === null) {
            $this->rollupQuery = $this->prepareRollupQuery();
        }

        return $this->rollupQuery;
    }

    protected function prepareRollupQuery()
    {
        $alias = 'sub';

        $dimensions = array_map(function ($val) {
            return $val;
        }, $this->listDimensions());

        $this->finalizeQuery();
        $columns = array();
        foreach ($dimensions as $dimension) {
            $columns[$dimension] = $alias . '.' . $dimension;
        }

        foreach ($this->listFacts() as $fact) {
            $columns[$fact] = 'SUM(' . $fact . ')';
        }

        $rollupQuery = new Select();

        $rollupQuery->columns($columns)->from([$alias => $this->innerQuery()->assembleSelect()]);

        $rollupQuery->groupBy('(' . implode('), (', $dimensions) . ') WITH ROLLUP');
        return $rollupQuery;
    }

    protected function fullQuery()
    {
        if ($this->fullQuery === null) {
            $this->fullQuery = $this->prepareFullQuery();
        }

        return $this->fullQuery;
    }

    protected function prepareFullQuery()
    {
        $alias = 'rollup';
        $cols = $this->listColumns();

        $columns = [];

        foreach ($cols as $col) {
            $columns[$col] = $alias . '.' . $col;
        }

        $fullQuery = new Select();

        $fullQuery->columns($columns)->from([$alias => $this->rollupQuery()]);

        return $fullQuery;
    }

    /**
     * Choose a one or more facts
     *
     * This also initializes a fact column lookup array
     *
     * @param  array $facts
     * @return $this
     */
    public function chooseFacts(array $facts)
    {
        parent::chooseFacts($facts);

        $this->factColumns = array();
        $columns = $this->getAvailableFactColumns();
        foreach ($this->chosenFacts as $name) {
            $this->factColumns[$name] = $columns[$name];
        }

        return $this;
    }

    /**
     * Prepare the query and fetch all data
     *
     * @return array
     */
    public function fetchAll()
    {
        $query = $this->fullQuery();

        return $this->getDb()->select($query)->fetchAll();
    }
}
