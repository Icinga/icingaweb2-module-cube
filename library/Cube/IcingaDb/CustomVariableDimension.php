<?php

// Icinga Web 2 Cube Module | (c) 2022 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\IcingaDb;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Dimension;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;

class CustomVariableDimension implements Dimension
{
    use Auth;

    /** @var string Prefix for host custom variable */
    public const HOST_PREFIX = 'host.vars.';

    /** @var string Prefix for service custom variable */
    public const SERVICE_PREFIX = 'service.vars.';

    /** @var ?string variable source name */
    protected $sourceName;

    /** @var ?string Variable name without prefix */
    protected $varName;

    /** @var string Variable name with prefix */
    protected $name;

    protected $label;

    protected $wantNull = false;

    public function __construct($name)
    {
        if (preg_match('/^(host|service)\.vars\.(.*)/', $name, $matches)) {
            $this->sourceName = $matches[1];
            $this->varName = $matches[2];
        }

        $this->name = $name;
    }

    /**
     * Get the variable name without prefix
     *
     * @return string
     */
    public function getVarName(): string
    {
        return $this->varName ?? $this->getName();
    }

    /**
     * Get the variable source name
     *
     * @return ?string
     */
    public function getSourceName(): ?string
    {
        return $this->sourceName;
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
        $expression = $cube->getDb()->quoteIdentifier([$this->createCustomVarAlias(), 'flatvalue']);

        if ($this->wantNull) {
            return new Expression("COALESCE($expression, '-')");
        }

        return $expression;
    }

    public function addToCube(Cube $cube)
    {
        /** @var IcingaDbCube $cube */
        $innerQuery = $cube->innerQuery();
        $sourceTable = $this->getSourceName() ?? $innerQuery->getModel()->getTableName();

        $subQuery = $innerQuery->createSubQuery(new CustomvarFlat(), $sourceTable . '.vars');
        $subQuery->getSelectBase()->resetWhere(); // The link to the outer query is the ON condition
        $subQuery->columns(['flatvalue', 'object_id' => $sourceTable . '.id']);
        $subQuery->filter(Filter::like('flatname', $this->getVarName()));

        // Values might not be unique (wildcard dimensions)
        $subQuery->getSelectBase()->groupBy([
            $subQuery->getResolver()->getAlias($subQuery->getModel()) . '.flatvalue',
            'object_id'
        ]);

        $this->applyRestrictions($subQuery);

        $subQueryAlias = $cube->getDb()->quoteIdentifier([$this->createCustomVarAlias()]);
        $innerQuery->getSelectBase()->groupBy($subQueryAlias . '.flatvalue');

        $sourceIdPath = '.id';
        if ($innerQuery->getModel() instanceof Service && $sourceTable === 'host') {
            $sourceIdPath = '.host_id';
        }

        $innerQuery->getSelectBase()->join(
            [$subQueryAlias => $subQuery->assembleSelect()],
            [
                $subQueryAlias . '.object_id = '
                . $innerQuery->getResolver()->getAlias($innerQuery->getModel()) . $sourceIdPath
            ]
        );
    }

    protected function createCustomVarAlias(): string
    {
        return implode('_', ['c', $this->getSourceName(), $this->getVarName()]);
    }
}
