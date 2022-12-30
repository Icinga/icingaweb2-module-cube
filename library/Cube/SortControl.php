<?php

namespace Icinga\Module\Cube;

use ipl\Html\Form;
use ipl\Html\FormDecorator\DivDecorator;
use ipl\Html\FormElement\ButtonElement;
use ipl\Html\HtmlElement;
use ipl\Orm\Common\SortUtil;
use ipl\Orm\Query;
use ipl\Stdlib\Str;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;

/**
 * Allows to adjust the order of the items to display
 */
class SortControl extends Form
{
    /** @var string Default sort param */
    const DEFAULT_SORT_PARAM = 'sort';

    protected $defaultAttributes = ['class' => 'sort-control'];

    /** @var string Name of the URL parameter which stores the sort column */
    protected $sortParam = self::DEFAULT_SORT_PARAM;

    /** @var Url Request URL */
    protected $url;

    /** @var array Possible sort columns as sort string-value pairs */
    private $columns;

    /** @var string Default sort string */
    private $default;

    protected $method = 'GET';

    /**
     * Create a new sort control
     *
     * @param Url $url Request URL
     */
    public function __construct(Url $url)
    {
        $this->url = $url;
    }

    /**
     * Create a new sort control with the given options
     *
     * @param array<string,string> $options A sort spec to label map
     *
     * @return static
     */
    public static function create(array $options)
    {
        $normalized = [];
        foreach ($options as $spec => $label) {
            $normalized[SortUtil::normalizeSortSpec($spec)] = $label;
        }

        return (new static(Url::fromRequest()))
            ->setColumns($normalized);
    }

    /**
     * Get the possible sort columns
     *
     * @return array Sort string-value pairs
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Set the possible sort columns
     *
     * @param array $columns Sort string-value pairs
     *
     * @return $this
     */
    public function setColumns(array $columns)
    {
        // We're working with lowercase keys throughout the sort control
        $this->columns = array_change_key_case($columns, CASE_LOWER);

        return $this;
    }

    /**
     * Get the default sort string
     *
     * @return string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Set the default sort string
     *
     * @param array|string $default
     *
     * @return $this
     */
    public function setDefault($default)
    {
        // We're working with lowercase keys throughout the sort control
        $this->default = strtolower($default);

        return $this;
    }

    /**
     * Get the name of the URL parameter which stores the sort
     *
     * @return string
     */
    public function getSortParam()
    {
        return $this->sortParam;
    }

    /**
     * Set the name of the URL parameter which stores the sort
     *
     * @param string $sortParam
     *
     * @return $this
     */
    public function setSortParam($sortParam)
    {
        $this->sortParam = $sortParam;

        return $this;
    }

    /**
     * Get the sort string
     *
     * @return string|null
     */
    public function getSort()
    {
        $sort = $this->url->getParam($this->getSortParam(), $this->getDefault());

        if (! empty($sort)) {
            $columns = $this->getColumns();

            if (! isset($columns[$sort])) {
                // Choose sort string based on the first closest match
                foreach (array_keys($columns) as $key) {
                    if (Str::startsWith($key, $sort)) {
                        $sort = $key;

                        break;
                    }
                }
            }
        }

        return $sort;
    }

    /**
     * Sort the given query according to the request
     *
     * @param Query $query
     *
     * @return $this
     */
    public function apply(Query $query)
    {
        $default = (array) $query->getModel()->getDefaultSort();
        if (! empty($default)) {
            $this->setDefault(SortUtil::normalizeSortSpec($default));
        }

        $sort = $this->getSort();
        if (! empty($sort)) {
            $query->orderBy(SortUtil::createOrderBy($sort));
        }

        return $this;
    }

    protected function assemble()
    {
        $columns = $this->getColumns();
        $sort = $this->getSort();

        if (empty($sort)) {
            reset($columns);
            $sort = key($columns);
        }

        $sort = explode(',', $sort, 2);
        list($column, $direction) = Str::symmetricSplit(array_shift($sort), ' ', 2);

        if (! $direction || strtolower($direction) === 'asc') {
            $toggleIcon = 'sort-alpha-down';
            $toggleDirection = 'desc';
        } else {
            $toggleIcon = 'sort-alpha-down-alt';
            $toggleDirection = 'asc';
        }

        if ($direction !== null) {
            $value = implode(',', array_merge(["{$column} {$direction}"], $sort));
            if (! isset($columns[$value])) {
                foreach ([$column, "{$column} {$toggleDirection}"] as $key) {
                    $key = implode(',', array_merge([$key], $sort));
                    if (isset($columns[$key])) {
                        $columns[$value] = $columns[$key];
                        unset($columns[$key]);

                        break;
                    }
                }
            }
        } else {
            $value = implode(',', array_merge([$column], $sort));
        }

        if (! isset($columns[$value])) {
            $columns[$value] = 'Custom';
        }

        $this->addElement('select', $this->getSortParam(), [
            'class'   => 'autosubmit',
            'label'   => 'Sort By',
            'options' => $columns,
            'value'   => $value
        ]);
        $select = $this->getElement($this->getSortParam());
        (new DivDecorator())->decorate($select);

        // Apply Icinga Web 2 style, for now
        $select->prependWrapper(HtmlElement::create('div', ['class' => 'icinga-controls']));

        $toggleButton = new ButtonElement($this->getSortParam(), [
            'class' => 'control-button spinner',
            'title' => t('Change sort direction'),
            'type'  => 'submit',
            'value' => implode(',', array_merge(["{$column} {$toggleDirection}"], $sort))
        ]);
        $toggleButton->add(new Icon($toggleIcon));

        $this->addElement($toggleButton);
    }
}
