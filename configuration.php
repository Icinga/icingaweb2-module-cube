<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

$section = $this->menuSection(N_('Reporting'));
$section->add($this->translate('Cube'))->setUrl('cube/hosts')->setPriority(10);
