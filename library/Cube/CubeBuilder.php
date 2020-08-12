<?php

namespace Icinga\Module\Cube;

use ipl\Html\Html;
use RecursiveIteratorIterator;
use SplStack;

class CubeBuilder extends RecursiveIteratorIterator
{
    protected $stack;

    /**
     * @var  BaseCube
     */
    protected $cube;

    /**
     * @var  bool
     */
    protected $isLoopBeginning = false;

    /**
     * CubeBuilder constructor.
     *
     * @param iterable $data Rows from database
     *
     * @param BaseCube $cube Contains dimensions, slices and related methods
     */
    public function __construct($data, BaseCube $cube)
    {
        $this->cube = $cube;

        parent::__construct(
            (new RollupIterator($data, $cube)),
            static::CHILD_FIRST | static::LEAVES_ONLY
        );
    }

    /**
     * @param integer $index
     *
     * @return string dimension at given index if exists, otherwise dimension at 0 index
     */
    public function getDimension(int $index)
    {
        if (array_key_exists($index, $this->getDimensions())) {
            return $this->getDimensions()[$index];
        }

        return $this->getDimensions()[0];
    }

    /**
     * @return array all dimensions, excluded slices
     */
    public function getDimensions()
    {
        return $this->cube->getDimensionsWithoutSliceValues();
    }

    public function beginIteration()
    {
        $this->stack = new SplStack();
        $this->stack->push($this->cube);
        $this->isLoopBeginning = true;
    }

    /**
     * {@inheritdoc}
     */
    public function beginChildren()
    {
        if ($this->isLoopBeginning) {
            $this->isLoopBeginning = false;
        }
        else {
            $this->stack->push(
                $this->cube->renderDimension(
                    // value of parent node
                    $this->getSubIterator($this->getDepth() - 1)->current()->getValue(),
                    $this->getDimension($this->getDepth() - 2),
                    count($this->getDimensions()) - $this->getDepth()
                )
            );
        }
    }

    public function endChildren()
    {
        if ($this->stack->count() > 1) {
            $childEl = $this->stack->pop();
            $this->stack->top()->add($childEl);
        }
    }

    /**
     * build the cube
     *
     * loop only render measures
     *
     * CubeBuilder::beginChildren() render the dimensions
     *
     * @param BaseCube $cube
     *
     */
    public static function build(BaseCube $cube)
    {
        $builder = new static($cube->getData(), $cube);

        foreach($builder as $item) {
            if ($builder->getInnerIterator()->hasChildren()) {
                continue;
            }

           $builder->stack->top()->add(
               $builder->cube->renderMeasure($item->getValue(), $builder->getDimension($builder->getDepth() - 1))
           );
        }
    }
}
