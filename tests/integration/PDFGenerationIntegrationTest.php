<?php

namespace OCA\DoorEstimator\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration test for PDF generation functionality
 */
class PDFGenerationIntegrationTest extends TestCase
{
    public function testTCPDFBasicFunctionality(): void
    {
        // Test that TCPDF can create a basic PDF
        $pdf = new \TCPDF();
        $pdf->SetCreator('Test');
        $pdf->SetTitle('Test PDF');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Test PDF Content', 0, 1);
        
        $pdfContent = $pdf->Output('', 'S');
        
        $this->assertIsString($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
        $this->assertGreaterThan(1000, strlen($pdfContent)); // PDF should have reasonable size
    }
    
    public function testHTMLToPDFConversion(): void
    {
        // Test HTML to PDF conversion with TCPDF
        $html = '
        <h1>Test Quote</h1>
        <table border="1">
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
            </tr>
            <tr>
                <td>Test Door</td>
                <td>1</td>
                <td>$150.00</td>
            </tr>
        </table>
        <p><strong>Total: $150.00</strong></p>
        ';
        
        $pdf = new \TCPDF();
        $pdf->SetCreator('Door Estimator Test');
        $pdf->SetTitle('HTML Test');
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $pdfContent = $pdf->Output('', 'S');
        
        $this->assertIsString($pdfContent);
        $this->assertStringStartsWith('%PDF', $pdfContent);
        $this->assertGreaterThan(2000, strlen($pdfContent)); // Should be larger with HTML content
    }
    
    public function testPDFFileNamingPattern(): void
    {
        // Test the PDF filename pattern used in the service
        $quoteId = 123;
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = sprintf('quote_%d_%s.pdf', $quoteId, $timestamp);
        
        $this->assertMatchesRegularExpression('/^quote_\d+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.pdf$/', $fileName);
        $this->assertStringStartsWith('quote_123_', $fileName);
        $this->assertStringEndsWith('.pdf', $fileName);
    }
    
    public function testEstimatorUtilsFormatSectionName(): void
    {
        // Test section name formatting
        $this->assertEquals('Doors', \OCA\DoorEstimator\Service\EstimatorUtils::formatSectionName('doors'));
        $this->assertEquals('Door Options', \OCA\DoorEstimator\Service\EstimatorUtils::formatSectionName('doorOptions'));
        $this->assertEquals('Glass Inserts', \OCA\DoorEstimator\Service\EstimatorUtils::formatSectionName('inserts'));
        $this->assertEquals('Frames', \OCA\DoorEstimator\Service\EstimatorUtils::formatSectionName('frames'));
        $this->assertEquals('Hardware', \OCA\DoorEstimator\Service\EstimatorUtils::formatSectionName('hardware'));
    }
    
    public function testEstimatorUtilsGetSectionMarkup(): void
    {
        // Test markup calculation
        $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];
        
        $this->assertEquals(15, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('doors', $markups));
        $this->assertEquals(15, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('doorOptions', $markups));
        $this->assertEquals(15, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('inserts', $markups));
        $this->assertEquals(12, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('frames', $markups));
        $this->assertEquals(12, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('frameOptions', $markups));
        $this->assertEquals(18, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('hardware', $markups));
        $this->assertEquals(18, \OCA\DoorEstimator\Service\EstimatorUtils::getSectionMarkup('locksets', $markups));
    }
    
    public function testQuoteTotalCalculation(): void
    {
        // Test quote total calculation
        $quoteData = [
            'doors' => [
                ['qty' => 2, 'price' => 150.00], // 300.00
                ['qty' => 1, 'price' => 200.00]  // 200.00
            ],
            'frames' => [
                ['qty' => 3, 'price' => 75.00]   // 225.00
            ],
            'hardware' => [
                ['qty' => 1, 'price' => 50.00]   // 50.00
            ]
        ];
        
        $markups = ['doors' => 15, 'frames' => 12, 'hardware' => 18];
        
        $total = \OCA\DoorEstimator\Service\EstimatorUtils::calculateQuoteTotal($quoteData, $markups);
        
        // Expected: (300+200)*1.15 + 225*1.12 + 50*1.18 = 575 + 252 + 59 = 886
        $this->assertEquals(886.0, $total);
    }
}