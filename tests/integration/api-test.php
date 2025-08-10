<?php

declare(strict_types=1);

use OCP\AppFramework\Http\WebTestCase;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;

final class DoorEstimatorApiIntegrationTest extends WebTestCase
{
    private static $adminUser = 'admin';
    private static $adminPass = 'adminpass';
    private static $userUser = 'user';
    private static $userPass = 'userpass';
    private static $testQuoteId;
    private static $testPricingId;

    public static function setUpBeforeClass(): void
    {
        // Create test users using Nextcloud's provisioning API
        exec('php occ user:add --password-from-env admin', $output, $code);
        exec('php occ user:add --password-from-env user', $output, $code);
        // Set passwords via environment or occ user:resetpassword if needed

        // Clean DB before tests (custom logic or via API)
        // e.g., call an endpoint to reset test data if available
    }

    public static function tearDownAfterClass(): void
    {
        // Remove test users
        exec('php occ user:delete admin', $output, $code);
        exec('php occ user:delete user', $output, $code);
        // Clean up test data if needed
    }

    public function testGetAllPricingDataEmpty(): void
    {
        $start = microtime(true);
        $response = $this->client->request('GET', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $duration = microtime(true) - $start;
        $this->assertLessThan(1.0, $duration, 'Response time for empty DB should be <1s');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertEmpty($json);
        $this->assertJsonStringEqualsJsonString(json_encode([]), $response->getContent());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider largePricingDataProvider
     */
    public function testGetAllPricingDataLarge($largeData): void
    {
        // Insert large pricing data via updatePricingItem
        foreach ($largeData as $item) {
            $response = $this->client->request('POST', '/api/pricing', [
                'auth_basic' => [self::$userUser, self::$userPass],
                'json' => $item
            ]);
            $this->assertEquals(200, $response->getStatusCode());
        }
        $start = microtime(true);
        $response = $this->client->request('GET', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $duration = microtime(true) - $start;
        $this->assertLessThan(2.0, $duration, 'Response time for large dataset should be <2s');
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertCount(count($largeData), array_merge(...array_values($json)));
    }

    public static function largePricingDataProvider(): array
    {
        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items[] = [
                'item' => "BulkItem$i",
                'price' => 10 + $i,
                'category' => 'bulk',
                'stock_status' => 'stock',
                'description' => "Bulk item $i"
            ];
        }
    
        return [[$items]];
    }

    /**
     * @dataProvider sqliXssPayloadProvider
     */
    public function testUpdatePricingItemSecurity($payload): void
    {
        $response = $this->client->request('POST', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $payload
        ]);
        $this->assertEquals(400, $response->getStatusCode(), 'Should reject SQLi/XSS payload');
        $json = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid', $json['error']);
    }

    public static function sqliXssPayloadProvider(): array
    {
        return [[
            ['item' => "'; DROP TABLE pricing; --", 'price' => 100, 'category' => 'doors'],
            ['item' => "<script>alert(1)</script>", 'price' => 100, 'category' => 'doors'],
            ['item' => "Test Door", 'price' => 100, 'category' => "<img src=x onerror=alert(1)>"]
        ]];
    }

    public function testUpdatePricingItemDuplicate(): void
    {
        $data = [
            'item' => 'Duplicate Door',
            'price' => 200,
            'category' => 'doors',
            'stock_status' => 'stock',
            'description' => 'First insert'
        ];
        $response1 = $this->client->request('POST', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response1->getStatusCode());
        $response2 = $this->client->request('POST', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        // Should handle duplicate gracefully (either update or error)
        $this->assertContains($response2->getStatusCode(), [200, 409]);
    }

    public function testUpdatePricingItemConcurrent(): void
    {
        $data = [
            'item' => 'Concurrent Door',
            'price' => 300,
            'category' => 'doors',
            'stock_status' => 'stock',
            'description' => 'Concurrent test'
        ];
        $responses = [];
        $client = $this->client;
        // Simulate 5 concurrent updates
        foreach (range(1, 5) as $i) {
            $responses[] = $client->request('POST', '/api/pricing', [
                'auth_basic' => [self::$userUser, self::$userPass],
                'json' => array_merge($data, ['price' => 300 + $i])
            ]);
        }
        foreach ($responses as $response) {
            $this->assertEquals(200, $response->getStatusCode());
        }
        // Check final state
        $getResp = $client->request('GET', '/api/pricing/doors', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $json = json_decode($getResp->getContent(), true);
        $found = false;
        foreach ($json as $item) {
            if ($item['item'] === 'Concurrent Door') {
                $found = true;
                $this->assertGreaterThanOrEqual(301, $item['price']);
            }
}
        $this->assertTrue($found, 'Concurrent Door not found after concurrent updates');
    }

    /**
     * @dataProvider malformedPricingDataProvider
     */
    public function testGetAllPricingDataMalformed($malformedData): void
    {
        // Insert malformed pricing data
        foreach ($malformedData as $item) {
            $response = $this->client->request('POST', '/api/pricing', [
                'auth_basic' => [self::$userUser, self::$userPass],
                'json' => $item
            ]);
            // Should return 400 for invalid input
            $this->assertEquals(400, $response->getStatusCode());
        }
        $response = $this->client->request('GET', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $json = json_decode($response->getContent(), true);
        // Should not contain malformed items
        foreach ($json as $cat => $items) {
            foreach ($items as $item) {
                $this->assertNotEquals('', $item['item']);
                $this->assertIsNumeric($item['price']);
            }
        }
    }

    public static function malformedPricingDataProvider(): array
    {
        return [[
            [
                ['item' => '', 'price' => 10, 'category' => 'bulk'],
                ['item' => 'Valid', 'price' => 'not-a-number', 'category' => 'bulk'],
                ['item' => 'Valid', 'price' => 10, 'category' => '']
            ]
        ]];
    }

    public function testUpdatePricingItemSuccess(): void
    {
        $data = [
            'item' => 'Test Door',
            'price' => 123.45,
            'category' => 'doors',
            'stock_status' => 'stock',
            'description' => 'Test description'
        ];
        $response = $this->client->request('POST', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);

        // Save for later tests
        self::$testPricingId = $json['id'] ?? null;
    }

    public function testUpdatePricingItemInvalidInput(): void
    {
        $data = [
            'item' => '',
            'price' => 'not-a-number',
            'category' => ''
        ];
        $response = $this->client->request('POST', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid input data', $json['error']);
    }

    public function testGetAllPricingDataPopulated(): void
    {
        $response = $this->client->request('GET', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertNotEmpty($json);
        // Strengthen: check that the pricing data contains the item we added
        $found = false;
        foreach ($json as $cat => $items) {
            foreach ($items as $item) {
                if ($item['item'] === 'Test Door' && $item['price'] == 123.45) {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($found, 'Pricing data does not contain expected item');
    }

    public function testGetPricingByCategorySuccess(): void
    {
        $response = $this->client->request('GET', '/api/pricing/doors', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        // Strengthen: check that the returned category matches and contains expected item
        $this->assertArrayHasKey('0', $json);
        $this->assertEquals('Test Door', $json[0]['item']);
        $this->assertEquals(123.45, $json[0]['price']);
        $this->assertEquals('doors', $json[0]['category']);
    }

    public function testGetPricingByCategoryNotFound(): void
    {
        $response = $this->client->request('GET', '/api/pricing/nonexistent', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertEmpty($json);
    }

    public function testLookupPriceSuccess(): void
    {
        $data = [
            'category' => 'doors',
            'item' => 'Test Door',
            'frameType' => null
        ];
        $response = $this->client->request('POST', '/api/lookup-price', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(123.45, $json['price']);
    }

    public function testLookupPriceInvalidInput(): void
    {
        $data = [
            'category' => '',
            'item' => ''
        ];
        $response = $this->client->request('POST', '/api/lookup-price', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid input data', $json['error']);
    }

    /**
     * @dataProvider lookupPriceEdgeProvider
     */
    public function testLookupPriceEdgeCases($category, $item, $frameType, $expectedStatus, $expectedPrice): void
    {
        $data = [
            'category' => $category,
            'item' => $item,
            'frameType' => $frameType
        ];
        $response = $this->client->request('POST', '/api/lookup-price', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        if ($expectedStatus === 200) {
            $this->assertIsNumeric($json['price']);
            if ($expectedPrice !== null) {
                $this->assertEquals($expectedPrice, $json['price']);
            }
        } else {
            $this->assertArrayHasKey('error', $json);
        }
    }

    public static function lookupPriceEdgeProvider(): array
    {
        return [
            // Case variations
            ['doors', 'Test Door', null, 200, 123.45],
            ['DOORS', 'TEST DOOR', null, 200, 123.45],
            ['doors', 'test door', null, 200, 123.45],
            // Special characters
            ['doors', 'Test@Door#$', null, 400, null],
            ['doors', 'Test Door', 'Steel', 200, 123.45],
            ['doors', 'Test Door', '', 200, 123.45],
            // FrameType edge cases
            ['doors', 'Test Door', str_repeat('A', 256), 400, null],
            // Boundary: empty/null
            ['', 'Test Door', null, 400, null],
            ['doors', '', null, 400, null],
            [null, 'Test Door', null, 400, null],
            ['doors', null, null, 400, null],
            // Wrong types
            [[], 'Test Door', null, 400, null],
            ['doors', [], null, 400, null],
        ];
    }

    public function testSaveQuoteSuccess(): void
    {
        $data = [
            'quoteData' => [
                'doors' => [
                    ['item' => 'Test Door', 'qty' => 2, 'price' => 123.45]
                ]
            ],
            'markups' => ['doors' => 10, 'frames' => 5, 'hardware' => 8],
            'quoteName' => 'Integration Test Quote',
            'customerInfo' => ['name' => 'Test Customer']
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
        $this->assertIsInt($json['quoteId']);

        self::$testQuoteId = $json['quoteId'];
    }

    public function testSaveQuoteInvalidInput(): void
    {
        $data = [
            'quoteData' => [],
            'markups' => []
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid input data', $json['error']);
    }

    /**
     * @dataProvider saveQuoteEdgeProvider
     */
    public function testSaveQuoteEdgeCases($quoteData, $markups, $quoteName, $customerInfo, $expectedStatus, $expectedSuccess): void
    {
        $data = [
            'quoteData' => $quoteData,
            'markups' => $markups,
            'quoteName' => $quoteName,
            'customerInfo' => $customerInfo
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        if ($expectedStatus === 200) {
            $this->assertEquals($expectedSuccess, $json['success']);
            $this->assertIsInt($json['quoteId']);
        } else {
            $this->assertArrayHasKey('error', $json);
        }
    }

    public static function saveQuoteEdgeProvider(): array
    {
        $largeQuoteData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeQuoteData['doors'][] = ['item' => "BulkItem$i", 'qty' => 1, 'price' => 10 + $i];
        }
        $validMarkups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        $validCustomer = ['name' => 'Test Customer'];

        return [
            // Large quote data
            [$largeQuoteData, $validMarkups, 'LargeQuote', $validCustomer, 200, true],
            // Malformed quote data
            [[], $validMarkups, 'EmptyQuote', $validCustomer, 400, false],
            [['doors' => [['item' => '', 'qty' => 1, 'price' => 10]]], $validMarkups, 'MalformedItem', $validCustomer, 400, false],
            [['doors' => [['item' => 'Valid', 'qty' => 'not-a-number', 'price' => 10]]], $validMarkups, 'MalformedQty', $validCustomer, 400, false],
            // Name validation
            [$largeQuoteData, $validMarkups, '', $validCustomer, 400, false],
            [$largeQuoteData, $validMarkups, str_repeat('A', 256), $validCustomer, 400, false],
            [$largeQuoteData, $validMarkups, 'ValidName', $validCustomer, 200, true],
            [$largeQuoteData, $validMarkups, '<script>alert(1)</script>', $validCustomer, 400, false],
            // Duplicate name
            [$largeQuoteData, $validMarkups, 'DuplicateName', $validCustomer, 200, true],
            [$largeQuoteData, $validMarkups, 'DuplicateName', $validCustomer, 409, false],
        ];
    }

    public function testGetQuoteSuccess(): void
    {
        $response = $this->client->request('GET', '/api/quotes/' . self::$testQuoteId, [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(self::$testQuoteId, $json['id']);
    }

    public function testGetQuoteNotFound(): void
    {
        $response = $this->client->request('GET', '/api/quotes/999999', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(404, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Quote not found', $json['error']);
    }

    public function testGetUserQuotes(): void
    {
        $response = $this->client->request('GET', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertNotEmpty($json);
    }

    /**
     * @dataProvider userQuotesPaginationProvider
     */
    public function testGetUserQuotesPaginationSorting($quotes, $page, $perPage, $sortField, $sortOrder, $expectedIds): void
    {
        // Insert quotes for pagination/sorting
        foreach ($quotes as $q) {
            $data = [
                'quoteData' => $q['quoteData'],
                'markups' => $q['markups'],
                'quoteName' => $q['quoteName'],
                'customerInfo' => $q['customerInfo']
            ];
            $response = $this->client->request('POST', '/api/quotes', [
                'auth_basic' => [self::$userUser, self::$userPass],
                'json' => $data
            ]);
            $this->assertEquals(200, $response->getStatusCode());
        }
        $params = [
            'page' => $page,
            'perPage' => $perPage,
            'sort' => $sortField,
            'order' => $sortOrder
        ];
        $query = http_build_query($params);
        $response = $this->client->request('GET', '/api/quotes?' . $query, [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $ids = array_column($json, 'id');
        $this->assertEquals($expectedIds, $ids);
    }

    public static function userQuotesPaginationProvider(): array
    {
        $quotes = [];
        for ($i = 1; $i <= 5; $i++) {
            $quotes[] = [
                'quoteData' => ['doors' => [['item' => "Door$i", 'qty' => 1, 'price' => 100 + $i]]],
                'markups' => ['doors' => 10, 'frames' => 5, 'hardware' => 8],
                'quoteName' => "Quote$i",
                'customerInfo' => ['name' => "Customer$i"]
            ];
        }
        return [
            [$quotes, 1, 2, 'id', 'asc', [1, 2]],
            [$quotes, 2, 2, 'id', 'asc', [3, 4]],
            [$quotes, 1, 5, 'id', 'desc', [5, 4, 3, 2, 1]],
        ];
    }

    public function testGetUserQuotesIsolation(): void
    {
        // Insert a quote as user
        $data = [
            'quoteData' => ['doors' => [['item' => 'UserOnly', 'qty' => 1, 'price' => 111]]],
            'markups' => ['doors' => 10, 'frames' => 5, 'hardware' => 8],
            'quoteName' => 'UserOnlyQuote',
            'customerInfo' => ['name' => 'UserOnly']
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        // Insert a quote as admin
        $data['quoteName'] = 'AdminOnlyQuote';
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$adminUser, self::$adminPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());

        // User should not see admin's quote
        $response = $this->client->request('GET', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $json = json_decode($response->getContent(), true);
        foreach ($json as $quote) {
            $this->assertNotEquals('AdminOnlyQuote', $quote['quoteName']);
        }

        // Admin should see both
        $response = $this->client->request('GET', '/api/quotes', [
            'auth_basic' => [self::$adminUser, self::$adminPass]
        ]);
        $json = json_decode($response->getContent(), true);
        $foundUser = $foundAdmin = false;
        foreach ($json as $quote) {
            if ($quote['quoteName'] === 'UserOnlyQuote') $foundUser = true;
            if ($quote['quoteName'] === 'AdminOnlyQuote') $foundAdmin = true;
        }
        $this->assertTrue($foundUser && $foundAdmin, 'Admin should see both quotes');
    }

    public function testGenerateQuotePDFSuccess(): void
    {
        $response = $this->client->request('GET', '/api/quotes/' . self::$testQuoteId . '/pdf', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('pdfPath', $json);
        $this->assertArrayHasKey('downloadUrl', $json);
        // Strengthen: check that the PDF file exists and is readable
        $this->assertFileExists($json['pdfPath']);
        $this->assertGreaterThan(0, filesize($json['pdfPath']), 'PDF file is empty');
    }

    public function testGenerateQuotePDFNotFound(): void
    {
        $response = $this->client->request('GET', '/api/quotes/999999/pdf', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Failed to generate PDF', $json['error']);
    }

    /**
     * @dataProvider largeQuotePDFProvider
     */
    public function testGenerateQuotePDFLarge($quoteData, $markups): void
    {
        $data = [
            'quoteData' => $quoteData,
            'markups' => $markups,
            'quoteName' => 'LargePDFQuote',
            'customerInfo' => ['name' => 'PDF Customer']
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $quoteId = $json['quoteId'];

        $start = microtime(true);
        $response = $this->client->request('GET', '/api/quotes/' . $quoteId . '/pdf', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $duration = microtime(true) - $start;
        $this->assertLessThan(5.0, $duration, 'PDF generation should be <5s');
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
        $this->assertFileExists($json['pdfPath']);
        $this->assertGreaterThan(10000, filesize($json['pdfPath']), 'PDF file should be large');
        unlink($json['pdfPath']);
    }

    public static function largeQuotePDFProvider(): array
    {
        $largeQuoteData = ['doors' => []];
        for ($i = 0; $i < 200; $i++) {
            $largeQuoteData['doors'][] = ['item' => "BulkItem$i", 'qty' => 1, 'price' => 10 + $i];
        }
        $markups = ['doors' => 10, 'frames' => 5, 'hardware' => 8];
        return [[$largeQuoteData, $markups]];
    }

    public function testGenerateQuotePDFConcurrent(): void
    {
        // Create a quote
        $data = [
            'quoteData' => ['doors' => [['item' => 'ConcurrentPDF', 'qty' => 1, 'price' => 123.45]]],
            'markups' => ['doors' => 10, 'frames' => 5, 'hardware' => 8],
            'quoteName' => 'ConcurrentPDFQuote',
            'customerInfo' => ['name' => 'Concurrent']
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $quoteId = $json['quoteId'];

        // Simulate 3 concurrent PDF generations
        $pdfPaths = [];
        foreach (range(1, 3) as $i) {
            $response = $this->client->request('GET', '/api/quotes/' . $quoteId . '/pdf', [
                'auth_basic' => [self::$userUser, self::$userPass]
            ]);
            $this->assertEquals(200, $response->getStatusCode());
            $json = json_decode($response->getContent(), true);
            $this->assertTrue($json['success']);
            $pdfPaths[] = $json['pdfPath'];
        }
        // All files should exist and be cleaned up after unlink
        foreach ($pdfPaths as $path) {
            $this->assertFileExists($path);
            unlink($path);
            $this->assertFileDoesNotExist($path);
        }
    }

    public function testDeleteQuoteSuccess(): void
    {
        $response = $this->client->request('DELETE', '/api/quotes/' . self::$testQuoteId, [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
    }

    public function testDeleteQuoteNotFound(): void
    {
        $response = $this->client->request('DELETE', '/api/quotes/999999', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertFalse($json['success']);
    }

    public function testDuplicateQuoteSuccess(): void
    {
        // First, create a new quote to duplicate
        $data = [
            'quoteData' => [
                'doors' => [
                    ['item' => 'Test Door', 'qty' => 1, 'price' => 123.45]
                ]
            ],
            'markups' => ['doors' => 10, 'frames' => 5, 'hardware' => 8],
            'quoteName' => 'Duplicate Test Quote',
            'customerInfo' => ['name' => 'Test Customer']
        ];
        $response = $this->client->request('POST', '/api/quotes', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $json = json_decode($response->getContent(), true);
        $quoteId = $json['quoteId'];

        $response = $this->client->request('POST', '/api/quotes/' . $quoteId . '/duplicate', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => []
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($json['success']);
        $this->assertIsInt($json['newQuoteId']);
        // Strengthen: check that duplicated quote matches original
        $origQuote = $this->client->request('GET', '/api/quotes/' . $quoteId, [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $origJson = json_decode($origQuote->getContent(), true);
        $dupQuote = $this->client->request('GET', '/api/quotes/' . $json['newQuoteId'], [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $dupJson = json_decode($dupQuote->getContent(), true);
        $this->assertEquals($origJson['quoteData'], $dupJson['quoteData'], 'Duplicated quote data does not match original');
        $this->assertEquals($origJson['markups'], $dupJson['markups'], 'Duplicated markups do not match original');
        $this->assertEquals($origJson['customerInfo'], $dupJson['customerInfo'], 'Duplicated customer info does not match original');
}

    public function testDuplicateQuoteNotFound(): void
    {
        $response = $this->client->request('POST', '/api/quotes/999999/duplicate', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => []
        ]);
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($json['success'] ?? false);
    }
    public function testSearchPricing(): void
    {
        $response = $this->client->request('GET', '/api/pricing/search?query=Test', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        // Strengthen: check that search results contain expected item
        $found = false;
        foreach ($json as $cat => $items) {
            foreach ($items as $item) {
                if (isset($item['item']) && $item['item'] === 'Test Door') {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($found, 'Search did not return expected item');
    }

    public function testGetMarkupDefaults(): void
    {
        $response = $this->client->request('GET', '/api/markup-defaults', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('doors', $json);
        $this->assertArrayHasKey('frames', $json);
        $this->assertArrayHasKey('hardware', $json);
    }

    public function testUpdateMarkupDefaultsSuccess(): void
    {
        $data = ['markups' => ['doors' => 20, 'frames' => 15, 'hardware' => 25]];
        $response = $this->client->request('POST', '/api/markup-defaults', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
    }

    public function testUpdateMarkupDefaultsInvalidInput(): void
    {
        $data = ['markups' => []];
        $response = $this->client->request('POST', '/api/markup-defaults', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'json' => $data
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid input data', $json['error']);
    }

    public function testGetOnboardingStatus(): void
    {
        $response = $this->client->request('GET', '/api/onboardingStatus', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('onboardingRequired', $json);
    }

    public function testImportPricingDataSuccess(): void
    {
        $file = [
            'type' => 'application/json',
            'size' => 100,
            'tmp_name' => __DIR__ . '/test_pricing.json'
        ];
        // Create a valid test_pricing.json file
        file_put_contents($file['tmp_name'], json_encode([
            'pricingData' => [
                ['item' => 'Import Door', 'price' => 99.99, 'category' => 'doors']
            ],
            'markups' => [
                ['type' => 'doors', 'value' => 10]
            ]
        ]));

        $response = $this->client->request('POST', '/api/import', [
            'auth_basic' => [self::$adminUser, self::$adminPass],
            'files' => ['file' => $file]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue($json['success']);
        $this->assertEquals(1, $json['imported']);
        unlink($file['tmp_name']);
    }

    public function testImportPricingDataInvalidFile(): void
    {
        $file = [
            'type' => 'application/json',
            'size' => 100,
            'tmp_name' => __DIR__ . '/invalid_pricing.json'
        ];
        // Create an invalid test file
        file_put_contents($file['tmp_name'], '{invalid json');

        $response = $this->client->request('POST', '/api/import', [
            'auth_basic' => [self::$adminUser, self::$adminPass],
            'files' => ['file' => $file]
        ]);
        $this->assertEquals(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid JSON file.', $json['error']);
        unlink($file['tmp_name']);
    }

    public function testImportPricingDataUnauthorized(): void
    {
        $file = [
            'type' => 'application/json',
            'size' => 100,
            'tmp_name' => __DIR__ . '/test_pricing.json'
        ];
        file_put_contents($file['tmp_name'], json_encode([
            'pricingData' => [
                ['item' => 'Import Door', 'price' => 99.99, 'category' => 'doors']
            ],
            'markups' => [
                ['type' => 'doors', 'value' => 10]
            ]
        ]));

        $response = $this->client->request('POST', '/api/import', [
            'auth_basic' => [self::$userUser, self::$userPass],
            'files' => ['file' => $file]
        ]);
        $this->assertEquals(403, $response->getStatusCode());
        unlink($file['tmp_name']);
}
    }