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
    }

    public function testGetSectionAndPriority()
    {
        $settings = new AdminSettings();
        $this->assertEquals('door_estimator', $settings->getSection());
        $this->assertEquals(50, $settings->getPriority());
    }
}