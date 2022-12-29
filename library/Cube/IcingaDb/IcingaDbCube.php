<?php

// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Orm\Common\SortUtil;
use ipl\Orm\Query;
use ipl\Sql\Adapter\Pgsql;
use ipl\Sql\Expression;
use ipl\Sql\Select;

abstract class IcingaDbCube extends Cube
{
    use Auth;
    use Database;

    public const SLICE_PREFIX = 'slice.';
    public const IS_USING_ICINGADB = true;

    /** @var bool Whether to show problems only */
    protected $problemsOnly = false;

    /** @var string Sort param used to sort dimensions by value */
    public const DIMENSION_VALUE_SORT_PARAM = 'value';

    /** @var string Sort param used to sort dimensions by severity */
    public const DIMENSION_SEVERITY_SORT_PARAM = 'severity';

    /** @var Query The inner query fetching all required data */
    protected $innerQuery;

    /** @var Select The rollup query, creating grouped sums over innerQuery */
    protected $rollupQuery;

    /** @var Select The outer query, orders respecting NULL values, rollup first */
    protected $fullQuery;

    protected $objectsFilter;

    /** @var array The sort order of dimensions, column as key and direction as value */
    protected $sortBy;

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
     * Set whether to show problems only
     *
     * @param bool $problemOnly
     *
     * @return $this
     */
    public function problemsOnly(bool $problemOnly = true): self
    {
        $this->problemsOnly = $problemOnly;

        return $this;
    }


    /**
     * Get whether to show problems only
     *
     * @return bool
     */
    public function isProblemsOnly(): bool
    {
        return $this->problemsOnly;
    }

    /**
     * Fetch the host variable dimensions
     *
     * @return array
     */
    public function fetchHostVariableDimensions(): array
    {
        $query = Host::on($this->getDb())
            ->with('customvar_flat')
            ->columns('customvar_flat.flatname')
            ->orderBy('customvar_flat.flatname');

        $this->applyRestrictions($query);

        $query->getSelectBase()->groupBy('flatname');

        $dimensions = [];
        foreach ($query as $row) {
            // Replaces array index notations with [*] to get results for arbitrary indexes
            $name = preg_replace('/\\[\d+](?=\\.|$)/', '[*]', $row->customvar_flat->flatname);
            $name = strtolower($name);
            $dimensions[CustomVariableDimension::HOST_PREFIX . $name] = 'Host ' . $name;
        }

        return $dimensions;
    }

    /**
     * Fetch the service variable dimensions
     *
     * @return array
     */
    public function fetchServiceVariableDimensions(): array
    {
        $query = Service::on($this->getDb())
            ->with('customvar_flat')
            ->columns('customvar_flat.flatname')
            ->orderBy('customvar_flat.flatname');

        $this->applyRestrictions($query);

        $query->getSelectBase()->groupBy('flatname');

        $dimensions = [];
        foreach ($query as $row) {
            // Replaces array index notations with [*] to get results for arbitrary indexes
            $name = preg_replace('/\\[\d+](?=\\.|$)/', '[*]', $row->customvar_flat->flatname);
            $name = strtolower($name);
            $dimensions[CustomVariableDimension::SERVICE_PREFIX . $name] = 'Service ' . $name;
        }

        return $dimensions;
    }

    /**
     * Set sort by columns
     *
     * @param ?string $sortBy
     *
     * @return $this
     */
    public function sortBy(?string $sortBy): self
    {
        if (empty($sortBy)) {
            return $this;
        }

        $this->sortBy = SortUtil::createOrderBy($sortBy)[0];

        return $this;
    }

    /**
     * Get sort by columns
     *
     * @return ?array Column as key and direction as value
     */
    public function getSortBy(): ?array
    {
        return $this->sortBy;
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
            $columns[$alias] = new Expression('SUM(f.' . $availableFacts[$alias] . ')');
        }

        if (! empty($groupBy)) {
            if ($this->getDb()->getAdapter() instanceof Pgsql) {
                $groupBy = 'ROLLUP(' . implode(', ', $groupBy) . ')';
            } else {
                $groupBy[count($groupBy) - 1] .= ' WITH ROLLUP';
            }
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
        $orderBy = [];
        $sortBy = $this->getSortBy();
        foreach ($this->listColumns() as $column) {
            $quotedColumn = $this->getDb()->quoteIdentifier([$column]);
            $columns[$quotedColumn] = 'rollup.' . $quotedColumn;

            if ($this->hasDimension($column)) {
                $orderBy["($quotedColumn IS NOT NULL)"] = null;

                $sortDir = 'ASC';
                if ($sortBy && self::DIMENSION_VALUE_SORT_PARAM === $sortBy[0]) {
                    $sortDir = $sortBy[1] ?? 'ASC';
                }

                $orderBy[$quotedColumn] = $sortDir;
            }
        }

        return (new Select())
            ->from(['rollup' => $rollupQuery])
            ->columns($columns)
            ->orderBy($orderBy);
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
