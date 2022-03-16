<?php
// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use ipl\Orm\Query;
use ipl\Sql\Select;

abstract class IcingaDbCube extends Cube
{
    use Auth;
    use Database;

    /** @var Query The inner query fetching all required data */
    protected $innerQuery;

    /** @var Select The rollup query, creating grouped sums over innerQuery */
    protected $rollupQuery;

    /** @var Select The outer query, orders respecting NULL values, rollup first */
    protected $fullQuery;

    protected $objectsFilter;

    abstract public function getObjectsFilter();
    /**
     * An IcingaDbCube must provide a list of all available columns
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

    /**
     * Get our inner query
     *
     * Hint: mostly used to get rid of NULL values
     *
     * @return Query
     */
    public function innerQuery()
    {
        if ($this->innerQuery === null) {
            $this->innerQuery = $this->prepareInnerQuery();
        }

        return $this->innerQuery;
    }

    /**
     * Get our rollup query
     *
     * @return Select
     */
    protected function rollupQuery()
    {
        if ($this->rollupQuery === null) {
            $this->rollupQuery = $this->prepareRollupQuery();
        }

        return $this->rollupQuery;
    }

    /**
     * Add a specific named dimension
     *
     * @param string $name
     * @return $this
     */
    public function addDimensionByName($name)
    {
        $this->addDimension($this->createDimension($name));

        return $this;
    }

    /**
     * We first prepare the queries and to finalize it later on
     *
     * This way dimensions can be added one by one, they will be allowed to
     * optionally join additional tables or apply other modifications late
     * in the process
     *
     * @return void
     */
    protected function finalizeInnerQuery()
    {
        $query = $this->innerQuery()->getSelectBase();
        $columns = [];
        foreach ($this->dimensions as $name => $dimension) {
            $quotedDimension = $this->getDb()->quoteIdentifier([$name]);
            $dimension->addToCube($this);
            $columns[$quotedDimension] = $dimension->getColumnExpression($this);

            if ($this->hasSlice($name)) {
                $query->where(
                    $dimension->getColumnExpression($this) . ' = ?',
                    $this->slices[$name]
                );
            } else {
                $columns[$quotedDimension] = $dimension->getColumnExpression($this);
            }
        }

        $query->columns($columns);
    }

    protected function prepareRollupQuery()
    {
        $dimensions = $this->listDimensions();
        $this->finalizeInnerQuery();

        $columns = [];
        $groupBy = [];
        foreach ($dimensions as $name => $dimension) {
            $quotedDimension = $this->getDb()->quoteIdentifier([$name]);

            $columns[$quotedDimension] = 'f.' . $quotedDimension;
            $groupBy[] = $quotedDimension;
        }

        $availableFacts = $this->getAvailableFactColumns();

        foreach ($this->chosenFacts as $alias) {
            $columns[$alias] = 'SUM(f.' . $availableFacts[$alias] . ')';
        }

        if (! empty($groupBy)) {
            $groupBy[count($groupBy) - 1] .= ' WITH ROLLUP';
        }

        $rollupQuery = new Select();
        $rollupQuery->from(['f' => $this->innerQuery()->assembleSelect()])
            ->columns($columns)
            ->groupBy($groupBy);

        return $rollupQuery;
    }

    protected function prepareFullQuery()
    {
        $rollupQuery = $this->rollupQuery();
        $columns = [];
        foreach ($this->listColumns() as $column) {
            $quotedColumn = $this->getDb()->quoteIdentifier([$column]);
            $columns[$quotedColumn] = 'rollup.' . $this->getDb()->quoteIdentifier([$column]);
        }

        $fullQuery = new Select();
        $fullQuery->from(['rollup' => $rollupQuery])->columns($columns);

        foreach ($columns as $quotedColumn => $_) {
            $fullQuery->orderBy("($quotedColumn IS NOT NULL)");
            $fullQuery->orderBy($quotedColumn);
        }

        return $fullQuery;
    }

    /**
     * Lazy-load our full query
     *
     * @return Select
     */
    protected function fullQuery()
    {
        if ($this->fullQuery === null) {
            $this->fullQuery = $this->prepareFullQuery();
        }

        return $this->fullQuery;
    }

    public function fetchAll()
    {
        $query = $this->fullQuery();
        return $this->getDb()->fetchAll($query);
    }
}
