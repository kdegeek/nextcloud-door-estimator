# Tutorial: Set up a quick development environment with GitHub Codespaces

1. Log in to GitHub
2. Go to <https://codespaces.new/nextcloud/server> and click **Create codespace**. You don't have to adjust any of the settings. (see screenshot)

   ::: info
   As the latest development (`master`) branch of Nextcloud may contain breaking changes, there is a chance that the later tutorials may no longer work as well. To avoid this, you can select a branch named `stableXX`, where "XX" is the Nextcloud version you want to install (e.g. 31).

   :::

   ::: info
   GitHub Codespaces is a paid service (you pay per time of use) but if you only use it for this workshop, you probably won't ever run into the paywall. At the end of this tutorial, we will explain you how to stop the service so that the paywall won't be reached.

   :::

![Scherm­afbeelding 2024-01-05 om 11.16.48.png](.attachments.8178895/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.16.48.png)

- You will see the screen "Setting up your codespace". On a normal internet speed, this may take a few minutes.

  ::: info
  On Firefox, you may see a "Oh no, it looks like you are offline!" error message displayed instead. This is likely due to Firefox's Enhanced Tracking Protection feature preventing you from connecting to the codespace. Adding the codespace URL as an exception (under **Settings > Privacy & Security > Enhanced Tracking Protection > Manage Exceptions...**) and refreshing the page may resolve this.

  :::

![Scherm­afbeelding 2024-01-05 om 11.18.38.png](.attachments.8178895/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.18.38.png)

- Once completed, you will see the following screen:

![Scherm­afbeelding 2024-01-05 om 11.23.01.png](.attachments.8178895/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.23.01.png)

- In the bottom-right window, click on the **PORTS** tab. You should see the following:

![Scherm­afbeelding 2024-01-05 om 11.23.46.png](.attachments.8178895/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.23.46.png)

- Right-click on the entry for port **80**. Click "**Open in Browser**".

![Scherm­afbeelding 2024-01-05 om 11.25.39.png](.attachments.8178895/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.25.39.png)

- Your Nextcloud site will open. You can log in with the username **admin** and password **admin**.

![Scherm­afbeelding 2024-01-05 om 11.27.12.png](.attachments.8178895/Scherm%C2%ADafbeelding%202024-01-05%20om%2011.27.12.png)

::: info
Would you like a more permanent, local solution? See our tutorial titled [Setting up a development environment](https://cloud.nextcloud.com/s/iyNGp8ryWxc7Efa?path=%2F1%20Setting%20up%20a%20development%20environment).

:::

::: warn
When you are done with your coding, don't forget to stop this environment because you have to pay after a certain amount of time.

:::

## How to stop your environment:

- Go to <https://github.com/codespaces>
- Under **By repository**, click on **nextcloud/server** to see only your Nextcloud codespaces
- Click on the three-dot menu item on the right where you can click **Delete** (see screenshot).

![afbeelding.png](.attachments.8178895/afbeelding.png)