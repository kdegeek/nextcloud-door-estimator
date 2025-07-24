<?php

namespace OCA\DoorEstimator\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCA\DoorEstimator\Service\EstimatorService;

class EstimatorController extends Controller {
    
    private $estimatorService;
    
    public function __construct($AppName, IRequest $request, EstimatorService $estimatorService) {
        parent::__construct($AppName, $request);
        $this->estimatorService = $estimatorService;
    }
    
    /**
     * @NoAdminRequired
     */
    public function getAllPricingData(): JSONResponse {
        try {
            $data = $this->estimatorService->getAllPricingData();
            return new JSONResponse($data);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function getPricingByCategory(string $category): JSONResponse {
        try {
            $data = $this->estimatorService->getPricingByCategory($category);
            return new JSONResponse($data);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function updatePricingItem(): JSONResponse {
        try {
            $data = $this->request->getParams();
            $result = $this->estimatorService->updatePricingItem($data);
            return new JSONResponse(['success' => $result]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function lookupPrice(): JSONResponse {
        try {
            $category = $this->request->getParam('category');
            $item = $this->request->getParam('item');
            $frameType = $this->request->getParam('frameType');
            
            $price = $this->estimatorService->lookupPrice($category, $item, $frameType);
            return new JSONResponse(['price' => $price]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function saveQuote(): JSONResponse {
        try {
            $quoteData = $this->request->getParam('quoteData');
            $markups = $this->request->getParam('markups');
            $quoteName = $this->request->getParam('quoteName');
            $customerInfo = $this->request->getParam('customerInfo');
            
            $quoteId = $this->estimatorService->saveQuote($quoteData, $markups, $quoteName, $customerInfo);
            return new JSONResponse([
                'success' => true,
                'quoteId' => $quoteId
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function getQuote(int $quoteId): JSONResponse {
        try {
            $quote = $this->estimatorService->getQuote($quoteId);
            if ($quote) {
                return new JSONResponse($quote);
            } else {
                return new JSONResponse(['error' => 'Quote not found'], 404);
            }
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function getUserQuotes(): JSONResponse {
        try {
            $quotes = $this->estimatorService->getUserQuotes();
            return new JSONResponse($quotes);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function generateQuotePDF(int $quoteId): JSONResponse {
        try {
            $pdfResult = $this->estimatorService->generateQuotePDF($quoteId);
            if ($pdfResult) {
                return new JSONResponse([
                    'success' => true,
                    'pdfPath' => $pdfResult['path'],
                    'downloadUrl' => $pdfResult['downloadUrl']
                ]);
            } else {
                return new JSONResponse(['error' => 'Failed to generate PDF'], 500);
            }
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function importPricingData(): JSONResponse {
        try {
            $uploadedFile = $this->request->getUploadedFile('file');
            if (!$uploadedFile) {
                return new JSONResponse(['error' => 'No file uploaded'], 400);
            }
            
            $result = $this->estimatorService->importPricingFromUpload($uploadedFile);
            return new JSONResponse([
                'success' => true,
                'imported' => $result['imported'],
                'errors' => $result['errors']
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function deleteQuote(int $quoteId): JSONResponse {
        try {
            $result = $this->estimatorService->deleteQuote($quoteId);
            return new JSONResponse(['success' => $result]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function duplicateQuote(int $quoteId): JSONResponse {
        try {
            $newQuoteId = $this->estimatorService->duplicateQuote($quoteId);
            return new JSONResponse([
                'success' => true,
                'newQuoteId' => $newQuoteId
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function searchPricing(): JSONResponse {
        try {
            $query = $this->request->getParam('query');
            $category = $this->request->getParam('category');
            $limit = (int)($this->request->getParam('limit') ?? 50);
            
            $results = $this->estimatorService->searchPricing($query, $category, $limit);
            return new JSONResponse($results);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired  
     */
    public function getMarkupDefaults(): JSONResponse {
        try {
            $markups = $this->estimatorService->getDefaultMarkups();
            return new JSONResponse($markups);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     */
    public function updateMarkupDefaults(): JSONResponse {
        try {
            $markups = $this->request->getParam('markups');
            $result = $this->estimatorService->updateDefaultMarkups($markups);
            return new JSONResponse(['success' => $result]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
}