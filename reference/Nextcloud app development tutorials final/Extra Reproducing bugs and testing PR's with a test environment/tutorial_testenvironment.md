# Tutorial: Reproducing bugs and testing PRs with a test environment

## 1: Make sure you have Docker installed

For this tutorial we assume you have already followed our previous tutorial about setting up a development environment. If you did so, you already have Docker installed. If you didn't, you can find the tutorial [here](https://cloud.nextcloud.com/s/iyNGp8ryWxc7Efa?path=%2F1%20Setting%20up%20a%20development%20environment).

(If you have followed the whole tutorial about setting up a development environment, you don't have to shut down your development environment for this tutorial. You can just follow the steps of this tutorial and the environments can co-exist with each other.)

## 2: Install the test environment

- Go to [__https://github.com/szaimen/nextcloud-easy-test__](https://github.com/szaimen/nextcloud-easy-test)  
  In the 'Execution' section, you can find the commands either for Linux and Mac or Windows (see screenshot below). Copy the command for your operating system.

  ![Screenshot 2023-05-26 at 13.55.56.png](.attachments.7491110/Screenshot%202023-05-26%20at%2013.55.56.png)
- Run the command in a new window of your terminal or command line.
- When the installation is done, you will see the following message in green:

  ```
  The server should now be reachable via https://localhost:8443/
  You can log in with the user 'admin' and its password 'nextcloud'
  ```
- Go to the URL in the message and login with the username and password provided in the message.

## 3: Testing a PR

- Stop and remove the container you started in the previous step by running:

  ```
  docker stop nextcloud-easy-test
  docker rm nextcloud-easy-test
  ```
- Find a pull request you want to test in the [Nextcloud server repository](https://github.com/nextcloud/server/pulls).
- Copy the branch name of the pull request by clicking the icon, see screenshot below:

  ![Screenshot 2023-05-31 at 11.59.18.png](.attachments.7491110/Screenshot%202023-05-31%20at%2011.59.18.png)
- Copy the command again for your operating system from [__https://github.com/szaimen/nextcloud-easy-test__](https://github.com/szaimen/nextcloud-easy-test) and replace `master` in the second line of the command with the branch name of your pull request.

  ❗️Good to know: If you picked a front-end PR where the changes need to be compiled, like the one we picked as an example, you also need to add the COMPILE_SERVER by adding the following line between line 5 and 6 of the command:

  ```
  -e COMPILE_SERVER=1 \
  ```
- On Linux/macOS, an example command for a front-end PR might look like:

  ```
  docker run -it \
  -e SERVER_BRANCH=enh/noid/adjust-light-color \
  --name nextcloud-easy-test \
  -p 127.0.0.1:8443:443 \
  --volume="nextcloud_easy_test_npm_cache_volume:/var/www/.npm" \
  -e COMPILE_SERVER=1 \
  ghcr.io/szaimen/nextcloud-easy-test:latest
  ```
- Run the command in your terminal.
- An environment will be started with this PR. Then you can test to see if the PR does what the description says it does.

## 4: Running different Nextcloud versions and adjusting the log level

Maybe you want to test a PR against different Nextcloud versions, or a bug got reported and you want to see if you can reproduce it on a vanilla version of the same Nextcloud version and also on the latest version of Nextcloud.

- Stop and remove the container you started in the previous steps by running:

  ```
  docker stop nextcloud-easy-test
  docker rm nextcloud-easy-test
  ```
- Find the branch of the Nextcloud version you want to test. You can find this by going to the [Nextcloud server repository](https://github.com/nextcloud/server) and searching for `stable` (see screenshot below). The listed branches are all the different Nextcloud versions. So for example, `stable23` is the current master branch of the Nextcloud 23 version.

  ![Screenshot 2023-05-26 at 15.24.02.png](.attachments.7491110/Screenshot%202023-05-26%20at%2015.24.02.png)
- Similar to the previous step, replace `master` in the second line of the command with the branch name of your pull request.

  If you are reproducing bugs, you also want to adjust the log level to get more detailed messages in your terminal. Do so by adding the following line in between line 5 and 6:

  ```
  -e NEXTCLOUD_LOGLEVEL=0 \
  ```
- For example, for Linux or Mac the whole command would look like:

```
docker run -it \
-e SERVER_BRANCH=stable25 \
--name nextcloud-easy-test \
-p 127.0.0.1:8443:443 \
--volume="nextcloud_easy_test_npm_cache_volume:/var/www/.npm" \
-e NEXTCLOUD_LOGLEVEL=0 \
ghcr.io/szaimen/nextcloud-easy-test:latest
```

::: info
Good to know:

\- If bugs cannot be reproduced on a vanilla Nextcloud like this setup, there is a good chance the bug is not a bug but an infrastructure / configuration issue, or maybe it is caused by code customizations or other apps. It would be useful to add as a comment that the bug could not be reproduced on this Nextcloud Easy Test Instance.

\- Always check if a bug can still be reproduced on the latest version of Nextcloud. The bug report can be closed if it can not be reproduced on the latest version.

\- If the bug is still there on the latest version, and it is a back-end bug, it is useful to find the relevant logs and add them to the GitHub issue. Logs of errors are directly logged to the terminal. Make sure to format them (e.g. by using <https://jsonformatter.org/>).

:::

## 5: Running two containers with different branches at the same time

Running two different test environments at the same time is useful if you, for example, want to do a user test. You might want to run one branch with master and one branch with some changes.

- Stop and remove the container you started in the previous steps by running:

  ```
  docker stop nextcloud-easy-test
  docker rm nextcloud-easy-test
  ```
- For the first environment, just start the master branch (as in step 2).
- For the second environment,
  - Copy the command again for your operating system at [__https://github.com/szaimen/nextcloud-easy-test__](https://github.com/szaimen/nextcloud-easy-test)
  - Replace `master` in the second line of the command with the branch name of your pull request (as in step 3).
  - Adjust the name to something else other than `nextcloud-easy-test`, e.g. **nextcloud-easy-test2**
  - Adjust the port to something else other than 8443:443, e.g. **8444:443**

    On Linux/macOS, an example command might look like:

    ```
    docker run -it \
    -e SERVER_BRANCH=log-cert-list-errors \
    --name nextcloud-easy-test2 \
    -p 127.0.0.1:8444:443 \
    --volume="nextcloud_easy_test_npm_cache_volume:/var/www/.npm" \
    ghcr.io/szaimen/nextcloud-easy-test:latest
    ```
- You will not get a URL to access your second instance, but you can access the second instance in a similar manner by taking the URL of the first instance and changing the port accordingly. So for the example command above, the URL of the instance would be:

  ```
  https://localhost:8444/
  ```
- To stop both and remove containers, run the following:

```
docker stop nextcloud-easy-test nextcloud-easy-test2
docker rm nextcloud-easy-test nextcloud-easy-test2
```

## 6: Testing different apps, or different Vue.js versions, or other things, at the same time

If you have a new feature that requires a change in server and another change in, for example, the Vue library, you could run a server branch, an app branch, and a branch of the Vue library at the same time.

- Stop and remove any containers you ran previously.
- For this example we will spin off Nextcloud version 27, a Vue branch, and the Talk app.
- Copy the right command for your operating system and add new lines between line 5 and 6 with the following template:

  ```
  -e VARIABLE_HERE=master \
  ```
  - Instead of `VARIABLE_HERE`, insert one of the variables specified in the "[Available APPS](https://github.com/szaimen/nextcloud-easy-test?tab=readme-ov-file#available-apps)" or "[Other variables](https://github.com/szaimen/nextcloud-easy-test?tab=readme-ov-file#other-variables)" sections of the nextcloud-easy-test documentation. For example, the variable for Nextcloud Vue is `NEXTCLOUDVUE_BRANCH`, and the variable for the Talk app is `TALK_BRANCH`.
  - Instead of `master`, you can also run any branch of a PR you can find on GitHub.
- On Linux/macOS, an example command might look like:

  ```
  docker run -it \
  -e SERVER_BRANCH=stable27 \
  --name nextcloud-easy-test \
  -p 127.0.0.1:8443:443 \
  --volume="nextcloud_easy_test_npm_cache_volume:/var/www/.npm" \
  -e NEXTCLOUDVUE_BRANCH=stable7 \
  -e TALK_BRANCH=stable27 \
  ghcr.io/szaimen/nextcloud-easy-test:latest
  ```
- Run the command in your terminal, and when the installation is done, the URL to access your instance will be provided to you.

## Final notes

- It is not possible to test your custom apps with this environment, but if your app is in the App Store, then it is possible to download the app through the App Store in the environment. Instructions on how to upload your app to the App Store can be found [here](https://nextcloudappstore.readthedocs.io/en/latest/developer.html).
- More complex infrastructures (like LDAP, etc.) or extra containers (like Nextcloud Office / Collabora) cannot be added to this environment. This is possible with the development environment. We have a [tutorial](https://cloud.nextcloud.com/s/iyNGp8ryWxc7Efa?path=%2F1%20Setting%20up%20a%20development%20environment) that covers how to set up a basic one, and [this README](https://github.com/juliushaertl/nextcloud-docker-dev) contains instructions on how to add extra elements.