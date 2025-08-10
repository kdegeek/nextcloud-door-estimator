<?php

use PHPUnit\Framework\TestCase;
use OCA\DoorEstimator\Settings\AdminSettings;
use OCP\AppFramework\Http\TemplateResponse;

class AdminSettingsTest extends TestCase
{
    public function testImplementsISettings()
    {
        $settings = new AdminSettings();
        $this->assertInstanceOf(AdminSettings::class, $settings);
        $this->assertTrue(method_exists($settings, 'getForm'));
        $this->assertTrue(method_exists($settings, 'getSection'));
        $this->assertTrue(method_exists($settings, 'getPriority'));
    }

    public function testGetFormReturnsTemplateResponse()
    {
        $settings = new AdminSettings();
        $response = $settings->getForm();
        $this->assertInstanceOf(TemplateResponse::class, $response);

        // Check template name and app name if available
        if (method_exists($response, 'getTemplateName')) {
            $this->assertSame('admin', $response->getTemplateName());
        }
        if (method_exists($response, 'getAppName')) {
            $this->assertSame('door_estimator', $response->getAppName());
        }
    }

    public function testGetSectionAndPriority()
    {
        $settings = new AdminSettings();
        $this->assertEquals('door_estimator', $settings->getSection());
        $this->assertIsString($settings->getSection());
        $this->assertEquals(50, $settings->getPriority());
        $this->assertIsInt($settings->getPriority());
    }
    public function testCanInstantiateMultipleTimes()
    {
        $settings1 = new AdminSettings();
        $settings2 = new AdminSettings();
        $this->assertInstanceOf(AdminSettings::class, $settings1);
        $this->assertInstanceOf(AdminSettings::class, $settings2);
        $this->assertNotSame($settings1, $settings2);
    }

    public function testIdempotentMethodCalls()
    {
        $settings = new AdminSettings();

        $form1 = $settings->getForm();
        $form2 = $settings->getForm();
        $this->assertEquals($form1, $form2);

        $section1 = $settings->getSection();
        $section2 = $settings->getSection();
        $this->assertSame($section1, $section2);

        $priority1 = $settings->getPriority();
        $priority2 = $settings->getPriority();
        $this->assertSame($priority1, $priority2);
    }
}