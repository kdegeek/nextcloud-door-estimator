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

    // getAllPricingData
    public function testGetAllPricingDataReturnsJSONResponse()
    {
        $this->service->method('getAllPricingData')->willReturn(['data' => []]);
        $response = $this->controller->getAllPricingData();
        $this->assertInstanceOf(JSONResponse::class, $response);
        $this->assertEquals(['data' => []], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetAllPricingDataHandlesServiceException()
    {
        $this->service->method('getAllPricingData')->willThrowException(new \Exception('fail'));
        $response = $this->controller->getAllPricingData();
        $this->assertEquals(500, $response->getStatus());
        $this->assertArrayHasKey('error', $response->getData());
    }

    // getPricingByCategory
    public function testGetPricingByCategoryReturnsData()
    {
        $this->service->method('getPricingByCategory')->willReturn(['cat' => 'doors']);
        $response = $this->controller->getPricingByCategory('doors');
        $this->assertEquals(['cat' => 'doors'], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetPricingByCategoryHandlesServiceException()
    {
        $this->service->method('getPricingByCategory')->willThrowException(new \Exception('fail'));
        $response = $this->controller->getPricingByCategory('doors');
        $this->assertEquals(500, $response->getStatus());
        $this->assertArrayHasKey('error', $response->getData());
    }

    // updatePricingItem
    public function testUpdatePricingItemValidatesRequiredFields()
    {
        $this->request->method('getParams')->willReturn([]);
        $response = $this->controller->updatePricingItem();
        $this->assertEquals(400, $response->getStatus());
        $this->assertArrayHasKey('error', $response->getData());
    }

    public function testUpdatePricingItemValidatesTypes()
    {
        $this->request->method('getParams')->willReturn([
            'item' => '',
            'price' => 'not-a-number',
            'category' => ''
        ]);
        $response = $this->controller->updatePricingItem();
        $this->assertEquals(400, $response->getStatus());
    }

    public function testUpdatePricingItemSuccess()
    {
        $this->request->method('getParams')->willReturn([
            'item' => 'door',
            'price' => 100,
            'category' => 'doors'
        ]);
        $this->service->method('updatePricingItem')->willReturn(true);
        $response = $this->controller->updatePricingItem();
        $this->assertEquals(['success' => true], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testUpdatePricingItemHandlesServiceException()
    {
        $this->request->method('getParams')->willReturn([
            'item' => 'door',
            'price' => 100,
            'category' => 'doors'
        ]);
        $this->service->method('updatePricingItem')->willThrowException(new \Exception('fail'));
        $response = $this->controller->updatePricingItem();
        $this->assertEquals(500, $response->getStatus());
    }

    // lookupPrice
    public function testLookupPriceHandlesMissingParams()
    {
        $this->request->method('getParam')->willReturn(null);
        $response = $this->controller->lookupPrice();
        $this->assertEquals(400, $response->getStatus());
    }

    public function testLookupPriceHandlesNullFrameType()
    {
        $this->request->method('getParam')->willReturnMap([
            ['category', 'doors'],
            ['item', 'door'],
            ['frameType', null]
        ]);
        $this->service->method('lookupPrice')->willReturn(123);
        $response = $this->controller->lookupPrice();
        $this->assertEquals(['price' => 123], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testLookupPriceHandlesServiceException()
    {
        $this->request->method('getParam')->willReturnMap([
            ['category', 'doors'],
            ['item', 'door'],
            ['frameType', 'wood']
        ]);
        $this->service->method('lookupPrice')->willThrowException(new \Exception('fail'));
        $response = $this->controller->lookupPrice();
        $this->assertEquals(500, $response->getStatus());
    }

    // saveQuote
    public function testSaveQuoteHandlesInvalidInput()
    {
        $this->request->method('getParam')->willReturn(null);
        $response = $this->controller->saveQuote();
        $this->assertEquals(400, $response->getStatus());
    }

    public function testSaveQuoteHandlesEmptyArrays()
    {
        $this->request->method('getParam')->willReturnMap([
            ['quoteData', []],
            ['markups', []],
            ['quoteName', 'Q1'],
            ['customerInfo', ['name' => 'John']]
        ]);
        $response = $this->controller->saveQuote();
        $this->assertEquals(400, $response->getStatus());
    }

    public function testSaveQuoteSuccess()
    {
        $this->request->method('getParam')->willReturnMap([
            ['quoteData', [['item' => 'door']]],
            ['markups', [['markup' => 10]]],
            ['quoteName', 'Q1'],
            ['customerInfo', ['name' => 'John']]
        ]);
        $this->service->method('saveQuote')->willReturn(42);
        $response = $this->controller->saveQuote();
        $this->assertEquals(['success' => true, 'quoteId' => 42], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testSaveQuoteHandlesServiceException()
    {
        $this->request->method('getParam')->willReturnMap([
            ['quoteData', [['item' => 'door']]],
            ['markups', [['markup' => 10]]],
            ['quoteName', 'Q1'],
            ['customerInfo', ['name' => 'John']]
        ]);
        $this->service->method('saveQuote')->willThrowException(new \Exception('fail'));
        $response = $this->controller->saveQuote();
        $this->assertEquals(500, $response->getStatus());
    }

    // getQuote
    public function testGetQuoteReturnsQuote()
    {
        $this->service->method('getQuote')->willReturn(['id' => 1, 'name' => 'Q1']);
        $response = $this->controller->getQuote(1);
        $this->assertEquals(['id' => 1, 'name' => 'Q1'], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetQuoteNotFound()
    {
        $this->service->method('getQuote')->willReturn(null);
        $response = $this->controller->getQuote(999);
        $this->assertEquals(404, $response->getStatus());
        $this->assertArrayHasKey('error', $response->getData());
    }

    public function testGetQuoteHandlesServiceException()
    {
        $this->service->method('getQuote')->willThrowException(new \Exception('fail'));
        $response = $this->controller->getQuote(1);
        $this->assertEquals(500, $response->getStatus());
    }

    // getUserQuotes
    public function testGetUserQuotesReturnsQuotes()
    {
        $this->service->method('getUserQuotes')->willReturn([['id' => 1]]);
        $response = $this->controller->getUserQuotes();
        $this->assertEquals([['id' => 1]], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetUserQuotesEmpty()
    {
        $this->service->method('getUserQuotes')->willReturn([]);
        $response = $this->controller->getUserQuotes();
        $this->assertEquals([], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetUserQuotesHandlesServiceException()
    {
        $this->service->method('getUserQuotes')->willThrowException(new \Exception('fail'));
        $response = $this->controller->getUserQuotes();
        $this->assertEquals(500, $response->getStatus());
    }

    // generateQuotePDF
    public function testGenerateQuotePDFSuccess()
    {
        $this->service->method('generateQuotePDF')->willReturn([
            'path' => '/tmp/quote.pdf',
            'downloadUrl' => '/download/quote.pdf'
        ]);
        $response = $this->controller->generateQuotePDF(1);
        $this->assertEquals(['success' => true, 'pdfPath' => '/tmp/quote.pdf', 'downloadUrl' => '/download/quote.pdf'], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGenerateQuotePDFNotFound()
    {
        $this->service->method('generateQuotePDF')->willReturn(null);
        $response = $this->controller->generateQuotePDF(1);
        $this->assertEquals(500, $response->getStatus());
        $this->assertArrayHasKey('error', $response->getData());
    }

    public function testGenerateQuotePDFHandlesServiceException()
    {
        $this->service->method('generateQuotePDF')->willThrowException(new \Exception('fail'));
        $response = $this->controller->generateQuotePDF(1);
        $this->assertEquals(500, $response->getStatus());
    }

    // deleteQuote
    public function testDeleteQuoteSuccess()
    {
        $this->service->method('deleteQuote')->willReturn(true);
        $response = $this->controller->deleteQuote(1);
        $this->assertEquals(['success' => true], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testDeleteQuoteHandlesServiceException()
    {
        $this->service->method('deleteQuote')->willThrowException(new \Exception('fail'));
        $response = $this->controller->deleteQuote(1);
        $this->assertEquals(500, $response->getStatus());
    }

    // duplicateQuote
    public function testDuplicateQuoteSuccess()
    {
        $this->service->method('duplicateQuote')->willReturn(2);
        $response = $this->controller->duplicateQuote(1);
        $this->assertEquals(['success' => true, 'newQuoteId' => 2], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testDuplicateQuoteHandlesSourceNotFound()
    {
        $this->service->method('duplicateQuote')->willThrowException(new \Exception('fail'));
        $response = $this->controller->duplicateQuote(999);
        $this->assertEquals(500, $response->getStatus());
    }

    // searchPricing
    public function testSearchPricingHandlesQueryParams()
    {
        $this->request->method('getParam')->willReturnMap([
            ['query', 'door'],
            ['category', 'doors'],
            ['limit', 10]
        ]);
        $this->service->method('searchPricing')->willReturn([['item' => 'door']]);
        $response = $this->controller->searchPricing();
        $this->assertEquals([['item' => 'door']], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testSearchPricingEmptyResults()
    {
        $this->request->method('getParam')->willReturnMap([
            ['query', ''],
            ['category', ''],
            ['limit', 10]
        ]);
        $this->service->method('searchPricing')->willReturn([]);
        $response = $this->controller->searchPricing();
        $this->assertEquals([], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testSearchPricingHandlesServiceException()
    {
        $this->request->method('getParam')->willReturnMap([
            ['query', 'door'],
            ['category', 'doors'],
            ['limit', 10]
        ]);
        $this->service->method('searchPricing')->willThrowException(new \Exception('fail'));
        $response = $this->controller->searchPricing();
        $this->assertEquals(500, $response->getStatus());
    }

    // getMarkupDefaults
    public function testGetMarkupDefaultsReturnsMarkups()
    {
        $this->service->method('getDefaultMarkups')->willReturn(['markup' => 10]);
        $response = $this->controller->getMarkupDefaults();
        $this->assertEquals(['markup' => 10], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetMarkupDefaultsHandlesServiceException()
    {
        $this->service->method('getDefaultMarkups')->willThrowException(new \Exception('fail'));
        $response = $this->controller->getMarkupDefaults();
        $this->assertEquals(500, $response->getStatus());
    }

    // updateMarkupDefaults
    public function testUpdateMarkupDefaultsValidatesInput()
    {
        $this->request->method('getParam')->willReturn([]);
        $response = $this->controller->updateMarkupDefaults();
        $this->assertEquals(400, $response->getStatus());
    }

    public function testUpdateMarkupDefaultsSuccess()
    {
        $this->request->method('getParam')->willReturn([['markup' => 10]]);
        $this->service->method('updateDefaultMarkups')->willReturn(true);
        $response = $this->controller->updateMarkupDefaults();
        $this->assertEquals(['success' => true], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testUpdateMarkupDefaultsHandlesServiceException()
    {
        $this->request->method('getParam')->willReturn([['markup' => 10]]);
        $this->service->method('updateDefaultMarkups')->willThrowException(new \Exception('fail'));
        $response = $this->controller->updateMarkupDefaults();
        $this->assertEquals(500, $response->getStatus());
    }

    // getOnboardingStatus
    public function testGetOnboardingStatusTrue()
    {
        $this->service->method('isPricingDataPresent')->willReturn(false);
        $response = $this->controller->getOnboardingStatus();
        $this->assertEquals(['onboardingRequired' => true], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetOnboardingStatusFalse()
    {
        $this->service->method('isPricingDataPresent')->willReturn(true);
        $response = $this->controller->getOnboardingStatus();
        $this->assertEquals(['onboardingRequired' => false], $response->getData());
        $this->assertEquals(200, $response->getStatus());
    }

    public function testGetOnboardingStatusHandlesServiceException()
    {
        $this->service->method('isPricingDataPresent')->willThrowException(new \Exception('fail'));
        $response = $this->controller->getOnboardingStatus();
        $this->assertEquals(500, $response->getStatus());
    }

    // importPricingData (admin-only, file upload)
    public function testImportPricingDataValidatesMissingFile()
    {
        $this->request->method('getUploadedFile')->willReturn(null);
        $response = $this->controller->importPricingData();
        $this->assertEquals(400, $response->getStatus());
        $this->assertArrayHasKey('error', $response->getData());
    }

    public function testImportPricingDataValidatesFileType()
    {
        $file = ['type' => 'application/zip', 'size' => 100, 'tmp_name' => '/tmp/file.zip'];
        $this->request->method('getUploadedFile')->willReturn($file);
        $response = $this->controller->importPricingData();
        $this->assertEquals(400, $response->getStatus());
        $this->assertStringContainsString('Unsupported file type', $response->getData()['error']);
    }

    public function testImportPricingDataValidatesFileSize()
    {
        $file = ['type' => 'application/json', 'size' => 0, 'tmp_name' => '/tmp/file.json'];
        $this->request->method('getUploadedFile')->willReturn($file);
        $response = $this->controller->importPricingData();
        $this->assertEquals(400, $response->getStatus());
        $this->assertStringContainsString('File size', $response->getData()['error']);
    }

    public function testImportPricingDataValidatesUnreadableFile()
    {
        $file = ['type' => 'application/json', 'size' => 100, 'tmp_name' => '/tmp/unreadable.json'];
        $this->request->method('getUploadedFile')->willReturn($file);
        // Simulate unreadable file
        $this->assertFalse(is_readable($file['tmp_name']));
        $response = $this->controller->importPricingData();
        $this->assertEquals(400, $response->getStatus());
        $this->assertStringContainsString('not readable', $response->getData()['error']);
    }

    public function testImportPricingDataValidatesMalformedJSON()
    {
        $file = ['type' => 'application/json', 'size' => 100, 'tmp_name' => __DIR__ . '/malformed.json'];
        $this->request->method('getUploadedFile')->willReturn($file);
        file_put_contents($file['tmp_name'], '{bad json');
        $response = $this->controller->importPricingData();
        $this->assertEquals(400, $response->getStatus());
        $this->assertStringContainsString('Invalid JSON', $response->getData()['error']);
        unlink($file['tmp_name']);
    }

    public function testImportPricingDataValidatesJSONStructure()
    {
        $file = ['type' => 'application/json', 'size' => 100, 'tmp_name' => __DIR__ . '/badstructure.json'];
        $this->request->method('getUploadedFile')->willReturn($file);
        file_put_contents($file['tmp_name'], json_encode(['foo' => 'bar']));
        $response = $this->controller->importPricingData();
        $this->assertEquals(400, $response->getStatus());
        $this->assertStringContainsString('pricingData and markups', $response->getData()['error']);
        unlink($file['tmp_name']);
    }

    public function testImportPricingDataSuccess()
    {
        $file = ['type' => 'application/json', 'size' => 100, 'tmp_name' => __DIR__ . '/good.json'];
        $this->request->method('getUploadedFile')->willReturn($file);
        file_put_contents($file['tmp_name'], json_encode(['pricingData' => [], 'markups' => []]));
        $this->service->method('importPricingFromUpload')->willReturn(['imported' => 1, 'errors' => []]);
        $response = $this->controller->importPricingData();
        $this->assertEquals(['success' => true, 'imported' => 1, 'errors' => []], $response->getData());
        $this->assertEquals(200, $response->getStatus());
        unlink($file['tmp_name']);
    }

    public function testImportPricingDataHandlesServiceException()
    {
        $file = ['type' => 'application/json', 'size' => 100, 'tmp_name' => __DIR__ . '/good.json'];
        $this->request->method('getUploadedFile')->willReturn($file);
        file_put_contents($file['tmp_name'], json_encode(['pricingData' => [], 'markups' => []]));
        $this->service->method('importPricingFromUpload')->willThrowException(new \Exception('fail'));
        $response = $this->controller->importPricingData();
        $this->assertEquals(500, $response->getStatus());
        unlink($file['tmp_name']);
    }
}