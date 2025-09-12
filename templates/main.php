<?php
declare(strict_types=1);

/**
 * Door Estimator App Main Template
 * 
 * This template serves as the entry point for the Vue.js application.
 * It includes necessary scripts and styles, and provides the mounting point
 * for the Vue app with proper Nextcloud integration.
 */

use OCP\Util;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;

// Set Content Security Policy headers for XSS prevention
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Load the main application JavaScript bundle
Util::addScript('door_estimator', 'door-estimator');

// Load application styles
Util::addStyle('door_estimator', 'style');

// Add initial state for the application
\OCP\Util::addInitialState('door_estimator', 'app_config', [
    'version' => \OC_App::getAppVersion('door_estimator'),
    'user_id' => \OC_User::getUser(),
    'is_admin' => \OC_User::isAdminUser(\OC_User::getUser()),
    'max_upload_size' => \OCP\Util::maxUploadFilesize(),
    'csrf_token' => \OC::$server->getCSRFTokenManager()->getToken()->getEncryptedValue(),
]);

?>

<div id="content-vue" class="app-door-estimator">
    <div id="door-estimator-app">
        <!-- Loading state while Vue app initializes -->
        <div class="loading-container" style="display: flex; justify-content: center; align-items: center; height: 200px;">
            <div class="icon-loading" style="margin-right: 10px;"></div>
            <span><?php p($l->t('Loading Door Estimator...')); ?></span>
        </div>
    </div>
</div>

<style>
/* Ensure proper app container styling */
.app-door-estimator {
    width: 100%;
    height: 100%;
    min-height: calc(100vh - 50px);
}

#door-estimator-app {
    width: 100%;
    height: 100%;
}

/* Loading state styling */
.loading-container {
    color: var(--color-text-lighter);
    font-size: 14px;
}

/* Ensure proper integration with Nextcloud theming */
.app-door-estimator * {
    box-sizing: border-box;
}
</style>