# Tutorial: Basic app development troubleshooting

## Disable browser caching

**Steps to take:**

- In your Nextcloud instance in your browser, open Developer Tools.
  - Firefox:

    You open the Web Console from a menu or with a keyboard shortcut:
    - Select the *Web Console* panel in the Web Developer Tools, accessible from the Browser Tools sub-menu.
    - Press the **Ctrl** + **Shift** + **K** (**Cmd** + **Opt** + **K** on macOS) keyboard shortcut.
  - Chrome (or any Chromium-based browser such as Brave):

    Click on the **three-dot icon** in the top right, or the 'settings' button in Brave > **More tools** > **Developer Tools** (see screenshot).

    ![image (3).png](.attachments.7574438/image%20%283%29.png)
  - Safari

    If you don’t see the Develop menu in the menu bar, choose **Safari** > **Settings**, click **Advanced**, then select “**Show Develop menu in menu bar**”. Then access the developer tools through the '**Develop**' menu > '**Show JavaScript Console**' (see screenshot).

    ![Screenshot 2023-06-16 at 10.52.47 (2).png](.attachments.7574438/Screenshot%202023-06-16%20at%2010.52.47%20%282%29.png)
- Navigate to the *Network* tab and make sure the option to disable the caching is active.
- Keep the developer tools open (also for later use) and reload the page.

## Restart your docker container

::: info
This eliminates problems caused by caching.

:::

- In the terminal screen of your docker environment, first press **Ctrl + C**, then run:

```
docker-compose down
```

- Then to restart the container, run:

```
docker-compose up nextcloud proxy
```

::: info
Known errors that are solved with this step:

\- `Autoload path not allowed` in the nextcloud.log file

\- When you adjust a file name in your app, the change does not seem to take effect.

:::

## Check the developer console for hints

**Steps to take:**

- In your Nextcloud in your browser, open your Developer Tools.
- Go to the **Network** tab, optionally clean the history, and trigger the error in the browser window (i.e., reproduce the bug).
- Analyze the network traffic of the browser where the problem is located. The points here are only shortly described, below are more detailed descriptions of the various steps.
  - The PHP code in the back-end can fail. You will see the **status code** to be in the range of **400 and above**. Status code 500 in particular indicates a problem that is thrown by the server in the case of an uncaught exception. Look out for failing requests in the log.
  - It is possible that the back-end **response** is simply **not as expected**. It could be the wrong data, the wrong format, or anything else. You can skim over the returned values if they seem feasible. Unfortunately, this is very app-specific, so you need to know what you are expecting.  
    Problems of this category will also surface in the front-end, so you might want to go back to this point later on.
  - Problems in the front-end typically leave a trace in the **Console** tab visible in the development tools. Have a look at errors and warnings.
  - If you are using a **Vue front-end**, it might be a good idea to look *inside* the Vue components to find problems. This could be caused by wrong input data (see above, provided by the server) or simply bugs in the Vue components.  
    This needs a bit of preparation both in the server and the browser to work. There will be a separate troubleshooting for this.

### Analyzing server exceptions

- Go to the **Network** tab in the developer console.
- In the list of requests ('**name**' section), select the offending request (see screenshot) that typically threw a status code of **500**.

![Screenshot 2023-06-16 at 11.07.36.png](.attachments.7574438/Screenshot%202023-06-16%20at%2011.07.36.png)

