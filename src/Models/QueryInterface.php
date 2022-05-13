<?php

namespace Crm\SegmentModule;

interface QueryInterface
{
    public function getCountQuery();

    public function getIdsQuery();

    public function getNextPageQuery($lastPagerId, $count);

    public function getIsInQuery($field, $value);

    public function getQuery();
}
