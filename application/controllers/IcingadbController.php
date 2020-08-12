<?php

namespace Icinga\Module\Cube\Controllers;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Cube\Common\IcingaDb;
use Icinga\Module\Cube\CubeSettings;
use Icinga\Module\Cube\HostCube;
use Icinga\Module\Cube\SelectDimensionForm;
use ipl\Html\Html;
use ipl\Sql\Select;
use ipl\Stdlib\Str;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ActionLink;
use PDO;

/**
 * Icingadb Hostcube controller
 */
class IcingadbController extends CompatController
{
    use IcingaDb;

    /**
     * @var int Limit for the dimensions
     *
     * "Add a dimension" drop-down menu is removed when the limit is exceeded.
     */
    protected static $DIMENSION_LIMIT = 3;

    public function indexAction()
    {
        $this->setTitle('Icinga DB Host Cube');
        $isSetShowSettings = $this->params->get('showsettings');
        //$urlDimensions is null or string
        $urlDimensions = array_filter(Str::trimSplit($this->params->get('dimensions')));
        $slices = [];

        foreach ($urlDimensions as $dimension) {
            if ($this->params->has($dimension)) {
                $slices[$dimension] = $this->params->get($dimension);
            }
        }

        $header = Html::tag('h1',
            ['class' => 'dimension-header'],
            'Cube: '. str_replace(',', ' -> ', implode(',', $urlDimensions))
        );
        $this->addControl($header);

        if (empty($urlDimensions) || $isSetShowSettings) {
            $showSettings = new ActionLink(
                $this->translate('Hide settings'),
                Url::fromRequest()->remove('showsettings'),
                'wrench',
                ['data-base-target' => '_self']
            );
        } else {
            $showSettings = new ActionLink(
                $this->translate('Show settings'),
                Url::fromRequest()->addParams(['showsettings' => 1]),
                'wrench',
                ['data-base-target' => '_self']
            );
        }

        $this->addControl($showSettings);

        $select = (new Select())
            ->columns('customvar.name')
            ->from('host')
            ->join('host_customvar','host_customvar.host_id = host.id')
            ->join('customvar','customvar.id = host_customvar.customvar_id')
            ->groupBy('customvar.name');

        $dimensions = $this->getDb()->select($select)->fetchAll(PDO::FETCH_COLUMN, 0);

        // remove already selected items from the option list
        foreach ($urlDimensions as $item) {
            if (($key = array_search($item, $dimensions)) !== false) {
                unset($dimensions[$key]);
            }
        }

        $urlDimensionsAsString = implode(',', $urlDimensions);
        $selectForm = (new SelectDimensionForm())
            ->on(SelectDimensionForm::ON_SUCCESS, function ($selectForm) use($urlDimensionsAsString) {
                if (empty($urlDimensionsAsString)) {
                    // get the selected value
                    $newUrlDimensions = $selectForm->getValue('dimensions');
                } else {
                    $newUrlDimensions = $urlDimensionsAsString . ',' . $selectForm->getValue('dimensions');
                }

                $this->redirectNow(Url::fromRequest()->with('dimensions', $newUrlDimensions));
            })
            ->setDimensions($dimensions)
            ->handleRequest(ServerRequest::fromGlobals());

        if ($isSetShowSettings || empty($urlDimensions)) {
            $this->addContent($selectForm);
        }

        if (count($urlDimensions) === static::$DIMENSION_LIMIT) {
            $selectForm->remove($selectForm->getElement('dimensions'));
        }

        if (! empty($urlDimensions)) {

            if ($isSetShowSettings) {
                $settings = (new CubeSettings())
                    ->setBaseUrl(Url::fromRequest())
                    ->setSlices($slices)
                    ->setDimensions($urlDimensions);
                $this->addContent($settings);
            }

            $select = (new Select())
                ->from('host h')
                ->join(
                    "host_state state",
                    "state.host_id = h.id"
                );

            $columns = [];
            foreach ($urlDimensions as $dimension) {
                $select
                    ->join(
                        "host_customvar {$dimension}_junction",
                        "{$dimension}_junction.host_id = h.id"
                    )
                    ->join(
                        "customvar {$dimension}",
                        "{$dimension}.id = {$dimension}_junction.customvar_id AND {$dimension}.name = \"{$dimension}\""
                    );

                $columns[$dimension] = $dimension . '.value';
            }

            $groupByValues = $columns;
            $columns['cnt'] = 'SUM(1)';
            $columns['count_up'] = 'SUM(CASE WHEN state.soft_state = 0 THEN  1 ELSE 0 END)';
            $columns['count_down'] = 'SUM(CASE WHEN state.soft_state = 1 THEN  1 ELSE 0 END)';
            $lastKey = array_key_last($groupByValues);
            $groupByValues[$lastKey] = $groupByValues[$lastKey] . ' WITH ROLLUP';

            $select
                ->columns($columns)
                ->groupBy($groupByValues);

            if (! empty($slices)) {
                foreach ($slices as $key => $value) {
                    $select
                        ->where("{$key}.value = '\"{$value}\"'");
                }
            }

            $rs = $this->getDb()->select($select)->fetchAll();

            $this->addContent((new HostCube($rs, $urlDimensions, $slices)));
        }
    }
}
