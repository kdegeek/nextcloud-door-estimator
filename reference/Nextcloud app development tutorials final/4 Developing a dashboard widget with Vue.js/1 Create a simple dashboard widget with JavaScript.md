# Tutorial: Developing a dashboard widget with Vue.js 😺

::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

## Introduction

This tutorial is split into three parts.

In the first part, we learn how to create a simple dashboard widget using pure (or vanilla) JavaScript. This section repeats the steps of creating an app architecture that were introduced and explained in the previous tutorials.

In the second part, we learn how to create a dashboard widget using Vue components.

In the third part, we learn how to create a simple dashboard widget without any JavaScript at all using some recently developed PHP interfaces.

## Outline

**Part 1: Create a simple dashboard widget with JavaScript**

1. Prepare the app skeleton
2. Implement and register the dashboard widget
3. Front-end
4. Network requests
5. Enable and test the app

**Part 2: Create a dashboard widget using Vue components**

1. Implement and register the dashboard widget
2. Front-end
3. Test the app

**Part 3: Create a dashboard widget using** `IAPIWidgetV2`

1. Implement and register the dashboard widget
2. Optional: front-end
3. Test the app

## Part 1: Create a simple dashboard widget with pure JavaScript (without Vue.js)

### 1. Prepare the app skeleton

