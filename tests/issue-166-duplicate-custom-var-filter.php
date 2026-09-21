<?php

// phpcs:ignoreFile -- Standalone regression harness intentionally colocates lightweight test doubles.

// Regression for Icinga/icingaweb2-module-cube#166.
// Execute production URL-building code with minimal Icinga Web boundary doubles.

namespace ipl\Stdlib\Filter {
    interface Rule {}

    class Equal implements Rule
    {
        public function __construct(private string $column, private $value) {}
        public function getColumn(): string { return $this->column; }
        public function getValue() { return $this->value; }
    }

    class Chain implements Rule, \IteratorAggregate
    {
        protected array $rules;
        public function __construct(Rule ...$rules) { $this->rules = $rules; }
        public function add(Rule $rule): void { $this->rules[] = $rule; }
        public function getIterator(): \Traversable { return new \ArrayIterator($this->rules); }
    }

    class All extends Chain {}
    class Any extends Chain {}
}

namespace ipl\Stdlib {
    class Filter
    {
        public static function all(\ipl\Stdlib\Filter\Rule ...$rules): \ipl\Stdlib\Filter\All
        {
            return new \ipl\Stdlib\Filter\All(...$rules);
        }

        public static function equal(string $column, $value): \ipl\Stdlib\Filter\Equal
        {
            return new \ipl\Stdlib\Filter\Equal($column, $value);
        }
    }
}

namespace ipl\Web {
    class Params
    {
        public array $entries = [];
        public function add(string $key, $value): void { $this->entries[] = [$key, $value]; }
    }

    class Url
    {
        private ?\ipl\Stdlib\Filter\Rule $filter = null;
        private Params $params;

        public function __construct(public string $path) { $this->params = new Params(); }
        public static function fromPath(string $path): self { return new self($path); }
        public function setFilter(\ipl\Stdlib\Filter\Rule $filter): self
        {
            $this->filter = $filter;
            return $this;
        }
        public function getFilter(): ?\ipl\Stdlib\Filter\Rule { return $this->filter; }
        public function getParams(): Params { return $this->params; }

        // Match ipl-web URL semantics: render the filter plus URL parameters.
        public function conditions(): array
        {
            $result = [];
            $walk = function (\ipl\Stdlib\Filter\Rule $rule) use (&$walk, &$result): void {
                if ($rule instanceof \ipl\Stdlib\Filter\Equal) {
                    $result[] = [$rule->getColumn(), $rule->getValue()];
                } elseif ($rule instanceof \ipl\Stdlib\Filter\Chain) {
                    foreach ($rule as $child) { $walk($child); }
                }
            };
            if ($this->filter !== null) { $walk($this->filter); }
            foreach ($this->params->entries as $entry) { $result[] = $entry; }
            return $result;
        }
    }
}

namespace Icinga\Module\Cube {
    interface Dimension {}

    class DimensionParams
    {
        public static function update(array $dims): self { return new self(); }
        public function getParams(): string { return ''; }
    }

    class Cube
    {
        public const SLICE_PREFIX = 'slice.';
        public static function isUsingIcingaDb(): bool { return false; }
        public function listDimensions(): array { return []; }
        public function listSlices(): array { return []; }
        public function listDimensionsUpTo($name): array { return [$name]; }
        public function getSlices(): array { return []; }
    }
}

namespace Icinga\Module\Cube\IcingaDb {
    class IcingaDbCube extends \Icinga\Module\Cube\Cube
    {
        public static function isUsingIcingaDb(): bool { return true; }
        public ?\ipl\Stdlib\Filter\Rule $baseFilter = null;
        public array $sliceData = [];
        public function hasBaseFilter(): bool { return $this->baseFilter !== null; }
        public function getBaseFilter(): \ipl\Stdlib\Filter\Rule { return $this->baseFilter; }
        public function getSlices(): array { return $this->sliceData; }
    }

    class IcingaDbServiceStatusCube extends IcingaDbCube {}
}

namespace Icinga\Web {
    class View {}
    class Url {}
}

namespace Icinga\Module\Cube\Hook {
    abstract class IcingaDbActionsHook
    {
        public array $links = [];
        abstract public function createActionLinks(\Icinga\Module\Cube\IcingaDb\IcingaDbCube $cube);
        protected function addActionLink(\ipl\Web\Url $url, $title, $description, $icon): void
        {
            $this->links[] = $url;
        }
    }
}

namespace ipl\Html {
    class BaseHtmlElement {}
    class HtmlDocument {}
}

namespace ipl\I18n { trait Translation {} }

namespace {
    function t(string $message): string { return $message; }

    $helper = __DIR__ . '/../library/Cube/IcingaDb/FilterUtil.php';
    if (file_exists($helper)) { require $helper; }
    require __DIR__ . '/../library/Cube/Web/Widget/DimensionWidget.php';
    require __DIR__ . '/../library/Cube/Web/Widget/ServiceDimensionWidget.php';
    require __DIR__ . '/../library/Cube/Web/Widget/HostDimensionWidget.php';
    require __DIR__ . '/../library/Cube/ProvidedHook/Cube/IcingaDbActions.php';

