<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Service\EstimatorUtils;

class EstimatorUtilsTest extends TestCase
{
    // --- calculateQuoteTotal() tests ---

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

    public function testCalculateQuoteTotalWithCustomSectionFn()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 1, 'price' => 100]
            ]
        ];
        $markups = ['doors' => 10];
        $sectionAggregateFn = function ($sectionSubtotal, $sectionKey, $markups) {
            // Add flat $50 to each section
            return $sectionSubtotal + 50;
        };
        // (1*100) + 50 = 150
        $this->assertEquals(150, EstimatorUtils::calculateQuoteTotal($quoteData, $markups, null, $sectionAggregateFn));
    }

    public function testCalculateQuoteTotalEmptyQuoteDataAndMarkups()
    {
        $quoteData = [];
        $markups = [];
        $this->assertEquals(0, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    public function testCalculateQuoteTotalSectionWithNoItems()
    {
        $quoteData = [
            'doors' => [],
            'frames' => [
                ['qty' => 1, 'price' => 100]
            ]
        ];
        $markups = ['frames' => 10];
        // doors: 0 items, subtotal = 0, frames: 1*100*1.10 = 110
        $this->assertEquals(110, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    public function testCalculateQuoteTotalInvalidItemStructure()
    {
        $quoteData = [
            'doors' => [
                ['foo' => 1, 'bar' => 2]
            ]
        ];
        $markups = ['doors' => 10];
        // qty and price default to 0, subtotal = 0
        $this->assertEquals(0, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    public function testCalculateQuoteTotalMissingQtyOrPrice()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 2], // price missing, defaults to 0
                ['price' => 100] // qty missing, defaults to 0
            ]
        ];
        $markups = ['doors' => 10];
        // both items subtotal = 0
        $this->assertEquals(0, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    public function testCalculateQuoteTotalNegativeQtyAndPrice()
    {
        $quoteData = [
            'doors' => [
                ['qty' => -2, 'price' => 100],
                ['qty' => 1, 'price' => -200]
            ]
        ];
        $markups = ['doors' => 10];
        // (-2*100 + 1*-200) = -200 + -200 = -400 * 1.10 = -440
        $this->assertEquals(-440, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    public function testCalculateQuoteTotalLargeNumbersAndFloatPrecision()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 1000000, 'price' => 9999.99],
                ['qty' => 1, 'price' => 0.123456]
            ]
        ];
        $markups = ['doors' => 15];
        $expected = ((1000000 * 9999.99) + (1 * 0.123456)) * 1.15;
        $result = EstimatorUtils::calculateQuoteTotal($quoteData, $markups);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result, '', 0.01); // allow small floating point error
    }

    public function testCalculateQuoteTotalNullMarkups()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 1, 'price' => 100]
            ]
        ];
        $markups = null;
        // Should use default doors markup 15
        $expected = 100 * 1.15;
        $result = EstimatorUtils::calculateQuoteTotal($quoteData, (array)$markups);
        $this->assertEquals($expected, $result);
    }

    public function testCalculateQuoteTotalSectionMarkupMissing()
    {
        $quoteData = [
            'doors' => [
                ['qty' => 1, 'price' => 100]
            ],
            'frames' => [
                ['qty' => 1, 'price' => 200]
            ],
            'hardware' => [
                ['qty' => 1, 'price' => 300]
            ]
        ];
        $markups = []; // all missing, should use defaults
        $expected = (100 * 1.15) + (200 * 1.12) + (300 * 1.18);
        $this->assertEquals($expected, EstimatorUtils::calculateQuoteTotal($quoteData, $markups));
    }

    // --- getSectionMarkup() tests ---

    public function testGetSectionMarkupAllSections()
    {
        $markups = ['doors' => 10, 'frames' => 20, 'hardware' => 30];
        $this->assertEquals(10, EstimatorUtils::getSectionMarkup('doors', $markups));
        $this->assertEquals(10, EstimatorUtils::getSectionMarkup('doorOptions', $markups));
        $this->assertEquals(10, EstimatorUtils::getSectionMarkup('inserts', $markups));
        $this->assertEquals(20, EstimatorUtils::getSectionMarkup('frames', $markups));
        $this->assertEquals(20, EstimatorUtils::getSectionMarkup('frameOptions', $markups));
        $this->assertEquals(30, EstimatorUtils::getSectionMarkup('hardware', $markups));
        $this->assertEquals(30, EstimatorUtils::getSectionMarkup('locksets', $markups));
        $this->assertEquals(30, EstimatorUtils::getSectionMarkup('other', $markups));
    }

    public function testGetSectionMarkupMissingKeys()
    {
        $markups = [];
        $this->assertEquals(15, EstimatorUtils::getSectionMarkup('doors', $markups));
        $this->assertEquals(12, EstimatorUtils::getSectionMarkup('frames', $markups));
        $this->assertEquals(18, EstimatorUtils::getSectionMarkup('hardware', $markups));
        $this->assertEquals(18, EstimatorUtils::getSectionMarkup('other', $markups));
    }

    public function testGetSectionMarkupNullOrEmptyMarkups()
    {
        $this->assertEquals(15, EstimatorUtils::getSectionMarkup('doors', null));
        $this->assertEquals(12, EstimatorUtils::getSectionMarkup('frames', []));
        $this->assertEquals(18, EstimatorUtils::getSectionMarkup('hardware', []));
    }

    public function testGetSectionMarkupNonStandardSectionNames()
    {
        $markups = ['hardware' => 22];
        $this->assertEquals(22, EstimatorUtils::getSectionMarkup('custom_section', $markups));
        $this->assertEquals(18, EstimatorUtils::getSectionMarkup('unknown', []));
    }

    // --- formatSectionName() tests ---

    public function testFormatSectionNamePredefined()
    {
        $this->assertEquals('Doors', EstimatorUtils::formatSectionName('doors'));
        $this->assertEquals('Door Options', EstimatorUtils::formatSectionName('doorOptions'));
        $this->assertEquals('Glass Inserts', EstimatorUtils::formatSectionName('inserts'));
        $this->assertEquals('Frames', EstimatorUtils::formatSectionName('frames'));
        $this->assertEquals('Frame Options', EstimatorUtils::formatSectionName('frameOptions'));
        $this->assertEquals('Hinges', EstimatorUtils::formatSectionName('hinges'));
        $this->assertEquals('Weatherstrip', EstimatorUtils::formatSectionName('weatherstrip'));
        $this->assertEquals('Door Closers', EstimatorUtils::formatSectionName('closers'));
        $this->assertEquals('Locksets', EstimatorUtils::formatSectionName('locksets'));
        $this->assertEquals('Exit Devices', EstimatorUtils::formatSectionName('exitDevices'));
        $this->assertEquals('Hardware', EstimatorUtils::formatSectionName('hardware'));
    }

    public function testFormatSectionNameUnknown()
    {
        $this->assertEquals('Custom Section', EstimatorUtils::formatSectionName('custom_section'));
        $this->assertEquals('Other', EstimatorUtils::formatSectionName('other'));
        $this->assertEquals('My Section', EstimatorUtils::formatSectionName('my_section'));
    }

    public function testFormatSectionNameWithUnderscoresAndOptionsSuffix()
    {
        $this->assertEquals('Special Options', EstimatorUtils::formatSectionName('specialOptions'));
        $this->assertEquals('Extra Section Options', EstimatorUtils::formatSectionName('extra_sectionOptions'));
        $this->assertEquals('Another Section', EstimatorUtils::formatSectionName('another_section'));
    }

    public function testFormatSectionNameEmptyAndSpecialCharacters()
    {
        $this->assertEquals('', EstimatorUtils::formatSectionName(''));
        $this->assertEquals('123', EstimatorUtils::formatSectionName('123'));
        $this->assertEquals('!@#$', EstimatorUtils::formatSectionName('!@#$'));
    }

    public function testFormatSectionNameVeryLongSectionName()
    {
        $longName = str_repeat('section_', 10);
        $expected = ucwords(str_replace(['_', 'Options'], [' ', ' Options'], $longName));
        $this->assertEquals($expected, EstimatorUtils::formatSectionName($longName));
    }
}