<?php

namespace Icinga\Module\Cube\Hook;

use Icinga\Module\Cube\Cube;
use Icinga\Web\View;
use Icinga\Application\Hook;

abstract class ActionLinksHook
{
    abstract public function getHtml(View $view, Cube $cube);

    final public static function getAllHtml(View $view, Cube $cube)
    {
        $htm = [];

        foreach (Hook::all('Cube/ActionLinks') as $links) {
            $htm[] = $links->getHtml($view, $cube);
        }

        if (empty($htm)) {
            $htm[] = $view->translate(
                'No action links have been provided for this cube'
            );
        }

        return implode('<br />', $htm);
    }
}
