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
        $response = $this->client->request('GET', '/api/pricing', [
            'auth_basic' => [self::$userUser, self::$userPass]
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertIsArray($json);
        $this->assertEmpty($json);
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

/**
 * Dummy API client for demonstration.
 * Replace with actual HTTP client or Nextcloud test client.
 */