::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

# 2 Write the front-end scripts

::: info
In this section of the tutorial, we are going to introduce 4 concepts:

1\. JavaScript dependency list in `package.json`, aka npm packages (Step 2)  
This JSON file contains all the dependencies that your source JavaScript files need plus some metadata. It can be considered as a local configuration file for npm.

2\. JavaScript sources (step 3)  
These are the files you write to define what the app's front-end will do.

3\. Vite (step 4)  
Vite is a tool to compile your source scripts (and their dependencies) to produce the production files.

4\. Production front-end scripts (step 5)  
They are the final scripts that are actually loaded in your app's front-end (in the browser).

This script can also make requests to the server (AJAX calls). The server then does what we ask it to do and optionally returns data.

In our app the script reacts to the button click.  
This button click will first change the GIF size directly in your page. However this would get lost if you refresh the page. To save the setting, this script will send a data request to the server. Then the server will save the setting value!

In summary, Vite **reads** the source scripts and their dependencies in order to **build** the production scripts. The production scripts then **communicate with** the page controller by making network requests to the server.

*Note: as of 2025, Vite has replaced webpack as the JavaScript bundler of choice. While webpack is still mentioned in the schematic below, the overall diagram still holds true today.*

:::

![Screenshot 2022-11-17 at 14.17.30.png](.attachments.6798798/Screenshot%202022-11-17%20at%2014.17.30.png)

### Step 1: Generate your skeleton app with app name CatGifs

