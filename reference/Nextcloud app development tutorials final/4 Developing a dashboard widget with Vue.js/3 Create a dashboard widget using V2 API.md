## Part 3: Create a dashboard widget using `IAPIWidgetV2`

::: info
While `IAPIWidget` may be a powerful interface for developing custom widgets, for simpler widgets, it doesn't make much sense to load an entire JavaScript file or Vue component just to e.g. display some text or list some files, impacting performance. In this part, we will replace the `IAPIWidget` interface with `IAPIWidgetV2` which simplifies the dashboard widget implementation and improves performance.

:::

### 1. Implement and register the dashboard widget

First, implement the new dashboard widget. Create the file `lib/Dashboard/WidgetV2.php` and set its content to:

```php
<?php

declare(strict_types=1);

namespace OCA\CatGifsDashboard\Dashboard;

use OCA\CatGifsDashboard\Service\GifService;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

class WidgetV2 implements IIconWidget, IReloadableWidget {

	/** @var IL10N */
	private $l10n;
	/**
	 * @var GifService
	 */
	private $gifService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(IL10N $l10n,
								GifService $gifService,
								IURLGenerator $urlGenerator,
								?string $userId) {
		$this->l10n = $l10n;
		$this->gifService = $gifService;
		$this->urlGenerator = $urlGenerator;
		$this->userId = $userId;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'catgifsdashboard-widget-v2';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Widget V2');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-catgifsdashboard';
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath('catgifsdashboard', 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		// No need to provide initial state or inject javascript code anymore
	}

	/**
	 * @inheritDoc
	 */
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$items = $this->gifService->getWidgetItems($userId);
		return new WidgetItems(
			$items,
			empty($items) ? $this->l10n->t('No gifs found') : '',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getReloadInterval(): int {
		// Reload data every minute
		return 60;
	}
}
```

::: info
Here, we actually use the `IReloadableWidget` interface instead, as it is an extension of `IAPIWidgetV2` that adds a `getReloadInterval` method. This method allow us to update the list of GIFs automatically after a number of seconds returned by the method. We also include the `IIconWidget` interface and `getIconUrl` method in order to load the widget icon without any external CSS.

:::

Then, in a similar manner as in Part 2, register the dashboard widget in `lib/AppInfo/Application.php` (i.e. replace both occurrences of VueWidget with WidgetV2). The affected lines should look like those below:

```php
use OCA\CatGifsDashboard\Dashboard\WidgetV2;
```

```php
$context->registerDashboardWidget(WidgetV2::class);
```

### 2. Optional: front-end

Technically, we are already done, but to demonstrate that the front-end components are no longer needed, you can now delete all of the following:

- the **css/**, **js/**, **node_modules/**, and **src/** directories
- the `.eslintrc.cjs`, `.nvmrc`, `package.json`, `package-lock.json`, `stylelint.config.cjs`, and `vite.config.js` files

### 3. Test the app

- And that's it! Refresh the Nextcloud dashboard page to run the updated widget, which should look similar to the widget in Part 2.
- In a new browser tab, open the Files app and go to the `gifs` directory. Add any number of additional cat GIFs in this directory, and go back to the dashboard app tab. In less than a minute, the newly added GIFs should automatically appear.

## Questions?

If something is wrong, check the Nextcloud server logs or [ask for help in the Nextcloud forum](https://help.nextcloud.com/t/new-tutorial-creating-a-dashboard-widget-with-vue-js/155406).