# Tutorial: Develop your first Hello World app

**The purpose of this tutorial is to familiarize yourself with the basic structure and functionalities of a classic Nextcloud app. Your app will display a button that can show "Hello World!👋"**

![Hello World Tutorial - Preview.png](.attachments.8177723/Screenshot%202024-07-30%20at%2016-03-26%20Hello%20World%20-%20Nextcloud%20%282%29.png)

::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

# 1 Create a skeleton app

::: info
The Nextcloud App Store provides an app skeleton generator. The app skeleton generator generates the necessary core files for your app.

:::

- Go to <https://apps.nextcloud.com/developer/apps/generate>
- Fill in all the required fields. Here are some tips: 
  - **App name:** the name needs to be `HelloWorld`. You should not choose another name, because there are references to this namespace elsewhere in the code that you should then also adjust. Also, pay attention that the name needs to be in PascalCase, for example: *HelloWorld* and not *hello_world*.
  - **Issue tracker URL:** if you are serious about developing an app for the app store, you need to fill in a link to the GitHub repository here where users can report problems or request features. If you are not serious about this (for now 😉), you can fill in a fake URL (e.g., <http://test.com>), but it does need to include the `https://` or `http://` prefix.
  - Except for the app name, you can always easily change the data anytime.
- Click the **Generate and download** button. You will receive a file named `app.tar.gz`.
- Extract this file. You will get a directory with the name of your app (`helloworld`). Copy this directory.

::: info
In a Linux terminal, you can extract the file using the "tar" command: `tar -xzf app.tar.gz`. This will generate the `helloworld` directory in your current working directory.

:::

# 2 Add the template app to your development environment

::: info
You will need to copy the app folder `helloworld` to the right directory of your development environment. How to do this depends on how you have set up your development environment. Scroll to the right option:

2.1a Using the Docker dev environment

2.1aa Using the Docker dev environment on Windows

2.1b Using the GitHub Codespaces environment

:::

## 2.1a Using the Docker dev environment:

- Find the directory `nextcloud-docker-dev`. The location is usually in the root folder as this is where you started the git clone command in the tutorial "Install Nextcloud Docker."
- Open the directory `nextcloud-docker-dev`. Go in the directory `workspace/server/apps-extra`**.**
- Paste the directory of your app, `helloworld`, in the `apps-extra` directory.

## 2.1aa Using the Docker dev environment on Windows:

Find and open the folder `nextcloud-docker-dev`.

- Find this folder through doing a search in your Windows file explorer by searching `nextcloud-docker-dev`.
- Alternatively, if this does not work, use your Windows computer navigation to open **Linux**, then select **Ubuntu-20.04** > **home** > **nextcloud-docker-dev** (see screenshots below).

  ![Hello World Tutorial - Windows 1.png](.attachments.8177723/afbeelding%20%288%29.png)

![Hello World Tutorial - Windows 2.png](.attachments.8177723/afbeelding%20%289%29.png)

![Hello World Tutorial - Windows 3.png](.attachments.8177723/afbeelding%20%2810%29.png)

- Inside `nextcloud-docker-dev`, navigate to the folder `workspace/server/apps-extra`.
- Paste the folder of your app in the `apps-extra` folder.

## 2.1b Using the GitHub Codespaces environment:

- In the sidebar on the left, find the "apps" directory. Drag and drop the `helloworld` directory of your skeleton app into it.

  ![Hello World Tutorial - GitHub Codespaces.png](.attachments.8177723/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.39.52.png)

# 3 Enable the app in Nextcloud

- In your Nextcloud development environment, go to the **Apps** menu (see screenshot):

  ![Hello World Tutorial - Apps.png](.attachments.8177723/Screenshot%202024-07-30%20at%2016-29-06%20Dashboard%20-%20Nextcloud.png)
- From the left sidebar, click on **Your apps**.
- Scroll down to the `Hello World` app. You will see the button that says either **Allow untested app** or **Enable** (see screenshots).
  - **Allow untested app:** click the button. If asked, fill in the password `admin`, and then click **Enable**. If you get an error message that the app is not installed because the version is not correct, you can safely ignore this. The app will be enabled.
  - **Enable:** click the button.

    ![Hello World Tutorial - App - Untested.png](.attachments.8177723/Screen%20Shot%202024-07-30%20at%2016.37.03.png)

    ![Hello World Tutorial - App - Disabled.png](.attachments.8177723/Screen%20Shot%202024-07-30%20at%2016.37.20%20%282%29.png)

    ![Hello World Tutorial - App - Enabled.png](.attachments.8177723/Screen%20Shot%202024-07-30%20at%2016.36.42%20%282%29.png)
- Your app should now appear in the blue bar on the top with a cloud icon (see screenshot):

  ![Hello World Tutorial - Top Bar.png](.attachments.8177723/Screenshot%202024-07-30%20at%2016-04-18%20Hello%20World%20-%20Nextcloud.png)
- If you click on the cloud icon, a blank screen will appear. This is fine as the app is now open but we did not develop any functionality yet (see screenshot):

  ![Hello World Tutorial - Initial App.png](.attachments.8177723/Screenshot%202024-07-30%20at%2016-04-08%20Hello%20World%20-%20Nextcloud.png)

# 4 Optional information: An overview of the skeleton files

You can skip this chapter entirely, but if you are interested in what the skeleton includes, let us explain.

Go back to your app's directory, `helloworld`.

There are a lot of files here for an app that only shows an empty page! The skeleton sets up most, but not all, of the basic functionalities an app can have, so you will only need some of the files. Below, you'll find an overview of the folder structure in a Nextcloud app and what each file is for.

**You don't have to memorize this list and you can even skip reading this chapter entirely**, but you can use it as reference to decide if you need a file provided by the skeleton generator or not. In this tutorial and the other tutorials, we will go through the different files in the skeleton that you need one by one to explain you how to use them.

| File/Folder        | Description                                                                                                                                                                                                                                              |
|--------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **./appinfo/info.xml** | Contains the metadata (name, description, license, etc.) of your app and the basic configuration (navigation items, admin/user setting pages). [More info]().                                                                                                |
| **./img/**             | Images and icons that may be used by the app are placed here                                                                                                                                                                                             |
| **./LICENSES/**        | Should contain all licenses used by the app as separate text files. Sometimes, a single LICENSE file may be used instead.                                                                                                                                |
| **./lib/**             | Contains all PHP class files of the app                                                                                                                                                                                                                  |
| **./lib/AppInfo/**     | The location of the Application.php file. The Application class is auto-loaded by Nextcloud **on every page load** and should register all relevant services of your app. It may also run custom code like we'll see later...                                |
| **./lib/Controller/**  | Must contain the PHP class(es) with matching names and methods for all routes specified in the routes.php file. These methods should contain only basic handling of the incoming request; all processing should be offloaded to a separate Service class |
| **./lib/Db/**          | Contains all database entity and mapper definitions for storing and retrieving data from the Nextcloud database                                                                                                                                          |
| **./lib/Migration/**   | Contains the database schema migration classes. All database tables and schema updates are defined here.                                                                                                                                                 |
| **.lib/Service/**      | The service PHP classes are usually where the core functionalities of the app backend are implemented. You may have separate service classes for handling incoming requests, settings etc.                                                               |
| **./src/**             | The JS or Vue.js source code files for your app's front-end are placed here. The "compiled" JS files will be placed under **./js/**                                                                                                                          |
| **./templates/**       | PHP templates can be used to render custom pages by your app (like the page that opened when you clicked your apps name in the navigation bar).                                                                                                          |
| **./composer.json**    | The place to define all PHP dependencies (if any)                                                                                                                                                                                                        |
| **./openapi.json**     | The place to define any OpenAPI endpoints (if any)                                                                                                                                                                                                       |
| **./package.json**     | The place to define all JavaScript/Vue dependencies (if any)                                                                                                                                                                                             |

You will also see several files that are not necessary for the core app functionalities but may be helpful in setting up your development pipeline in the future. We will cover some aspects of this in the later tutorials.

- **./.github/** (Contains a basic set of GitHub workflows useful for the development cycle)
- **./tests/** (PHP unit and integrations tests for speeding up your release cycle)
- **./README.md** (Contains a user manual and is prominently displayed on e.g. GitHub)
- **./CHANGELOG.md** (Contains a changelog to record changes between app versions/releases)
- **./CODE_OF_CONDUCT.md** (Contains the Nextcloud code of conduct)
- **./Makefile** (Using this is up to the developer, it's the classic way to build app releases, but not mandatory)
- **./babel.config.js** (Babel JavaScript compiler config file)
- **./.nvmrc** (Controls the Node.js version used if using nvm)
- **./stylelint.config.cjs** (CSS linter config. Only needed if stylelint is used in package.json)
- **./webpack.js** (Needed if you "compile" your JavaScript/Vue.js code from **./src/** with webpack)
- **./vite.config.js** (Needed if you "compile" your JavaScript/Vue.js code from **./src/** with Vite)
- **./.eslintrc.cjs** (ESLint configuration file for linting JavaScript)
- **./psalm.xml** (Static PHP analysis config. Not needed but recommended; see [here](https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/continuous_integration.html) for more information.)
- **./.php-cs-fixer.dist.php** (PHP-CS-Fixer configuration file for linting PHP)
- **./rector.php** (Rector configuration file for automated PHP refactoring and upgrading)

# 5 Changing the skeleton to suit our needs

::: info
Our goal is to develop an app that loads a top-bar navigation item that displays a page (template) that has a button. Pressing the button will display the classic "Hello World!" message. With these goals in mind we can delete parts of the skeleton app that we don't need.

:::

## 5.1 Optional: delete files and directories we don't need

- Some files in the skeleton are only needed for documentation or for setting up a development pipeline. These files are not in the scope of this tutorial but will be covered in later tutorials. Delete the following files or directories, which are located in the `helloworld` directory.

**./.github/**  
**./tests/**  
**./.eslintrc.cjs**  
**./.php-cs-fixer.dist.php**  
**./CHANGELOG.md**  
**./CODE_OF_CONDUCT.md**  
**./.nvmrc**  
**./psalm.xml**  
**./README.md**  
**./rector.php**  
**./stylelint.config.cjs**  
**./webpack.js**  
**./vite.config.js**

You can delete them manually. But you can do it faster in a terminal, you can delete all of these files in a single "rm" command, like so:

```sh
rm -rf .github tests .eslintrc.cjs .nvmrc .php-cs-fixer.dist.php rector.php *.cjs *.js *.md Makefile psalm.xml
```

::: info
If you do not see some of these files, they may not exist in the skeleton app or you have to show "hidden files" on your system. Use your favorite search engine with the search term "show hidden files \[your operating system here\]" to find instructions.

:::

- For this app, we only need one controller, namely `PageController.php`. Delete any other controllers in **./lib/Controller/**:

**./lib/Controller/ApiController.php**

No dependencies are necessary for this app. Delete the following files:

**./composer.json**  
.**/openapi.json**  
.**/package.json**

This app doesn't require any JavaScript or Vue.js components either, so delete the **src** directory as well.

**./src/App.vue**  
**./src/main.js**

## 5.2 Update the remaining skeleton files

### 5.2.1 ./appinfo/info.xml

- Open `info.xml`.
- The Nextcloud version filled by the skeleton generator is likely not the right version, because we are often late with upping the version number. Adjust `max-version` to the Nextcloud version of your development environment. You can find the version of your Nextcloud installation in **Administration settings > Overview > Version**.

::: info
The `./appinfo/` directory is for app metadata and configuration. The `info.xml` file contains the metadata (name, description, license, etc.) of your app and the basic configuration (navigation items, admin/user setting pages).  
  
When enabling your app, if you got the warning "allow untested app" it means that your Nextcloud server version is higher than the maximum version that is specified in this `info.xml` file. 

In the `info.xml` file, the `<navigations>` tag specifies that our app will implement a top bar navigation item with the name "Hello World," and that the route that should be accessed when clicking on the navigation item named `page.index`.

And lastly, a beginner tip: if you adjust any file in your app, don't forget to save the file. 😉

:::

### 5.2.2 ./lib/Controller/PageController.php

::: info
Controllers are classes that handle incoming network requests made by clients or by the web interface. The different endpoints of your app are either defined in `appinfo/routes.php` (for legacy apps) or next to each Controller method via the `\OCP\AppFramework\Http\Attribute\FrontpageRoute` or `\OCP\AppFramework\Http\Attribute\ApiRoute` method attributes (for newer apps). The PageController is the controller that will serve the main helloworld app page (<http://your.nextcloud.org/index.php/apps/helloworld/>) accessed when clicking on the app's icon in the Nextcloud top menu bar.

:::

- Change the content of the file to:

```php
<?php

declare(strict_types=1);

namespace OCA\HelloWorld\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller
{
    public function __construct($appName, IRequest $request)
    {
        parent::__construct($appName, $request);
    }

    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[FrontpageRoute(verb: 'GET', url: '/')]
    public function index(?string $getParameter): TemplateResponse
    {
        if ($getParameter === null) {
            $getParameter = "";
        }

        // The TemplateResponse loads the 'index.php'
        // defined in our app's 'templates' folder.
        // We pass the $getParameter variable to the template
        // so that the value is accessible in the template.
        return new TemplateResponse(
            'helloworld',
            'index',
            ['myMessage' => $getParameter]
        );
    }
}
```

::: info
Beginner tip: Wondering what do you see here? You can read that in the code by checking the lines starting with `//`. These comments explain what the code does. Comments can also be indicated with `/* `

Do you see the method with the name `index`? This method is called when requesting the route as described by the `FrontpageRoute` attribute.

Since we pass the `$getParameter` variable to the template, the next step is to look at this template file.

:::

### 

### 5.2.3 ./templates/index.php

::: info
Templates render custom pages by your app (like the page that opened when you clicked your apps name in the navigation bar).

Here, we will update the `index.php` template to render an HTML web page with a button. When the button is pressed the same page is reloaded with a GET parameter: 'getParameter' => 'Hello World!👋'. This message will be displayed.

:::

- Change the content of the file to:

```php
<!DOCTYPE html>
<html>

<head>
    <title>Hello World Page</title>
    <style>
        /* Necessary style definitions for the page */
        /* Center the content vertically and horizontally */
        myPageContent {
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        /* Style the header with a background color */
        /* and some padding */
        myHeader {
            background-color: #4CAFF0;
            color: #fff;
            border-radius: 10px;
            padding: 10px;
            font-size: 36px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <myPageContent>
        <?php
        if (
            isset($_GET['getParameter']) &&
            $_GET['getParameter'] !== ''
        ) {
            // Display the message if it is specified
            echo '<myHeader>' .
                $_GET['getParameter'] .
                '</myHeader>';
            // Display a reset button
            $submitGetParameter = '';
            $buttonText = 'Reset';
        } else {
            // If no get parameters were specified, display
            // a button that when clicked, reloads the page
            // with the get parameter:
            // myMessage => "Hello World!👋"
            echo '<myHeader>Press the button!</myHeader>';
            // Display a "Click me" button
            $submitGetParameter = 'Hello World!👋';
            $buttonText = 'Click Me';
        }
        ?>
        <!-- Display the button -->
        <form method="GET" action="">
            <button type="submit" name="getParameter" value="<?php echo $submitGetParameter; ?>">
                <?php echo $buttonText; ?>
            </button>
        </form>
    </myPageContent>
</body>

</html>
```

## 6 It's done! 🎉 Test your app

That's it! If you've followed along, you should now have your app ready. Browse to your Nextcloud instance and click your app icon in the top navigation bar. If all went according to plan, you should see a button.

Pressing that button will reload the page while providing "Hello World!👋" as the *getParameter*. This message will then be displayed on the page after it reloads.

::: info
Hint: you can try changing the message by changing the *getParameter* in your browser's address bar.

:::

![Hello World Tutorial - Hello World App 1.png](.attachments.8177723/Screenshot%202024-07-30%20at%2016-03-43%20Hello%20World%20-%20Nextcloud.png)

![Hello World Tutorial - Hello World App 2.png](.attachments.8177723/Screenshot%202024-07-30%20at%2016-03-26%20Hello%20World%20-%20Nextcloud.png)

## What if something went wrong?

More often than not, things don't work out on the first try.

When following a tutorial like this, you probably made a small typing mistake or a copy-paste mistake.

You could start from scratch and double-check that you did not make any copy-paste mistakes.

You could also try debugging. We have a [tutorial](https://cloud.nextcloud.com/s/iyNGp8ryWxc7Efa?path=%2FExtra%3A%20Basic%20app%20development%20troubleshooting) on that.

You can also ask a question in the [forums](https://help.nextcloud.com/c/dev/11) where many volunteers and employees are actively reading. You could also seek help in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y).

## What's next?

Congratulations! Now you know the basics of setting up a Nextcloud app! After following these steps, you can already create simple Nextcloud apps. As your projects grow more ambitious, you'll perhaps need to store information to a database table, add elements to the UI (like a dashboard widget or a Smart Picker), or store settings for both admins and users. We recommend you to go through the next tutorials! You can find all of the tutorials at <https://nextcloud.com/developer/>.