* Generate a skeleton app in the app store. [This is a direct link to the generator](https://apps.nextcloud.com/developer/apps/generate).

  Use the app name **CatGifs**. *(⚠️ make sure to use **CatGifs** and not CatGif)*

  This will download a .tar.gz file.
* Unpack the .tar.gz file and move the folder **catgifs** to the **apps-extra** directory of your local Nextcloud Docker setup. You don't have to enable the app yet.
* In the `appinfo/info.xml` file, make sure the app is compatible with the Nextcloud version of your local Nextcloud by adjusting the value of **<max-version>**.
* Some parts of the skeleton app we won't need. **Delete** the following files:
  * In the **src** directory, remove all files *except* main.js
  * In the **lib** directory, remove the **Service**, **Db**, and **Migration** directories if they exist
  * In the **lib/Controller** directory, remove all files *except* PageController.php

### Step 2: Install npm packages

* In a terminal, get **into the catgifs folder** in your local Nextcloud setup.
* Run `npm i`.

::: info
`npm i` (or `npm install`) will install the dependency packages. These dependencies are defined in the **package.json** file.

The package.json file contains the npm configuration. In this file, there is a "dependencies" section which contains the list of packages your app's scripts depend on.

The "scripts" section lists the actions you can run with `npm run`

When installing the dependencies through `npm i`, a **package-lock.json** file will be created. This file will list the versions (that have been defined in the **package.json** file) that are now accessible. Once you have run `npm i` once and the **package-lock.json** file exists, you only need to run `npm ci` (or `npm clean-install`) to install your dependencies again.

It’s up to the developer to write the npm scripts but for Nextcloud apps everyone has the same scripts, namely the ones listed here, so the scripts listed here are very common.

:::

When the installation of npm packages is complete:

* In both the package.json and package-lock.json files, there are a few dependencies missing. Add them by running the following command:

```
npm i --save @nextcloud/axios @nextcloud/dialogs @nextcloud/initial-state @nextcloud/router
```

This will add the packages listed above as dependencies to both JSON files and install them.

You can verify this by checking that there are additional lines in the dependencies section of package.json with the packages listed above.

### Step 3: Write our source JavaScript file

Edit the `src/main.js` file and set its content to:

```js
import { generateUrl, imagePath } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

function main() {
	// we get the data injected via the Initial State mechanism
	const state = loadState('catgifs', 'tutorial_initial_state')

	// this is the empty div from the main template (templates/index.php)
	const tutorialDiv = document.querySelector('#app-content #catgifs')

	addConfigButton(tutorialDiv, state)
	addGifs(tutorialDiv, state)
}

function addGifs(container, state) {
	const fileNameList = state.file_name_list
	// for each file, we create a div which contains a button and an image
	fileNameList.forEach(name => {
		const fileDiv = document.createElement('div')
		fileDiv.classList.add('gif-wrapper')

		const img = document.createElement('img')
		img.setAttribute('src', imagePath('catgifs', 'gifs/' + name))
		img.style.display = 'none'

		// the button toggles the image visibility
		const button = document.createElement('button')
		button.innerText = 'Show/hide ' + name
		button.addEventListener('click', (e) => {
			if (img.style.display === 'block') {
				img.style.display = 'none'
			} else {
				img.style.display = 'block'
			}
		})

		fileDiv.append(button)
		fileDiv.append(img)
		container.append(fileDiv)
	})
}

function addConfigButton(container, state) {
	// add a button to switch theme
	const themeButton = document.createElement('button')
	themeButton.innerText = state.fixed_gif_size === '1' ? 'Enable variable gif size' : 'Enable fixed gif size'
	if (state.fixed_gif_size === '1') {
		container.classList.add('fixed-size')
	}
	themeButton.addEventListener('click', (e) => {
		if (state.fixed_gif_size === '1') {
			state.fixed_gif_size = '0'
			themeButton.innerText = 'Enable fixed gif size'
			container.classList.remove('fixed-size')
		} else {
			state.fixed_gif_size = '1'
			themeButton.innerText = 'Enable variable gif size'
			container.classList.add('fixed-size')
		}
		const url = generateUrl('/apps/catgifs/config')
		const params = {
			key: 'fixed_gif_size',
			value: state.fixed_gif_size,
		}
		axios.put(url, params)
			.then((response) => {
				showSuccess('Settings saved: ' + response.data.message)
			})
			.catch((error) => {
				showError('Failed to save settings: ' + error.response.data.error_message)
				console.error(error)
			})
	})
	container.append(themeButton)
}

// we wait for the page to be fully loaded
document.addEventListener('DOMContentLoaded', (event) => {
	main()
})
```

::: info
This file contains comments to give you an idea of what the code actually does. This tutorial will not cover the details of the JavaScript sources.

Our JavaScript source file has to be compiled with Vite. A basic configuration is defined in the vite.config.js file, which you can view and optionally edit to your liking.

:::

### Step 4: Configure Vite (Optional)

In the app's directory (`catgifs`), you can find the **vite.config.js** file, containing a basic Vite configuration. No further edits are required, but you are free to change it however you like according to the [Vite documentation](https://vite.dev/config/).

::: info
In the basic configuration, the rule named "main" says that Vite should build one production file named `js/catgifs-main.mjs` from the `src/main.js` source file.

:::

### Step 5: Compile the source script

In the terminal, in our app's directory, run the following command to compile the JavaScript file:

```
npm run dev
```

::: warn
If this is not successful, and you think you broke something:

First run:  
**rm -r node_modules package-lock.json**

This will remove everything you generated so you can start from scratch.

Then run:  
**npm install**

Followed by:  
**npm run dev**

:::

If successful, you should see `catgifs-main.mjs` created in a new `js` directory and `catgifs-main.css` (currently unused) in a new `css` directory.

::: info
There are three ways to compile JavaScript:

`npm run dev`  
is meant for development. The build time is smaller but the production files are bigger, so this means the overall performance of the app is not optimal for production environments.

`npm run build`  
is meant for production. It is slower to run but the output is a small file that is more performant.

`npm run watch`  
is the most interesting one for developers. This will consistently check if you make changes and compile the source files again if needed. This means if you make a change in a source file and then refresh your app's web page in Nextcloud you will immediately see the changes. This is a convenient way to compile which you may prefer over `npm run dev`.

For this tutorial, we use **npm run dev**.

:::