<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript('door_estimator', 'door-estimator');
Util::addStyle('door_estimator', 'style');

?>

<div id="door-estimator-app" class="min-h-screen bg-gray-100">
    <div class="loading text-center py-20">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-2 text-gray-600">Loading Door Estimator...</p>
    </div>
</div>