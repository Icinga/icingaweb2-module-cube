<?php

// Regression test for https://github.com/Icinga/icingaweb2-module-cube/issues/144.
// Exercise the real Cube dimension widgets with lightweight dependencies; no database is needed.

namespace Icinga\Module\Cube {
    class Cube
    {
        public const SLICE_PREFIX = 'slice.';

        public static function isUsingIcingaDb(): bool
        {
            return false;
        }

        public function listDimensions(): array
        {
            return ['service.vars.role' => new \stdClass()];
        }

        public function listSlices(): array
        {
            return [];
        }

        public function listDimensionsUpTo(string $name): array
        {
            return [$name];
        }

        public function getSlices(): array
        {
            return ['service.vars.environment' => 'production'];
        }

        public function getDimensionAfter(string $name)
        {
            return null;
        }
    }

    interface Dimension {}

    class DimensionParams
    {
        public static function update(array $dimensions): self
        {
            return new self();
        }

        public function getParams(): string
        {
            return 'service.vars.role';
        }
    }
}

namespace Icinga\Module\Cube\IcingaDb {
    class IcingaDbCube extends \Icinga\Module\Cube\Cube
    {
        public static function isUsingIcingaDb(): bool
        {
            return true;
        }

        public function hasBaseFilter(): bool
        {
            return true;
        }

        public function getBaseFilter(): \ipl\Stdlib\Filter\Rule
        {
            return new \ipl\Stdlib\Filter\Rule();
        }
    }
}

namespace Icinga\Web {
    class View {}
    class Url {}
}

namespace ipl\Html {
    class BaseHtmlElement {}
    class HtmlDocument {}
}

namespace ipl\I18n {
    trait Translation {}
}

namespace ipl\Stdlib\Filter {
    class Rule {}
}

namespace ipl\Web {
    class Params
    {
        public array $values = [];

        public function add(string $key, $value): void
        {
            $this->values[$key] = $value;
        }
    }

    class Url
    {
        public ?\ipl\Stdlib\Filter\Rule $filter = null;
        public Params $params;

        private function __construct(public string $path)
        {
            $this->params = new Params();
        }

        public static function fromPath(string $path): self
        {
            return new self($path);
        }

        public function getParams(): Params
        {
            return $this->params;
        }

        public function setFilter(\ipl\Stdlib\Filter\Rule $filter): void
        {
            $this->filter = $filter;
        }
    }
}

namespace {
    require __DIR__ . '/../library/Cube/Web/Widget/DimensionWidget.php';
    require __DIR__ . '/../library/Cube/Web/Widget/ServiceDimensionWidget.php';
    require __DIR__ . '/../library/Cube/Web/Widget/HostDimensionWidget.php';

    class TestServiceWidget extends \Icinga\Module\Cube\Web\Widget\ServiceDimensionWidget
    {
        public function __construct(\Icinga\Module\Cube\Cube $cube)
        {
            $this->cube = $cube;
            $this->dimension = [
                'name' => 'service.vars.role',
                'row' => (object) ['service.vars.role' => 'backend'],
                'summaries' => (object) []
            ];
        }

        public function detailsUrl(): \ipl\Web\Url
        {
            return $this->getDetailsUrl();
        }
    }

    class TestHostWidget extends \Icinga\Module\Cube\Web\Widget\HostDimensionWidget
    {
        public function __construct(\Icinga\Module\Cube\Cube $cube)
        {
            $this->cube = $cube;
            $this->dimension = [
                'name' => 'service.vars.role',
                'row' => (object) ['service.vars.role' => 'backend'],
                'summaries' => (object) []
            ];
        }

        public function detailsUrl(): \ipl\Web\Url
        {
            return $this->getDetailsUrl();
        }
    }

    function verify(bool $passed, string $description): void
    {
        if (! $passed) {
            fwrite(STDERR, "FAIL: $description\n");
            exit(1);
        }

        echo "PASS: $description\n";
    }

    $cube = new \Icinga\Module\Cube\IcingaDb\IcingaDbCube();
    $url = (new TestServiceWidget($cube))->detailsUrl();
    verify($url->path === 'icingadb/services', 'Icinga DB service details path');
    verify($url->filter instanceof \ipl\Stdlib\Filter\Rule, 'Icinga DB base filter preserved');
    verify(
        ($url->getParams()->values['service.vars.role'] ?? null) === 'backend',
        'Selected service dimension preserved'
    );
    verify(
        ($url->getParams()->values['service.vars.environment'] ?? null) === 'production',
        'Service cube slice preserved'
    );

    // This assertion must FAIL on the original code and PASS after applying the fix.
    verify(
        ($url->getParams()->values['sort'] ?? null) === 'service.state.severity desc',
        'Service details default to descending severity'
    );

    $hostUrl = (new TestHostWidget($cube))->detailsUrl();
    verify($hostUrl->path === 'icingadb/hosts', 'Host details path unchanged');
    verify(! array_key_exists('sort', $hostUrl->getParams()->values), 'Host sorting unchanged');

    $idoUrl = (new TestServiceWidget(new \Icinga\Module\Cube\Cube()))->detailsUrl();
    verify($idoUrl->path === 'cube/services/details', 'Legacy IDO details path unchanged');
    verify(! array_key_exists('sort', $idoUrl->getParams()->values), 'Icinga DB sort is not added to IDO');
    verify(
        ($idoUrl->getParams()->values['slice.service.vars.role'] ?? null) === 'backend',
        'Legacy IDO dimension slice preserved'
    );
}
