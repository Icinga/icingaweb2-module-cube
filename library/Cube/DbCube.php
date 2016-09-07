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

    protected $dbName;

    abstract public function prepareInnerQuery();

    public function setConnection($connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        return $this;
    }

    public function fetchAll()
    {
        $query = $this->fullQuery();
        return $this->db()->fetchAll($query);
    }

    public function setDbName($name)
    {
        $this->dbName = $name;
        return $this;
    }

    public function tableName($name)
    {
        if ($this->dbName === null) {
            return $name;
        } else {
            return $this->dbName . '.' . $name;
        }
    }

    public function getDbName()
    {
        return $this->dbName;
    }

    // Used to get rid of NULL values
    public function innerQuery()
    {
        if ($this->innerQuery === null) {
            $this->innerQuery = $this->prepareInnerQuery();

        }

        return $this->innerQuery;
    }

    protected function finalizeInnerQuery()
    {
        $query = $this->innerQuery();
        $columns = array();
        foreach ($this->dimensions as $name => $dimension) {
            $dimension->addToCube($this);
            if ($this->hasSlice($name)) {
                $query->where(
                    $dimension->getColumnExpression() . ' = ?',
                    $this->slices[$name]
                );
            } else {
                $columns[$name] = $dimension->getColumnExpression();
            }
        }

        $query->columns($columns + $this->factColumns);
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

        $select = $this->db()->select()->from(
            array($alias => $this->rollupQuery()),
            $columns
        );

        foreach ($columns as $col) {
            $select->order('(' . $col . ' IS NOT NULL)');
            $select->order($col);
        }

        return $select;
    }

    public function db()
    {
        return $this->db;
    }

    // Do the rollup
    protected function prepareRollupQuery()
    {
        $alias = 'sub';

        $dimensions = $this->listDimensions();
        $this->finalizeInnerQuery();
        foreach ($dimensions as $dimension) {
            $columns[$dimension] = $alias . '.' . $dimension;
        }
        
        foreach ($this->listFacts() as $fact) {
            $columns[$fact] = 'SUM(' . $fact . ')';
        }

        $select = $this->db()->select()->from(
            array($alias => $this->innerQuery()),
            $columns
        )->group('(' . implode('), (', $dimensions) . ') WITH ROLLUP');

        return $select;
    }
}
