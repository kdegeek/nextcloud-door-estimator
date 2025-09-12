<?php
/**
 * Door Estimator App Routes
 * 
 * RESTful API endpoints following Nextcloud app framework conventions
 * All API routes require authentication and proper CSRF protection
 */
return [
    'routes' => [
        // Main application page
        [
            'name' => 'page#index', 
            'url' => '/', 
            'verb' => 'GET',
            'requirements' => []
        ],

        // Pricing Data Management API
        [
            'name' => 'estimator#getAllPricingData', 
            'url' => '/api/pricing', 
            'verb' => 'GET',
            'requirements' => []
        ],
        [
            'name' => 'estimator#getPricingByCategory', 
            'url' => '/api/pricing/{category}', 
            'verb' => 'GET',
            'requirements' => ['category' => '[a-zA-Z0-9_-]+']
        ],
        [
            'name' => 'estimator#updatePricingItem', 
            'url' => '/api/pricing', 
            'verb' => 'POST',
            'requirements' => []
        ],
        [
            'name' => 'estimator#searchPricing', 
            'url' => '/api/pricing/search', 
            'verb' => 'GET',
            'requirements' => []
        ],

        // Price Lookup API
        [
            'name' => 'estimator#lookupPrice', 
            'url' => '/api/lookup-price', 
            'verb' => 'POST',
            'requirements' => []
        ],

        // Quote Management API
        [
            'name' => 'estimator#saveQuote', 
            'url' => '/api/quotes', 
            'verb' => 'POST',
            'requirements' => []
        ],
        [
            'name' => 'estimator#getUserQuotes', 
            'url' => '/api/quotes', 
            'verb' => 'GET',
            'requirements' => []
        ],
        [
            'name' => 'estimator#getQuote', 
            'url' => '/api/quotes/{quoteId}', 
            'verb' => 'GET',
            'requirements' => ['quoteId' => '\d+']
        ],
        [
            'name' => 'estimator#deleteQuote', 
            'url' => '/api/quotes/{quoteId}', 
            'verb' => 'DELETE',
            'requirements' => ['quoteId' => '\d+']
        ],
        [
            'name' => 'estimator#duplicateQuote', 
            'url' => '/api/quotes/{quoteId}/duplicate', 
            'verb' => 'POST',
            'requirements' => ['quoteId' => '\d+']
        ],

        // PDF Generation API
        [
            'name' => 'estimator#generateQuotePDF', 
            'url' => '/api/quotes/{quoteId}/pdf', 
            'verb' => 'GET',
            'requirements' => ['quoteId' => '\d+']
        ],
        [
            'name' => 'estimator#downloadQuotePDF', 
            'url' => '/api/quotes/{quoteId}/pdf/download/{fileName}', 
            'verb' => 'GET',
            'requirements' => ['quoteId' => '\d+', 'fileName' => '[a-zA-Z0-9_\-\.]+']
        ],

        // Configuration Management API
        [
            'name' => 'estimator#getMarkupDefaults', 
            'url' => '/api/markup-defaults', 
            'verb' => 'GET',
            'requirements' => []
        ],
        [
            'name' => 'estimator#updateMarkupDefaults', 
            'url' => '/api/markup-defaults', 
            'verb' => 'POST',
            'requirements' => []
        ],

        // Data Import/Export API
        [
            'name' => 'estimator#importPricingData', 
            'url' => '/api/import', 
            'verb' => 'POST',
            'requirements' => []
        ],
        [
            'name' => 'estimator#exportPricingData', 
            'url' => '/api/export', 
            'verb' => 'GET',
            'requirements' => []
        ],

        // System Status API
        [
            'name' => 'estimator#getOnboardingStatus', 
            'url' => '/api/onboarding-status', 
            'verb' => 'GET',
            'requirements' => []
        ],
    ]
];