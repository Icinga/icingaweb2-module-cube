<?php
// Icinga Web 2 Cube Module | (c) 2016 Icinga GmbH | GPLv2

namespace Icinga\Module\Cube\Web;

use Exception;
use Icinga\Application\Hook;
use Icinga\Application\Modules\Module;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Hook\ActionsHook;
use Icinga\Module\Cube\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Web\View;

/**
 * ActionLink
 *
 * ActionsHook implementations return instances of this class
 *
 * @package Icinga\Module\Cube\Web
 */
class ActionLinks
{
    /** @var ActionLink[] */
    protected $links = array();

    /**
     * Get all links for all Hook implementations
     *
     * This is what the Cube calls when rendering details
     *
     * @param Cube $cube
     * @param View $view
     *
     * @return string
     */
    public static function renderAll(Cube $cube, View $view)
    {
        $html = array();

            /** @var ActionsHook $hook */
        foreach (Hook::all('Cube/Actions') as $hook) {
            try {
                $hook->prepareActionLinks($cube, $view);
            } catch (Exception $e) {
                $html[] = static::renderErrorItem($e, $view);
            }

            foreach ($hook->getActionLinks()->getLinks() as $link) {
                $html[] = '<li>' . $link->render($view) . '</li>';
            }
        }

        if (empty($html)) {
            $html[] = static::renderErrorItem(
                $view->translate('No action links have been provided for this cube'),
                $view
            );
        }

        return implode("\n", $html) . "\n";
    }

    /**
     * @param Exception|string $error
     * @param View $view
     * @return string
     */
    private static function renderErrorItem($error, View $view)
    {
        if ($error instanceof Exception) {
            $error = $error->getMessage();
        }
        return '<li class="error">' . $view->escape($error) . '</li>';
    }

    /**
     * Add an ActionLink to this set of actions
     *
     * @param ActionLink $link
     * @return $this
     */
    public function add(ActionLink $link)
    {
        $this->links[] = $link;
        return $this;
    }

    /**
     * @return ActionLink[]
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * @param View $view
     *
     * @return string
     */
    public function render(View $view)
    {
        $links = $this->getLinks();
        if (empty($links)) {
            return '';
        }

        $html = '<ul class="action-links">';
        foreach ($links as $link) {
            $html .= $link->render($view);
        }
        $html .= '</ul>';

        return $html;
    }
}
