<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\IcingaDb\IcingaDbHostStatusCube;
use Icinga\Module\Cube\Web\Controller;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;

class HostsController extends Controller
{
    public function indexAction(): void
    {
        $this->createTabs()->activate('cube/hosts');

        $this->renderCube();
    }

    protected function getCube(): IcingaDbCube
    {
        return new IcingaDbHostStatusCube();
    }

    public function completeAction(): void
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Host::class);
        $suggestions->forRequest($this->getServerRequest());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction(): void
    {
        $editor = $this->createSearchEditor(
            Host::on($this->getDb()),
            $this->preserveParams
        );

        $this->getDocument()->add($editor);
        $this->setTitle($this->translate('Adjust Filter'));
    }
}
