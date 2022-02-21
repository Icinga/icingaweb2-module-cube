<?php
// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use ipl\Orm\Model;
use ipl\Orm\Query;
use ipl\Sql\Select;

abstract class IcingaDbCube extends Cube
{
    use Auth;
    use Database;

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

    public function fetchAll()
    {
        $innerQuery = $this->prepareInnerQuery();
        $resolver = $innerQuery->getResolver();
        $dimensions = $this->listDimensions();
        $sourceTable = $innerQuery->getModel()->getTableName();

        $select = $innerQuery->assembleSelect();

        // TODO: $dimension->addToCube($this)
        foreach ($resolver->resolveRelations($sourceTable . '.vars') as $relation) {
            foreach ($dimensions as $dimension => $_) {
                $quotedDimension = $this->getDb()->quoteIdentifier([$dimension]);
                foreach ($relation->resolve() as list($source, $target, $relatedKeys)) {
                    /** @var Model $source */
                    /** @var Model $target */

                    $sourceAlias = $resolver->getAlias($source);
                    if ($sourceAlias !== $resolver->getAlias($innerQuery->getModel())) {
                        $sourceAlias = $this->getDb()->quoteIdentifier(
                            [$sourceAlias . '_' . $dimension]
                        );
                    }

                    if ($target instanceof CustomvarFlat) {
                        $targetAlias = $this->getDb()->quoteIdentifier(['c_' . $dimension]);
                    } else {
                        $targetAlias = $this->getDb()->quoteIdentifier(
                            [$resolver->getAlias($target) . '_' . $dimension]
                        );
                    }

                    $conditions = [];
                    foreach ($relatedKeys as $fk => $ck) {
                        $conditions[] = sprintf(
                            '%s = %s',
                            $resolver->qualifyColumn($fk, $targetAlias),
                            $resolver->qualifyColumn($ck, $sourceAlias)
                        );
                    }

                    if ($target instanceof CustomvarFlat) {
                        $select->groupBy("$targetAlias.flatvalue");
                        $select->columns([$quotedDimension => $targetAlias . '.flatvalue']); // TODO: $dimension->getColumnExpression($this)
                        $conditions[sprintf('%s = ?', $resolver->qualifyColumn('flatname', $targetAlias))] = $dimension;
                    }

                    $table = [$targetAlias => $target->getTableName()];
                    $select->join($table, $conditions);
                }

                $columns[$quotedDimension] = 'f.' . $quotedDimension;
                $groupBy[] = $quotedDimension;
            }
        }

        // TODO: Should be performed by CustomVarDimension::addToCube()
        foreach ($resolver->resolveRelations($sourceTable . '.vars') as $relation) {
            foreach ($this->listSlices() as $dimension) {
                foreach ($relation->resolve() as list($source, $target, $relatedKeys)) {
                    /** @var Model $source */
                    /** @var Model $target */

                    $sourceAlias = $resolver->getAlias($source);
                    if ($sourceAlias !== $resolver->getAlias($innerQuery->getModel())) {
                        $sourceAlias = $this->getDb()->quoteIdentifier(
                            [$sourceAlias . '_' . $dimension]
                        );
                    }

                    if ($target instanceof CustomvarFlat) {
                        $targetAlias = $this->getDb()->quoteIdentifier(['c_' . $dimension]);
                    } else {
                        $targetAlias = $this->getDb()->quoteIdentifier(
                            [$resolver->getAlias($target) . '_' . $dimension]
                        );
                    }

                    $conditions = [];
                    foreach ($relatedKeys as $fk => $ck) {
                        $conditions[] = sprintf(
                            '%s = %s',
                            $resolver->qualifyColumn($fk, $targetAlias),
                            $resolver->qualifyColumn($ck, $sourceAlias)
                        );
                    }

                    if ($target instanceof CustomvarFlat) {
                        $select->groupBy("$targetAlias.flatvalue");
                        $select->where(["$targetAlias.flatvalue = ?" => $this->slices[$dimension]]); // TODO: $dimension->getColumnExpression($this)
                        $conditions[sprintf('%s = ?', $resolver->qualifyColumn('flatname', $targetAlias))] = $dimension;
                    }

                    $table = [$targetAlias => $target->getTableName()];
                    $select->join($table, $conditions);
                }
            }
        }

        $groupBy[count($groupBy) - 1] .= ' WITH ROLLUP';

        $availableFacts = $this->getAvailableFactColumns();
        foreach ($this->chosenFacts as $alias) {
            $columns[$alias] = 'SUM(f.' . $availableFacts[$alias] . ')';
        }

        $outerQuery = new Select();
        $outerQuery->from(['f' => $select])->columns($columns)->groupBy($groupBy);



        $rollupColumns = [];
        foreach ($this->listColumns() as $column) {
            $quotedColumn = $this->getDb()->quoteIdentifier([$column]);
            $rollupColumns[$quotedColumn] = 'rollup.' . $quotedColumn;
        }

        $rollupQuery = new Select();
        $rollupQuery->from(['rollup' => $outerQuery])->columns($rollupColumns);

        foreach ($rollupColumns as $quotedColumn => $_) {
            $rollupQuery->orderBy("($quotedColumn IS NOT NULL)");
            $rollupQuery->orderBy($quotedColumn);
        }

        return $this->getDb()->fetchAll($rollupQuery);
    }
}
