<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

$this->menuSection(N_('Reporting'), ['icon' => 'fa-chart-simple', 'priority' => 100])
    ->add($this->translate('Cube'))->setUrl('cube/hosts')->setPriority(10);
