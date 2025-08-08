<?php
return [
    'routes' => [
        // Main app page
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        
        // API endpoints
        ['name' => 'estimator#getAllPricingData', 'url' => '/api/pricing', 'verb' => 'GET'],
        ['name' => 'estimator#getPricingByCategory', 'url' => '/api/pricing/{category}', 'verb' => 'GET'],
        ['name' => 'estimator#updatePricingItem', 'url' => '/api/pricing', 'verb' => 'POST'],
        ['name' => 'estimator#lookupPrice', 'url' => '/api/lookup-price', 'verb' => 'POST'],
        
        // Quote management
        ['name' => 'estimator#saveQuote', 'url' => '/api/quotes', 'verb' => 'POST'],
        ['name' => 'estimator#getQuote', 'url' => '/api/quotes/{quoteId}', 'verb' => 'GET'],
        ['name' => 'estimator#getUserQuotes', 'url' => '/api/quotes', 'verb' => 'GET'],
        ['name' => 'estimator#generateQuotePDF', 'url' => '/api/quotes/{quoteId}/pdf', 'verb' => 'GET'],
        
        ['name' => 'estimator#deleteQuote', 'url' => '/api/quotes/{quoteId}', 'verb' => 'DELETE'],
        ['name' => 'estimator#duplicateQuote', 'url' => '/api/quotes/{quoteId}/duplicate', 'verb' => 'POST'],
        ['name' => 'estimator#searchPricing', 'url' => '/api/pricing/search', 'verb' => 'GET'],
        ['name' => 'estimator#getMarkupDefaults', 'url' => '/api/markup-defaults', 'verb' => 'GET'],
        ['name' => 'estimator#updateMarkupDefaults', 'url' => '/api/markup-defaults', 'verb' => 'POST'],
        
        // Bulk import
        ['name' => 'estimator#importPricingData', 'url' => '/api/import', 'verb' => 'POST'],
        ['name' => 'estimator#getOnboardingStatus', 'url' => '/api/onboardingStatus', 'verb' => 'GET'],
    ]
];