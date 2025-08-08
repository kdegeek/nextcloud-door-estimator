# Tutorial: Developing a complete app with a navigation bar and database

::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

## 1: Pull the latest development release of Nextcloud

- go into the nextcloud-docker-dev/workspace/server directory and run:

```
git pull origin master
git submodule update
```

- Refresh the local Nextcloud instance in your browser without the cache (Ctrl+F5, or for Mac: Cmd+Shift+R). 
  - If you get prompted to update, do so by clicking the 'Update' button and follow the procedure.
  - If you receive an error *"The files of the app \[app name here\] were not replaced correctly. Make sure it is a version compatible with the server."*, you can solve this by running  `git pull`  in each of the mentioned app directories.

## 2: Prepare the app skeleton

- Go to the [app skeleton generator](https://apps.nextcloud.com/developer/apps/generate) and generate an app with the name `NoteBook`.
- Extract the `app.tar.gz` file and move the generated `notebook` folder to the apps-extra folder of your local Nextcloud instance.

  ::: info
  There are already many note apps in the app store. Make sure you choose an app name that does not exist yet in the app store.

  :::
- In the `appinfo/info.xml` file:
  - adjust the compatible Nextcloud version to meet the version of your development environment in the `dependencies` element.
- Remove the directories and files that we will not use:
  - The contents of the **tests** directory.
  - The `psalm.xml` and `lib/Controller/ApiController.php` files.

    ::: info
    We're keeping the `tests` directory this time because, in the next tutorial, we will cover how to add automated testing to this app!

    :::
- Create a `l10n` directory in `notebook` for the translations.
- (Optional) If you want, you can adjust the icon that will be displayed in the navigation menu by replacing the files in the **img** directory. You can find any icon of your liking from the [Vue Material Design Icons page](https://pictogrammers.com/library/mdi/). Make sure to download them as .svg files, and get a normal version and a dark-mode version of your icon. Then add them to the img directory with the file names  `app.svg` and `app-dark.svg`.

::: info
For this app, we will first create an interface-only app like we did in a previous tutorial. When you load the app on your instance it will then have the entire user interface but the buttons won't work. Then we add the functionality to the app by getting a database to store the notes and retrieve the notes.

:::

## 3: Set the Application.php file

- In the `lib/AppInfo/Application.php` file, set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\AppInfo;
  
  use OCP\AppFramework\App;
  use OCP\AppFramework\Bootstrap\IBootContext;
  use OCP\AppFramework\Bootstrap\IBootstrap;
  use OCP\AppFramework\Bootstrap\IRegistrationContext;
  
  class Application extends App implements IBootstrap {
  
  	public const APP_ID = 'notebook';
  //	public const NOTE_FOLDER_NAME = 'TutorialNotes';
  
  	public function __construct(array $urlParams = []) {
  		parent::__construct(self::APP_ID, $urlParams);
  	}
  
  	public function register(IRegistrationContext $context): void {
  	}
  
  	public function boot(IBootContext $context): void {
  	}
  }
  ```

::: info
Remember from the previous tutorials that this file is loaded every time you load any page in Nextcloud. The Application.php file contains the dynamic declaration of an app. It defines what the app does in Nextcloud in general, on the server side.

:::

## 4: Create the page controller and template

- Edit the `lib/Controller/PageController.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Controller;
  
  use OCA\NoteBook\AppInfo\Application;
  // use OCA\NoteBook\Db\NoteMapper;
  use OCP\AppFramework\Controller;
  use OCP\AppFramework\Http\Attribute\FrontpageRoute;
  use OCP\AppFramework\Http\Attribute\NoAdminRequired;
  use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
  use OCP\AppFramework\Http\TemplateResponse;
  use OCP\AppFramework\Services\IInitialState;
  use OCP\Collaboration\Reference\RenderReferenceEvent;
  use OCP\EventDispatcher\IEventDispatcher;
  use OCP\IConfig;
  use OCP\IRequest;
  use OCP\PreConditionNotMetException;
  
  class PageController extends Controller {
  
  	public function __construct(
  		string   $appName,
  		IRequest $request,
  		private IEventDispatcher $eventDispatcher,
  		private IInitialState $initialStateService,
  		private IConfig $config,
  //		private NoteMapper $noteMapper,
  		private ?string $userId
  	) {
  		parent::__construct($appName, $request);
  	}
  
  	/**
  	 * @return TemplateResponse
  	 * @throws PreConditionNotMetException
  	 */
  	#[NoAdminRequired]
  	#[NoCSRFRequired]
  	#[FrontpageRoute(verb: 'GET', url: '/')]
  	public function index(): TemplateResponse {
  		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());
  // 		try {
  // 			$notes = $this->noteMapper->getNotesOfUser($this->userId);
  // 		} catch (\Exception | \Throwable $e) {
  // 			$notes = [];
  // 		}
  		$notes = [];
  		$selectedNoteId = (int) $this->config->getUserValue($this->userId, Application::APP_ID, 'selected_note_id', '0');
  		$state = [
  			'notes' => $notes,
  			'selected_note_id' => $selectedNoteId,
  		];
  		$this->initialStateService->provideInitialState('notes-initial-state', $state);
  		return new TemplateResponse(Application::APP_ID, 'index');
  	}
  }
  ```
- The PageController loads the index template and defines the root endpoint linking the `/` route with the `index` controller method.  
  Edit the file `templates/index.php` and set its content to:

  ```php
  <?php
  $appId = OCA\NoteBook\AppInfo\Application::APP_ID;
  \OCP\Util::addScript($appId, $appId . '-main');
  ?>
  ```

  ::: info
  Remember from the previous tutorials that the template loads the JavaScript files. Therefore, the next step is to install all the npm dependencies before we add all the JavaScript files.

  :::

## 5: Install npm dependencies

- Go to the directory of the `notebook` app in your local Nextcloud setup
- Make sure you are using the latest (LTS) version of Node.js. Run the following commands to ensure you are using the right versions of Node.js and npm:

  ```
  nvm use --lts
  node -v
  ```

  You should see a version number output to the terminal.
- Run the following command to perform the initial dependency installation:

  ```
  npm install
  ```

  ::: info
  As a reminder, this will create a `package-lock.json` file. Once you have this file, you won't have to run `npm install` again. To reinstall all the dependencies, you can run `npm ci`.

  :::
- There are several dependencies missing. Add these dependencies by running the following commands:

  ```
  npm i --save @nextcloud/axios @nextcloud/dialogs @nextcloud/initial-state @nextcloud/l10n @nextcloud/router @nextcloud/vue-richtext vue-material-design-icons vue-click-outside
  ```

  ```
  npm i --save-dev vite-plugin-eslint vite-plugin-stylelint
  ```

Next, we're going to turn off some rules in ESLint. Remember that ESLint checks the code before it is being compiled. But ESLint has some rules that are not needed to respect and it is annoying to fix those errors every time.

So we are going to adjust the ESLint file. The ESLint file is a hidden file which means you cannot see them on most operating systems if you browse the directories through your file explorer or viewer. You can find out how to display 'hidden files' by using a search engine (on Mac you can display these files by pressing Cmd+Shift+.).

- Make your hidden files visible, then find the `.eslintrc.cjs` file and set its content to:

  ```js
  module.exports = {
      globals: {
          appVersion: true
      },
      parserOptions: {
          requireConfigFile: false
      },
      extends: [
          '@nextcloud'
      ],
      rules: {
          'jsdoc/require-jsdoc': 'off',
          'jsdoc/tag-lines': 'off',
          'vue/first-attribute-linebreak': 'off',
          'import/extensions': 'off'
      }
  }
  ```

## 6: Create the front-end

::: info
The front-end of this app is created in Vue.js. To do so, we need to 'mount' our top Vue component (App.vue) in an HTML element. We do this 'mount' in a small script. The first step is to create this script that mounts the App.vue component. We then create the App.vue component.

:::

- Edit the `src/main.js` file and set its content to:

```js
import App from './views/App.vue'
import Vue from 'vue'
Vue.mixin({ methods: { t, n } })

const VueApp = Vue.extend(App)
new VueApp().$mount('#content')
```

- Create the `src/views` directory. Move the `src/App.vue` file to the `src/views` directory and set its content to:

  ```vue
  <template>
  	<NcContent app-name="notebook">
  		<MyNavigation
  			:notes="displayedNotesById"
  			:selected-note-id="state.selected_note_id"
  			@click-note="onClickNote"
  			@export-note="onExportNote"
  			@create-note="onCreateNote"
  			@delete-note="onDeleteNote" />
  		<NcAppContent>
  			<MyMainContent v-if="selectedNote"
  				:note="selectedNote"
  				@edit-note="onEditNote" />
  			<NcEmptyContent v-else
  				:title="t('tutorial_5', 'Select a note')">
  				<template #icon>
  					<NoteIcon :size="20" />
  				</template>
  			</NcEmptyContent>
  		</NcAppContent>
  	</NcContent>
  </template>
  
  <script>
  import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
  import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
  import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
  
  import NoteIcon from '../components/icons/NoteIcon.vue'
  
  import MyNavigation from '../components/MyNavigation.vue'
  import MyMainContent from '../components/MyMainContent.vue'
  
  import axios from '@nextcloud/axios'
  import { generateOcsUrl, generateUrl } from '@nextcloud/router'
  import { showSuccess, showError, showUndo } from '@nextcloud/dialogs'
  import { loadState } from '@nextcloud/initial-state'
  
  import { Timer } from '../utils.js'
  
  export default {
  	name: 'App',
  
  	components: {
  		NoteIcon,
  		NcContent,
  		NcAppContent,
  		NcEmptyContent,
  		MyMainContent,
  		MyNavigation,
  	},
  
  	props: {
  	},
  
  	data() {
  		return {
  			state: loadState('notebook', 'notes-initial-state'),
  		}
  	},
  
  	computed: {
  		allNotes() {
  			return this.state.notes
  		},
  		notesToDisplay() {
  			return this.state.notes.filter(n => !n.trash)
  		},
  		displayedNotesById() {
  			const nbi = {}
  			this.notesToDisplay.forEach(n => {
  				nbi[n.id] = n
  			})
  			return nbi
  		},
  		notesById() {
  			const nbi = {}
  			this.allNotes.forEach(n => {
  				nbi[n.id] = n
  			})
  			return nbi
  		},
  		selectedNote() {
  			return this.displayedNotesById[this.state.selected_note_id]
  		},
  	},
  
  	watch: {
  	},
  
  	mounted() {
  	},
  
  	beforeDestroy() {
  	},
  
  	methods: {
  		onEditNote(noteId, content) {
  			const options = {
  				content,
  			}
  			const url = generateOcsUrl('apps/notebook/api/v1/notes/{noteId}', { noteId })
  			axios.put(url, options).then(response => {
  				this.notesById[noteId].content = content
  				this.notesById[noteId].last_modified = response.data.ocs.data.last_modified
  			}).catch((error) => {
  				showError(t('notebook', 'Error saving note content'))
  				console.error(error)
  			})
  		},
  		onCreateNote(name) {
  			console.debug('create note', name)
  			const options = {
  				name,
  			}
  			const url = generateOcsUrl('apps/notebook/api/v1/notes')
  			axios.post(url, options).then(response => {
  				this.state.notes.push(response.data.ocs.data)
  				this.onClickNote(response.data.ocs.data.id)
  			}).catch((error) => {
  				showError(t('notebook', 'Error creating note'))
  				console.error(error)
  			})
  		},
  		onDeleteNote(noteId) {
  			console.debug('delete note', noteId)
  			this.$set(this.notesById[noteId], 'trash', true)
  			// cancel or delete
  			const deletionTimer = new Timer(() => {
  				this.deleteNote(noteId)
  			}, 10000)
  			showUndo(
  				t('notebook', '{name} deleted', { name: this.notesById[noteId].name }),
  				() => {
  					deletionTimer.pause()
  					this.notesById[noteId].trash = false
  				},
  				{ timeout: 10000 },
  			)
  		},
  		deleteNote(noteId) {
  			const url = generateOcsUrl('apps/notebook/api/v1/notes/{noteId}', { noteId })
  			axios.delete(url).then(response => {
  				const indexToDelete = this.state.notes.findIndex(n => n.id === noteId)
  				if (indexToDelete !== -1) {
  					this.state.notes.splice(indexToDelete, 1)
  				}
  			}).catch((error) => {
  				showError(t('notebook', 'Error deleting note'))
  				console.error(error)
  			})
  		},
  		onClickNote(noteId) {
  			console.debug('click note', noteId)
  			this.state.selected_note_id = noteId
  			const options = {
  				values: {
  					selected_note_id: noteId,
  				},
  			}
  			const url = generateUrl('apps/notebook/config')
  			axios.put(url, options).then(response => {
  			}).catch((error) => {
  				showError(t('notebook', 'Error saving selected note'))
  				console.error(error)
  			})
  		},
  		onExportNote(noteId) {
  			const url = generateOcsUrl('apps/notebook/api/v1/notes/{noteId}/export', { noteId })
  			axios.get(url).then(response => {
  				showSuccess(t('notebook', 'Note exported in {path}', { path: response.data.ocs.data }))
  			}).catch((error) => {
  				showError(t('notebook', 'Error deleting note'))
  				console.error(error)
  			})
  		},
  	},
  }
  </script>
  
  <style scoped lang="scss">
  // nothing yet
  </style>
  ```

  ::: info
  Just to recap: a Vue component consists of a template (with HTML), a script (methods, computed properties, and the definitions of the component), and a style (CSS/Sass).

  To use a component you get from a library (an npm package, for example), you need to import it:

  `import PlusIcon from 'vue-material-design-icons/Plus.vue'`

  Then, add it in the list of components that you will use in the templates. This is the "components" attribute in the "script" section.

  And then you can insert it in the template just like if it was an HTML element.

  `<PlusIcon />`

  :::
- Implement the Vue component of the navigation bar. Create the `src/components` directory. Create the `src/components/MyNavigation.vue` file and set its content to:

  ```vue
  <template>
  	<NcAppNavigation>
  		<template #list>
  			<NcAppNavigationNewItem
  				:name="t('notebook', 'Create note')"
  				@new-item="$emit('create-note', $event)">
  				<template #icon>
  					<PlusIcon />
  				</template>
  			</NcAppNavigationNewItem>
  			<h2 v-if="loading"
  				class="icon-loading-small loading-icon" />
  			<NcEmptyContent v-else-if="sortedNotes.length === 0"
  				:title="t('notebook', 'No notes yet')">
  				<template #icon>
  					<NoteIcon :size="20" />
  				</template>
  			</NcEmptyContent>
  			<NcAppNavigationItem v-for="note in sortedNotes"
  				:key="note.id"
  				:name="note.name"
  				:class="{ selectedNote: note.id === selectedNoteId }"
  				:force-display-actions="true"
  				:force-menu="false"
  				@click="$emit('click-note', note.id)">
  				<template #icon>
  					<NoteIcon />
  				</template>
  				<template #actions>
  					<NcActionButton
  						:close-after-click="true"
  						@click="$emit('export-note', note.id)">
  						<template #icon>
  							<FileExportOutlineIcon />
  						</template>
  						{{ t('notebook', 'Export to file') }}
  					</NcActionButton>
  					<NcActionButton
  						:close-after-click="true"
  						@click="$emit('delete-note', note.id)">
  						<template #icon>
  							<TrashCanOutlineIcon />
  						</template>
  						{{ t('notebook', 'Delete') }}
  					</NcActionButton>
  				</template>
  			</NcAppNavigationItem>
  		</template>
  	</NcAppNavigation>
  </template>
  
  <script>
  import FileExportOutlineIcon from 'vue-material-design-icons/FileExportOutline.vue'
  import PlusIcon from 'vue-material-design-icons/Plus.vue'
  import TrashCanOutlineIcon from 'vue-material-design-icons/TrashCanOutline.vue'
  
  import NoteIcon from './icons/NoteIcon.vue'
  
  import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
  import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
  import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
  import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
  import NcAppNavigationNewItem from '@nextcloud/vue/dist/Components/NcAppNavigationNewItem.js'
  
  import ClickOutside from 'vue-click-outside'
  
  export default {
  	name: 'MyNavigation',
  
  	components: {
  		NoteIcon,
  		NcAppNavigation,
  		NcEmptyContent,
  		NcAppNavigationItem,
  		NcActionButton,
  		NcAppNavigationNewItem,
  		FileExportOutlineIcon,
  		PlusIcon,
  		TrashCanOutlineIcon,
  	},
  
  	directives: {
  		ClickOutside,
  	},
  
  	props: {
  		notes: {
  			type: Object,
  			required: true,
  		},
  		selectedNoteId: {
  			type: Number,
  			default: 0,
  		},
  		loading: {
  			type: Boolean,
  			default: false,
  		},
  	},
  
  	data() {
  		return {
  			creating: false,
  		}
  	},
  	computed: {
  		sortedNotes() {
  			return Object.values(this.notes).sort((a, b) => {
  				const { tsA, tsB } = { tsA: a.last_modified, tsB: b.last_modified }
  				return tsA > tsB
  					? -1
  					: tsA < tsB
  						? 1
  						: 0
  			})
  		},
  	},
  	beforeMount() {
  	},
  	methods: {
  		onCreate(value) {
  			console.debug('create new note')
  		},
  	},
  }
  </script>
  <style scoped lang="scss">
  .addNoteItem {
  	position: sticky;
  	top: 0;
  	z-index: 1000;
  	border-bottom: 1px solid var(--color-border);
  	:deep(.app-navigation-entry) {
  		background-color: var(--color-main-background-blur, var(--color-main-background));
  		backdrop-filter: var(--filter-background-blur, none);
  		&:hover {
  			background-color: var(--color-background-hover);
  		}
  	}
  }
  
  :deep(.selectedNote) {
  	> .app-navigation-entry {
  		background: var(--color-primary-light, lightgrey);
  	}
  
  	> .app-navigation-entry a {
  		font-weight: bold;
  	}
  }
  </style>
  ```

  ::: info
  We're using the [NcAppNavigation](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppNavigation?id=ncappnavigation-1) component.

  For each component, the documentation linked above lists its properties, slots, and events.  
  Properties are the customizable attributes of the components. Slots are a way to inject HTML content inside an empty spot of your component (for example, the white space in your navigation bar could be the footer, and then you decide how to fill it). Examples of events include a button click, or when a value is selected. To learn more, you can review [the starting guide of the Vue.js version we are using](https://v2.vuejs.org/v2/guide/) or follow some [tutorials on Vue.js specifically](https://vueschool.io).

  To get a general understanding of Vue.js and how to use the components, one strategy is to look at apps that implement similar components and how the apps are done. A good starting point if you want to learn how to use the navigation component is the [official Notes app](https://github.com/nextcloud/notes).

  **The Nextcloud components nearly every app developer will use are:**  
  [NcContent](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers?id=nccontent): this is your main container of your app. You put your app inside NcContent to make sure your app gets displayed correctly in Nextcloud. If you don't use this component, your content will overlap with the top menu bar of Nextcloud, for example, and tiles your app correctly.

  [NcAppContent](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppContent?id=ncappcontent-1): your main content inside your app, which is your main center (anything except the left and right sidebar). You define the main slot of this component and put whatever you want inside there.

  [NcAppNavigation](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppNavigation?id=ncappnavigation-1): the navigation bar on the left.

  Inside NcAppNavigation, it is obvious to use [NcAppNavigationItem](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppNavigation?id=ncappnavigationitem) which are the entries in the sidebar (in our app it is the different notes). We also use [NcAppNavigationNewItem](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppNavigation?id=ncappnavigationnewitem) which is a button (in our app we use it to create a new note). [NcAppNavigationNew](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppNavigation?id=ncappnavigationnew) is just a button, if you use NcAppNavigationNewItem it replaces the button text by an input field and directly name the new item you want to create.

  Note that both NcAppContent and NcAppNavigation have to be put inside NcContent. You cannot put them everywhere in your app. If you place the navigation bar deeper in your app, it will not work.

  Our app does not implement it, but if you want, you can add a right sidebar in your app with the component [NcAppSidebar](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppSidebar?id=ncappsidebar-1). For an example, you can take a look at the [official Calendar app](https://github.com/nextcloud/calendar/blob/91c295485bd16b5d9f413e98c048ef1b53c92133/src/views/EditSidebar.vue).

  :::
- Implement the app icon component. Create the directory `src/components/icons` and create file `src/components/icons/NoteIcon.vue` and set its content to:

  ```vue
  <template>
  	<span :aria-hidden="!title"
  		:aria-label="title"
  		class="material-design-icon note-icon"
  		role="img"
  		v-bind="$attrs"
  		@click="$emit('click', $event)">
  		<svg
  			:fill="fillColor"
  			:width="size"
  			:height="size"
  			enable-background="new 0 0 24 24"
  			version="1.1"
  			viewBox="0 0 24 24"
  			xml:space="preserve"
  			xmlns="http://www.w3.org/2000/svg">
  			<path d="M18.5 2H5.5C3.6 2 2 3.6 2 5.5V18.5C2 20.4 3.6 22 5.5 22H16L22 16V5.5C22 3.6 20.4 2 18.5 2M20.1 15H18.6C16.7 15 15.1 16.6 15.1 18.5V20H5.8C4.8 20 4 19.2 4 18.2V5.8C4 4.8 4.8 4 5.8 4H18.3C19.3 4 20.1 4.8 20.1 5.8V15M7 7H17V9H7V7M7 11H17V13H7V11M7 15H13V17H7V15Z" />
  		</svg>
  	</span>
  </template>
  
  <script>
  export default {
  	name: 'NoteIcon',
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
To get an Icon you can load the icons from Vue Material Design Icons. It brings all icons as Vue components so you just need to know the name. You can find the names here: <https://pictogrammers.com/library/mdi/>

So for example, if you search for the 'note' icon you will find the icon we will use in this code.

Note that the code examples on the website above use a different package from npm (@jamescoyle/vue-icon), but we are using the package vue-material-design-icons. You can choose which method to use, but in Nextcloud, most developers use the vue-material-design-icons package.

:::

- Now we have to implement the part where users can write and save the notes. Create the file `src/components/MyMainContent.vue` and set its content to:

  ```vue
  <template>
  	<div class="main-content">
  		<h2>
  			{{ note.name }}
  		</h2>
  		<NcRichContenteditable
  			class="content-editable"
  			:value="note.content"
  			:maxlength="10000"
  			:multiline="true"
  			:placeholder="t('notebook', 'Write a note')"
  			@update:value="onUpdateValue" />
  	</div>
  </template>
  
  <script>
  import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
  
  import { delay } from '../utils.js'
  
  export default {
  	name: 'MyMainContent',
  
  	components: {
  		NcRichContenteditable,
  	},
  
  	props: {
  		note: {
  			type: Object,
  			required: true,
  		},
  	},
  
  	data() {
  		return {
  		}
  	},
  
  	computed: {
  	},
  
  	watch: {
  	},
  
  	mounted() {
  	},
  
  	beforeDestroy() {
  	},
  
  	methods: {
  		onUpdateValue(newValue) {
  			delay(() => {
  				this.$emit('edit-note', this.note.id, newValue)
  			}, 2000)()
  		},
  	},
  }
  </script>
  
  <style scoped lang="scss">
  .main-content {
  	height: 100%;
  	display: flex;
  	flex-direction: column;
  	align-items: center;
  	justify-content: center;
  
  	.content-editable {
  		min-width: 600px;
  		min-height: 200px;
  	}
  }
  </style>
  ```

  ::: info
  Here we import the Vue component [NcRichContenteditable](https://nextcloud-vue-components.netlify.app/#/Components/NcRichContenteditable). The maxlength property defines how large the input can be. The input gets automatically saved using the utils.js script which we will implement next.

  :::
- Create the file `src/utils.js` and set its content to:

  ```js
  let mytimer = 0
  export function delay(callback, ms) {
  	return function() {
  		const context = this
  		const args = arguments
  		clearTimeout(mytimer)
  		mytimer = setTimeout(function() {
  			callback.apply(context, args)
  		}, ms || 0)
  	}
  }
  
  export function Timer(callback, mydelay) {
  	let timerId
  	let start
  	let remaining = mydelay
  
  	this.pause = function() {
  		window.clearTimeout(timerId)
  		remaining -= new Date() - start
  	}
  
  	this.resume = function() {
  		start = new Date()
  		window.clearTimeout(timerId)
  		timerId = window.setTimeout(callback, remaining)
  	}
  
  	this.resume()
  }
  
  export function strcmp(a, b) {
  	const la = a.toLowerCase()
  	const lb = b.toLowerCase()
  	return la > lb
  		? 1
  		: la < lb
  			? -1
  			: 0
  }
  ```

## 7: Compile the scripts

- Configure Vite. Edit the `vite.config.js` file and set its content to:

  ```js
  import { createAppConfig } from "@nextcloud/vite-config";
  import { join, resolve } from "path";
  import eslint from "vite-plugin-eslint";
  import stylelint from "vite-plugin-stylelint";
  
  const isProduction = process.env.NODE_ENV === "production";
  
  export default createAppConfig(
    {
      main: resolve(join("src", "main.js")),
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

## 8: Enable and test the app

- Enable the app in Apps settings
- The app will not yet do anything, it's just an interface-only app displaying the navigation bar which will be empty.

::: info
To add any features to our app, we need a database. Each app can create a new table in the database, modify existing database tables, modify data structures of existing tables, or even the data in the database itself.

To do so, we need to implement a database migration.

This requires creating a migration file and you have to bump the version number in the `info.xml` file which will trigger the 'update' screen. We will first create the migration file.

:::

## 9: Database migration

- Create the `lib/Migration` directory. Create a migration file in this directory with the filename following this naming convention: `Version010100Date20230524153814.php`. Replace the version number with the next app release version (in our case, 1.1.0) and the date number with the current date and time.

  ::: info
  **About the naming convention:** The migration file name follows the naming convention where the version number is in `MMmmpp` format (e.g. version 1.20.3 becomes `012003`) and the date number is in `YYYYMMDDHHmmss` format (e.g. May 24, 2023 3:38:14 PM becomes `20230524153814`). The exact time doesn't matter too much, but it's good practice to follow this naming convention, at least for the date.

  For your convenience, you can also generate a new migration file with the `occ migrations:generate` command. See the [Nextcloud developer documentation](https://docs.nextcloud.com/server/latest/developer_manual/basics/storage/migrations.html#console-commands) for details.

  :::
- Set its content to the following and adjust in line 13, `Version010100Date20230524153814` to match your file name.

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Migration;
  
  use Closure;
  use OCP\DB\ISchemaWrapper;
  use OCP\DB\Types;
  use OCP\Migration\IOutput;
  use OCP\Migration\SimpleMigrationStep;
  
  class Version010100Date20230524153814 extends SimpleMigrationStep {
  
  	/**
  	 * @param IOutput $output
  	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
  	 * @param array $options
  	 */
  	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
  	}
  
  	/**
  	 * @param IOutput $output
  	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
  	 * @param array $options
  	 * @return null|ISchemaWrapper
  	 */
  	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
  		/** @var ISchemaWrapper $schema */
  		$schema = $schemaClosure();
  
  		if (!$schema->hasTable('notebook_notes')) {
  			$table = $schema->createTable('notebook_notes');
  			$table->addColumn('id', Types::BIGINT, [
  				'autoincrement' => true,
  				'notnull' => true,
  				'length' => 4,
  			]);
  			$table->addColumn('user_id', Types::STRING, [
  				'notnull' => true,
  				'length' => 64,
  			]);
  			$table->addColumn('name', Types::STRING, [
  				'notnull' => true,
  				'length' => 300,
  			]);
  			$table->addColumn('content', Types::TEXT, [
  				'notnull' => true,
  			]);
  			$table->addColumn('last_modified', Types::INTEGER, [
  				'notnull' => true,
  			]);
  			$table->setPrimaryKey(['id']);
  			$table->addIndex(['user_id'], 'notebook_notes_uid');
  		}
  
  		return $schema;
  	}
  
  	/**
  	 * @param IOutput $output
  	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
  	 * @param array $options
  	 */
  	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
  	}
  }
  ```

  ::: info
  This migration file will create a new table, called `notebook_notes`, and then it will add different columns (to store an ID number, the title of the note, the content, the last modified date).

  :::
- In the `appinfo/info.xml` file, increment the version number from `1.0.0` to `1.1.0`.

  ::: info
  Increasing the version number will, when you reload your Nextcloud in the browser, trigger the upgrade screen. This procedure will trigger the database migration and create your table and columns. It doesn't matter which of the three numbers you increment, but we increment the middle (minor version) number here in order to adhere to the [Semantic Versioning](https://semver.org/) specification.

  :::
- Refresh your Nextcloud instance again (without the cache) to trigger the app upgrade. Click 'Start update' to run the upgrade.

  ::: info
  When the migration gets triggered, Nextcloud server will check if every migration file has run already. The server will try to run every file that has not run yet in the past. The oc_migrations table keeps track which migration steps have run already.

  This also means that if you uninstall an app, the database will stay changed forever unless undoing the changes is implemented specifically by the developer (but most apps don't). The data stays there which is also useful because if you reinstall the app, the data is still there.

  :::

## 10: Create a database mapper and note class

::: info
How can we make our app interact with the database? One way to interact with the database would be to directly write SQL queries in PHP, but this is not the recommended way. The recommended way in Nextcloud is to interact with the database through an abstraction layer.

To create this abstraction layer, you first create a class for each table in the lib/**Db** directory. Each class needs a mapper which defines the operations that you can do in the database.

Imagine there are two contexts: the database, and your programming language. those are two different contexts. The bridge in between them is the mapper, so that from your language you can easily query stuff from the database. When you get information you also have to store it in your language, for which we create the note entity class.

:::

- Create the `lib/Db` directory. Create the `lib/Db/Note.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Db;
  
  use OCP\AppFramework\Db\Entity;
  
  /**
   * @method string|null getUserId()
   * @method void setUserId(?string $userId)
   * @method string getName()
   * @method void setName(string $name)
   * @method string getContent()
   * @method void setContent(string $content)
   * @method int getLastModified()
   * @method void setLastModified(int $lastModified)
   */
  class Note extends Entity implements \JsonSerializable {
  
  	/** @var string */
  	protected $userId;
  	/** @var string */
  	protected $name;
  	/** @var string */
  	protected $content;
  	/** @var int */
  	protected $lastModified;
  
  	public function __construct() {
  		$this->addType('userId', 'string');
  		$this->addType('name', 'string');
  		$this->addType('content', 'string');
  		$this->addType('lastModified', 'integer');
  	}
  
  	#[\ReturnTypeWillChange]
  	public function jsonSerialize() {
  		return [
  			'id' => $this->id,
  			'user_id' => $this->userId,
  			'name' => $this->name,
  			'content' => $this->content,
  			'last_modified' => (int) $this->lastModified,
  		];
  	}
  }
  ```

  ::: info
  As we said, you need such a file for each table, but we only have one table for this app so we only need one of these files. This file declares the columns and we transform all the objects to an array so that it is easy to work with in the app.

  :::
- Create the `lib/Db/NoteMapper.php` file and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Db;
  
  use DateTime;
  use OCP\AppFramework\Db\MultipleObjectsReturnedException;
  use OCP\AppFramework\Db\QBMapper;
  use OCP\DB\Exception;
  use OCP\DB\QueryBuilder\IQueryBuilder;
  use OCP\IDBConnection;
  
  use OCP\AppFramework\Db\DoesNotExistException;
  
  class NoteMapper extends QBMapper {
  	public function __construct(IDBConnection $db) {
  		parent::__construct($db, 'notebook_notes', Note::class);
  	}
  
  	/**
  	 * @param int $id
  	 * @return Note
  	 * @throws \OCP\AppFramework\Db\DoesNotExistException
  	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
  	 */
  	public function getNote(int $id): Note {
  		$qb = $this->db->getQueryBuilder();
  
  		$qb->select('*')
  			->from($this->getTableName())
  			->where(
  				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
  			);
  
  		return $this->findEntity($qb);
  	}
  
  	/**
  	 * @param int $id
  	 * @param string $userId
  	 * @return Note
  	 * @throws DoesNotExistException
  	 * @throws Exception
  	 * @throws MultipleObjectsReturnedException
  	 */
  	public function getNoteOfUser(int $id, string $userId): Note {
  		$qb = $this->db->getQueryBuilder();
  
  		$qb->select('*')
  			->from($this->getTableName())
  			->where(
  				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
  			)
  			->andWhere(
  				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
  			);
  
  		return $this->findEntity($qb);
  	}
  
  	/**
  	 * @param string $userId
  	 * @return Note[]
  	 * @throws Exception
  	 */
  	public function getNotesOfUser(string $userId): array {
  		$qb = $this->db->getQueryBuilder();
  
  		$qb->select('*')
  			->from($this->getTableName())
  			->where(
  				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
  			);
  
  		return $this->findEntities($qb);
  	}
  
  	/**
  	 * @param string $userId
  	 * @param string $name
  	 * @param string $content
  	 * @return Note
  	 * @throws Exception
  	 */
  	public function createNote(string $userId, string $name, string $content): Note {
  		$note = new Note();
  		$note->setUserId($userId);
  		$note->setName($name);
  		$note->setContent($content);
  		$timestamp = (new DateTime())->getTimestamp();
  		$note->setLastModified($timestamp);
  		return $this->insert($note);
  	}
  
  	/**
  	 * @param int $id
  	 * @param string $userId
  	 * @param string|null $name
  	 * @param string|null $content
  	 * @return Note|null
  	 * @throws Exception
  	 */
  	public function updateNote(int $id, string $userId, ?string $name = null, ?string $content = null): ?Note {
  		if ($name === null && $content === null) {
  			return null;
  		}
  		try {
  			$note = $this->getNoteOfUser($id, $userId);
  		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
  			return null;
  		}
  		if ($name !== null) {
  			$note->setName($name);
  		}
  		if ($content !== null) {
  			$note->setContent($content);
  		}
  		$timestamp = (new DateTime())->getTimestamp();
  		$note->setLastModified($timestamp);
  		return $this->update($note);
  	}
  
  	/**
  	 * @param int $id
  	 * @param string $userId
  	 * @return Note|null
  	 * @throws Exception
  	 */
  	public function deleteNote(int $id, string $userId): ?Note {
  		try {
  			$note = $this->getNoteOfUser($id, $userId);
  		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
  			return null;
  		}
  
  		return $this->delete($note);
  	}
  
  	/**
  	 * @param string $userId
  	 * @return void
  	 * @throws Exception
  	 */
  	public function deleteNotesOfUser(string $userId): void {
  		$qb = $this->db->getQueryBuilder();
  
  		$qb->delete($this->getTableName())
  			->where(
  				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
  			);
  		$qb->executeStatement();
  		$qb->resetQueryParts();
  	}
  }
  ```

::: info
Each table needs a mapper. The mapper defines the operations you want to do in the database table (so the mapper is specific to one table only!). This mapper file defines the list of things you can do on a database related with notes: retrieving a specific note, retrieving all notes of a user, creating and updating notes, etc.

The mapper is where the interaction between the app and the database occurs.

Anywhere in your app, you can use the mapper to access the database. In this app, we will use the mapper in a controller, but if you would want to, you could also use the mapper in a background job to clean up old notes, for example (or anywhere else in your app). The next step is to implement something that uses the mapper.

:::

## 11: Adjust the PageController

::: info
Now that we have a database mapper and an entity class to manipulate notes, we can use it in the PageController class to get the list of notes stored in the database and pass it as initial data to our main app page.

For that we can uncomment some lines.

:::

- Open the `lib/Controller/PageController.php` file.
- Add the declaration to use the NoteMapper by uncommenting line 8:

  ```php
  use OCA\Notes\Db\NoteMapper;
  ```
- Declare the class by uncommenting line 29:

  ```php
  private NoteMapper $noteMapper,
  ```
- Extend the controller to use NoteMapper by uncommenting lines 44-48:

```php
		try {
			$notes = $this->noteMapper->getNotesOfUser($this->userId);
		} catch (\Exception | \Throwable $e) {
			$notes = [];
		}
```

- Delete line 49 which previously declared that the list of notes is empty, which was needed to load the app without the database in the previous steps:

  ```php
  $notes = [];
  ```

## 12: Add the configuration controller

- Create the file `lib/Controller/ConfigController.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Controller;
  
  use OCP\IConfig;
  use OCP\IRequest;
  use OCP\AppFramework\Http\Attribute\FrontpageRoute;
  use OCP\AppFramework\Http\Attribute\NoAdminRequired;
  use OCP\AppFramework\Http\DataResponse;
  use OCP\AppFramework\Controller;
  
  use OCA\NoteBook\AppInfo\Application;
  use OCP\PreConditionNotMetException;
  
  class ConfigController extends Controller {
  
  	public function __construct(
  		string   $appName,
  		IRequest $request,
  		private IConfig $config,
  		private ?string $userId
  	) {
  		parent::__construct($appName, $request);
  	}
  
  	/**
  	 * @param array $values
  	 * @return DataResponse
  	 * @throws PreConditionNotMetException
  	 */
  	#[NoAdminRequired]
  	#[FrontpageRoute(verb: 'PUT', url: '/config')]
  	public function setConfig(array $values): DataResponse {
  		foreach ($values as $key => $value) {
  			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
  		}
  		return new DataResponse([]);
  	}
  }
  ```

  ::: info
  This configuration stores the current selected note ID so that the selection can be kept after a page reload.

  :::

## 13: Extend the OCS API

::: info
The steps so far are perhaps known to you from the previous tutorial 'Developing a simple interface-only app'. But for this app, we not only want an interface, but also want to interact with it. Maybe we want to create new notes, delete notes, or edit notes, or even export the note to a file. We need to create endpoints for these actions.

There are 2 types of network APIs in Nextcloud:

the internal API (defined by the `FrontpageRoute` PHP attribute) and the OCS API (defined by the `ApiRoute` attribute).

The internal API is supposed to be created and consumed by the same developers. Since the API is not used by other developers, it does not have to be stable in time and developers can change their internal APIs whenever they wish.

The OCS APIs can be used internally but can also allow clients to interact with an app. Therefore, it must be stable to avoid breaking the clients. This is why we include the API version number in the endpoint paths. For example, any change breaking the version 1 of our API will lead to creating a version 2. This way we can keep version 1 untouched and old clients can still work because they still target version 1.

In this tutorial, we assume we are only going to use the version 1 of our app's API. So the front-end will call the v1 endpoints and the back-end will ignore the API version number.

:::

- Create the file `lib/Controller/NotesController.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Controller;
  
  use Exception;
  use OCA\NoteBook\Db\NoteMapper;
  use OCA\NoteBook\Service\NoteService;
  use OCP\AppFramework\Http;
  use OCP\AppFramework\Http\Attribute\ApiRoute;
  use OCP\AppFramework\Http\Attribute\NoAdminRequired;
  use OCP\AppFramework\Http\DataResponse;
  use OCP\AppFramework\OCSController;
  use OCP\IRequest;
  use Throwable;
  
  class NotesController extends OCSController {
  
  	public const REQUIREMENTS = [
  		'apiVersion' => 'v1',
  	];
  
  	public function __construct(
  		string             $appName,
  		IRequest           $request,
  		private NoteMapper $noteMapper,
  		private NoteService $noteService,
  		private ?string    $userId
  	) {
  		parent::__construct($appName, $request);
  	}
  
  	/**
  	 * @return DataResponse
  	 */
  	#[NoAdminRequired]
  	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/notes', requirements: self::REQUIREMENTS)]
  	public function getUserNotes(): DataResponse {
  		try {
  			return new DataResponse($this->noteMapper->getNotesOfUser($this->userId));
  		} catch (Exception | Throwable $e) {
  			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
  		}
  	}
  
  	/**
  	 * @param string $name
  	 * @param string $content
  	 * @return DataResponse
  	 */
  	#[NoAdminRequired]
  	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/notes', requirements: self::REQUIREMENTS)]
  	public function addUserNote(string $name, string $content = ''): DataResponse {
  		try {
  			$note= $this->noteMapper->createNote($this->userId, $name, $content);
  			return new DataResponse($note);
  		} catch (Exception | Throwable $e) {
  			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
  		}
  	}
  
  	/**
  	 * @param int $id
  	 * @param string|null $name
  	 * @param string|null $content
  	 * @return DataResponse
  	 */
  	#[NoAdminRequired]
  	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/notes/{id}', requirements: self::REQUIREMENTS)]
  	public function editUserNote(int $id, ?string $name = null, ?string $content = null): DataResponse {
  		try {
  			$note = $this->noteMapper->updateNote($id, $this->userId, $name, $content);
  			return new DataResponse($note);
  		} catch (Exception | Throwable $e) {
  			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
  		}
  	}
  
  	/**
  	 * @param int $id
  	 * @return DataResponse
  	 */
  	#[NoAdminRequired]
  	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/notes/{id}', requirements: self::REQUIREMENTS)]
  	public function deleteUserNote(int $id): DataResponse {
  		try {
  			$note = $this->noteMapper->deleteNote($id, $this->userId);
  			return new DataResponse($note);
  		} catch (Exception | Throwable $e) {
  			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
  		}
  	}
  
  	/**
  	 * @param int $id
  	 * @return DataResponse
  	 */
  	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/notes/{id}/export', requirements: self::REQUIREMENTS)]
  	public function exportUserNote(int $id): DataResponse {
  		try {
  			$path = $this->noteService->exportNote($id, $this->userId);
  			return new DataResponse($path);
  		} catch (Exception | Throwable $e) {
  			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
  		}
  	}
  }
  ```

::: info
Please note that we don't actually declare the API version here. The `REQUIREMENTS` array is a kind of validator. It tells the server if endpoints are correctly called.

A request will be refused if the front-end (or a client) make a request that does not respect the "requirements".

The meaning of `'apiVersion' => 'v1',` is: the variable 'apiVersion' must always be equal to "v1" when clients make requests to our app.

This file uses the `NoteMapper` to create a note or delete a note.

It also uses the `NoteService`. Remember from the previous tutorials that a service can be used anywhere in your app and is often used to improve readability of the code.

But before we can export the note, we need to define a folder name where the files can be exported to. First, we define the variable called NOTE_FOLDER_NAME in the `Application.php` file. Then, we will implement the `NoteService`.

:::

- In the `lib/AppInfo/Application.php` file, define the variable NOTE_FOLDER_NAME by uncommenting line 15:

  ```php
  public const NOTE_FOLDER_NAME = 'TutorialNotes';
  ```
- Create the `lib/Service` directory. Create the file `lib/Service/NoteService.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Service;
  
  use Exception;
  use OC\User\NoUserException;
  use OCA\NoteBook\AppInfo\Application;
  use OCA\NoteBook\Db\NoteMapper;
  use OCP\AppFramework\Db\DoesNotExistException;
  use OCP\AppFramework\Db\MultipleObjectsReturnedException;
  use OCP\Files\File;
  use OCP\Files\Folder;
  use OCP\Files\GenericFileException;
  use OCP\Files\IRootFolder;
  use OCP\Files\NotFoundException;
  use OCP\Files\NotPermittedException;
  use OCP\Lock\LockedException;
  
  class NoteService {
  
  	public function __construct(
  		string $appName,
  		private IRootFolder $rootFolder,
  		private NoteMapper $noteMapper
  	) {
  	}
  
  	/**
  	 * @param string $userId
  	 * @return Folder
  	 * @throws NotPermittedException
  	 * @throws NotFoundException
  	 * @throws NoUserException
  	 */
  	private function createOrGetNotesDirectory(string $userId): Folder {
  		$userFolder = $this->rootFolder->getUserFolder($userId);
  		if ($userFolder->nodeExists(Application::NOTE_FOLDER_NAME)) {
  			$node = $userFolder->get(Application::NOTE_FOLDER_NAME);
  			if ($node instanceof Folder) {
  				return $node;
  			}
  			throw new Exception('/' . Application::NOTE_FOLDER_NAME . ' exists and is not a directory');
  		} else {
  			return $userFolder->newFolder(Application::NOTE_FOLDER_NAME);
  		}
  	}
  
  	/**
  	 * @param int $noteId
  	 * @param string $userId
  	 * @return string
  	 * @throws DoesNotExistException
  	 * @throws MultipleObjectsReturnedException
  	 * @throws NoUserException
  	 * @throws NotFoundException
  	 * @throws NotPermittedException
  	 * @throws \OCP\DB\Exception
  	 * @throws GenericFileException
  	 * @throws LockedException
  	 */
  	public function exportNote(int $noteId, string $userId): string {
  		$noteFolder = $this->createOrGetNotesDirectory($userId);
  		$note = $this->noteMapper->getNoteOfUser($noteId, $userId);
  		$fileName = $note->getName() . '.txt';
  		$fileContent = $note->getContent();
  		if ($noteFolder->nodeExists($fileName)) {
  			$node = $noteFolder->get($fileName);
  			if ($node instanceof File) {
  				$node->putContent($fileContent);
  				return Application::NOTE_FOLDER_NAME . '/' . $fileName;
  			}
  			throw new Exception('/' . Application::NOTE_FOLDER_NAME . '/' . $fileName .' exists and is not a file');
  		} else {
  			$noteFolder->newFile($fileName, $fileContent);
  			return Application::NOTE_FOLDER_NAME . '/' . $fileName;
  		}
  	}
  }
  ```

  ::: info
  This service is for enabling users to export the note to a file. The `createOrGetNotesDirectory` method is for checking if a folder for the notes already exists, and if it doesn't, it creates a folder. The `exportNote` method is to export the note to a .txt file format.

  The NOTE_FOLDER_NAME is declared in `lib/AppInfo/Application.php`.

  Remember that a service is used to delegate some tasks from a controller. You could also put everything you put in a service in the controller, and instead just get a very long controller code, but using a service will make your code much more readable.

  :::

## 14: Try out your app 🎉

- Refresh the app in your browser and try it out!

## Questions?

If something is wrong, check the Nextcloud server logs or [ask for help in the Nextcloud forum](https://help.nextcloud.com/t/new-tutorial-developing-a-complete-app-with-a-navigation-bar-and-database/164195).