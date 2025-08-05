<?php

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Controller\EstimatorController;
use OCA\DoorEstimator\Service\EstimatorService;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http\JSONResponse;

class EstimatorControllerTest extends TestCase
{
    protected $controller;
    protected $service;
    protected $request;
    protected $logger;

    protected function setUp(): void
    {
        $this->service = $this->getMockBuilder(EstimatorService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->request = $this->getMockBuilder(IRequest::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(ILogger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->controller = new EstimatorController(
            'door_estimator',
            $this->request,
            $this->service,
            $this->logger
        );
    }

    public function testGetAllPricingDataReturnsJSONResponse()
    {
        $this->service->method('getAllPricingData')->willReturn(['data' => []]);
        $response = $this->controller->getAllPricingData();
        $this->assertInstanceOf(JSONResponse::class, $response);
    }

    public function testLookupPriceHandlesMissingParams()
    {
        $this->request->method('getParam')->willReturn(null);
        $response = $this->controller->lookupPrice();
        $this->assertInstanceOf(JSONResponse::class, $response);
    }

    public function testSaveQuoteHandlesInvalidInput()
    {
        $this->request->method('getParam')->willReturn(null);
        $response = $this->controller->saveQuote();
        $this->assertInstanceOf(JSONResponse::class, $response);
    }
}