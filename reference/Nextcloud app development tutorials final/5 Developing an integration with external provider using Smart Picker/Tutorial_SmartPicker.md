# Tutorial: Develop an integration with an external provider using Smart Picker

::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

## 1: Pull the latest development release of Nextcloud

::: info
It is best practice to develop against the latest development release of Nextcloud.

As the Smart Picker came with Nextcloud 26, you at least need to run a development environment running Nextcloud 26 or later.

First, we will show you how to ensure you are developing on the latest development environment.

:::

* go into the `nextcloud-docker-dev/workspace/server` directory and run:

```
git pull origin master
git submodule update
```

* Refresh the local Nextcloud instance in your browser without the cache (**Ctrl + F5**, or for Mac: **Cmd + Shift + R**). 
  - If you get prompted to update, do so by clicking on the **Start update** button to start the procedure.
  - If you receive an error *"The files of the app \[app name here\] were not replaced correctly. Make sure it is a version compatible with the server."*, you can solve this by running  `git pull`  in each of the mentioned app directories.

## 2: Install the required dependency apps

::: info
**About Smart Picker**

The Smart Picker (introduced in Nextcloud 26) is one of the best components to use to quickly create integrations that also interact with many different Nextcloud apps.

The Smart Picker allows users to search and get links they want to insert in Talk, Text, Collectives, Deck, and Mail. It can:

\- get a link to an internal object (like a Collective or a Collective page)

\- generate or transform content (e.g. translation)

\- get or generate external links (pointing to a picture from an external stock photo site)

