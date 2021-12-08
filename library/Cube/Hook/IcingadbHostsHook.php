<?php

namespace Icinga\Module\Cube\Hook;

abstract class IcingadbHostsHook implements Dimensions
{
    abstract public function getHostStateQuery();

    /**
     * @param array $slices
     */
    abstract public function getHostNames($slices);
}
