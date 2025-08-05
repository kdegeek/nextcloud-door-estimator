<?php

namespace OCA\DoorEstimator\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCA\DoorEstimator\Service\EstimatorService;
use OCP\ILogger;

class EstimatorController extends Controller {
    
    private $estimatorService;
    private $logger;
    
    public function __construct($AppName, IRequest $request, EstimatorService $estimatorService, ILogger $logger) {
        parent::__construct($AppName, $request);
        $this->estimatorService = $estimatorService;
        $this->logger = $logger;
    }
    
    /**
     * @NoAdminRequired
     */
    public function getAllPricingData(): JSONResponse {
        try {
            $data = $this->estimatorService->getAllPricingData();
            return new JSONResponse($data);
        } catch (\Exception $e) {
            $this->logger->error('getAllPricingData: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            $this->logger->error('getPricingByCategory: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @CsrfRequired
     */
    public function updatePricingItem(): JSONResponse {
        try {
            $data = $this->request->getParams();
            // Input validation
            if (
                !isset($data['item']) || !is_string($data['item']) || trim($data['item']) === '' ||
                !isset($data['price']) || !is_numeric($data['price']) ||
                !isset($data['category']) || !is_string($data['category']) || trim($data['category']) === ''
            ) {
                return new JSONResponse(['error' => 'Invalid input data'], 400);
            }
            $result = $this->estimatorService->updatePricingItem($data);
            return new JSONResponse(['success' => $result]);
        } catch (\Exception $e) {
            $this->logger->error('updatePricingItem: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            // Input validation
            if (
                !is_string($category) || trim($category) === '' ||
                !is_string($item) || trim($item) === ''
            ) {
                return new JSONResponse(['error' => 'Invalid input data'], 400);
            }
            $price = $this->estimatorService->lookupPrice($category, $item, $frameType);
            return new JSONResponse(['price' => $price]);
        } catch (\Exception $e) {
            $this->logger->error('lookupPrice: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @CsrfRequired
     */
    public function saveQuote(): JSONResponse {
        try {
            $quoteData = $this->request->getParam('quoteData');
            $markups = $this->request->getParam('markups');
            $quoteName = $this->request->getParam('quoteName');
            $customerInfo = $this->request->getParam('customerInfo');
            // Input validation
            if (
                !is_array($quoteData) || empty($quoteData) ||
                !is_array($markups) || empty($markups)
            ) {
                return new JSONResponse(['error' => 'Invalid input data'], 400);
            }
            $quoteId = $this->estimatorService->saveQuote($quoteData, $markups, $quoteName, $customerInfo);
            return new JSONResponse([
                'success' => true,
                'quoteId' => $quoteId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('saveQuote: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            $this->logger->error('getQuote: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            $this->logger->error('getUserQuotes: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            $this->logger->error('generateQuotePDF: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * @AdminRequired
     * @CsrfRequired
     */
    public function importPricingData(): JSONResponse {
        try {
            $uploadedFile = $this->request->getUploadedFile('file');
            // Input validation
            if (
                !$uploadedFile ||
                (!is_array($uploadedFile) && !is_object($uploadedFile))
            ) {
                return new JSONResponse(['error' => 'Invalid or missing uploaded file'], 400);
            }
            // File type/size/content validation
            $allowedTypes = [
                'application/json',
                'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $fileType = is_array($uploadedFile) ? ($uploadedFile['type'] ?? '') : ($uploadedFile->getType() ?? '');
            $fileSize = is_array($uploadedFile) ? ($uploadedFile['size'] ?? 0) : ($uploadedFile->getSize() ?? 0);
            $tmpName = is_array($uploadedFile) ? ($uploadedFile['tmp_name'] ?? '') : ($uploadedFile->getTmpName() ?? '');

            if (!in_array($fileType, $allowedTypes, true)) {
                return new JSONResponse(['error' => 'Unsupported file type. Only JSON, CSV, and Excel files are allowed.'], 400);
            }
            if ($fileSize <= 0 || $fileSize > $maxSize) {
                return new JSONResponse(['error' => 'File size exceeds limit or is empty'], 400);
            }
            if (!is_readable($tmpName)) {
                return new JSONResponse(['error' => 'Uploaded file is not readable'], 400);
            }

            // If JSON, validate structure before import
            if ($fileType === 'application/json') {
                $jsonContent = file_get_contents($tmpName);
                $data = json_decode($jsonContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new JSONResponse(['error' => 'Invalid JSON file.'], 400);
                }
                // Basic structure check: must have pricingData and markups keys
                if (!isset($data['pricingData']) || !isset($data['markups'])) {
                    return new JSONResponse(['error' => 'JSON must contain pricingData and markups keys.'], 400);
                }
            }

            $result = $this->estimatorService->importPricingFromUpload($uploadedFile);
            return new JSONResponse([
                'success' => true,
                'imported' => $result['imported'],
                'errors' => $result['errors']
            ]);
        } catch (\Exception $e) {
            $this->logger->error('importPricingData: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @CsrfRequired
     */
    public function deleteQuote(int $quoteId): JSONResponse {
        try {
            $result = $this->estimatorService->deleteQuote($quoteId);
            return new JSONResponse(['success' => $result]);
        } catch (\Exception $e) {
            $this->logger->error('deleteQuote: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @CsrfRequired
     */
    public function duplicateQuote(int $quoteId): JSONResponse {
        try {
            $newQuoteId = $this->estimatorService->duplicateQuote($quoteId);
            return new JSONResponse([
                'success' => true,
                'newQuoteId' => $newQuoteId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('duplicateQuote: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            $this->logger->error('searchPricing: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
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
            $this->logger->error('getMarkupDefaults: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @CsrfRequired
     */
    public function updateMarkupDefaults(): JSONResponse {
        try {
            $markups = $this->request->getParam('markups');
            // Input validation
            if (!is_array($markups) || empty($markups)) {
                return new JSONResponse(['error' => 'Invalid input data'], 400);
            }
            $result = $this->estimatorService->updateDefaultMarkups($markups);
            return new JSONResponse(['success' => $result]);
        } catch (\Exception $e) {
            $this->logger->error('updateMarkupDefaults: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
    /**
     * @NoAdminRequired
     */
    public function getOnboardingStatus(): JSONResponse {
        try {
            $hasPricing = $this->estimatorService->isPricingDataPresent();
            return new JSONResponse(['onboardingRequired' => !$hasPricing]);
        } catch (\Exception $e) {
            $this->logger->error('getOnboardingStatus: ' . $e->getMessage(), ['exception' => $e]);
            return new JSONResponse(['error' => 'Server error'], 500);
        }
    }
}