Some example apps implementing a Smart Picker provider are [Giphy](https://apps.nextcloud.com/apps/integration_giphy), [OpenStreetMap](https://apps.nextcloud.com/apps/integration_openstreetmap), and [GitHub](https://apps.nextcloud.com/apps/integration_github).

In this tutorial, we will go through the steps of creating an app which extends the Smart Picker with a provider that gets data from an external service.

The Smart Picker can be used in Talk, Text, Collectives, Deck, Notes, and Mail. You need at least one of these apps to be able to test your app. We recommend you to use Text for this.

The next step is to install the Text app.

:::

* Go into the `workspace/server/apps-extra` directory in a terminal and run:

  ```
  git clone https://github.com/nextcloud/text
  ```
* Refresh <http://nextcloud.local> without the cache.
* You may be prompted to update again. If so, click the **Start update** button and follow the procedure.
* In the Apps settings, enable the Text app.
* Test if the app works by creating a new text file in the Files app with the '**+ New**' button (see screenshot):

![Screenshot 2025-06-23 at 17-24-36 All files - Nextcloud.png](.attachments.7197328/Screenshot%202025-06-23%20at%2017-24-36%20All%20files%20-%20Nextcloud.png)

## 3: How to choose an external service that you can integrate

::: info
With the Smart Picker we will connect an external service to Nextcloud. When you have an idea to integrate an external service, there are three things you need to check:

**1. Links:** Does the external service provide links for the objects you want to render or search and does the link contain at least an identifier of the object (like an object ID number)?

**2. API:** Does the external service provide an API to get the information about the object ID that we want to render?

**3. Access to API:** Is the API public? If not, can we get an API key?

As an example, we will integrate the stock photo site [Pexels](https://www.pexels.com/), but these steps can apply to any external provider. By the end of this tutorial, you will be able to implement any search provider of your choice.

:::

* **Links:** Check if Pexels provides URLs to the individual objects (the stock photos) that contain at least an identification number to the object.

  ::: info
  The URLs of the stock photos look like this:  
  <https://www.pexels.com/photo/closeup-up-photography-of-tri-color-kitten-691583/>

  where `691583` looks like an ID number.

  :::
* **API:** Check if the Pexels API returns all the information we would like to render (like the photo, the title, the photographer's name, etc.) after searching the object ID number.

  ::: info
  The API documentation can be found at <https://www.pexels.com/api/documentation/> and indeed returns all photo resources.

  :::
* **Access to API:** Request an API key from Pexels.

  ::: info
  You first need to create an account. Then you can request the key at:

  <https://www.pexels.com/api/key/>

  When you make an integration for your own app you need to decide how you want to manage the API key. Do you want to provide a hard-coded API key that everybody who downloads the app will directly have, or do you want administrators to get an API key themselves and set it via the settings?

  In this tutorial we choose to let admins request an API key themselves and set it in the settings. We will cover how to implement admin settings later in the tutorial.

  :::

## 4: Prepare the app skeleton

* Go to the [app skeleton generator](https://apps.nextcloud.com/developer/apps/generate) and generate an app with the name `Pexels`. Set the category of the app to 'Integration'.
* Extract the `app.tar.gz` file and move the generated `pexels` folder to the apps-extra folder of your local Nextcloud instance.
* In the `appinfo/info.xml` file: 
  * remove the `navigations` element (delete the `<navigations>` tag and all its contents)
  * adjust the compatible Nextcloud version to meet the version of your development environment in the `dependencies` element.

    ::: info
    As we are producing a Smart Picker provider which was introduced in Nextcloud 26, the minimum version should at least be 26 or higher.

    :::
* (Optional) Remove the directories and files that we will not use: 
  * The contents of the **src/** and **templates/** directories
  * The **tests/** directory
  * If they exist, remove the lib/**Db** and lib/**Migration** directories
  * The contents of the lib/**Controller** and lib/**Service** directories
  * The files `composer.json` and `psalm.xml`
* Create a `l10n` directory in `pexels` for the translations.

## 5: Implement the reference and search providers

::: info
Similar to a dashboard widget, a reference provider and search provider is first implemented as a class and then registered in the `lib/AppInfo/Application.php` file.

:::

* First, implement the reference provider. Create the `lib/Reference` directory. Create the file `lib/Reference/PhotoReferenceProvider.php` and set its content to:

```php
<?php

declare(strict_types=1);

namespace OCA\Pexels\Reference;

use OC\Collaboration\Reference\ReferenceManager;
use OCA\Pexels\AppInfo\Application;
use OCA\Pexels\Service\PexelsService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;

class PhotoReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_photo';

	private ?string $userId;
	private IConfig $config;
	private ReferenceManager $referenceManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private PexelsService $pexelsService;

	public function __construct(IConfig $config,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								PexelsService $pexelsService,
								ReferenceManager $referenceManager,
								?string $userId) {
		$this->userId = $userId;
		$this->config = $config;
		$this->referenceManager = $referenceManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->pexelsService = $pexelsService;
	}

	public function getId(): string	{
		return 'pexels-photo';
	}

	public function getTitle(): string {
		return $this->l10n->t('Pexels photos');
	}

	public function getOrder(): int	{
		return 10;
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
		);
	}

	public function getSupportedSearchProviderIds(): array {
		return ['pexels-search-photos'];

	}


	public function matchReference(string $referenceText): bool {
		$adminLinkPreviewEnabled = $this->config->getAppValue(Application::APP_ID, 'link_preview_enabled', '1') === '1';
		if (!$adminLinkPreviewEnabled) {
			return false;
		}
		return preg_match('/^(?:https?:\/\/)?(?:www\.)?pexels\.com\/photo\/[^\/\?]+-\d+\/?$/i', $referenceText) === 1
			|| preg_match('/^(?:https?:\/\/)?(?:www\.)?pexels\.com\/photo\/\d+\/?$/i', $referenceText) === 1;
	}


	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$photoId = $this->getPhotoId($referenceText);
			if ($photoId !== null) {
				$photoInfo = $this->pexelsService->getPhotoInfo($photoId);
				$reference = new Reference($referenceText);
				$reference->setTitle($photoInfo['alt'] ?? $this->l10n->t('Pexels photo'));
				$reference->setDescription($photoInfo['photographer'] ?? $this->l10n->t('Unknown photographer'));
				$imageUrl = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.pexels.getPhotoContent', ['photoId' => $photoId, 'size' => 'large']);
				$reference->setImageUrl($imageUrl);
				$photoInfo['proxied_url'] = $imageUrl;
				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$photoInfo
				);
				return $reference;
			}
		}

		return null;
	}

	private function getPhotoId(string $url): ?int {
		preg_match('/^(?:https?:\/\/)?(?:www\.)?pexels\.com\/photo\/[^\/\?]+-(\d+)\/?$/i', $url, $matches);
		if (count($matches) > 1) {
			return (int)$matches[1];
		}

		preg_match('/^(?:https?:\/\/)?(?:www\.)?pexels\.com\/photo\/(\d+)\/?$/i', $url, $matches);
		if (count($matches) > 1) {
			return (int)$matches[1];
		}
		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
```

::: info
Similar to the dashboard widget implementation, we write a class that implements a PHP interface or extends other classes.

A Reference provider which resolves links (for link previews) only needs to implement the `IReferenceProvider` interface. As we want to do more here, we extend the `ADiscoverableReferenceProvider`. This means our Reference provider will be listed by the Smart Picker. We also implement the `ISearchableReferenceProvider` because our picker provider uses a unified search provider.

You only have to register and define the Reference Provider and you are done for the server part. This `PhotoReferenceProvider` class will:

\- resolve links (get the items information), and

\- declare the unified search provider we want the Smart Picker to use.

In this `PhotoReferenceProvider.php` file, we define the Reference Provider.

The structure of this file is as follows:

First, some methods providing information about the provider are defined: the ID, title, the position of the provider (this can be a number between 0 and 100, but numbers 0-9 are reserved for shipped apps so we use number 10), the icon URL, and the IDs of the search providers that we support.

Second is the `matchReference` method. When trying to resolve a link, Nextcloud asks every reference provider if it can handle this link. This is done by calling this `matchReference` method for all registered providers. The first provider returning `true` will be the chosen one to resolve the link.  
In our case, this method should return `true` if the link passed as parameter is pointing to a Pexels image page.

This logic is triggered when you paste a URL (for example in Text) so it can be rendered by this Pexels rendering app.

Third is the `resolveReference` method. This method gets in action when `matchReference` concludes the URL matches, and it gathers the relevant data to be displayed in the reference widget in the front-end. In our case, the photo title, author's name and thumbnail URL will be obtained via the Pexels API.

:::

* Implement an event listener which loads our scripts at the right moment.

  ::: info
  The event listener will react to the `RenderReferenceEvent` to load the scripts that will register the reference widget component in the front-end.

  The `RenderReferenceEvent`  is emitted (dispatched) by Text or Talk or any app that renders link previews or uses the Smart Picker. That is why we load our scripts in reaction to this event.

  :::

  Create the `lib/Listener` directory. Create the file `lib/Listener/PexelsReferenceListener.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\Listener;
  
  use OCA\Pexels\AppInfo\Application;
  use OCP\Collaboration\Reference\RenderReferenceEvent;
  use OCP\EventDispatcher\Event;
  use OCP\EventDispatcher\IEventListener;
  use OCP\Util;
  
  class PexelsReferenceListener implements IEventListener {
  	public function handle(Event $event): void {
  		if (!$event instanceof RenderReferenceEvent) {
  			return;
  		}
  
  		Util::addScript(Application::APP_ID, Application::APP_ID . '-reference');
  	}
  }
  ```

  ::: info
  This listener is waiting for the server to load a page where reference scripts are needed because link previews will be re-rendered or the Smart Picker will be used.

  Just a recap to connect the dots: we also implemented a listener during the tutorial about implementing a simple files plugin.

  :::
* Second, implement the search provider.

  ::: info
  This will enable you to extend the Nextcloud unified search in the top-right of Nextcloud to also search in Pexels. This Pexels search provider will also be used by the Smart Picker.

  :::

  Create the `lib/Search` directory. Create the file `lib/Search/PexelsSearchPhotosProvider.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\Search;
  
  use OCA\Pexels\AppInfo\Application;
  use OCA\Pexels\Service\PexelsService;
  use OCP\App\IAppManager;
  use OCP\IL10N;
  use OCP\IConfig;
  use OCP\IURLGenerator;
  use OCP\IUser;
  use OCP\Search\IProvider;
  use OCP\Search\ISearchQuery;
  use OCP\Search\SearchResult;
  use OCP\Search\SearchResultEntry;
  
  class PexelsSearchPhotosProvider implements IProvider {
  
  	private IAppManager $appManager;
  	private IL10N $l10n;
  	private IConfig $config;
  	private IURLGenerator $urlGenerator;
  	private PexelsService $pexelsService;
  
  	public function __construct(IAppManager   $appManager,
  								IL10N         $l10n,
  								IConfig       $config,
  								IURLGenerator $urlGenerator,
  								PexelsService $pexelsService) {
  		$this->appManager = $appManager;
  		$this->l10n = $l10n;
  		$this->config = $config;
  		$this->urlGenerator = $urlGenerator;
  		$this->pexelsService = $pexelsService;
  	}
  
  	
  	public function getId(): string {
  		return 'pexels-search-photos';
  	}
  
  	
  	public function getName(): string {
  		return $this->l10n->t('Pexels images');
  	}
  
  	
  	public function getOrder(string $route, array $routeParameters): int {
  		if (strpos($route, Application::APP_ID . '.') === 0) {
  			// Active app, prefer Pexels results
  			return -1;
  		}
  
  		return 20;
  	}
  
  	
  	public function search(IUser $user, ISearchQuery $query): SearchResult {
  		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
  			return SearchResult::complete($this->getName(), []);
  		}
  
  		$limit = $query->getLimit();
  		$term = $query->getTerm();
  		$offset = $query->getCursor();
  		$offset = $offset ? intval($offset) : 0;
  
  		$apiKey = $this->config->getAppValue(Application::APP_ID, 'api_key');
  		if ($apiKey === '') {
  			return SearchResult::paginated($this->getName(), [], 0);
  		}
  
  		$searchResult = $this->pexelsService->searchPhotos($term, $offset, $limit);
  		if (isset($searchResult['error'])) {
  			$photos = [];
  		} else {
  			$photos = $searchResult['photos'];
  		}
  
  		$formattedResults = array_map(function (array $entry): SearchResultEntry {
  			return new SearchResultEntry(
  				$this->getThumbnailUrl($entry),
  				$this->getMainText($entry),
  				$this->getSubline($entry),
  				$this->getUrl($entry),
  				'',
  				false
  			);
  		}, $photos);
  
  		return SearchResult::paginated(
  			$this->getName(),
  			$formattedResults,
  			$offset + $limit
  		);
  	}
  
  	/**
  	 * @param array $entry
  	 * @return string
  	 */
  	protected function getMainText(array $entry): string {
  		return $entry['alt'];
  	}
  
  	/**
  	 * @param array $entry
  	 * @return string
  	 */
  	protected function getSubline(array $entry): string {
  		return $entry['photographer'] ?? '';
  	}
  
  	/**
  	 * @param array $entry
  	 * @return string
  	 */
  	protected function getUrl(array $entry): string {
  		return $entry['url'] ?? '';
  	}
  
  	/**
  	 * @param array $entry
  	 * @return string
  	 */
  	protected function getThumbnailUrl(array $entry): string {
  		$photoId = $entry['id'] ?? 0;
  		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.pexels.getPhotoContent', ['photoId' => $photoId, 'size' => 'small']);
  	}
  }
  ```

::: info
Similar to the dashboard widget and the reference provider, with `OCP\Search\IProvider` we are again implementing a PHP interface from the Nextcloud core. The result search provider class then has to be registered.

The structure of this file is as follows:

Again, first some interface methods are to be implemented.

The lower the return value of getOrder is, the higher the Pexels search result will be displayed in the unified search top-right menu.

Second is the `search` method. This method first checks if the app is enabled for the user, it also requires that the API key is configured. Then it returns the result items we get from the Pexels API. The search provider will refuse to search if there is no API key. The API key is configured in the admin settings, so in other words, we fetch the API key from the admin settings. We will implement the admin settings later in the tutorial.

There is no need to implement a user interface for a search provider. The server generic search provider menu will render our search results.

:::

## Register the reference provider, the search provider and the event listener

* Set the content of the `lib/AppInfo/Application.php` file to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\AppInfo;
  
  use OCA\Pexels\Listener\PexelsReferenceListener;
  use OCA\Pexels\Reference\PhotoReferenceProvider;
  use OCA\Pexels\Search\PexelsSearchPhotosProvider;
  use OCP\AppFramework\App;
  use OCP\AppFramework\Bootstrap\IBootContext;
  use OCP\AppFramework\Bootstrap\IBootstrap;
  use OCP\AppFramework\Bootstrap\IRegistrationContext;
  use OCP\Collaboration\Reference\RenderReferenceEvent;
  
  class Application extends App implements IBootstrap {
  	public const APP_ID = 'pexels';
  
  	public function __construct(array $urlParams = []) {
  		parent::__construct(self::APP_ID, $urlParams);
  	}
  
  	public function register(IRegistrationContext $context): void {
  		$context->registerSearchProvider(PexelsSearchPhotosProvider::class);
  		$context->registerReferenceProvider(PhotoReferenceProvider::class);
  		$context->registerEventListener(RenderReferenceEvent::class, PexelsReferenceListener::class);
  	}
  
  	public function boot(IBootContext $context): void {
  	}
  }
  ```

## 6: Implement the admin settings

::: info
For this integration, we are first going to create an administration settings section (new navigation menu item) called 'Connected accounts'. This section might be already defined by other apps. If other apps already defined this section, our app will make no difference as our definition will then be ignored.

Within the Connected accounts section, the administrator will be able to set an API key for the Pexels integration.

If you add settings to your app you always need to register these in the `appinfo/info.xml` file.

:::

* Create the `lib/Settings` directory.
* Implement the administration settings section. Create the file `lib/Settings/AdminSection.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\Settings;
  
  use OCP\IL10N;
  use OCP\IURLGenerator;
  use OCP\Settings\IIconSection;
  
  class AdminSection implements IIconSection {
  
  	private IURLGenerator $urlGenerator;
  	private IL10N $l;
  
  	public function __construct(IURLGenerator $urlGenerator,
  								IL10N         $l) {
  		$this->urlGenerator = $urlGenerator;
  		$this->l = $l;
  	}
  
  	/**
  	 * returns the ID of the section. It is supposed to be a lower case string
  	 *
  	 * @returns string
  	 */
  	public function getID(): string {
  		return 'connected-accounts'; //or a generic id if feasible
  	}
  
  	/**
  	 * returns the translated name as it should be displayed, e.g. 'LDAP / AD
  	 * integration'. Use the L10N service to translate it.
  	 *
  	 * @return string
  	 */
  	public function getName(): string {
  		return $this->l->t('Connected accounts');
  	}
  
  	/**
  	 * @return int whether the form should be rather on the top or bottom of
  	 * the settings navigation. The sections are arranged in ascending order of
  	 * the priority values. It is required to return a value between 0 and 99.
  	 */
  	public function getPriority(): int {
  		return 80;
  	}
  
  	/**
  	 * @return ?string The relative path to a an icon describing the section
  	 */
  	public function getIcon(): ?string {
  		return $this->urlGenerator->imagePath('core', 'categories/integration.svg');
  	}
  }
  ```
* Implement the class that will load our settings. Create the file `lib/Settings/Admin.php` and set its content to:

```php
<?php

declare(strict_types=1);

namespace OCA\Pexels\Settings;

use OCA\Pexels\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	private IConfig $config;
	private IInitialState $initialStateService;
	private ?string $userId;

	public function __construct(IConfig       $config,
								IInitialState $initialStateService,
								?string       $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$apiKey = $this->config->getAppValue(Application::APP_ID, 'api_key');

		$state = [
			'api_key' => $apiKey,
		];
		$this->initialStateService->provideInitialState('admin-config', $state);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
```

::: info
Here we get our settings values (the API key) and provide them to the front-end.

We also tell the Nextcloud core which template we want to display in our "Connected accounts" section.

:::

* Implement the adminSettings template. Create the file `templates/adminSettings.php` and set its content to:

```php
<?php
$appId = OCA\Pexels\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-adminSettings');
?>

<div id="pexels_prefs"></div>
```

::: info
Templates are HTML pages (or just sub parts) that are meant to be displayed somewhere in Nextcloud's user interface. Within templates, we can provide HTML elements and load scripts.

In this example, we only create an empty <div> which will be used by our scripts to inject (mount) a Vue component.

:::

* Then, register these administration settings in `appinfo/info.xml`. Add the following <settings> tag after the <dependencies> tag:

  ```xml
  <settings>
  	<admin>OCA\Pexels\Settings\Admin</admin>
  	<admin-section>OCA\Pexels\Settings\AdminSection</admin-section>
  </settings>
  ```

## 7: Install npm dependencies

* Go to the `pexels` app directory in your local Nextcloud setup
* Make sure you are using the latest (LTS) version of Node.js. Run the following command to ensure you are using the right versions of Node.js and npm:

  ```sh
  nvm use --lts
  node -v
  ```
* Run the following command to perform the initial dependency installation:

  ```sh
  npm install
  ```

  This will create a `package-lock.json` file. Once you have this file, you won't have to run `npm install` again. To reinstall all the dependencies, you can run `npm ci` .
* There are several dependencies missing. Add these dependencies by running the following command:

  ```
  npm i --save @nextcloud/axios @nextcloud/dialogs @nextcloud/initial-state @nextcloud/l10n @nextcloud/router vue-material-design-icons
  ```

  ```
  npm i --save-dev vite-plugin-eslint vite-plugin-stylelint
  ```

## 8: Write the admin settings scripts

::: info
The search provider implementation does not need any script, but the implementation of the reference widget and the admin settings does need scripts.

We will first implement the scripts for the admin settings, then the scripts for the reference provider (the reference widget).

:::

- Implement the admin settings script. Create the `src/adminSettings.js` file and set its content to:

  ```js
  import Vue from 'vue'
  import AdminSettings from './components/AdminSettings.vue'
  Vue.mixin({ methods: { t, n } })
  
  const VueSettings = Vue.extend(AdminSettings)
  new VueSettings().$mount('#pexels_prefs')
  ```

  ::: info
  `Vue.mixin({ methods: { t, n } })` allows us to use the Nextcloud translations functions in our Vue.js components.

  :::
- Implement the Vue component which will be used in the admin settings. Create the `src/components` directory. Create the file `src/components/AdminSettings.vue` and set its content to:

  ```vue
  <template>
  	<div id="pexels_prefs" class="section">
  		<h2>
  			<PexelsIcon class="icon" />
  			{{ t('pexels', 'Pexels integration') }}
  		</h2>
  		<div id="pexels-content">
  			<div class="line">
  				<label for="pexels-api-key">
  					<KeyIcon :size="20" class="icon" />
  					{{ t('pexels', 'Pexels API key') }}
  				</label>
  				<input id="pexels-api-key"
  					v-model="state.api_key"
  					type="password"
  					:placeholder="t('pexels', '...')"
  					@input="inputChanged = true">
  				<NcButton v-if="inputChanged"
  					type="primary"
  					@click="onSave">
  					<template #icon>
  						<NcLoadingIcon v-if="loading" />
  						<ArrowRightIcon v-else />
  					</template>
  					{{ t('pexels', 'Save') }}
  				</NcButton>
  			</div>
  		</div>
  	</div>
  </template>
  
  <script>
  import KeyIcon from 'vue-material-design-icons/Key.vue'
  import ArrowRightIcon from 'vue-material-design-icons/ArrowRight.vue'
  
  import PexelsIcon from './icons/PexelsIcon.vue'
  
  import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
  import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
  import { loadState } from '@nextcloud/initial-state'
  import { generateUrl } from '@nextcloud/router'
  import axios from '@nextcloud/axios'
  import { showSuccess, showError } from '@nextcloud/dialogs'
  
  export default {
  	name: 'AdminSettings',
  
  	components: {
  		PexelsIcon,
  		KeyIcon,
  		NcButton,
  		NcLoadingIcon,
  		ArrowRightIcon,
  	},
  
  	props: [],
  
  	data() {
  		return {
  			state: loadState('pexels', 'admin-config'),
  			loading: false,
  			inputChanged: false,
  		}
  	},
  
  	computed: {
  	},
  
  	watch: {
  	},
  
  	mounted() {
  	},
  
  	methods: {
  		onSave() {
  			this.saveOptions({
  				api_key: this.state.api_key,
  			})
  		},
  		saveOptions(values) {
  			this.loading = true
  			const req = {
  				values,
  			}
  			const url = generateUrl('/apps/pexels/admin-config')
  			axios.put(url, req).then((response) => {
  				showSuccess(t('pexels', 'Pexels options saved'))
  				this.inputChanged = false
  			}).catch((error) => {
  				showError(
  					t('pexels', 'Failed to save Pexels options')
  					+ ': ' + (error.response?.data?.error ?? ''),
  				)
  				console.error(error)
  			}).then(() => {
  				this.loading = false
  			})
  		},
  	},
  }
  </script>
  
  <style scoped lang="scss">
  #pexels_prefs {
  	#pexels-content {
  		margin-left: 40px;
  	}
  
  	h2 .icon {
  		margin-right: 8px !important;
  	}
  
  	h2,
  	.line,
  	.settings-hint {
  		display: flex;
  		align-items: center;
  		.icon {
  			margin-right: 4px;
  		}
  	}
  
  	.line {
  		> label {
  			width: 300px;
  			display: flex;
  			align-items: center;
  		}
  		> input {
  			width: 300px;
  		}
  	}
  }
  </style>
  ```

::: info
Here's a short recap of the previous tutorial: a Vue component consists of a template, a script, and a style.

\- The template contains HTML (for example, we have a HTML header for "Pexels integration"). You can also use Vue components in the template (for example, we use an icon from Material design icons) just as if they were HTML elements.

\- The scripts section contains the methods, computed properties, and the definitions of the component. (For example, we have a method to save the API key)

\- The style section contains the CSS.

In this component's template, we use an icon component. It will display the Pexels SVG icon. The next step is to implement this component.

:::

- Implement the icon component that is needed for the admin settings. Create the directory `src/components/icons`. Create the file `src/components/icons/PexelsIcon.vue` and set its content to:

  ```vue
  <template>
  	<span :aria-hidden="!title"
  		:aria-label="title"
  		class="material-design-icon pexels-icon"
  		role="img"
  		v-bind="$attrs"
  		@click="$emit('click', $event)">
  		<svg :fill="fillColor"
  			:width="size"
  			:height="size"
  			enable-background="new 0 0 32 32"
  			version="1.1"
  			viewBox="0 0 32 32"
  			xml:space="preserve"
  			xmlns="http://www.w3.org/2000/svg">
  			<path d="M 2 0 A 2 2 0 0 0 0 2 L 0 30 A 2 2 0 0 0 2 32 L 30 32 A 2 2 0 0 0 32 30 L 32 2 A 2 2 0 0 0 30 0 L 2 0 z M 11 9 L 18.029297 9 A 5.124 5.124 0 0 1 18.863281 19.179688 L 18.863281 23 L 11 23 L 11 9 z M 13 11 L 13 21 L 16.863281 21 L 16.863281 17.248047 L 18.029297 17.248047 A 3.1240234 3.1240234 0 1 0 18.029297 11 L 13 11 z" />
  		</svg>
  	</span>
  </template>
  
  <script>
  export default {
  	name: 'PexelsIcon',
  	props: {
  		title: {
  			type: String,
  			default: '',
  		},
  		fillColor: {
  			type: String,
  			default: 'currentColor',
  		},
  		size: {
  			type: Number,
  			default: 24,
  		},
  	},
  }
  </script>
  ```

::: info
This component wraps the Pexels SVG icon. It makes it possible to change the icon color, title and size using component properties. This component is used in the admin settings.

Later in the tutorial we will also add a PNG version of the icon to the app. The PNG icon is used in the reference widget component.

:::

## 9: Write the reference widget script

- Implement the script to register the reference widget. Create the file `src/reference.js` and set its content to:

```js
import { registerWidget } from '@nextcloud/vue'
import PhotoReferenceWidget from './views/PhotoReferenceWidget.vue'
import Vue from 'vue'
Vue.mixin({ methods: { t, n } })

registerWidget('pexels_photo', (el, { richObjectType, richObject, accessible }) => {
	const Widget = Vue.extend(PhotoReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})
```

::: info
This script is registering the `PhotoReferenceWidget` component as the one to use to render any rich object (resolved link) whose type is "pexels_photo". This component defines how we want to render the link, so you can customize the rendering. This step is optional. If you do not register any custom widget component, the default rendering will be used.

Registering a reference widget component consists of providing a callback to the Nextcloud reference system to let it know how to render a specific type of link. In our case, the link type is named "pexels_photo". The provided callback will be called each time a Pexel link is rendered. Our callback mounts the `PhotoReferenceWidget` component where the reference system wants to display the widget.

For the use case of a stock photo provider, we think it is nice to adjust the default rendering to display the picture larger, display the photographer name to credit the photographer, and make some other customizations to the default rendering.

Implementing this Vue component is the next step.

:::

- Implement the `PhotoReferenceWidget` component that is needed for rendering the Pexels links. Create the `src/views` directory. Create the file `src/views/PhotoReferenceWidget.vue` and set its content to:

  ```vue
  <template>
  	<div class="pexels-photo-reference">
  		<div class="photo-wrapper">
  			<strong>
  				{{ richObject.alt }}
  			</strong>
  			<span>
  				{{ richObject.photographer }}
  			</span>
  			<div v-if="!isLoaded" class="loading-icon">
  				<NcLoadingIcon :size="44"
  					:title="t('pexels', 'Loading Pexels stock photo')" />
  			</div>
  			<img v-show="isLoaded"
  				class="image"
  				:src="richObject.proxied_url"
  				@load="isLoaded = true">
  			<a v-show="isLoaded"
  				class="attribution"
  				target="_blank"
  				:title="poweredByTitle"
  				href="https://pexels.com">
  				<div class="content" />
  			</a>
  		</div>
  	</div>
  </template>
  
  <script>
  import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
  
  import { imagePath } from '@nextcloud/router'
  
  export default {
  	name: 'PhotoReferenceWidget',
  
  	components: {
  		NcLoadingIcon,
  	},
  
  	props: {
  		richObjectType: {
  			type: String,
  			default: '',
  		},
  		richObject: {
  			type: Object,
  			default: null,
  		},
  		accessible: {
  			type: Boolean,
  			default: true,
  		},
  	},
  
  	data() {
  		return {
  			isLoaded: false,
  			poweredByImgSrc: imagePath('pexels', 'pexels.logo.png'),
  			poweredByTitle: t('pexels', 'Powered by Pexels'),
  		}
  	},
  
  	computed: {
  	},
  
  	methods: {
  	},
  }
  </script>
  
  <style scoped lang="scss">
  .pexels-photo-reference {
  	width: 100%;
  	padding: 12px;
  	white-space: normal;
  
  	.photo-wrapper {
  		width: 100%;
  		display: flex;
  		flex-direction: column;
  		align-items: center;
  		justify-content: center;
  		position: relative;
  
  		.image {
  			max-height: 300px;
  			max-width: 100%;
  			border-radius: var(--border-radius);
  			margin-top: 8px;
  		}
  
  		.attribution {
  			position: absolute;
  			left: 0;
  			bottom: 0;
  			height: 33px;
  			width: 80px;
  			padding: 0;
  			border-radius: var(--border-radius);
  			background-color: var(--color-main-background);
  			.content {
  				height: 33px;
  				width: 80px;
  				background-image: url('../../img/pexels.logo.png');
  				background-size: 80px 33px;
  				filter: var(--background-invert-if-dark);
  			}
  		}
  	}
  }
  </style>
  ```

::: info
In the CSS section the customizations are made: we define the layout, the size of the picture, the location of the attribution logo, etc.

We use the Pexels logo image that we can find in <https://www.pexels.com/api/documentation/#guidelines> (<https://images.pexels.com/lib/api/pexels.png>). We store it in the app's directory so we can import it directly in the component's style section.

:::

- Download the Pexels logo from the link above and save it as `pexels.logo.png` in the `img` directory.

```sh
curl -LSs https://images.pexels.com/lib/api/pexels.png -o img/pexels.logo.png
```

## 10: Compile the scripts

- Configure Vite. Edit the `vite.config.js` file and set its content to:

  ```js
  import { createAppConfig } from "@nextcloud/vite-config";
  import { join, resolve } from "path";
  import eslint from "vite-plugin-eslint";
  import stylelint from "vite-plugin-stylelint";
  
  const isProduction = process.env.NODE_ENV === "production";
  
  export default createAppConfig(
    {
      reference: resolve(join("src", "reference.js")),
      adminSettings: resolve(join("src", "adminSettings.js")),
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
- Run the following to compile the JavaScript source files from the src directory to the js directory:

  ```
  npm run dev
  ```
- If any ESLint errors appear, fix them and run `npm run dev` again.

## 11: Handle the network requests

Make sure that Nextcloud knows which controller method to execute when receiving network requests. When receiving a GET request to /apps/pexels/photos/PHOTO_ID/SIZE, the `getPhotoContent` method of the `PexelsController` class will be called. When receiving a PUT request to /apps/pexels/admin-config, the `setAdminConfig` method of the `ConfigController` class will be called.

- Implement the PexelsController class. Create the `lib/Controller/PexelsController.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\Controller;
  
  use OCA\Pexels\Service\PexelsService;
  use OCP\AppFramework\Controller;
  use OCP\AppFramework\Http;
  use OCP\AppFramework\Http\Attribute\FrontpageRoute;
  use OCP\AppFramework\Http\Attribute\NoAdminRequired;
  use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
  use OCP\AppFramework\Http\DataDisplayResponse;
  use OCP\IRequest;
  
  class PexelsController extends Controller {
  
  	private PexelsService $pexelsService;
  	private ?string $userId;
  
  	public function __construct(string        $appName,
  								IRequest      $request,
  								PexelsService $pexelsService,
  								?string       $userId)
  	{
  		parent::__construct($appName, $request);
  		$this->pexelsService = $pexelsService;
  		$this->userId = $userId;
  	}
  
  	// We use this route to get the search result thumbnail and in the reference widget to get the image itself.
  	// This is a way to avoid allowing the page to access Pexels directly. We let the server get the image.
  	#[NoAdminRequired]
  	#[NoCSRFRequired]
  	#[FrontpageRoute(verb: 'GET', url: '/photos/{photoId}/{size}')]
  	public function getPhotoContent(int $photoId, string $size = 'original'): DataDisplayResponse {
  		$photoResponse = $this->pexelsService->getPhotoContent($photoId, $size);
  		if ($photoResponse !== null && isset($photoResponse['body'], $photoResponse['headers'])) {
  			$response = new DataDisplayResponse(
  				$photoResponse['body'],
  				Http::STATUS_OK,
  				['Content-Type' => $photoResponse['headers']['Content-Type'][0] ?? 'image/jpeg']
  			);
  			$response->cacheFor(60 * 60 * 24, false, true);
  			return $response;
  		}
  		return new DataDisplayResponse('', Http::STATUS_BAD_REQUEST);
  	}
  }
  ```

::: info
This controller will respond with a data response (the photo content).

We could download the photo content in the controller but we put that process in a service to keep the controller simple and focused on the request handling.

In the data response, we tell the browser to cache this image during 24 hours to improve performance.

:::

- Implement the ConfigController class. Create the `lib/Controller/ConfigController.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\Controller;
  
  use OCA\Pexels\AppInfo\Application;
  use OCP\AppFramework\Controller;
  use OCP\AppFramework\Http\Attribute\FrontpageRoute;
  use OCP\AppFramework\Http\DataResponse;
  use OCP\IConfig;
  use OCP\IRequest;
  
  class ConfigController extends Controller {
  	private ?string $userId;
  	private IConfig $config;
  
  	public function __construct(string        $appName,
  								IRequest      $request,
  								IConfig $config,
  								?string       $userId)
  	{
  		parent::__construct($appName, $request);
  		$this->userId = $userId;
  		$this->config = $config;
  	}
  
  	/**
  	 * Set admin config values.
  	 * This route is used by the admin settings page to save the option values.
  	 *
  	 * @param array $values key/value pairs to store in app config
  	 * @return DataResponse
  	 */
  	#[FrontpageRoute(verb: 'PUT', url: '/admin-config')]
  	public function setAdminConfig(array $values): DataResponse {
  		foreach ($values as $key => $value) {
  			$this->config->setAppValue(Application::APP_ID, $key, $value);
  		}
  		return new DataResponse(1);
  	}
  }
  ```

  ::: info
  This is where the request to save the admin settings is handled. Here we use Nextcloud's config service to store the settings that are global in our app.

  :::
- Implement the PexelsService class. Create the `lib/Service` directory if it doesn't already exist, then create the `lib/Service/PexelsService.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\Pexels\Service;
  
  use Exception;
  use GuzzleHttp\Exception\ClientException;
  use GuzzleHttp\Exception\ServerException;
  use OCA\Pexels\AppInfo\Application;
  use OCP\Http\Client\IClient;
  use OCP\Http\Client\IClientService;
  use OCP\IConfig;
  use OCP\IL10N;
  use Psr\Log\LoggerInterface;
  use Throwable;
  
  
  class PexelsService {
  
  	private LoggerInterface $logger;
  	private IClient $client;
  	private IConfig $config;
  	private IL10N $l10n;
  
  	public function __construct (LoggerInterface $logger,
  								 IClientService  $clientService,
  								 IConfig $config,
  								 IL10N $l10n) {
  		$this->client = $clientService->newClient();
  
  		$this->logger = $logger;
  		$this->config = $config;
  		$this->l10n = $l10n;
  	}
  
  	/**
  	 * @param int $offset
  	 * @param int $limit
  	 * @return array [perPage, page, leftPadding]
  	 */
  	public static function getPexelsPaginationValues(int $offset = 0, int $limit = 5): array {
  		// compute pagination values
  		// indexes offset => offset + limit
  		if (($offset % $limit) === 0) {
  			$perPage = $limit;
  			// page number starts at 1
  			$page = ($offset / $limit) + 1;
  			return [$perPage, $page, 0];
  		} else {
  			$firstIndex = $offset;
  			$lastIndex = $offset + $limit - 1;
  			$perPage = $limit;
  			// while there is no page that contains them'all
  			while (intdiv($firstIndex, $perPage) !== intdiv($lastIndex, $perPage)) {
  				$perPage++;
  			}
  			$page = intdiv($offset, $perPage) + 1;
  			$leftPadding = $firstIndex % $perPage;
  
  			return [$perPage, $page, $leftPadding];
  		}
  	}
  
  	/**
  	 * @param string $query What to search for
  	 * @param int $offset
  	 * @param int $limit
  	 * @return array request result
  	 */
  	public function searchPhotos(string $query, int $offset = 0, int $limit = 5): array {
  		[$perPage, $page, $leftPadding] = self::getPexelsPaginationValues($offset, $limit);
  		$params = [
  			'query' => $query,
  			'per_page' => $perPage,
  			'page' => $page,
  		];
  		$result = $this->request('v1/search', $params);
  		if (!isset($result['error'])) {
  			$result['photos'] = array_slice($result['photos'], $leftPadding, $limit);
  		}
  		return $result;
  	}
  
  	public function getApiKey(): string {
  		return $this->config->getAppValue(Application::APP_ID, 'api_key');
  	}
  
  	public function getPhotoInfo(int $photoId): array {
  		return $this->request('v1/photos/' . $photoId);
  	}
  
  	public function getPhotoContent(int $photoId, string $size): ?array {
  		$photoInfo = $this->getPhotoInfo($photoId);
  		if (!isset($photoInfo['error']) && isset($photoInfo['src'], $photoInfo['src'][$size])) {
  			try {
  				$photoResponse = $this->client->get($photoInfo['src'][$size]);
  				return [
  					'body' => $photoResponse->getBody(),
  					'headers' => $photoResponse->getHeaders(),
  				];
  			} catch (Exception|Throwable $e) {
  				$this->logger->warning('Pexels photo content request error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
  				return null;
  			}
  		}
  		return null;
  	}
  
  	/**
  	 * Make an authenticated HTTP request to Pexels API
  	 * @param string $endPoint The path to reach in api.github.com
  	 * @param array $params Query parameters (key/val pairs)
  	 * @param string $method HTTP query method
  	 * @param int $timeout
  	 * @return array decoded request result or error
  	 */
  	public function request(string $endPoint, array $params = [], string $method = 'GET', int $timeout = 30): array {
  		try {
  			$url = 'https://api.pexels.com/' . $endPoint;
  			$options = [
  				'timeout' => $timeout,
  				'headers' => [
  					'User-Agent' => 'Nextcloud Pexels integration',
  				],
  			];
  			$apiKey = $this->getApiKey();
  			if ($apiKey !== '') {
  				$options['headers']['Authorization'] = $apiKey;
  			}
  
  			if (count($params) > 0) {
  				if ($method === 'GET') {
  					$paramsContent = http_build_query($params);
  					$url .= '?' . $paramsContent;
  				} else {
  					$options['body'] = json_encode($params);
  				}
  			}
  
  			if ($method === 'GET') {
  				$response = $this->client->get($url, $options);
  			} else if ($method === 'POST') {
  				$response = $this->client->post($url, $options);
  			} else if ($method === 'PUT') {
  				$response = $this->client->put($url, $options);
  			} else if ($method === 'DELETE') {
  				$response = $this->client->delete($url, $options);
  			} else {
  				return ['error' => $this->l10n->t('Bad HTTP method')];
  			}
  			$body = $response->getBody();
  			$respCode = $response->getStatusCode();
  
  			if ($respCode >= 400) {
  				return ['error' => $this->l10n->t('Bad credentials')];
  			} else {
  				return json_decode($body, true) ?: [];
  			}
  		} catch (ClientException | ServerException $e) {
  			$responseBody = $e->getResponse()->getBody();
  			$parsedResponseBody = json_decode($responseBody, true);
  			if ($e->getResponse()->getStatusCode() === 404) {
  				$this->logger->debug('Pexels API error : ' . $e->getMessage(), ['response_body' => $responseBody, 'app' => Application::APP_ID]);
  			} else {
  				$this->logger->warning('Pexels API error : ' . $e->getMessage(), ['response_body' => $responseBody, 'app' => Application::APP_ID]);
  			}
  			return [
  				'error' => $e->getMessage(),
  				'body' => $parsedResponseBody,
  			];
  		} catch (Exception | Throwable $e) {
  			$this->logger->warning('Pexels API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
  			return ['error' => $e->getMessage()];
  		}
  	}
  }
  ```

::: info
This is the service that communicates with the Pexels API. Any controller or any other service in our app can import and use this PexelsService. The overall role of this service is to communicate with the Pexels API and return formatted information. In other words, it provides an abstracted way to contact Pexels.

This service contains a generic "request" method to make authenticated requests (with an API key) to the Pexels API. This helps to factorize the code responsible of doing the network request and parsing the JSON result.

The searchPhotos and getPhotoInfo methods use the "request" method.

:::

## 12: Enable and test the app

Within the Nextcloud instance:

- Enable the app in App settings.
- Add your Pexels API key in the administration settings under `Connected accounts`.
- Test the app: open a text file and type `/`. This will open the Smart Picker. Select the Pexels Smart Picker and enjoy inserting stock photos. 📸

## 13: Create your own integration app 🎉

Congratulations! You now have a basic understanding of how to develop Nextcloud apps. The next step is to try to create and publish an app yourself.

We think a good starting point for your own first app could be an app that implements the Smart Picker.

- Think of what the Smart Picker could do for you, and build your own.
- You can use this app as an example app. You can also get inspiration from other apps that implement Smart Picker:  
  [Peertube](https://apps.nextcloud.com/apps/integration_peertube), [Text templates](https://apps.nextcloud.com/apps/text_templates), [Giphy](https://apps.nextcloud.com/apps/integration_giphy), [OpenStreetMap](https://apps.nextcloud.com/apps/integration_openstreetmap), [GitHub](https://apps.nextcloud.com/apps/integration_github)
- You can ask your coding questions in the [developer forum](https://help.nextcloud.com/c/dev/11) or [developer chat](https://cloud.nextcloud.com/call/xs25tz5y).
- Upload your app to the Nextcloud App Store. You can find instructions about how to upload your app to the Nextcloud App Store [here](https://nextcloudappstore.readthedocs.io/en/latest/developer.html).
- If you want, you can also request the community to translate your app in different languages. You can find instructions about that [here](https://docs.nextcloud.com/server/latest/developer_manual/basics/front-end/l10n.html).

## Questions?

If something is wrong, check the Nextcloud server logs or [ask for help in the Nextcloud forum](https://help.nextcloud.com/t/new-tutorial-announcing-app-contest-developing-a-smart-picker-provider/160451).