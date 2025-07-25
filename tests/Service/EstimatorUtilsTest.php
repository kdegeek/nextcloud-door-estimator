<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Service\EstimatorUtils;

class EstimatorUtilsTest extends TestCase
{
    public function testCalculateQuoteTotalDefault()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 2, 'price' => 100],
                ['qty' => 1, 'price' => 200]
            ],
            'frames' => [
                ['qty' => 1, 'price' => 150]
            ]
        ];
        $markups = ['doors' => 10, 'frames' => 20, 'hardware' => 30];
        // doors: (2*100 + 1*200) * 1.10 = 400 * 1.10 = 440
        // frames: (1*150) * 1.20 = 150 * 1.20 = 180
        // total = 440 + 180 = 620
        $this->assertEquals(620, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    public function testCalculateQuoteTotalWithCustomItemFn()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 2, 'price' => 100, 'discount' => 0.1]
            ]
        ];
        $markups = ['doors' => 0, 'frames' => 0, 'hardware' => 0];
        $itemSubtotalFn = function ($item, $sectionKey) {
            // Apply discount if present
            $subtotal = ($item['qty'] ?? 0) * ($item['price'] ?? 0);
            if (isset($item['discount'])) {
                $subtotal *= (1 - $item['discount']);
            }
            return $subtotal;
        };
        // (2*100) * 0.9 = 200 * 0.9 = 180
        $this->assertEquals(180, EstimatorUtils::calculateQuoteTotal($quoteData, $markups, $itemSubtotalFn));
    }

    public function testGetSectionMarkup()
    {
        $markups = ['doors' => 10, 'frames' => 20, 'hardware' => 30];
        $this->assertEquals(10, EstimatorUtils::getSectionMarkup('doors', $markups));
        $this->assertEquals(20, EstimatorUtils::getSectionMarkup('frames', $markups));
        $this->assertEquals(30, EstimatorUtils::getSectionMarkup('locksets', $markups));
        $this->assertEquals(30, EstimatorUtils::getSectionMarkup('hardware', $markups));
    }

    public function testFormatSectionName()
    {
        $this->assertEquals('Doors', EstimatorUtils::formatSectionName('doors'));
        $this->assertEquals('Door Options', EstimatorUtils::formatSectionName('doorOptions'));
        $this->assertEquals('Glass Inserts', EstimatorUtils::formatSectionName('inserts'));
        $this->assertEquals('Locksets', EstimatorUtils::formatSectionName('locksets'));
        $this->assertEquals('Custom Section', EstimatorUtils::formatSectionName('custom_section'));
    }
}