- By clicking on the request, a side panel is opened or updated. Go to the '**Preview**' tab in the side panel (see screenshot above).
- If you use the [official docker development environment](https://github.com/juliushaertl/nextcloud-docker-dev) (or have the `$debug` setting in the NC server set to `true`), there should be some information on what the exception was and also a back-trace that shows the exact line the exception was thrown. This should get you started to look for the bug.
- Every request to Nextcloud has a unique request ID. This can be used to find log messages that correspond to a request especially if there were failures. The request ID can be found in the response headers in the browsers network console as "x-request-id". The following screenshot shows how to find the x-request-id:

  ![image (2).png](.attachments.7574438/image%20%282%29.png)

  The same ID is then used in the Nextcloud.log file in the JSON log entry of the error in the "reqId" field.
- If you found some indication that the server is involved in the issue, a quick glance at the `nextcloud.log` file might be helpful (see below).

### Wrong data from the server and JS issues

If the server provides wrong data, this results in crazy things in the front-end, or the front-end just breaks completely.

To see the log of the browser you open the **Console** tab in the web developer tools. This is a list of all messages that were generated by the browser during rendering the site. There are multiple logging levels available, bigger issues should at least trigger a warning. So, make sure you have enabled the warnings (and all higher logs).

Unfortunately, there are many different sources of problems in the front-end. You will have to read the messages and understand them. If there is a problem with Vue.js, the error message might not be sufficient, have a look at the Vue.js section as well.

In all cases, you have to track down the problem to the corresponding server request that caused the trouble. You can then inspect the request in the **Network** tab in detail and compare its response with the expected one. You might want to look at the **Preview** or the (raw) **Response** side panel tab depending on the type of data transmitted.

If the server provided the correct data, debug your front-end code by all means. You can add debug logging (for very rough and long-running issues) or activate the step debugging the browser to get a clear understanding of what the front-end code is doing exactly. Eventually, you have already found the issue by now.

## Check the nextcloud.log file for hints

- For these steps we assume you are running the [Nextcloud-docker-dev docker instance of Julius](https://github.com/juliushaertl/nextcloud-docker-dev) which we set up in the tutorial about setting up your development environment.
- To get your nextcloud.log file, in a new window of your terminal, run:

  ```
  docker exec -ti master-nextcloud-1 tail -f data/nextcloud.log
  ```
- For better readability, press enter a few times to create some empty space. Then trigger your error.
- Copy-paste the log entry and format it using a JSON formatter.

::: info
There are some JSON formatters online [like this one](https://jsonformatter.org/) but do not use online formatters for log files of production instances since it might leak sensitive data.

:::

::: info
The JSON formatter `jq` is a local command line tool and can both prettify the output as well as restructure the JSON data. You can directly pipe the output of the docker command above by calling `docker exec ... | jq` or use `cat <some temp file> | jq` in order to avoid very long outputs.

:::

- Look at the "**Message**" section. Also the **reqId** corresponds with an entry in the browser console.

  For example, the error for a wrong namespace in the PageController of the tutorial 'Develop a complete app with a navigation bar and database" could look like this:

  ```json
  {
    "reqId": "UOtklTvu6kqLcsKgy185",
    "level": 3,
    "time": "2023-06-16T09:11:37+00:00",
    "remoteAddr": "192.168.21.5",
    "user": "admin",
    "app": "index",
    "method": "GET",
    "url": "/index.php/apps/notebook/",
    "message": "Could not resolve OCA\\WrongName\\Db\\NoteMapper! Class \"OCA\\WrongName\\Db\\NoteMapper\" does not exist",
   
  [... more content was here, but cut away for readability...]
  ```

## Problems in the Vue.js code

Many problems in Vue.js can be analyzed better if the data in the components can be checked. There is a tool available for this but it is sort of brittle due to security concerns by the browsers. Note, this is an **advanced topic**.

::: warn
The steps in this section open some security protection mechanisms. **Do not use them in productive environments!**

:::

In order to use this, you need a few pieces in place for it to work:

- The `hmr_enabler` app must be installed. You have to download it from [GitHub](https://github.com/nextcloud/hmr_enabler/) as it is not in the app store for good reasons.
- Install the **Vue.js dev tools for your browser** (for [Chrome](https://chrome.google.com/webstore/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd) and [Firefox](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/)).
- Build your **Vue app in development mode** and force-reload the browser (Ctrl + F5, typically).
- In the web developer console, there should be a new tab **Vue** visible close to the end. By clicking there you can navigate the page from the abstract perspective of the Vue components.

::: info
The most critical part is that the browser must not prohibit the execution of the Vue dev tools extension. This adds quite some JS to the side in order to access the values. Browsers do not like such hackery for security reasons.

The browser will potentially report that the page was not built in development mode which is no clear indication. However, for some configurations, the Vue tab is not showing up. Eventually, you will have to restart the web console (close and reopen) or even the browser. Feel free to report back on what helped in your case.

:::

## Ask questions

If you are running into issues with a tutorial, first retry the tutorial from scratch.

If you are developing a custom app, try to have a look at other apps that are implementing features similar to your app to see how they implemented it. You can ask for example apps on the Nextcloud development forum.

In any case, if you are stuck, upload your code to a public repository and ask your questions to the development forum. This forum is watched by several Nextcloud developers and maybe they have an idea to get you unstuck.

You can find the development forum here:

<https://help.nextcloud.com/c/dev/11>

You can also ask questions in the community developer chat:

<https://cloud.nextcloud.com/call/xs25tz5y>