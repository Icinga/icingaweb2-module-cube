<?php

// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

$this->menuSection(N_('Reporting'), ['icon' => 'fa-chart-simple', 'priority' => 100])
    ->add($this->translate('Cube'))->setUrl('cube/hosts')->setPriority(10);
