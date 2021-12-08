<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Hook;

/**
 * Dimensions interface
 *
 *
 * @package Icinga\Module\Cube\Hook
 */
interface Dimensions
{
    /**
     * Available dimensions
     *
     * @return \Generator
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function listAvailableDimensions();

    /**
     * Fact Columns
     *
     * @return string[]
     */
    public function getAvailableFactColumns();
}
