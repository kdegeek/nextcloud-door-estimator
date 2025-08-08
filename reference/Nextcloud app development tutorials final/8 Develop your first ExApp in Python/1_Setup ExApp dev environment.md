# Tutorial: Setup a development environment for Python ExApps

::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

### 0 Install Python

A local setup for Python development requires the following software to be installed globally on your system:

- [Python 3](https://www.python.org/),
- the [pip](https://pypi.org/project/pip/) package installer, and
- the [venv](https://docs.python.org/3/library/venv.html) Python module.

For the example app in this tutorial, we also need:

- the [Gifsicle](https://www.lcdf.org/gifsicle/) command-line tool.

On Ubuntu, Python should be automatically installed, but the others might not be. You can install them by running the following command in a terminal:

```
sudo apt update && sudo apt install python3-pip python3-venv gifsicle
```

### 1 Install AppAPI

::: info
AppAPI is a Nextcloud app that provides a network interface allowing ExApps (deployed as separate web servers or Docker containers) to communicate with the Nextcloud core via one or more "deploy daemons."

For more details, you can view the AppAPI documentation [here](https://nextcloud.github.io/app_api/DevSetup.html).

:::

::: success
As of Nextcloud version 30.0.1, AppAPI is now installed by default. If AppAPI is not installed, or if you want to run the latest development version of AppAPI, you can follow the steps below.

:::

In the `apps` directory within your Nextcloud setup, retrieve the latest development version of AppAPI from GitHub:

```
git clone https://github.com/nextcloud/app_api.git
```

Change into the newly created **app_api** app directory:

```
cd app_api
```

Install npm dependencies and build the front-end assets in development mode:

```
npm ci && npm run dev
```

Enable the app from the **Apps > Your apps** page in Nextcloud or via the `occ` command:

```
docker-compose exec -u www-data nextcloud php occ app:enable --force app_api
```

### 2 Create a Deploy Daemon

Create a deploy daemon for development with the `manual_install` type. You can do so in Nextcloud by heading to **Administration settings > AppAPI > Deploy Daemons** and clicking on **+ Register Daemon**. A form will pop up asking for the necessary information. Select "Manual install" for the daemon configuration template. Leave the remaining fields as their default values except for the Nextcloud URL which should be set to `0`. Click **Register** to confirm (see screenshot).

![Screenshot 2024-12-06 at 20-11-51 AppAPI - Administration settings - Nextcloud.png](.attachments.10359608/Screenshot%202024-12-06%20at%2020-11-51%20AppAPI%20-%20Administration%20settings%20-%20Nextcloud.png)

Alternatively, you can also do this from the terminal with the following `occ` command:

```
docker exec nextcloud sudo -u www-data php occ app_api:daemon:register manual_install "Manual Install" manual-install http host.docker.internal 0
```

### 3 Install the Notifications app

::: info
Depending on how Nextcloud is installed, certain apps may or may not be available out-of-the-box. This includes the Notifications app which is a required dependency for the example app in this tutorial. You can check the list of installed apps under **Apps > Your apps** and, if it is not installed, you can install it with the steps below.

:::

Back in the `apps` directory, retrieve the latest development version of the Notifications app from GitHub:

```
git clone https://github.com/nextcloud/notifications.git
```

Then, under **Apps > Your apps**, the app should now be added to the list and you can enable it from there. Refresh the page if you don't see it at first. Or, with the `occ` command:

```
/var/www/html/occ app:enable --force notifications
```