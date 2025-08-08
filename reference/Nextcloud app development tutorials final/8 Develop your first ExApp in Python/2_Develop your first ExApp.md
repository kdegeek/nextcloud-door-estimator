# Tutorial: Develop your first Python ExApp

**The purpose of this tutorial is to familiarize yourself with the basic structure and functionalities of a Nextcloud ExApp in Python. For this tutorial, we will implement a ToGif app which displays a drop-down menu that converts a video file into GIF format.**

![Screenshot 2024-12-06 at 20-05-22 Media - All files - Nextcloud.png](.attachments.10360315/Screenshot%202024-12-06%20at%2020-05-22%20Media%20-%20All%20files%20-%20Nextcloud.png)

## 1 Clone the skeleton app to your local environment

::: info
Currently, the Nextcloud App Store does not provide a skeleton generator for ExApps, so we must download the [Python skeleton app](https://github.com/nextcloud/app-skeleton-python) directly from GitHub and manually change the app's metadata to suit our needs.

:::

In a separate directory (outside of the Nextcloud server directory), clone the latest version of the Python skeleton app into a new directory named **to_gif**:

```
git clone https://github.com/nextcloud/app-skeleton-python.git to_gif
```

Change into the newly created **to_gif** directory:

```
cd to_gif
```

## 2 Optional information: An overview of the skeleton files

As with the [Hello World tutorial](https://cloud.nextcloud.com/s/iyNGp8ryWxc7Efa?path=%2F%2F2%20Develop%20your%20first%20Hello%20World%20app), **you don't have to memorize this section and can even skip reading this chapter entirely**, but if you are interested in what the Python skeleton includes, read on.

Some of the files in this directory serve exactly the same purpose as in a classic Nextcloud app and so we won't repeat information from the previous tutorials here. Below, you'll find an overview of the new files and directories present in the Python skeleton app and the purpose for each.

| File/Folder        | Description                                                 |
|--------------------|-------------------------------------------------------------|
| **./ex_app/**          | Contains all Python source code for the app                 |
| **./Dockerfile**       | Contains instructions on how to build the Docker container  |
| **./healthcheck.sh**   | Script for verifying the health of the Docker container     |
| **./requirements.txt** | The place to define all Python dependencies (if any)        |
| **./start.sh**         | Script that defines the entrypoint for the Docker container |

Other files and directories not necessary for the core functionality of the app include:

- **./.run/** (IntelliJ project run configuration)
- **./.pre-commit-config.yaml** (Defines [pre-commit](https://pre-commit.com/) hooks for automatic linting of source code)
- **./pyproject.toml** (Python linter config)

::: info
The most important file in this app is the `ex_app/lib/main.py` file which contains a minimal working Python example required for an ExApp to run. For a low-level explanation of the Python code, please refer to the documentation here:

<https://cloud-py-api.github.io/nc_py_api/NextcloudApp.html>

:::

## 3 Update app information

Open the `appinfo/info.xml` file and change its contents to:

```xml
<?xml version="1.0"?>
<info>
	<id>to_gif</id>
	<name>ToGif</name>
	<summary>Nextcloud To Gif Example</summary>
	<description>
	<![CDATA[Example of a Nextcloud application written in Python]]>
	</description>
	<version>1.0.0</version>
	<licence>MIT</licence>
	<author mail="your.name@example.com" homepage="https://example.com/my-site">Your Name</author>
	<namespace>ToGifExample</namespace>
	<category>tools</category>
	<website>https://github.com/YOUR_GITHUB_USERNAME/to_gif</website>
	<bugs>https://github.com/YOUR_GITHUB_USERNAME/to_gif/issues</bugs>
	<repository type="git">https://github.com/YOUR_GITHUB_USERNAME/to_gif</repository>
	<dependencies>
		<nextcloud min-version="29" max-version="32"/>
	</dependencies>
	<external-app>
		<docker-install>
			<registry>ghcr.io</registry>
			<image>YOUR_GITHUB_USERNAME/to_gif</image>
			<image-tag>latest</image-tag>
		</docker-install>
		<routes>
			<route>
				<url>.*</url>
				<verb>GET,POST,PUT,DELETE</verb>
				<access_level>USER</access_level>
				<headers_to_exclude>[]</headers_to_exclude>
			</route>
		</routes>
	</external-app>
</info>
```

::: info
Except for the `<external-app>` tag, all of the tags in this file are exactly the same as in a classic Nextcloud app. For some tags such as author, description, website, etc., feel free to change the values to your liking.

:::

Next, open the `Makefile` and change its contents to:

```make
.DEFAULT_GOAL := help

GITHUB_USERNAME := YOUR_GITHUB_USERNAME

APP_ID := to_gif
APP_NAME := ToGif
APP_VERSION := 1.0.0
APP_SECRET := 12345
APP_PORT := 9031

JSON_INFO := "{\"id\":\"$(APP_ID)\",\"name\":\"$(APP_NAME)\",\"daemon_config_name\":\"manual_install\",\"version\":\"$(APP_VERSION)\",\"secret\":\"$(APP_SECRET)\",\"port\":$(APP_PORT),\"routes\":[{\"url\":\".*\",\"verb\":\"GET, POST, PUT, DELETE\",\"access_level\":1,\"headers_to_exclude\":[]}]}"

.PHONY: help
help:
	@echo "  Welcome to $(APP_NAME) $(APP_VERSION)!"
	@echo " "
	@echo "  Please use \`make <target>\` where <target> is one of"
	@echo " "
	@echo "  build-push        builds CPU images and uploads them to ghcr.io"
	@echo " "
	@echo "  > Next commands are only for the dev environment with nextcloud-docker-dev!"
	@echo "  > They must be run from the host you are developing on, not in a Nextcloud container!"
	@echo " "
	@echo "  run31             installs $(APP_NAME) for Nextcloud 31"
	@echo "  run               installs $(APP_NAME) for Nextcloud Latest"
	@echo " "
	@echo "  > Commands for manual registration of ExApp($(APP_NAME) should be running!):"
	@echo " "
	@echo "  register31        performs registration of running $(APP_NAME) into the 'manual_install' deploy daemon."
	@echo "  register          performs registration of running $(APP_NAME) into the 'manual_install' deploy daemon."


.PHONY: build-push
build-push:
	docker login ghcr.io
	docker buildx build --push --platform linux/arm64/v8,linux/amd64 --tag ghcr.io/$(GITHUB_USERNAME)/$(APP_ID):latest .

.PHONY: run31
run31:
	docker exec master-stable31-1 sudo -u www-data php occ app_api:app:unregister $(APP_ID) --silent --force || true
	docker exec master-stable31-1 sudo -u www-data php occ app_api:app:register $(APP_ID) \
		--info-xml https://raw.githubusercontent.com/cloud-py-api/nc_py_api/main/examples/as_app/$(APP_ID)/appinfo/info.xml

.PHONY: run
run:
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:unregister $(APP_ID) --silent --force || true
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:register $(APP_ID) \
		--info-xml https://raw.githubusercontent.com/cloud-py-api/nc_py_api/main/examples/as_app/$(APP_ID)/appinfo/info.xml

.PHONY: register31
register31:
	docker exec master-stable31-1 sudo -u www-data php occ app_api:app:unregister $(APP_ID) --silent --force || true
	docker exec master-stable31-1 sudo -u www-data php occ app_api:app:register $(APP_ID) manual_install --json-info $(JSON_INFO) --wait-finish

.PHONY: register
register:
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:unregister $(APP_ID) --silent --force || true
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:register $(APP_ID) manual_install --json-info $(JSON_INFO) --wait-finish
```

::: info
This `Makefile` registers some common operations/commands such as registering the app as make targets so that we don't have to type the entire commands every single time. Compared to the skeleton `Makefile`, we merely just replace all mentions of the `skeleton` app with our `to_gif` app. We also change the port number of the server (from 9030 to 9031), as no two servers are allowed to share the same port within the same host.

:::

## 4 Enable the app in Nextcloud

::: info
Unlike other (classic) Nextcloud apps, ExApps need to be setup to run as a separate web server that communicates with Nextcloud. In this step, we detail how to setup and start the server for the first time. If you decide to develop multiple ExApps in the future, the following commands must be executed for each ExApp to run on your machine.

:::

Create a new Python virtual environment for the ToGif app. The name of the environment doesn't matter, but for this tutorial, we will just use the name `env` for simplicity.

```
python3 -m venv env
```

If successful, this will create a local `env` directory that will house all of the app's Python package dependencies.

Now, activate the virtual environment:

```
source ./env/bin/activate
```

(Optional) Ensure that pip is updated to the latest version:

```
python3 -m pip install --upgrade pip
```

Install the app's project dependencies (which are listed in the `requirements.txt` file):

```
pip install -r requirements.txt
```

(Optional, but recommended) Install pre-commit hooks for the ToGif app:

```
pre-commit install
```

Start the web server:

```
APP_ID=to_gif APP_PORT=9031 APP_SECRET=12345 APP_VERSION=1.0.0 NEXTCLOUD_URL=http://nextcloud.local APP_HOST=0.0.0.0 python3 ex_app/lib/main.py
```

::: info
This command runs the `main.py` script which defines the app's API based on the [Uvicorn](https://www.uvicorn.org/) and [FastAPI](https://fastapi.tiangolo.com/) frameworks, and starts up a server configured with the environment variables specified above.

:::

Now, while the above command is running, open a separate terminal and run:

```
make register
```

after which the app should now be enabled in Nextcloud. You can confirm this by going to the **Apps > Your apps** page, where you should see the ToGif app enabled in the list (see screenshot).

![Screenshot 2024-12-06 at 19-28-39 Your apps - App Store - Nextcloud.png](.attachments.10360315/Screenshot%202024-12-06%20at%2019-28-39%20Your%20apps%20-%20App%20Store%20-%20Nextcloud.png)

## 5 Changing the skeleton to suit our needs

As with the skeleton for the classic Nextcloud app, the Python skeleton app does nothing at the moment. We will now implement the ToGif app in this step.

First, we need to install some additional dependencies. Open the `requirements.txt` file and replace its contents with the following:

```
nc_py_api[app]>=0.14.0
pygifsicle
imageio
opencv-python
numpy
```

::: info
The Nextcloud Python Framework (or `nc_py_api` for short) is the Python package from which all ExApps in Python are built. For more information about the module and its capabilities, please refer to its documentation here:

<https://cloud-py-api.github.io/nc_py_api/index.html>

The remaining dependencies in this list are only needed for this tutorial.

:::

Back in the first terminal where the web server is running, press **Ctrl + C** to stop the running server/process, then install the new dependencies:

```
pip install -r requirements.txt
```

Now, open the `ex_app/lib/main.py` file and replace its contents with the following:

```python
"""Simplest example of files_dropdown_menu + notification."""

from contextlib import asynccontextmanager
import os
from pathlib import Path
import tempfile
from typing import Annotated

import cv2
import imageio
import numpy
from fastapi import BackgroundTasks, Depends, FastAPI, responses
from pygifsicle import optimize

from nc_py_api import FsNode, NextcloudApp
from nc_py_api.ex_app import AppAPIAuthMiddleware, LogLvl, nc_app, run_app, set_handlers
from nc_py_api.files import ActionFileInfoEx


@asynccontextmanager
async def lifespan(app: FastAPI):
    set_handlers(app, enabled_handler)
    yield


APP = FastAPI(lifespan=lifespan)
APP.add_middleware(AppAPIAuthMiddleware)  # set global AppAPI authentication middleware


def convert_video_to_gif(input_file: FsNode, nc: NextcloudApp):
    save_path = os.path.splitext(input_file.user_path)[0] + ".gif"
    nc.log(LogLvl.WARNING, f"Processing:{input_file.user_path} -> {save_path}")
    try:
        with tempfile.NamedTemporaryFile(mode="w+b") as tmp_in:
            nc.files.download2stream(input_file, tmp_in)
            nc.log(LogLvl.WARNING, "File downloaded")
            tmp_in.flush()
            cap = cv2.VideoCapture(tmp_in.name)
            with tempfile.NamedTemporaryFile(mode="w+b", suffix=".gif") as tmp_out:
                image_lst = []
                previous_frame = None
                skip = 0
                while True:
                    skip += 1
                    ret, frame = cap.read()
                    if frame is None:
                        break
                    if skip == 2:
                        skip = 0
                        continue
                    if previous_frame is not None:
                        diff = numpy.mean(previous_frame != frame)
                        if diff < 0.91:
                            continue
                    frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                    image_lst.append(frame_rgb)
                    previous_frame = frame
                    if len(image_lst) > 60:
                        break
                cap.release()
                imageio.mimsave(tmp_out.name, image_lst)
                optimize(tmp_out.name)
                nc.log(LogLvl.WARNING, "GIF is ready")
                nc.files.upload_stream(save_path, tmp_out)
                nc.log(LogLvl.WARNING, "Result uploaded")
                nc.notifications.create(f"{input_file.name} finished!", f"{save_path} is waiting for you!")
    except Exception as e:
        nc.log(LogLvl.ERROR, str(e))
        nc.notifications.create("Error occurred", "Error information was written to log file")


@APP.post("/video_to_gif")
async def video_to_gif(
    files: ActionFileInfoEx,
    nc: Annotated[NextcloudApp, Depends(nc_app)],
    background_tasks: BackgroundTasks,
):
    for one_file in files.files:
        background_tasks.add_task(convert_video_to_gif, one_file.to_fs_node(), nc)
    return responses.Response()


def enabled_handler(enabled: bool, nc: NextcloudApp) -> str:
    # This will be called each time application is `enabled` or `disabled`
    # NOTE: `user` is unavailable on this step, so all NC API calls that require it will fail as unauthorized.
    print(f"enabled={enabled}")
    try:
        if enabled:
            nc.ui.files_dropdown_menu.register_ex(
                "to_gif",
                "To GIF",
                "/video_to_gif",
                mime="video",
                icon="img/icon.svg",
            )
            nc.log(LogLvl.WARNING, f"Hello from {nc.app_cfg.app_name} :)")
        else:
            nc.log(LogLvl.WARNING, f"Bye bye from {nc.app_cfg.app_name} :(")
    except Exception as e:
        # In case of an error, a non-empty short string should be returned, which will be shown to the NC administrator.
        return str(e)
    return ""


if __name__ == "__main__":
    # Wrapper around `uvicorn.run`.
    # You are free to call it directly, with just using the `APP_HOST` and `APP_PORT` variables from the environment.
    os.chdir(Path(__file__).parent)
    run_app("main:APP", log_level="trace")
```

::: info
A quick explanation of the Python code: ExApps in Python are implemented as instances of the `FastAPI` class. Each time the app is enabled or disabled, the `enabled_handler` function is called, adding or removing the **To GIF** action in the drop-down menu as appropriate. When a POST request to the "/video_to_gif" endpoint is made (triggered by clicking on the **To GIF** button), the `video_to_gif` function takes the selected file(s) and adds a background task for each file to the queue. Each background task calls the `convert_video_to_gif` function which performs the actual conversion.

:::

Finally, we need an icon to display in the drop-down menu for this action. Create an `img` directory in the `ex_app` directory, download the app icon from [this link](https://raw.githubusercontent.com/cloud-py-api/nc_py_api/refs/heads/main/examples/as_app/to_gif/img/icon.svg), and place the file in the `img` directory.

```
mkdir -p ex_app/img && cd ex_app/img
```

```
curl -SsLO https://raw.githubusercontent.com/cloud-py-api/nc_py_api/refs/heads/main/examples/as_app/to_gif/img/icon.svg
```

## 6 Test the app

In order for the changes to be reflected in Nextcloud, we need to rebuild the server. Change back into the app root directory and start the server again.

```
APP_ID=to_gif APP_PORT=9031 APP_SECRET=12345 APP_VERSION=1.0.0 NEXTCLOUD_URL=http://nextcloud.local APP_HOST=0.0.0.0 python3 ex_app/lib/main.py
```

::: info
Tip: in a terminal, you can also press the **Up** arrow key multiple times to retrieve and auto-fill the previous commands, and then press **Enter** to execute the selected command again.

:::

Then, in the second terminal, re-register the app:

```
make register
```

Now, open the Files app in Nextcloud and navigate to the **Media** directory (create the directory if it doesn't exist already). Add your favorite videos to this directory (although each should be limited to a few seconds at most). If the ToGif app is running successfully, you should be able to right-click on one of the video files and select **To GIF** from the drop-down menu. You can also select multiple video files and click on the **To GIF** button that appears at the top.

After a short while, you will receive a notification for each video that has been successfully converted to a GIF (see screenshot). You can then refresh the page in order to view them in the files list.

![Screenshot 2025-01-31 at 17-57-43 Media - All files - Nextcloud.png](.attachments.10360315/Screenshot%202025-01-31%20at%2017-57-43%20Media%20-%20All%20files%20-%20Nextcloud.png)

## Appendix: Build and deploy the Docker app container

::: info
Once your ExApp is good enough, you might want to publish it to the Nextcloud App Store. Fortunately, the process is exactly the same as with any other Nextcloud app, but before that, there are some additional steps that need to be done. Specifically, we also need to publish the ExApp as a Docker container. When Nextcloud downloads an ExApp from the App Store, it will retrieve the Docker container specified in the `<docker-install>` tag in `appinfo/info.xml` and deploy it before registering the ExApp, completing the installation.

:::

First, open the `Dockerfile` and replace its contents with the following:

```
#############################
# Stage 1: Builder
#############################
FROM python:3.12-slim-bookworm AS builder

# Install build dependencies
RUN apt-get update && apt-get install -y curl && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy requirements and install Python dependencies using a cache mount.
COPY requirements.txt /
RUN --mount=type=cache,target=/root/.cache/pip \
    python3 -m pip install --root-user-action=ignore -r requirements.txt && rm requirements.txt

# Download and install FRP client into /usr/local/bin.
RUN set -ex; \
    ARCH=$(uname -m); \
    if [ "$ARCH" = "aarch64" ]; then \
      FRP_URL="https://raw.githubusercontent.com/nextcloud/HaRP/main/exapps_dev/frp_0.61.1_linux_arm64.tar.gz"; \
    else \
      FRP_URL="https://raw.githubusercontent.com/nextcloud/HaRP/main/exapps_dev/frp_0.61.1_linux_amd64.tar.gz"; \
    fi; \
    echo "Downloading FRP client from $FRP_URL"; \
    curl -L "$FRP_URL" -o /tmp/frp.tar.gz; \
    tar -C /tmp -xzf /tmp/frp.tar.gz; \
    mv /tmp/frp_0.61.1_linux_* /tmp/frp; \
    cp /tmp/frp/frpc /usr/local/bin/frpc; \
    chmod +x /usr/local/bin/frpc; \
    rm -rf /tmp/frp /tmp/frp.tar.gz

#############################
# Stage 2: Final Runtime Image
#############################
FROM python:3.12-slim-bookworm

# Copy installed Python packages and FRP client from the builder.
COPY --from=builder /usr/local/ /usr/local/

# Install any runtime apt packages your app needs.
RUN apt-get update && apt-get install -y ffmpeg libsm6 libxext6 gifsicle && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Add application files.
ADD /ex_app/cs[s] /ex_app/css
ADD /ex_app/im[g] /ex_app/img
ADD /ex_app/j[s] /ex_app/js
ADD /ex_app/l10[n] /ex_app/l10n
ADD /ex_app/li[b] /ex_app/lib

# Copy scripts with the proper permissions.
COPY --chmod=775 healthcheck.sh /
COPY --chmod=775 start.sh /

# Set working directory and define entrypoint/healthcheck.
WORKDIR /ex_app/lib
ENTRYPOINT ["/start.sh", "python3", "main.py"]
HEALTHCHECK --interval=2s --timeout=2s --retries=300 CMD /healthcheck.sh
```

::: info
This file tells Docker to make a copy of the `python:3.12-slim-bookworm` container (a lightweight Debian 12 instance with Python 3.12 installed), add the necessary files and directories for the app, install all dependencies, start the server, and check the health of the running server.

Compared to the skeleton Dockerfile, the only app-specific change we make here is to add the apt package dependencies (listed in line 40 above).

:::

Back inside the `Makefile` and `info.xml` files (especially inside the `<docker-install>` tag), replace `YOUR_GITHUB_USERNAME` with your actual GitHub username. The Docker container will be published to <https://ghcr.io/> under the GitHub account you specify, so make sure it is one that you own.

Then, create a Docker builder instance which will be needed to actually build the container:

```
docker buildx create --name to_gif --driver docker-container --platform linux/amd64,linux/arm64/v8 --use
```

Now, you can build and publish the Docker container with:

```
make build-push
```

This will prompt you to login to GitHub before running the build and publish process.

Once successful, you can follow the steps for [publishing to the App Store](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#publishing-apps-on-the-app-store) as with any other app.

## What's next?

Congratulations! Now you know the basics of developing a Nextcloud ExApp! We hope that after following these steps, you will be able to continue developing and publishing more Nextcloud ExApps, whether it be in Python or in any other language. If you want to see more examples, the `nc_py_api` documentation includes some code for building a simple UI [here](https://cloud-py-api.github.io/nc_py_api/NextcloudUiApp.html), while the [reference section](https://cloud-py-api.github.io/nc_py_api/reference/index.html) lists all of the functionality that is available in the Python module.

## Questions?

If something is wrong, check the Nextcloud server logs or [ask for help in the Nextcloud forum](https://help.nextcloud.com/t/new-tutorial-develop-your-first-exapp-in-python/205108).