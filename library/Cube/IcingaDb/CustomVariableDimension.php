<?php

// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;

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
        $sourceTable = $innerQuery->getModel()->getTableName();

        $subQuery = $innerQuery->createSubQuery(new CustomvarFlat(), $sourceTable . '.vars');
        $subQuery->getSelectBase()->resetWhere(); // The link to the outer query is the ON condition
        $subQuery->columns(['flatvalue', 'object_id' => $sourceTable . '.id']);
        $subQuery->filter(Filter::like('flatname', $name));

        // Values might not be unique (wildcard dimensions)
        $subQuery->getSelectBase()->groupBy([
            $subQuery->getResolver()->getAlias($subQuery->getModel()) . '.flatvalue',
            'object_id'
        ]);

        $subQueryAlias = $cube->getDb()->quoteIdentifier(['c_' . $name]);
        $innerQuery->getSelectBase()->groupBy($subQueryAlias . '.flatvalue');
        $innerQuery->getSelectBase()->join(
            [$subQueryAlias => $subQuery->assembleSelect()],
            [$subQueryAlias . '.object_id = ' . $innerQuery->getResolver()->getAlias($innerQuery->getModel()) . '.id']
        );
    }
}
