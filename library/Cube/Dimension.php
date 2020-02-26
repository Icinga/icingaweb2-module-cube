<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube;

/**
 * Dimension interface
 *
 * All available dimensions must implement this interface
 *
 * @package Icinga\Module\Cube
 */
interface Dimension
{
    /**
     * The name of this dimenstion
     *
     * @return string
     */
    public function getName();

    /**
     * Column expression
     *
     * This is the expression used to fetch the related column. Usually an SQL
     * snippet when a relational database is involved
     *
     * @param Cube $cube
     *
     * @return string
     */
    public function getColumnExpression(Cube $cube);

    /**
     * Add this dimension to a cube
     *
     * This allows your dimension to apply itself to the Cube. That way your
     * dimension is able to join optional tables and more
     *
     * @param Cube $cube
     * @return void
     */
    public function addToCube(Cube $cube);
}
