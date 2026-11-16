<?php

namespace Applu\CentralBridge\Contracts;

/** Capability `metrics`. */
interface ProvidesMetrics
{
    /**
     * @param  string  $from  YYYY-MM-DD (inclusive)
     * @param  string  $to  YYYY-MM-DD (inclusive)
     * @return iterable<\Applu\CentralBridge\Data\MetricsDay>
     */
    public function metrics(string $from, string $to): iterable;
}
