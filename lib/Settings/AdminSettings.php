<?php

namespace OCA\DoorEstimator\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IRequest;

class AdminSettings implements ISettings
{
    public function getForm(): TemplateResponse
    {
        // Render a simple admin settings form (placeholder)
        return new TemplateResponse('door_estimator', 'admin', []);
    }

    public function getSection(): string
    {
        return 'door_estimator';
    }

    public function getPriority(): int
    {
        return 50;
    }
}