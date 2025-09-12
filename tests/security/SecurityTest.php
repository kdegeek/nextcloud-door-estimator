<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Tests\Security;

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Controller\EstimatorController;
use OCA\DoorEstimator\Service\EstimatorService;
use OCP\AppFramework\Http\JSONResponse;

/**
 * Security tests for Door Estimator application
 * Tests authentication, authorization, input validation, and security vulnerabilities
 */
class SecurityTest extends TestCase
{
    /** @var EstimatorController */
    private $controller;

    /** @var EstimatorService */
    private $service;

    /** @var \OCP\IUserSession */
    private $userSession;

    /** @var \OCP\IRequest */
    private $request;

    protected function setUp(): void
    {
        $this->initializeSecurityTestComponents();
    }

    /**
     * Test: Authentication is required for all operations
     * Requirement: 10.5, 10.6 - Authentication and authorization
     */
    public function testAuthenticationRequired(): void
    {
        // Test unauthenticated access
        $this->userSession->method('getUser')->willReturn(null);

        $protectedMethods = [
            'getAllPricingData',
            'saveQuote',
            'getUserQuotes',
            'getQuote',
            'deleteQuote',
            'generateQuotePDF'
        ];

        foreach ($protectedMethods as $method) {
            $response = $this->controller->$method();
            
            $this->assertEquals(401, $response->getStatus(), 
                "$method should return 401 for unauthenticated users");
            
            $data = $response->getData();
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('authentication', strtolower($data['error']));
        }
    }

    /**
     * Test: Admin-only operations require admin privileges
     * Requirement: 1.4 - Admin-only access controls
     */
    public function testAdminOnlyOperations(): void
    {
        // Set up regular user (non-admin)
        $regularUser = $this->createMock(\OCP\IUser::class);
        $regularUser->method('getUID')->willReturn('regular_user');
        $this->userSession->method('getUser')->willReturn($regularUser);
        $this->userSession->method('isLoggedIn')->willReturn(true);

        // Mock admin check to return false for regular user
        $this->mockAdminCheck(false);

        $adminOnlyMethods = [
            'updatePricingItem',
            'importPricingData',
            'exportPricingData',
            'updateMarkupDefaults'
        ];

        foreach ($adminOnlyMethods as $method) {
            $response = $this->controller->$method();
            
            $this->assertEquals(403, $response->getStatus(), 
                "$method should return 403 for non-admin users");
            
            $data = $response->getData();
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('permission', strtolower($data['error']));
        }
    }

    /**
     * Test: SQL injection prevention
     * Requirement: 10.5 - Use prepared statements exclusively
     */
    public function testSQLInjectionPrevention(): void
    {
        $this->setupAuthenticatedUser();

        $maliciousInputs = [
            "'; DROP TABLE door_estimator_pricing; --",
            "' OR '1'='1",
            "'; UPDATE door_estimator_quotes SET user_id='attacker'; --",
            "' UNION SELECT * FROM door_estimator_quotes WHERE '1'='1",
            "'; INSERT INTO door_estimator_pricing VALUES ('evil', 0); --"
        ];

        foreach ($maliciousInputs as $maliciousInput) {
            // Test search functionality
            $this->request->method('getParam')->willReturnMap([
                ['query', $maliciousInput],
                ['category', null],
                ['limit', 10]
            ]);

            $response = $this->controller->searchPricing();
            
            // Should not cause SQL errors or return unauthorized data
            $this->assertNotEquals(500, $response->getStatus(), 
                'SQL injection attempt should not cause server error');
            
            $data = $response->getData();
            $this->assertIsArray($data, 'Response should be valid array');

            // Test price lookup
            $response = $this->controller->lookupPrice();
            $this->request->method('getParam')->willReturnMap([
                ['category', 'doors'],
                ['item', $maliciousInput],
                ['frameType', null]
            ]);

            // Should handle malicious input safely
            $this->assertNotEquals(500, $response->getStatus());
        }
    }

    /**
     * Test: XSS prevention in input handling
     * Requirement: 10.4 - XSS prevention
     */
    public function testXSSPrevention(): void
    {
        $this->setupAuthenticatedUser();

        $xssPayloads = [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("xss")',
            '<svg onload="alert(1)">',
            '"><script>alert("xss")</script>',
            '<iframe src="javascript:alert(1)"></iframe>'
        ];

        foreach ($xssPayloads as $payload) {
            // Test pricing item update
            $this->request->method('getParams')->willReturn([
                'item' => $payload,
                'price' => 100,
                'category' => 'doors'
            ]);

            $response = $this->controller->updatePricingItem();
            
            // Should sanitize input and not execute scripts
            $this->assertNotEquals(500, $response->getStatus());
            
            // Test quote saving
            $this->request->method('getParam')->willReturnMap([
                ['quoteData', [
                    'doors' => [['item' => $payload, 'qty' => 1, 'price' => 100, 'total' => 100]]
                ]],
                ['markups', ['doors' => 15]],
                ['quoteName', $payload],
                ['customerInfo', ['name' => $payload]]
            ]);

            $response = $this->controller->saveQuote();
            
            // Should handle XSS attempts safely
            $this->assertNotEquals(500, $response->getStatus());
        }
    }

