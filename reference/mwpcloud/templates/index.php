<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\MWPCloud\AppInfo\Application::APP_ID, OCA\MWPCloud\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\MWPCloud\AppInfo\Application::APP_ID, OCA\MWPCloud\AppInfo\Application::APP_ID . '-main');

?>

<div id="mwpcloud"></div>
