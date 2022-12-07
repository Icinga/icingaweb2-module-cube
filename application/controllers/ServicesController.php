<?php

// Icinga Web 2 Cube Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Controllers;

use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\IcingaDb\IcingaDbServiceStatusCube;
use Icinga\Module\Cube\Web\Controller;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Web\Control\SearchBar\ObjectSuggestions;

class ServicesController extends Controller
{
    public function indexAction(): void
    {
        $this->createTabs()->activate('cube/services');

        $this->renderCube();
    }

    protected function getCube(): IcingaDbCube
    {
        return new IcingaDbServiceStatusCube();
    }

    public function completeAction(): void
    {
        $suggestions = new ObjectSuggestions();
        $suggestions->setModel(Service::class);
        $suggestions->forRequest($this->getServerRequest());
        $this->getDocument()->add($suggestions);
    }

    public function searchEditorAction(): void
    {
        $editor = $this->createSearchEditor(
            Service::on($this->getDb()),
            $this->preserveParams
        );

        $this->getDocument()->add($editor);
        $this->setTitle($this->translate('Adjust Filter'));
    }
}