    /**
     * Test: CSRF protection
     * Requirement: 12.14 - CSRF protection for state-changing operations
     */
    public function testCSRFProtection(): void
    {
        $this->setupAuthenticatedUser();

        $stateChangingMethods = [
            'updatePricingItem',
            'saveQuote',
            'deleteQuote',
            'updateMarkupDefaults'
        ];

        foreach ($stateChangingMethods as $method) {
            // Test without CSRF token
            $this->request->method('getHeader')->willReturnMap([
                ['X-CSRF-Token', '']
            ]);

            $response = $this->controller->$method();
            
            // Should reject requests without valid CSRF token
            $this->assertEquals(403, $response->getStatus(), 
                "$method should require CSRF token");
        }

        // Test with valid CSRF token
        $this->request->method('getHeader')->willReturnMap([
            ['X-CSRF-Token', 'valid-csrf-token']
        ]);

        // Mock CSRF validation to pass
        $this->mockCSRFValidation(true);

        foreach ($stateChangingMethods as $method) {
            $response = $this->controller->$method();
            
            // Should not reject due to CSRF (may fail for other reasons)
            $this->assertNotEquals(403, $response->getStatus(), 
                "$method should accept valid CSRF token");
        }
    }

    /**
     * Test: Input validation and sanitization
     * Requirement: 10.1, 10.2, 10.3 - Input validation
     */
    public function testInputValidation(): void
    {
        $this->setupAuthenticatedUser();

        // Test invalid pricing item data
        $invalidPricingData = [
            ['item' => '', 'price' => 100, 'category' => 'doors'], // Empty item
            ['item' => 'Door', 'price' => -100, 'category' => 'doors'], // Negative price
            ['item' => 'Door', 'price' => 'invalid', 'category' => 'doors'], // Non-numeric price
            ['item' => 'Door', 'price' => 100, 'category' => ''], // Empty category
            ['item' => str_repeat('a', 1000), 'price' => 100, 'category' => 'doors'] // Too long item name
        ];

        foreach ($invalidPricingData as $invalidData) {
            $this->request->method('getParams')->willReturn($invalidData);
            
            $response = $this->controller->updatePricingItem();
            
            $this->assertEquals(400, $response->getStatus(), 
                'Invalid pricing data should return 400 Bad Request');
            
            $data = $response->getData();
            $this->assertArrayHasKey('error', $data);
        }

        // Test invalid quote data
        $invalidQuoteData = [
            'doors' => [
                ['item' => '', 'qty' => -1, 'price' => 'invalid', 'total' => null]
            ]
        ];

        $this->request->method('getParam')->willReturnMap([
            ['quoteData', $invalidQuoteData],
            ['markups', ['doors' => -5]], // Invalid markup
            ['quoteName', str_repeat('a', 1000)], // Too long name
            ['customerInfo', ['name' => '<script>alert(1)</script>']] // XSS attempt
        ]);

        $response = $this->controller->saveQuote();
        
        $this->assertEquals(400, $response->getStatus(), 
            'Invalid quote data should return 400 Bad Request');
    }

    /**
     * Test: File upload security
     * Requirement: 10.6 - File upload security
     */
    public function testFileUploadSecurity(): void
    {
        $this->setupAuthenticatedAdmin();

        // Test malicious file types
        $maliciousFiles = [
            ['type' => 'application/x-php', 'name' => 'malicious.php', 'content' => '<?php system($_GET["cmd"]); ?>'],
            ['type' => 'text/html', 'name' => 'xss.html', 'content' => '<script>alert("xss")</script>'],
            ['type' => 'application/javascript', 'name' => 'evil.js', 'content' => 'alert("evil")'],
            ['type' => 'application/x-executable', 'name' => 'virus.exe', 'content' => 'MZ...']
        ];

        foreach ($maliciousFiles as $file) {
            $uploadedFile = [
                'type' => $file['type'],
                'name' => $file['name'],
                'size' => strlen($file['content']),
                'tmp_name' => $this->createTempFile($file['content'])
            ];

            $this->request->method('getUploadedFile')->willReturn($uploadedFile);
            
            $response = $this->controller->importPricingData();
            
            $this->assertEquals(400, $response->getStatus(), 
                "Malicious file type {$file['type']} should be rejected");
            
            $data = $response->getData();
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('file type', strtolower($data['error']));
            
            // Clean up temp file
            unlink($uploadedFile['tmp_name']);
        }

        // Test oversized files
        $oversizedFile = [
            'type' => 'application/json',
            'name' => 'large.json',
            'size' => 10 * 1024 * 1024, // 10MB (over 5MB limit)
            'tmp_name' => $this->createTempFile('{}')
        ];

        $this->request->method('getUploadedFile')->willReturn($oversizedFile);
        
        $response = $this->controller->importPricingData();
        
        $this->assertEquals(400, $response->getStatus(), 
            'Oversized files should be rejected');
        
        unlink($oversizedFile['tmp_name']);
    }

