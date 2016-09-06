<?php

namespace Icinga\Module\Cube;

use Icinga\Data\Db\DbConnection;

abstract class DbCube extends Cube
{
    protected $connection;

    protected $db;

    protected $innerQuery;

    protected $rollupQuery;

    protected $fullQuery;

    abstract public function prepareInnerQuery();

    public function fetchAll()
    {
        return $this->db->fetchAll($this->fullQuery());
    }

    // Used to get rid of NULL values
    protected function innerQuery()
    {
        if ($this->innerQuery === null) {
            $this->innerQuery = $this->prepareInnerQuery();
        }

        return $this->innerQuery;
    }

    protected function fullQuery()
    {
        if ($this->fullQuery === null) {
            $this->fullQuery = $this->prepareFullQuery();
        }

        return $this->fullQuery;
    }

    protected function rollupQuery()
    {
        if ($this->rollupQuery === null) {
            $this->rollupQuery = $this->prepareRollupQuery();
        }

        return $this->rollupQuery;
    }

    // Sorting
    protected function prepareFullQuery()
    {
        $alias = 'rollup';
        $cols = $this->listColumns();
        $columns = array();

        foreach ($cols as $col) {
            $columns[$col] = $alias . '.' . $col;
        }

        $select = $this->db->select()->from(
            array($alias => $this->rollupQuery()),
            $columns
        );

        foreach ($columns as $col) {
            $select->order('(' . $col . ' IS NOT NULL)');
            $select->order($col);
        }

        return $select;
    }

    // Do the rollup
    protected function prepareRollupQuery()
    {
        $alias = 'sub';

        $dimensions = $this->listDimensions();
        foreach ($dimensions as $dimension) {
            $columns[$dimension] = $alias . '.' . $dimension;
        }
        
        foreach ($this->listFacts() as $fact) {
            $columns[$fact] = 'SUM(' . $fact . ')';
        }

        $select = $this->db->select()->from(
            array($alias => $this->innerQuery()),
            $columns
        )->group('(' . implode('), (', $dimensions) . ') WITH ROLLUP');

        return $select;
    }
}
