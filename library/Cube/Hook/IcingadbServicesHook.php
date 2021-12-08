<?php

namespace Icinga\Module\Cube\Hook;

abstract class IcingadbServicesHook implements Dimensions
{
    abstract public function getServiceStateQuery();
}
