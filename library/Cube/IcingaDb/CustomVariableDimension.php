<?php
// Icinga DB Web | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use ipl\Orm\Model;
use ipl\Sql\Expression;

class CustomVariableDimension implements Dimension
{
    protected $name;

    protected $label;

    protected $wantNull = false;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLabel()
    {
        return $this->label ?: $this->getName();
    }

    /**
     * Set the label
     *
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Add a label
     *
     * @param string $label
     * @return $this
     */
    public function addLabel($label)
    {
        if ($this->label === null) {
            $this->setLabel($label);
        } else {
            $this->label .= ' & ' . $label;
        }

        return $this;
    }

    /**
     * Define whether null values should be shown
     *
     * @param bool $wantNull
     * @return $this
     */
    public function wantNull($wantNull = true)
    {
        $this->wantNull = $wantNull;

        return $this;
    }

    /**
     * @param IcingaDbCube $cube
     * @return Expression|string
     */
    public function getColumnExpression(Cube $cube)
    {
        $expression = $cube->getDb()->quoteIdentifier(['c_' . $this->getName(), 'flatvalue']);

        if ($this->wantNull) {
            return new Expression("COALESCE($expression, '-')");
        }

        return $expression;
    }

    public function addToCube(Cube $cube)
    {
        /** @var IcingaDbCube $cube */
        $name = $this->getName();
        $innerQuery = $cube->innerQuery();
        $resolver = $innerQuery->getResolver();
        $sourceTable = $innerQuery->getModel()->getTableName();

        foreach ($resolver->resolveRelations($sourceTable . '.vars') as $relation) {
            foreach ($relation->resolve() as list($source, $target, $relatedKeys)) {
                /** @var Model $source */
                /** @var Model $target */

                $sourceAlias = $resolver->getAlias($source);
                if ($sourceAlias !== $resolver->getAlias($innerQuery->getModel())) {
                    $sourceAlias = $cube->getDb()->quoteIdentifier(
                        [$sourceAlias . '_' . $name]
                    );
                }

                if ($target instanceof CustomvarFlat) {
                    $targetAlias = $cube->getDb()->quoteIdentifier(['c_' . $name]);
                } else {
                    $targetAlias = $cube->getDb()->quoteIdentifier(
                        [$resolver->getAlias($target) . '_' . $name]
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
                    $innerQuery->getSelectBase()->groupBy("$targetAlias.flatvalue");
                    $conditions[sprintf('%s = ?', $resolver->qualifyColumn('flatname', $targetAlias))] = $name;
                }

                $table = [$targetAlias => $target->getTableName()];
                $innerQuery->getSelectBase()->join($table, $conditions);
            }
        }
    }
}
