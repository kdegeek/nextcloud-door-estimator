# Tutorial: Developing an integration with external provider using Smart Picker

**In this tutorial, you will learn how to develop an integration with external provider using the Smart Picker. Smart Picker is a feature that was first released in Nextcloud Hub 4 (Nextcloud 26) and is the easiest and fastest way to create integrations that also interact with many different Nextcloud apps. After this tutorial, you will know:**

- **What the Smart Picker is**
- **How to add a unified search provider which searches in your external software using a web service API**
- **How to make the Smart Picker use this search provider to get links to external resources**
- **How to ensure these links get rendered in Nextcloud apps like Text, Talk, Notes, Deck, Collectives, and Mail**
- **How to customize the rendering of these links**
- **How to create admin settings**
- **How to update your development environment so you are developing against the latest development release of Nextcloud**

**This tutorial will guide you through the steps of creating an integration with the stock photo site [Pexels](https://www.pexels.com/). After this tutorial, you can use this app as an example to develop your own integration. If it is good enough, you can submit it to the [Nextcloud App Store](https://apps.nextcloud.com/) and make it available to a larger audience.**

## Required knowledge:

For this tutorial, we assume you have followed all of the previous tutorials.

This tutorial is for anyone who is new to developing Nextcloud apps but do have a working development environment and are familiar with the structure of the Nextcloud app architecture and the usage of Vue components.

::: warn
In this tutorial, we assume you have a development environment created through the Nextcloud Docker setup. This is the only Nextcloud instance that you need for this tutorial. Everything will happen in this instance.

:::

## Screenshots:

When you are in an app that supports the Smart Picker, like Text, any link to Pexels will be rendered:

![Screenshot 2023-03-19 at 15.53.58.png](.attachments.7197220/Screenshot%202023-03-19%20at%2015.53.58.png)

You can also search Pexels content in the unified search bar in the top right menu:

![Screenshot 2023-03-19 at 15.55.04.png](.attachments.7197220/Screenshot%202023-03-19%20at%2015.55.04.png)

## 🤔 Do you have questions?

Reach out on the forum post:

<https://help.nextcloud.com/t/new-tutorial-announcing-app-contest-developing-a-smart-picker-provider/160451>