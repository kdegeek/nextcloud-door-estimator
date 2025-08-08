<?php

declare(strict_types=1);

namespace OCA\DoorEstimator\Controller;

use OCA\DoorEstimator\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller
{

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[FrontpageRoute(verb: 'GET', url: '/')]
    #[OpenAPI(OpenAPI::SCOPE_IGNORE)]
    public function index(): TemplateResponse
    {
        return new TemplateResponse(
            Application::APP_ID,
            'main'
        );
    }
}