<?php
namespace Icinga\Module\Cube\Common;

use Icinga\Web\Widget\Tab;
use ipl\Web\Url;

trait AddTabs
{
    public function addTabs($active)
    {
        $this->getTabs()->add('hosts', new Tab([
            'title' => 'hosts',
            'url' => Url::fromPath('cube/icingadb/hosts'),
        ]));

        $this->getTabs()->add('services', new Tab([
            'title' => 'services',
            'url' => Url::fromPath('cube/icingadb/services'),
        ]));


        $this->getTabs()->activate($active);
    }
}
