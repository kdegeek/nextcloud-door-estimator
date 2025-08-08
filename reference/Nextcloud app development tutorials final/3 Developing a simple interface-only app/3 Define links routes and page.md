# 3 Define links routes and page

::: info
In this section of the tutorial, we will start with working on the `info.xml` file that you might remember from the tutorial about creating a Hello World app.

In this `info.xml` file we will define a navigation item so there will be a button in the top bar of your Nextcloud interface to access your app. This navigation item is defined by the <route> tag. The <route> value defines the navigation item link target (Step 1).

The <route> value points to a controller method of our app (or any other app). Controllers handle network requests and can answer with either some data (string, array, etc.) or a template (a piece a HTML).

The page controller of this tutorial provides two public methods:

one which returns a template (which is the page we will display), and another which handles AJAX calls (requests to the server). The controller performs actions on the server side and then returns data (Steps 2).

The template (Step 3) defines the content of a web page plus the CSS style files (that we will create in Step 4) and scripts that should be loaded (the production script that we created in the previous step).

The schematic overview below shows a summary of how all the components of this tutorial relate and includes the previous step about front-end scripts.

*Note: as of 2025, Vite has replaced webpack as the JavaScript bundler of choice. While webpack is still mentioned in the schematic below, the overall diagram still holds true today.*

:::

![Screenshot 2022-11-17 at 14.16.49.png](.attachments.6686436/Screenshot%202022-11-17%20at%2014.16.49.png)

### Step 1: Add the navigation item in the `info.xml` file

In the `appinfo/info.xml` file, replace the content of the navigation tag (everything in between `<navigation>` and `</navigation>`) with the following:

```
<id>catgifs</id>
<name>Cat Gifs</name>
<!-- syntax is APP_ID.CONTROLLER_NAME.METHOD_NAME -->
<route>catgifs.page.mainPage</route>
<icon>app.svg</icon>
<order>8</order>
```

::: info
The <navigation> element is inside the **<navigations>** element.

The <navigation> element defines the menu link.

:::

### Step 2: Implement the PageController class

* Edit the `lib/AppInfo/Application.php` file and replace its content with the following:

```php
<?php

declare(strict_types=1);

namespace OCA\CatGifs\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/**
 * Class Application
 *
 * @package OCA\CatGifs\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'catgifs';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}
```

* Edit the `lib/Controller/PageController.php` file and replace its content with the following:

```php
<?php

declare(strict_types=1);

namespace OCA\CatGifs\Controller;

use Exception;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use Throwable;

use OCA\CatGifs\AppInfo\Application;
use OCA\CatGifs\Service\ImageService;

class PageController extends Controller {

	public const FIXED_GIF_SIZE_CONFIG_KEY = 'fixed_gif_size';

	public const CONFIG_KEYS = [
		self::FIXED_GIF_SIZE_CONFIG_KEY,
	];

	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(string $appName,
								IRequest $request,
								IInitialState $initialStateService,
								IConfig $config,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->initialStateService = $initialStateService;
		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * This returns the template of the main app's page
	 * It adds some initialState data (file list and fixed_gif_size config value)
	 * and also provide some data to the template (app version)
	 *
	 * @return TemplateResponse
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')] // this tells Nextcloud to link GET requests to /index.php/apps/catgifs/ with the "mainPage" method
	public function mainPage(): TemplateResponse {
		$fileNameList = $this->getGifFilenameList();
		$fixedGifSize = $this->config->getUserValue($this->userId, Application::APP_ID, self::FIXED_GIF_SIZE_CONFIG_KEY);
		$myInitialState = [
			'file_name_list' => $fileNameList,
			self::FIXED_GIF_SIZE_CONFIG_KEY => $fixedGifSize,
		];
		$this->initialStateService->provideInitialState('tutorial_initial_state', $myInitialState);

		$appVersion = $this->config->getAppValue(Application::APP_ID, 'installed_version');
		return new TemplateResponse(
			Application::APP_ID,
			'index',
			[
				'app_version' => $appVersion,
			]
		);
	}

	/**
	 * Get the names of files stored in apps/my_app/img/gifs/
	 *
	 * @return array
	 */
	private function getGifFilenameList(): array {
		$path = dirname(__DIR__, 2) . '/img/gifs';
		$names = array_filter(scandir($path), static function ($name) {
			return $name !== '.' && $name !== '..';
		});
		return array_values($names);
	}

	/**
	 * This is an API endpoint to set a user config value
	 * It returns a simple DataResponse: a message to be displayed
	 *
	 * @param string $key
	 * @param string $value
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/config')] // this tells Nextcloud to link PUT requests to /index.php/apps/catgifs/config with the "saveConfig" method
	public function saveConfig(string $key, string $value): DataResponse {
		if (in_array($key, self::CONFIG_KEYS, true)) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
			return new DataResponse([
				'message' => 'Everything went fine',
			]);
		}
		return new DataResponse([
			'error_message' => 'Invalid config key',
		], Http::STATUS_FORBIDDEN);
	}
}
```

