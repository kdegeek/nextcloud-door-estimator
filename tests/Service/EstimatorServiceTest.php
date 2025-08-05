<?php

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Service\EstimatorService;

class EstimatorServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        // Use mocks or stubs for dependencies as needed
        $this->service = $this->getMockBuilder(EstimatorService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testLookupPriceReturnsFloat()
    {
        $this->service->method('lookupPrice')->willReturn(123.45);
        $result = $this->service->lookupPrice('doors', 'Door A');
        $this->assertIsFloat($result);
    }

    public function testUpdatePricingItemThrowsOnInvalidInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->method('updatePricingItem')->will($this->throwException(new \InvalidArgumentException()));
        $this->service->updatePricingItem(['invalid' => 'data']);
    }

    public function testSaveQuoteReturnsInt()
    {
        $this->service->method('saveQuote')->willReturn(1);
        $result = $this->service->saveQuote([], []);
        $this->assertIsInt($result);
    }

    public function testGetQuoteReturnsArrayOrNull()
    {
        $this->service->method('getQuote')->willReturn(['id' => 1]);
        $result = $this->service->getQuote(1);
        $this->assertIsArray($result);

        $this->service->method('getQuote')->willReturn(null);
        $result = $this->service->getQuote(999);
        $this->assertNull($result);
    }
}