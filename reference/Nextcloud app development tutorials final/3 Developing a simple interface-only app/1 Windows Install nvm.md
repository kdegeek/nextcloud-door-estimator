# 1 Windows install nvm

::: info
In this tutorial, we will first introduce nvm, which is a Node Version Manager. Through nvm we will install [Node.js](https://en.wikipedia.org/wiki/Node.js) and [npm](https://en.wikipedia.org/wiki/Npm), which are tools you need for running and compiling JavaScript.

It is possible to install Node.js and npm without nvm, but as you might need different versions of Node.js/npm depending on the project you are working on, it is much more convenient to be able to quickly switch between versions thanks to nvm.

:::

### Prerequisites

We assume you followed the tutorial to set up your development environment for Windows, which means you already have nvm installed. You can run the following in the (Ubuntu) terminal to check which version of nvm you have installed:

```
nvm -v
```

::: warn
From now on, if we refer in the tutorials to the terminal or ask you to run commands, make sure to use the Ubuntu terminal.

:::

### Step 1: Install Node.js and npm

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