::: info
An app can define its own API endpoints. An endpoint has a route and a controller method to handle the network requests.

The `FrontpageRoute` attribute defines which method will be called when you browse to a URL defined in your app.

The first makes the link between the "/" URL and the PageController's `mainPage` method. The "/" URL is actually relative to your app's path in Nextcloud. The full URL is:

<http://nextcloud.local/index.php/apps/catgifs/>

Do you remember about saving the setting of the GIF size button in the front-end script we created previously? The "/config" route is requested by the script when the GIF-size button is clicked.

If a route has been defined correctly, the target controller method handles the HTTP request and responds with some **data** or a **template**.

A **data response** is not a full page but just data, for example, a string or an array.

The `saveConfig` method (accessed by the "/config" route), will handle the requests made by the front-end script. It will save the setting value and return a string as a response. The string is the message ("Everything went fine" or "Invalid config key"). The response could include any type of data that the front-end script needs or asks for.

A **template response** is when you get a full page.

In this controller, you can see an example of template response: the code refers to the `index` template that we will edit in the next step.

You can inject values into the template response when you create the *TemplateResponse*. For example, we do that with the `app_version` value.

You can also pass data to the front-end scripts, like what we do here with the initial state mechanism. We pass the list of GIF files with the initial state. The list is just provided when the page loads, and after that it won't be updated.

:::

### Step 3: Write the template file

Edit the `templates/index.php` file and set its content to:

```php
<?php
use OCP\Util;
$appId = OCA\CatGifs\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-main');
Util::addStyle($appId, 'main');
?>

<div id="app-content">
<?php
if ($_['app_version']) {
    // you can get the values you injected as template parameters in the "$_" array
    echo '<h3>Cat Gif app version: ' . $_['app_version'] . '</h3>';
}
?>
    <div id="catgifs"></div>
</div>
```

::: info
In the template, we can call some functions to load the scripts and the CSS files.

With CSS files we can define the page style, for example to align the cat GIFs in the center. This is the next step.

:::

### Step 4: Define the style

Create a new `css/main.css` file and set its content to:

```css
#app-content {
	display: flex;
	flex-direction: column;
	align-items: center;
}

#catgifs {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
}

#catgifs > button {
	margin-bottom: 30px;
}

.gif-wrapper {
	display: flex;
	align-items: center;
}

#catgifs.fixed-size img {
	width: auto;
	height: 100px;
}
```

### Step 5: Add cat GIFs

* Create a `img/gifs/` directory.
* Add your favorite cat GIFs in this directory.

### Step 6: Compile the scripts

As a precaution, let's compile your source script again in case you made a change after the last time you compiled it. To do that, run this command in your app's directory:

```
npm run build
```

### Step 7: Enable the app

Find and enable the app in the apps settings page of Nextcloud, and refresh the page.

::: warn
Nothing to download here, we have the app as we wrote it.

No need to specify that this happens in a "local Nextcloud", the whole tutorial happens in this setup.

:::

# **Enjoy your cat GIFs!** 😻

Everything is now ready to try the app we just implemented. The app navigation item should appear in Nextcloud's top menu. Click on it and you should be able to see your cat GIFs!

If something is wrong, check the Nextcloud server logs or [ask for help in the Nextcloud forum](https://help.nextcloud.com/t/new-tutorial-how-to-develop-a-simple-interface-only-app/150862).