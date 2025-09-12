<?php

namespace OCA\DoorEstimator\Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use OCA\DoorEstimator\Service\EstimatorService;
use OCA\DoorEstimator\Repository\EstimatorRepository;
use OCP\IUserSession;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use OCP\IUser;
use OCP\Files\IFolder;
use OCP\Files\IFile;
use OCP\Files\NotFoundException;

/**
 * Test PDF generation functionality
 */
class PDFGenerationTest extends TestCase
{
    private EstimatorService $service;
    private MockObject $repository;
    private MockObject $userSession;
    private MockObject $appData;
    private MockObject $config;
    private MockObject $db;
    private MockObject $logger;
    private MockObject $user;
    private MockObject $folder;
    private MockObject $file;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EstimatorRepository::class);
        $this->userSession = $this->createMock(IUserSession::class);
        $this->appData = $this->createMock(IAppData::class);
        $this->config = $this->createMock(IConfig::class);
        $this->db = $this->createMock(IDBConnection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->user = $this->createMock(IUser::class);
        $this->folder = $this->createMock(IFolder::class);
        $this->file = $this->createMock(IFile::class);

        $this->service = new EstimatorService(
            $this->repository,
            $this->userSession,
            $this->appData,
            $this->config,
            $this->db,
            $this->logger
        );
    }

    public function testGeneratePDFWithValidQuote(): void
    {
        $quoteId = 123;
        $mockQuote = [
            'id' => $quoteId,
            'quote_name' => 'Test Quote',
            'customer_info' => [
                'name' => 'John Doe',
                'company' => 'Test Company',
                'email' => 'john@test.com',
                'phone' => '555-1234'
            ],
            'quote_data' => [
                'doors' => [
                    [
                        'item' => 'Standard Door',
                        'qty' => 2,
                        'price' => 150.00
                    ]
                ],
                'frames' => [
                    [
                        'item' => 'Metal Frame',
                        'qty' => 2,
                        'price' => 75.00,
                        'frameType' => 'HM Drywall'
                    ]
                ]
            ],
            'markups' => [
                'doors' => 15,
                'frames' => 12,
                'hardware' => 18
            ],
            'total_amount' => 513.00,
            'created_at' => '2025-01-15 10:30:00',
            'updated_at' => '2025-01-15 10:30:00'
        ];

        // Mock user authentication
        $this->user->method('getUID')->willReturn('testuser');
        $this->userSession->method('getUser')->willReturn($this->user);

        // Mock database query for getQuote
        $queryBuilder = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
        $queryResult = $this->createMock(\OCP\DB\IResult::class);
        
        $this->db->method('getQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturnArgument(0);
        $queryBuilder->method('expr')->willReturnSelf();
        $queryBuilder->method('eq')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($queryResult);
        
        $queryResult->method('fetch')->willReturn([
            'id' => $quoteId,
            'quote_name' => 'Test Quote',
            'customer_info' => json_encode($mockQuote['customer_info']),
            'quote_data' => json_encode($mockQuote['quote_data']),
            'markups' => json_encode($mockQuote['markups']),
            'total_amount' => 513.00,
            'created_at' => '2025-01-15 10:30:00',
            'updated_at' => '2025-01-15 10:30:00'
        ]);

        // Mock file storage
        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->method('newFile')->willReturn($this->file);
        $this->file->method('putContent')->willReturn(true);

        // Test PDF generation
        $result = $this->service->generateQuotePDF($quoteId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('downloadUrl', $result);
        $this->assertStringStartsWith('quote_123_', $result['path']);
        $this->assertStringEndsWith('.pdf', $result['path']);
        $this->assertStringContains('/api/quotes/123/pdf/download/', $result['downloadUrl']);
    }

    public function testGeneratePDFWithInvalidQuoteId(): void
    {
        $this->expectException(\OCA\DoorEstimator\Exception\ValidationException::class);
        $this->expectExceptionMessage('Invalid quote ID');

        $this->service->generateQuotePDF(0);
    }

    public function testGetPDFFileContentWithValidFile(): void
    {
        $fileName = 'quote_123_2025-01-15_10-30-00.pdf';
        $mockContent = '%PDF-1.4 mock content';

        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->method('getFile')->willReturn($this->file);
        $this->file->method('getContent')->willReturn($mockContent);

        $result = $this->service->getPDFFileContent($fileName);

        $this->assertEquals($mockContent, $result);
    }

    public function testGetPDFFileContentWithInvalidFileName(): void
    {
        $fileName = 'invalid_filename.pdf';

        $result = $this->service->getPDFFileContent($fileName);

        $this->assertNull($result);
    }

    public function testGetPDFFileContentWithNonExistentFile(): void
    {
        $fileName = 'quote_123_2025-01-15_10-30-00.pdf';

        $this->appData->method('getFolder')->willReturn($this->folder);
        $this->folder->method('getFile')->willThrowException(new NotFoundException());

        $result = $this->service->getPDFFileContent($fileName);

        $this->assertNull($result);
    }

    public function testPDFGenerationCreatesQuotesFolder(): void
    {
        $quoteId = 123;
        
        // Mock user authentication
        $this->user->method('getUID')->willReturn('testuser');
        $this->userSession->method('getUser')->willReturn($this->user);

        // Mock database query for getQuote
        $queryBuilder = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
        $queryResult = $this->createMock(\OCP\DB\IResult::class);
        
        $this->db->method('getQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturnArgument(0);
        $queryBuilder->method('expr')->willReturnSelf();
        $queryBuilder->method('eq')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($queryResult);
        
        $queryResult->method('fetch')->willReturn([
            'id' => $quoteId,
            'quote_name' => 'Test Quote',
            'customer_info' => null,
            'quote_data' => json_encode(['doors' => []]),
            'markups' => json_encode(['doors' => 15, 'frames' => 12, 'hardware' => 18]),
            'total_amount' => 0.00,
            'created_at' => '2025-01-15 10:30:00',
            'updated_at' => '2025-01-15 10:30:00'
        ]);

        // Mock folder creation when it doesn't exist
        $this->appData->method('getFolder')
            ->willThrowException(new NotFoundException());
        $this->appData->method('newFolder')
            ->with('quotes')
            ->willReturn($this->folder);
        $this->folder->method('newFile')->willReturn($this->file);
        $this->file->method('putContent')->willReturn(true);

        $result = $this->service->generateQuotePDF($quoteId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
    }
}