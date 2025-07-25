<?php

namespace OCA\DoorEstimator\Service;

class EstimatorUtils
{
    /**
     * Calculate the total for a quote, allowing custom item and section aggregation logic.
     *
     * @param array $quoteData
     * @param array $markups
     * @param callable|null $itemSubtotalFn function($item, $sectionKey): float
     * @param callable|null $sectionAggregateFn function($sectionSubtotal, $sectionKey, $markups): float
     * @return float
     */
    public static function calculateQuoteTotal(
        array $quoteData,
        array $markups,
        ?callable $itemSubtotalFn = null,
        ?callable $sectionAggregateFn = null
    ): float {
        $total = 0;
        foreach ($quoteData as $sectionKey => $items) {
            if (is_array($items)) {
                $sectionSubtotal = 0;
                foreach ($items as $item) {
                    $sectionSubtotal += $itemSubtotalFn
                        ? $itemSubtotalFn($item, $sectionKey)
                        : (($item['qty'] ?? 0) * ($item['price'] ?? 0));
                }
                $sectionTotal = $sectionAggregateFn
                    ? $sectionAggregateFn($sectionSubtotal, $sectionKey, $markups)
                    : ($sectionSubtotal * (1 + self::getSectionMarkup($sectionKey, $markups) / 100));
                $total += $sectionTotal;
            }
        }
        return $total;
    }

    public static function getSectionMarkup(string $sectionKey, array $markups): float
    {
        if (in_array($sectionKey, ['doors', 'doorOptions', 'inserts'])) {
            return $markups['doors'] ?? 15;
        } elseif (in_array($sectionKey, ['frames', 'frameOptions'])) {
            return $markups['frames'] ?? 12;
        }
        return $markups['hardware'] ?? 18;
    }

    public static function formatSectionName(string $sectionKey): string
    {
        $names = [
            'doors' => 'Doors',
            'doorOptions' => 'Door Options',
            'inserts' => 'Glass Inserts',
            'frames' => 'Frames',
            'frameOptions' => 'Frame Options',
            'hinges' => 'Hinges',
            'weatherstrip' => 'Weatherstrip',
            'closers' => 'Door Closers',
            'locksets' => 'Locksets',
            'exitDevices' => 'Exit Devices',
            'hardware' => 'Hardware'
        ];
        return $names[$sectionKey] ?? ucwords(str_replace(['_', 'Options'], [' ', ' Options'], $sectionKey));
    }
}