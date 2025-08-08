# 1 Ubuntu install nvm

::: info
In this tutorial, we will first introduce nvm, which is a Node Version Manager. Through nvm we will install [Node.js](https://en.wikipedia.org/wiki/Node.js) and [npm](https://en.wikipedia.org/wiki/Npm), which are tools you need for running and compiling JavaScript.

It is possible to install Node.js and npm without nvm, but as you might need different versions of Node.js/npm depending on the project you are working on, it is much more convenient to be able to quickly switch between versions thanks to nvm.

:::

### Prerequisites

We assume you run an Ubuntu 24.04 Linux system with shell access and `curl` already installed.

### Step 1: Install nvm

Run the following installer script:

```
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
```

Enter your administrator password if prompted and press **Enter/Return** to continue. Wait for the download to finish.

The nvm installer script creates an environment entry to the login script of the current user. You can either log out and log in again to load the environment or execute the below command to do the same.

```
source ~/.bashrc 
```

Then, run the following to confirm that nvm is installed:

```
nvm -v
```

You should see a version number output to the terminal.

### Step 3: Install Node.js and npm

Run the following to install the latest LTS (Long-Term Support) versions of Node.js and npm:

```
nvm install --lts
```

Run the following to start using this version:

```
nvm use --lts
```

Check if you have npm installed by running the following:

```
npm -v
```

You should see a version number output to the terminal.

Check if you have Node.js installed by running the following:

```
node -v
```

You should see a version number output to the terminal.