    class TestWidget extends \Icinga\Module\Cube\Web\Widget\ServiceDimensionWidget
    {
        public function __construct(\Icinga\Module\Cube\IcingaDb\IcingaDbCube $cube, string $value)
        {
            $this->cube = $cube;
            $this->dimension = [
                'name' => 'service.vars.rpaprocess',
                'row' => (object) ['service.vars.rpaprocess' => $value],
                'summaries' => (object) []
            ];
        }
        public function detailsUrl(): \ipl\Web\Url { return $this->getDetailsUrl(); }
    }

    class TestHostWidget extends \Icinga\Module\Cube\Web\Widget\HostDimensionWidget
    {
        public function __construct(\Icinga\Module\Cube\IcingaDb\IcingaDbCube $cube, string $value)
        {
            $this->cube = $cube;
            $this->dimension = [
                'name' => 'host.vars.region',
                'row' => (object) ['host.vars.region' => $value],
                'summaries' => (object) []
            ];
        }
        public function detailsUrl(): \ipl\Web\Url { return $this->getDetailsUrl(); }
    }

    function assertCondition(bool $ok, string $description): void
    {
        if (! $ok) {
            fwrite(STDERR, "FAIL: $description\n");
            exit(1);
        }
        echo "PASS: $description\n";
    }

    function occurrences(\ipl\Web\Url $url, string $column, string $value): int
    {
        return count(array_filter(
            $url->conditions(),
            static fn ($entry) => $entry[0] === $column && $entry[1] === $value
        ));
    }

    $column = 'service.vars.rpaprocess';
    $cube = new \Icinga\Module\Cube\IcingaDb\IcingaDbServiceStatusCube();
    $cube->baseFilter = \ipl\Stdlib\Filter::equal($column, 'bob');
    $url = (new TestWidget($cube, 'bob'))->detailsUrl();
    assertCondition($url->path === 'icingadb/services', 'Icinga DB service detail target');
    assertCondition(
        occurrences($url, $column, 'bob') === 1,
        'Issue 166: repeated custom var used as filter and dimension appears once'
    );

    // Explicit slice plus identical base filter also reaches the action hook.
    $cube->sliceData = [$column => 'bob'];
    $hook = new \Icinga\Module\Cube\ProvidedHook\Cube\IcingaDbActions();
    $hook->createActionLinks($cube);
    assertCondition(count($hook->links) === 1, 'Show services status link created');
    assertCondition(
        occurrences($hook->links[0], $column, 'bob') === 1,
        'Show services status link does not repeat the identical custom var filter'
    );

    // Recursive conjunctions, including an independent constraint.
    $cube->baseFilter = \ipl\Stdlib\Filter::all(
        \ipl\Stdlib\Filter::equal('host.name', 'server1'),
        \ipl\Stdlib\Filter::all(\ipl\Stdlib\Filter::equal($column, 'bob'))
    );
    $cube->sliceData = [];
    $url = (new TestWidget($cube, 'bob'))->detailsUrl();
    assertCondition(occurrences($url, $column, 'bob') === 1, 'Nested AND filter deduplicated');
    assertCondition(occurrences($url, 'host.name', 'server1') === 1, 'Independent filter retained');

    // A different dimension value must not be silently discarded.
    $url = (new TestWidget($cube, 'alice'))->detailsUrl();
    assertCondition(
        occurrences($url, $column, 'alice') === 1,
        'Distinct dimension selection is preserved'
    );

    // A condition inside OR is not an unconditional restriction and must be retained.
    $cube->baseFilter = new \ipl\Stdlib\Filter\Any(
        \ipl\Stdlib\Filter::equal($column, 'bob'),
        \ipl\Stdlib\Filter::equal($column, 'alice')
    );
    $url = (new TestWidget($cube, 'bob'))->detailsUrl();
    assertCondition(
        occurrences($url, $column, 'bob') === 2,
        'OR alternatives are not incorrectly eliminated'
    );

    $hostCube = new \Icinga\Module\Cube\IcingaDb\IcingaDbCube();
    $hostColumn = 'host.vars.region';
    $hostCube->baseFilter = \ipl\Stdlib\Filter::equal($hostColumn, 'eu');
    $hostDetails = (new TestHostWidget($hostCube, 'eu'))->detailsUrl();
    assertCondition($hostDetails->path === 'icingadb/hosts', 'Host details target retained');
    assertCondition(
        occurrences($hostDetails, $hostColumn, 'eu') === 1,
        'Host detail URL does not duplicate a matching base filter'
    );

    $hostCube->sliceData = [$hostColumn => 'eu'];
    $hostHook = new \Icinga\Module\Cube\ProvidedHook\Cube\IcingaDbActions();
    $hostHook->createActionLinks($hostCube);
    assertCondition($hostHook->links[0]->path === 'icingadb/hosts', 'Show hosts status target retained');
    assertCondition(
        occurrences($hostHook->links[0], $hostColumn, 'eu') === 1,
        'Show hosts status link does not repeat the same custom variable filter'
    );
}
