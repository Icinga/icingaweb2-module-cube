<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cube\IcingaDb;

use ipl\Stdlib\Filter\All;
use ipl\Stdlib\Filter\Equal;
use ipl\Stdlib\Filter\Rule;

final class FilterUtil
{
    /**
     * Whether an unconditional equality in the filter already restricts this column to the given value.
     *
     * Do not descend into OR filters: matching one alternative does not imply that the whole filter
     * already constrains the selected dimension to that value.
     *
     * @param mixed $value
     */
    public static function containsEqual(Rule $filter, string $column, $value): bool
    {
        if ($filter instanceof Equal) {
            return $filter->getColumn() === $column && $filter->getValue() === $value;
        }

        if ($filter instanceof All) {
            foreach ($filter as $rule) {
                if (self::containsEqual($rule, $column, $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