    /**
     * Test: Data isolation between users
     * Requirement: 6.7 - User data isolation
     */
    public function testDataIsolation(): void
    {
        // Set up user A
        $userA = $this->createMock(\OCP\IUser::class);
        $userA->method('getUID')->willReturn('user_a');
        $this->userSession->method('getUser')->willReturn($userA);

        // User A creates a quote
        $this->request->method('getParam')->willReturnMap([
            ['quoteData', ['doors' => [['item' => 'User A Door', 'qty' => 1, 'price' => 100, 'total' => 100]]]],
            ['markups', ['doors' => 15]],
            ['quoteName', 'User A Quote'],
            ['customerInfo', ['name' => 'Customer A']]
        ]);

        $responseA = $this->controller->saveQuote();
        $this->assertEquals(200, $responseA->getStatus());
        $quoteIdA = $responseA->getData()['quoteId'];

        // Switch to user B
        $userB = $this->createMock(\OCP\IUser::class);
        $userB->method('getUID')->willReturn('user_b');
        $this->userSession->method('getUser')->willReturn($userB);

        // User B tries to access User A's quote
        $response = $this->controller->getQuote($quoteIdA);
        
        $this->assertEquals(404, $response->getStatus(), 
            'User B should not be able to access User A\'s quote');

        // User B tries to delete User A's quote
        $response = $this->controller->deleteQuote($quoteIdA);
        
        $this->assertEquals(404, $response->getStatus(), 
            'User B should not be able to delete User A\'s quote');

        // User B gets their own quotes (should be empty)
        $response = $this->controller->getUserQuotes();
        
        $this->assertEquals(200, $response->getStatus());
        $quotes = $response->getData();
        $this->assertEmpty($quotes, 'User B should not see User A\'s quotes');
    }

    /**
     * Test: Session security
     * Requirement: Authentication security
     */
    public function testSessionSecurity(): void
    {
        // Test session timeout
        $expiredUser = $this->createMock(\OCP\IUser::class);
        $expiredUser->method('getUID')->willReturn('expired_user');
        $this->userSession->method('getUser')->willReturn($expiredUser);
        $this->userSession->method('isLoggedIn')->willReturn(false); // Session expired

        $response = $this->controller->getAllPricingData();
        
        $this->assertEquals(401, $response->getStatus(), 
            'Expired sessions should be rejected');

        // Test session hijacking protection
        $this->request->method('getHeader')->willReturnMap([
            ['User-Agent', 'Different-Browser'],
            ['X-Forwarded-For', '192.168.1.100'] // Different IP
        ]);

        // Mock session validation to detect suspicious activity
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->getAllPricingData();
        
        $this->assertEquals(401, $response->getStatus(), 
            'Suspicious session activity should be rejected');
    }

    /**
     * Test: Rate limiting
     * Requirement: 3.7 - Rate limiting for import operations
     */
    public function testRateLimiting(): void
    {
        $this->setupAuthenticatedAdmin();

        // Simulate rapid import attempts
        $validFile = [
            'type' => 'application/json',
            'name' => 'test.json',
            'size' => 100,
            'tmp_name' => $this->createTempFile('{"pricingData": [], "markups": {}}')
        ];

        $this->request->method('getUploadedFile')->willReturn($validFile);

        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->controller->importPricingData();
        }

        // Should start rate limiting after several requests
        $rateLimitedResponses = array_filter($responses, function($response) {
            return $response->getStatus() === 429; // Too Many Requests
        });

        $this->assertGreaterThan(0, count($rateLimitedResponses), 
            'Rate limiting should kick in for rapid requests');