* Go to the [app skeleton generator](https://apps.nextcloud.com/developer/apps/generate) and generate an app with the name `CatGifsDashboard`
* Move the generated folder `catgifsdashboard` to the apps-extra folder of your local Nextcloud
* In the `appinfo/info.xml` file:
  * remove the `navigations` element, i.e. all lines in between and including `<navigations>` and `</navigations>` (we won’t need a navigation entry in Nextcloud's top menu for this app, as this app will only register widgets for the dashboard)
  * adjust the compatible Nextcloud version to meet the version of your development environment in the `dependencies` element.

    ::: info
    As we are producing a dashboard widget which got introduced in Nextcloud 20, the minimum version should at least be 20 or higher.

    :::
* Remove the directories and files that we will not use:
  * The contents of the **src** directory
  * The **tests** and **templates** directories
  * If they exist, remove the lib/**Db** and lib/**Migration** directories
  * In the lib/**Controller** and lib/**Service** directories, delete all files within
  * remove the files `composer.json` and `psalm.xml`
* Create a `l10n` directory in `catgifsdashboard`, it is needed to make your app translatable. This directory will store the translation files.

  ::: info
  Nextcloud provides mechanisms for internationalization (make an application translatable) and localization (add translations for specific languages). Nextcloud's translation system is powered by [Transifex](https://www.transifex.com/nextcloud/). Your Nextcloud app can be translated by the Nextcloud community as well.

  The automated translation files will be stored in the `l10n` directory. To make this work with the automated translations you do need to configure this, but we will not cover these steps in this tutorial.

  You can find detailed information about how to make your app translatable in the documentation [here](https://docs.nextcloud.com/server/latest/developer_manual/basics/front-end/l10n.html).

  :::

### 2. Implement and register the dashboard widget

::: info
A dashboard widget is first implemented and then registered in `lib/AppInfo/Application.php`.

:::

* First, implement the dashboard widget. Create the `lib/Dashboard` directory and create the file `lib/Dashboard/SimpleWidget.php`, then set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\CatGifsDashboard\Dashboard;
  
  use OCA\CatGifsDashboard\Service\GifService;
  use OCP\AppFramework\Services\IInitialState;
  use OCP\Dashboard\IAPIWidget;
  use OCP\IL10N;
  
  use OCA\CatGifsDashboard\AppInfo\Application;
  use OCP\Util;
  
  class SimpleWidget implements IAPIWidget {
  
  	private $l10n;
  	private $gifService;
  	private $initialStateService;
  	private $userId;
  
  	public function __construct(IL10N $l10n,
  								GifService $gifService,
  								IInitialState $initialStateService,
  								?string $userId) {
  		$this->l10n = $l10n;
  		$this->gifService = $gifService;
  		$this->initialStateService = $initialStateService;
  		$this->userId = $userId;
  	}
  
  	public function getId(): string {
  		return 'catgifsdashboard-simple-widget';
  	}
  
  	public function getTitle(): string {
  		return $this->l10n->t('Simple widget');
  	}
  
  	public function getOrder(): int {
  		return 10;
  	}
  
  	public function getIconClass(): string {
  		return 'icon-catgifsdashboard';
  	}
  
  	public function getUrl(): ?string {
  		return null;
  	}
  
  	public function load(): void {
  		if ($this->userId !== null) {
  			$items = $this->getItems($this->userId);
  			$this->initialStateService->provideInitialState('dashboard-widget-items', $items);
  		}
  
  		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboardSimple');
  		Util::addStyle(Application::APP_ID, 'dashboard');
  	}
  
  	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
  		return $this->gifService->getWidgetItems($userId);
  	}
  }
  ```

::: info
`IAPIWidget` is a PHP interface from the Nextcloud core. You only have to register and define the widget and you are done for the server part. This `SimpleWidget` class will get the dashboard widget items information and load the necessary script and CSS files in the front-end.

In this `SimpleWidget.php` file, we define the widget.

The structure of this file is as follows:

First, some methods providing information about the widget are defined: the ID of the widget, the title of the widget, the position of the widget (this can be a number between 0 and 100, but numbers 0-9 are reserved for shipped apps so we use number 10), and the icon of the widget (which we will define later on in the CSS file).

All these variables, including the icon, are mandatory to define due to the architecture of the dashboard widget.

Second, in the `public function (load): void {}` you see that this file loads the JavaScript and style (CSS) files, which are files we still have to create.

Third, the items that have to be displayed in the widget are loaded. This is done by using our `GifService` class. Implementing this class is done later in the tutorial below.

:::

* Then, register the dashboard widget in the `lib/AppInfo/Application.php` file. Set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\CatGifsDashboard\AppInfo;
  
  use OCA\CatGifsDashboard\Dashboard\SimpleWidget;
  use OCP\AppFramework\App;
  use OCP\AppFramework\Bootstrap\IRegistrationContext;
  use OCP\AppFramework\Bootstrap\IBootContext;
  use OCP\AppFramework\Bootstrap\IBootstrap;
  
  class Application extends App implements IBootstrap {
  	public const APP_ID = 'catgifsdashboard';
  
  	public function __construct(array $urlParams = []) {
  		parent::__construct(self::APP_ID, $urlParams);
  	}
  
  	public function register(IRegistrationContext $context): void {
  		$context->registerDashboardWidget(SimpleWidget::class);
  	}
  
  	public function boot(IBootContext $context): void {
  	}
  }
  ```

  ::: info
  Remember that the Application.php files of all enabled apps are loaded every time a Nextcloud page is loaded.

  This Application.php file registers the `SimpleWidget` (Dashboard widget) class. This class is defined in the `SimpleWidget.php` file from the `Dashboard` directory.

  :::

### 3. Front-end

* Go to the directory of the `catgifsdashboard` app in your local Nextcloud setup.
* Make sure you are using the latest LTS version of Node.js. Run the following to ensure you are using the right version of Node.js and npm:

  ```sh
  nvm use --lts
  ```
* Run the following to install the dependency packages:

  ```sh
  npm install
  ```
* There are a couple dependencies missing. Add these dependencies by running the following two commands:

  ```
  npm i --save @nextcloud/axios @nextcloud/dialogs @nextcloud/initial-state @nextcloud/l10n @nextcloud/router vue-material-design-icons
  ```

  ```
  npm i --save-dev vite-plugin-eslint vite-plugin-stylelint
  ```
* Check if the dependencies were successfully added. The `package.json` file should, for example, contain the following dependencies under the "dependencies" list (the version numbers may differ from those shown here, but that's okay):

  ```json
  "@nextcloud/axios": "^2.5.0",
  "@nextcloud/dialogs": "^3.2.0",
  "@nextcloud/initial-state": "^2.2.0",
  "@nextcloud/l10n": "^2.2.0",
  "@nextcloud/router": "^2.2.1",
  "@nextcloud/vue": "^7.12.8",
  "vue": "^2.7.16",
  "vue-material-design-icons": "^5.3.0"
  ```

  Similarly, the `vite-plugin-eslint` and `vite-plugin-stylelint` packages should be found under "devDependencies". If any of them are missing, add them with the command `npm i --save[-dev] <insert dependency here>`

  ::: info
  Each Nextcloud app has different dependencies. If you want to start another project outside these tutorials, have a look at other apps to see which dependencies they use.

  For this app, we use:

  \- nextcloud/axios: to make network requests to the server in the front-end

  \- nextcloud/dialogs: to display temporary messages in the top-right page corner (errors, success, warnings, etc...)

  \- nextcloud/initial-state: to load data injected by the server side during page creation

  \- nextcloud/l10n: provides the translation functions

  \- nextcloud/router: provides many things, but in this app we use it to generate API endpoint URLs to pass to axios. We will use it, for example, in the Vue widget to generate the 'show more' button if there are too many GIFs in the list.

  \- nextcloud/vue: the Vue library providing a lot of Nextcloud components

  \- vue: this is Vue.js

  \- vue-material-design-icons: a library to use icons in Vue.js

  We also use these packages for app development only (i.e. they are not required for running the app):

  \- vite-plugin-eslint: ESLint plugin for Vite

  \- vite-plugin-stylelint: Stylelint plugin for Vite

  :::
* Write the JavaScript source file of the simple dashboard widget. This is the file that will actually be loaded and executed in the dashboard page! The SimpleWidget class will load this file.

  Create the `src/dashboardSimple.js` file and set its content to:

```js
import {
	translate as t,
	// translatePlural as n,
} from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'

function renderWidget(el) {
	const gifItems = loadState('catgifsdashboard', 'dashboard-widget-items')

	const paragraph = document.createElement('p')
	paragraph.textContent = t('catgifsdashboard', 'You can define the frontend part of a widget with plain Javascript.')
	el.append(paragraph)

	const paragraph2 = document.createElement('p')
	paragraph2.textContent = t('catgifsdashboard', 'Here is the list of files in your gif folder:')
	el.append(paragraph2)

	const list = document.createElement('ul')
	list.classList.add('widget-list')
	gifItems.forEach(item => {
		const li = document.createElement('li')
		li.textContent = item.title
		list.append(li)
	})
	el.append(list)
}

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('catgifsdashboard-simple-widget', (el, { widget }) => {
		renderWidget(el)
	})
})
```

::: info
You are free to write the front-end scripts exactly how you like.

In this script, we wait for the page to be fully loaded and then tell the dashboard we want to register a widget. While doing that, we define a function which receives the widget HTML element created by the dashboard (el). We can then manipulate this element, which we do in our `renderWidget` function.

In the `renderWidget` function we create HTML elements to append in our widget. In this case we create two paragraphs and we list the GIF files names.

Note that we are using data from the initial-state. Perhaps you remember from the previous tutorials that this means that the data is only loaded on page load so this data is static. So if you load the dashboard page, and the content is listed, and you delete a file, you will not see this change in the dashboard widget unless you refresh the page.

On the top you see an import statement for translations. To make the strings translatable, every string is structured as `t('app ID here', 'string here')`

:::

* Configure Vite: edit the `vite.config.js` file and set its content to:

  ```js
  import { createAppConfig } from "@nextcloud/vite-config";
  import { join, resolve } from "path";
  import eslint from "vite-plugin-eslint";
  import stylelint from "vite-plugin-stylelint";
  
  const isProduction = process.env.NODE_ENV === "production";
  
  export default createAppConfig(
    {
      dashboardSimple: resolve(join("src", "dashboardSimple.js")),
    },
    {
    	config: {
    		css: {
    			modules: {
    				localsConvention: "camelCase",
    			},
    		},
    		plugins: [eslint(), stylelint()],
    	},
    	inlineCSS: { relativeCSSInjection: true },
    	minify: isProduction,
      createEmptyCSSEntryPoints: true,
      extractLicenseInformation: true,
      thirdPartyLicense: false,
    }
  );
  ```

  ::: info
  We recommend importing the Nextcloud Vite configuration (`@nextcloud/vite-config`) even if you are not using Vue.js. This configuration includes ESLint and Stylelint settings to respect our code style.

  We extend this configuration by adding ESLint and Stylelint checks on compilation. This is not mandatory. For example, you can also run those check manually with npm scripts.

  We define an explicit rule to compile the `src/dashboardSimple.js` to `js/catgifsdashboard-dashboardSimple.mjs`

  :::
* Now, run the following to compile the JavaScript source files:

  ```sh
  npm run dev
  ```

### 4. Handle the network requests

* Implement the GifController. Create the `lib/Controller/GifController.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\CatGifsDashboard\Controller;
  
  use OC\User\NoUserException;
  use OCA\CatGifsDashboard\Service\GifService;
  use OCP\AppFramework\Http;
  use OCP\AppFramework\Http\Attribute\FrontpageRoute;
  use OCP\AppFramework\Http\Attribute\NoAdminRequired;
  use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
  use OCP\AppFramework\Http\DataDownloadResponse;
  use OCP\AppFramework\Services\IInitialState;
  use OCP\Files\InvalidPathException;
  use OCP\Files\NotFoundException;
  use OCP\Files\NotPermittedException;
  use OCP\Lock\LockedException;
  use OCP\AppFramework\Controller;
  use OCP\AppFramework\Http\DataResponse;
  use OCP\IRequest;
  
  class GifController extends Controller {
  	/**
  	 * @var string|null
  	 */
  	private $userId;
  	/**
  	 * @var GifService
  	 */
  	private $gifService;
  
  	public function __construct(string        $appName,
  								IRequest      $request,
  								IInitialState $initialStateService,
  								GifService    $gifService,
  								?string       $userId)
  	{
  		parent::__construct($appName, $request);
  		$this->initialStateService = $initialStateService;
  		$this->userId = $userId;
  		$this->gifService = $gifService;
  	}
  
  	/**
  	 * @param int $fileId
  	 * @return DataDownloadResponse|DataResponse
  	 * @throws InvalidPathException
  	 * @throws NoUserException
  	 * @throws NotFoundException
  	 * @throws NotPermittedException
  	 * @throws LockedException
  	 */
  	#[NoAdminRequired]
  	#[NoCSRFRequired]
  	#[FrontpageRoute(verb: 'GET', url: '/gif/{fileId}')]
  	public function getGifFile(int $fileId) {
  		$file = $this->gifService->getGifFile($this->userId, $fileId);
  		if ($file !== null) {
  			$response = new DataDownloadResponse(
  				$file->getContent(),
  				'',
  				$file->getMimeType()
  			);
  			$response->cacheFor(60 * 60);
  			return $response;
  		}
  
  		return new DataResponse('', Http::STATUS_NOT_FOUND);
  	}
  }
  ```

::: info
A short recap of the last tutorials: Controllers can handle network requests and they can respond with data or a template.

This controller will respond with a data response (a GIF file content).

To make the file more readable we choose to split the implementation of retrieving those GIF files into a separate class: `GifService`. So the service does the action and the controller is only receiving the request and building the response. When you want to do complex actions you do this in another class. This is to avoid complexity in the controllers and being able to reuse pieces of code. Building this `GifService` class is the next step.

:::

* Create a new `lib/Service/GifService.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\CatGifsDashboard\Service;
  
  use OC\Files\Node\File;
  use OC\Files\Node\Node;
  use OC\User\NoUserException;
  use OCA\CatGifsDashboard\AppInfo\Application;
  use OCP\Dashboard\Model\WidgetItem;
  use OCP\Files\Folder;
  use OCP\Files\InvalidPathException;
  use OCP\Files\IRootFolder;
  use OCP\Files\NotFoundException;
  use OCP\Files\NotPermittedException;
  use OCP\IURLGenerator;
  use Psr\Log\LoggerInterface;
  
  
  class GifService {
  
  	public const GIF_DIR_NAME = 'gifs';
  
  	/**
  	 * @var IRootFolder
  	 */
  	private $root;
  	/**
  	 * @var LoggerInterface
  	 */
  	private $logger;
  	/**
  	 * @var IURLGenerator
  	 */
  	private $urlGenerator;
  
  	public function __construct (IRootFolder $root,
  								LoggerInterface $logger,
  								IURLGenerator $urlGenerator) {
  		$this->root = $root;
  		$this->logger = $logger;
  		$this->urlGenerator = $urlGenerator;
  	}
  
  	/**
  	 * @param string $userId
  	 * @return array|string[]
  	 * @throws NotFoundException
  	 * @throws NotPermittedException
  	 * @throws NoUserException
  	 */
  	public function getGifFiles(string $userId): array {
  		$userFolder = $this->root->getUserFolder($userId);
  		if ($userFolder->nodeExists(self::GIF_DIR_NAME)) {
  			$gifDir = $userFolder->get(self::GIF_DIR_NAME);
  			if ($gifDir instanceof Folder) {
  				$nodeList = $gifDir->getDirectoryListing();
  				return array_filter($nodeList, static function (Node $node) {
  					return $node instanceof File;
  				});
  			} else {
  				return [
  					'error' => '/' . self::GIF_DIR_NAME . ' is a file',
  				];
  			}
  		}
  		return [];
  	}
  
  	/**
  	 * @param string $userId
  	 * @param int $fileId
  	 * @return File|null
  	 * @throws NoUserException
  	 * @throws NotFoundException
  	 * @throws NotPermittedException
  	 * @throws InvalidPathException
  	 */
  	public function getGifFile(string $userId, int $fileId): ?File {
  		$userFolder = $this->root->getUserFolder($userId);
  		if ($userFolder->nodeExists(self::GIF_DIR_NAME)) {
  			$gifDir = $userFolder->get(self::GIF_DIR_NAME);
  			if ($gifDir instanceof Folder) {
  				$gifDirId = $gifDir->getId();
  				// Folder::getById() returns a list because one file ID can be found multiple times
  				// if it was shared multiple times for example
  				$files = $gifDir->getById($fileId);
  				foreach ($files as $file) {
  					if ($file instanceof File && $file->getParent()->getId() === $gifDirId) {
  						return $file;
  					}
  				}
  			}
  		}
  		$this->logger->debug('File ' . $fileId . ' was not found in the gif folder', ['app' => Application::APP_ID]);
  		return null;
  	}
  
  	public function getWidgetItems(string $userId): array {
  		$files = $this->getGifFiles($userId);
  		if (isset($files['error'])) {
  			return [];
  		}
  		return array_map(function (File $file) {
  			return new WidgetItem(
  				$file->getName(),
  				'',
  				$this->urlGenerator->linkToRouteAbsolute('files.View.showFile', ['fileid' => $file->getId()]),
  				// if we want to get a preview instead of the full file (gif previews are static)
  				// $this->urlGenerator->linkToRouteAbsolute('core.Preview.getPreviewByFileId', ['x' => 32, 'y' => 32, 'fileId' => $file->getId()]),
  				$this->urlGenerator->linkToRouteAbsolute('catgifsdashboard.gif.getGifFile', ['fileId' => $file->getId()]),
  				(string) $file->getMTime()
  			);
  		}, $files);
  	}
  }
  ```

::: info
This `getWidgetItems` method of the GifService class returns the items that will be loaded in the widget.

First, the directory where to look for the GIFs is defined: the directory has to be called `gifs` and the user has to make this directory manually in their Nextcloud Files app.

Following, there are three methods:

`getGifFiles` `getGifFile` `getWidgetItems`

The `getGifFiles` method first confirms that the defined GIFs directory ( `gifs`) is indeed a folder. If so, it returns an array of files in the GIFs directory.

Each file has a corresponding file ID. The `getGifFile` function returns a file's content given its file ID. So this is used when each GIF image is loaded by the front-end.

The `getWidgetItems` method returns the items data provided to the widget.

:::

* Define the style. Create the `css` directory. Create a new `css/dashboard.css` file and set its content to:

  ```css
  .icon-catgifsdashboard {
  	background-image: url('../img/app-dark.svg');
  	filter: var(--background-invert-if-dark);
  }
  
  .widget-list li {
  	list-style-type: disc;
  }
  
  #app-dashboard .panels .panel--header h2 {
  	display: flex;
  }
  ```

### 5. Enable and test the app

Within the Nextcloud instance:

* Enable the Cat Gifs Dashboard app from the app settings.
* Open the Files app and create a `gifs` directory. Add your favorite cat GIFs in this directory.
* Go to the Dashboard app and click the **Customize** button. Enable the 'Simple widget'.

Result:

![Screenshot 2023-02-09 at 12.55.28.png](.attachments.7071750/Screenshot%202023-02-09%20at%2012.55.28.png)