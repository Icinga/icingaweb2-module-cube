<?php

// Icinga Web 2 Cube Module | (c) 2021 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Ido\DataView;

use Icinga\Data\ConnectionInterface;
use Icinga\Module\Cube\Ido\Query\HoststatusQuery;

class Hoststatus extends \Icinga\Module\Monitoring\DataView\Hoststatus
{
    public function __construct(ConnectionInterface $connection, ?array $columns = null)
    {
        $this->connection = $connection;
        $this->query = new HoststatusQuery($connection->getResource(), $columns);
    }
}
