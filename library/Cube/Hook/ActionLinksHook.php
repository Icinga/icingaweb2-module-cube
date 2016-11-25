<?php

namespace Icinga\Module\Cube\Hook;

use Icinga\Module\Cube\Cube;
use Icinga\Web\View;
use Icinga\Application\Hook;

/**
 * ActionLinksHook
 *
 * Implement this hook in case your module wants to add links to the detail
 * page shown for a slice.
 *
 * @package Icinga\Module\Cube\Hook
 */
abstract class ActionLinksHook
{
    /**
     * Your implementation needs to extend this methos
     *
     * This allows you to return free-form HTML. With great power comes great
     * responsibility, so please try to provide just links fitting the layout
     * of currently available examples.
     *
     * In case you have the need to provide a neon-green/pink blinking 72pt
     * "CLICK ME" link nobody will hinder you from doing so. Have fun!
     *
     * @param View $view
     * @param Cube $cube
     * @return string
     */
    abstract public function getHtml(View $view, Cube $cube);

    /**
     * Get all links for all Hook implementations
     *
     * This is what the Cube calls when rendering details
     *
     * @param View $view
     * @param Cube $cube
     *
     * @return string
     */
    final public static function getAllHtml(View $view, Cube $cube)
    {
        $htm = array();

        /** @var ActionLinksHook $links */
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
