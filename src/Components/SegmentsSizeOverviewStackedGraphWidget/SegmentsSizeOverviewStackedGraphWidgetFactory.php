<?php

namespace Crm\SegmentModule\Components\SegmentsSizeOverviewStackedGraphWidget;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SegmentModule\Repositories\SegmentsRepository;

class SegmentsSizeOverviewStackedGraphWidgetFactory
{
    public function __construct(
        private readonly LazyWidgetManager $lazyWidgetManager,
        private readonly SegmentsRepository $segmentsRepository,
        private readonly Translator $translator,
    ) {
    }

    public function create()
    {
        return new SegmentsSizeOverviewStackedGraphWidget(
            $this->lazyWidgetManager,
            $this->segmentsRepository,
            $this->translator,
        );
    }
}
