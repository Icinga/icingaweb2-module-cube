<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
