# Install the Nextcloud development environment on Windows

### Step 1: Check your system requirements

* Make sure you are using the latest version of Windows 10 or 11. You can update Windows from the Settings menu or with [Windows Update Assistant](https://www.microsoft.com/software-download/).
* 8GB system RAM or more
* SSD system-storage with at least 40GB free space
* 4-core, 8-thread CPU

  ::: warn
  You really have to check your system requirements before continuing. Don't skip this step. If your computer does not meet these system requirements, there is a good chance that this tutorial will not work.

  :::

### Step 2: Install WSL 2

* Open the Windows Command Prompt in administrator mode by right-clicking and selecting 'Run as Administrator'
* Enter the following command:  
  `wsl --install -d Ubuntu-20.04`
* Follow the installation procedure. 
  * If you get the error `WSL 2 requires an update to its kernel component. For information please visit https://aka.ms/wsl2kernel`, visit the link, download the kernel, and install the kernel.

::: info
The --install command performs the following actions:  
1 Enables the WSL and Virtual Machine Platform components  
2 Downloads and installs the latest Linux kernel  
3 Sets WSL2 as the default  
4 Downloads and installs the Ubuntu Linux distribution version 20.04

After the installation procedure you will be able to open the Ubuntu Linux distribution on your computer through the start menu by searching 'Ubuntu'  
\[[source](https://learn.microsoft.com/en-us/windows/wsl/setup/environment)\]

[More resources about installing WSL2 here.](https://learn.microsoft.com/en-us/windows/wsl/install)

[More resources on installing Ubuntu 20.04 in WSL2 + setup of file access here.](https://learn.microsoft.com/en-us/windows/wsl/setup/environment)

:::

* Restart your computer so all changes take effect.

### Step 3: Set up Ubuntu

* Open 'Ubuntu' through the Windows start menu.
* You will be asked to create a **User Name** and **Password** for your Linux distribution. 
  * This **User Name** and **Password** is specific to each separate Linux distribution that you install and has no bearing on your Windows user name.
  * Please note that whilst entering the **Password**, nothing will appear on screen. This is called blind typing. You won't see what you are typing, this is completely normal.

### Step 4: Set up Docker

::: info
[Source of these steps here.](https://learn.microsoft.com/en-us/windows/wsl/tutorials/wsl-containers)

:::

* Go to <https://www.docker.com/products/docker-desktop/>. Click on the download link for Windows users (see screenshot below). This will download a .exe file.

  ![image (2).png](.attachments.7061933/image%20%282%29.png)
* When the download is finished, open the .exe file. Follow the installation procedure.
* When the installation procedure is finished, you might get a prompt to restart your computer. If you get this prompt, restart your computer.
* Once installed, start Docker Desktop from the Windows Start menu, then select the Docker icon from the hidden icons menu of your taskbar (see screenshot below). Right-click the icon to display the Docker commands menu and select "Settings".

  ![image (3).png](.attachments.7061933/image%20%283%29.png)
* Read 😊 and accept the terms of service when prompted:

  ![image (4).png](.attachments.7061933/image%20%284%29.png)
* Ensure that "Use the WSL 2 based engine" is checked in **Settings** > **General**

  ![image (5).png](.attachments.7061933/image%20%285%29.png)
* Ensure that the Ubuntu distributions are selected in **Settings** > **Resources** > **WSL Integration**.
* To confirm that Docker has been installed, open a WSL distribution (e.g. Ubuntu) and display the version and build number by entering: `docker --version`
* Inside Ubuntu some docker commands can only be run by members of the docker group. Add your user to the group so you can run docker commands easily (Replace `user_name` with your login):

  ```
  sudo usermod -aG docker user_name
  ```
* Confirm that the following command works:

  ```
  docker ps
  ```

  The output should be: `CONTAINER ID IMAGE COMMAND CREATED STATUS PORT NAMES`

::: info
If the command `docker ps` does not work, you might be able to fix this by toggling the WSL integration in docker desktop off and on.

:::

### Step 5: Install nvm and Node.js in Ubuntu

::: info
[Source of these steps here.](https://learn.microsoft.com/en-us/windows/dev-environment/javascript/nodejs-on-wsl#install-nvm-nodejs-and-npm)

:::

* Open Ubuntu and make sure it is up to date by running:

```
sudo apt update && sudo apt upgrade
```

* Install curl:

```
sudo apt install curl
```

* Install nvm:

```
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/master/install.sh | bash
```

* Check if nvm is working and see which versions of Node.js are installed (should be none):

```
nvm ls
```

* Install and enable the latest LTS version of Node.js:

```
nvm install --lts
nvm use --lts
```

* Check if Node.js and npm are installed:

```
node --version
npm --version
```

If successful, you should see version numbers for each program output to the terminal.

### Step 6: Edit your host file

You can do this while the command from Step 2 is still loading.

You have to add the following entry to the host file:

```
127.0.0.1 nextcloud.local
```

To do so, we copied the steps from [this guide](https://phoenixnap.com/kb/how-to-edit-hosts-file-in-windows-mac-or-linux#ftoc-heading-6): see below:

#### Step 6.1: Open Notepad as an Administrator

You’ll need administrator privileges for this operation.

1. Click the **Windows button** and type “notepad.” Let the search feature find the Notepad application.
2. Right-click the Notepad app, then click **Run as administrator.**
3. Windows User Account Control should pop up asking, “Do you want to allow this app to make changes to your device?” Click **Yes.**

#### Step 6.2: Open the Windows Hosts File

1. In Notepad, click **File**> **Open**
2. Navigate to C:\\Windows\\System32\\drivers\\etc
3. In the lower-right corner, just above the **Open** button, click the drop-down menu to change the file type to **All Files**.
4. Select “hosts” and click **Open**.

   ![image (6).png](.attachments.7061933/image%20%286%29.png)

#### Step 6.3: Edit the File

Add the following entry to the bottom of the host file and save the file:

```
127.0.0.1 nextcloud.local
```

::: info
Here is a brief breakdown of how the lines of the Windows hosts file are structured:

0.0.0.0 server.domain.com

The first set of four (4) digits is the IP address you’re mapping. This could be the internal IP address of a server on the network, or it could be the IP address of a website.

The second label is the name you want to be able to type in a browser to access the server at the IP address you just specified.

:::

### Step 7: Install git and nextcloud-docker-dev

* Install the git version control system:

```sh
sudo apt install git
```

* Clone the `nextcloud-docker-dev` development environment for Nextcloud and follow the [simple master setup](https://github.com/juliushaertl/nextcloud-docker-dev/#simple-master-setup) to download and install Nextcloud:

```sh
git clone https://github.com/juliushaertl/nextcloud-docker-dev.git
cd nextcloud-docker-dev
./bootstrap.sh
sudo sh -c "echo '127.0.0.1 nextcloud.local' >> /etc/hosts"
```

* Now you can start nextcloud:

```sh
docker-compose up nextcloud proxy
```

### Step 8: Access your Nextcloud

* Open your browser and browse to <http://nextcloud.local/> which should open the Nextcloud in the browser
  * You maybe get prompted to update before proceeding. Click on the "Update" button and follow the procedure.
* Log in through using the username "admin" and password "admin"

  *(ℹ️ the user id and password are the same for all users)*

## 