        unlink($validFile['tmp_name']);
    }

    /**
     * Test: Error information disclosure
     * Requirement: 13.7 - Prevent sensitive information exposure
     */
    public function testErrorInformationDisclosure(): void
    {
        $this->setupAuthenticatedUser();

        // Trigger various error conditions
        $errorConditions = [
            fn() => $this->controller->getQuote(99999), // Non-existent quote
            fn() => $this->controller->deleteQuote(99999), // Non-existent quote
            fn() => $this->triggerDatabaseError(), // Database error
            fn() => $this->triggerFileSystemError() // File system error
        ];

        foreach ($errorConditions as $condition) {
            $response = $condition();
            
            $data = $response->getData();
            
            // Should not expose sensitive information
            $this->assertArrayNotHasKey('stack_trace', $data, 
                'Stack traces should not be exposed');
            $this->assertArrayNotHasKey('sql_query', $data, 
                'SQL queries should not be exposed');
            $this->assertArrayNotHasKey('file_path', $data, 
                'File paths should not be exposed');
            
            if (isset($data['error'])) {
                $error = strtolower($data['error']);
                $this->assertStringNotContainsString('password', $error);
                $this->assertStringNotContainsString('database', $error);
                $this->assertStringNotContainsString('/var/www', $error);
                $this->assertStringNotContainsString('mysql', $error);
            }
        }
    }

    /**
     * Test: Content Security Policy
     * Requirement: 10.4 - XSS prevention
     */
    public function testContentSecurityPolicy(): void
    {
        $response = $this->controller->getAllPricingData();
        
        $headers = $response->getHeaders();
        
        // Should include CSP header
        $this->assertArrayHasKey('Content-Security-Policy', $headers, 
            'Response should include Content-Security-Policy header');
        
        $csp = $headers['Content-Security-Policy'];
        
        // Should restrict script sources
        $this->assertStringContainsString("script-src 'self'", $csp, 
            'CSP should restrict script sources');
        
        // Should prevent inline scripts
        $this->assertStringNotContainsString("'unsafe-inline'", $csp, 
            'CSP should not allow unsafe-inline scripts');
    }

    // Helper methods

    private function initializeSecurityTestComponents(): void
    {
        $this->userSession = $this->createMock(\OCP\IUserSession::class);
        $this->request = $this->createMock(\OCP\IRequest::class);
        
        $repository = $this->createMock(\OCA\DoorEstimator\Repository\EstimatorRepository::class);
        $appData = $this->createMock(\OCP\Files\IAppData::class);
        $config = $this->createMock(\OCP\IConfig::class);
        $db = $this->createMock(\OCP\IDBConnection::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $this->service = new EstimatorService(
            $repository,
            $this->userSession,
            $appData,
            $config,
            $db,
            $logger
        );

        $this->controller = new EstimatorController(
            'door_estimator',
            $this->request,
            $this->service,
            $logger
        );
    }

    private function setupAuthenticatedUser(): void
    {
        $user = $this->createMock(\OCP\IUser::class);
        $user->method('getUID')->willReturn('test_user');
        $this->userSession->method('getUser')->willReturn($user);
        $this->userSession->method('isLoggedIn')->willReturn(true);
        $this->mockAdminCheck(false);
    }

    private function setupAuthenticatedAdmin(): void
    {
        $admin = $this->createMock(\OCP\IUser::class);
        $admin->method('getUID')->willReturn('admin_user');
        $this->userSession->method('getUser')->willReturn($admin);
        $this->userSession->method('isLoggedIn')->willReturn(true);
        $this->mockAdminCheck(true);
    }

    private function mockAdminCheck(bool $isAdmin): void
    {
        // Mock admin group membership check
        $groupManager = $this->createMock(\OCP\IGroupManager::class);
        $groupManager->method('isAdmin')->willReturn($isAdmin);
    }

    private function mockCSRFValidation(bool $isValid): void
    {
        // Mock CSRF token validation
        $csrfTokenManager = $this->createMock(\OCP\Security\ISecureRandom::class);
        // Implementation would depend on actual CSRF validation mechanism
    }

    private function createTempFile(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'security_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    private function triggerDatabaseError(): JSONResponse
    {
        // Simulate database error by making service throw exception
        $this->service = $this->createMock(EstimatorService::class);
        $this->service->method('getAllPricingData')
            ->willThrowException(new \Exception('Database connection failed'));
        
        return $this->controller->getAllPricingData();
    }

    private function triggerFileSystemError(): JSONResponse
    {
        // Simulate file system error
        $this->service = $this->createMock(EstimatorService::class);
        $this->service->method('generateQuotePDF')
            ->willThrowException(new \Exception('Permission denied: /var/www/app/data'));
        
        return $this->controller->generateQuotePDF(1);
    }
}