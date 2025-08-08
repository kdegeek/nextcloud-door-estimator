# Install the Docker Desktop app

::: info
**The steps below are adapted from** [**this tutorial**](https://ostechnix.com/docker-desktop-for-linux/) **which also contains instructions for Debian 11 and Fedora 36.**

:::

**Before getting started, make sure you are using a user with** `sudo` **privileges and that your system has access to the internet.**

### 1: Check Linux kernel Version And Architecture

To view the Kernel and architecture details, run the following command from the terminal:

```
uname -a
```

Sample output:

```
Linux ubuntu2204 5.15.0-41-generic #44-Ubuntu SMP Wed Jun 22 14:20:53 UTC 2022 x86_64 x86_64 x86_64 GNU/Linux
```

This indicates that the Ubuntu system's kernel version is **5.15.0-41-generic** and the Ubuntu system's architecture is **64 bit** (**x86_64 x86_64 x86_64 GNU/Linux**).  
Your system should meet the minimum requirements. The minimum requirements are:

* **64 bit** Linux.
* The Kernel version should be **3.10** or above.

If your system meets the minimal requirements, you can continue.

### 2: Enable KVM Virtualization Support (VT-X)

If your host system supports VT-X, the KVM module should be automatically loaded.

If it is not loaded for any reason, you can manually load the KVM kernel module using one of these commands: `modprobe kvm_intel` (for Intel CPUs) or `modprobe kvm_amd` (for AMD CPUs).

To check if KVM modules are enabled, you can run one of the `kvm-ok` or `lsmod | grep kvm` commands. If enabled, the output for `kvm-ok` might look like:

```
INFO: /dev/kvm exists
KVM acceleration can be used
```

Or, for `lsmod | grep kvm`:

```
kvm_intel             364544  0
kvm                  1003520  1 kvm_intel
```

Finally, we must add our user to the `kvm` group in order to access the `/dev/kvm` device. To do so, run (replacing $USER with your username):

```
sudo usermod -aG kvm $USER
```

Reboot your system for the changes to take effect.

Let us verify the current ownership of `/dev/kvm` using command:

```
ls -al /dev/kvm 
```

The output should look something like:

```
crw-rw----+ 1 root kvm 10, 232 Jul 14 13:31 /dev/kvm
```

::: warn
**Troubleshooting:**

If these steps don't seem to work, you might have to activate the Virtualization feature in the BIOS settings of your computer before taking these steps. [Here's more info for Intel processors](https://www.intel.com/content/www/us/en/support/articles/000005486/processors.html).

:::

#### 3: Update Ubuntu

Open your terminal and run the following commands one-by-one:

```
sudo apt update
```

```
sudo apt full-upgrade
```

#### 4: Add Docker Repository

Install the necessary certificates to allow the **apt** package manager to use a repository over HTTPS using the command:

```
sudo apt install apt-transport-https ca-certificates curl software-properties-common gnupg lsb-release wget
```

Next, add Docker's official GPG key by running:

```
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
```

Add the Docker official repository:

```
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

Update the Ubuntu sources list again:

```
sudo apt update
```

#### 5: Install Docker Desktop

Download latest Docker Desktop DEB package from the official [**release**](https://docs.docker.com/desktop/release-notes/) page.

At the time of writing this documentation, the latest version was 4.10.1. Please check the latest version number from the above link and replace the <version> tags in the following commands accordingly.

```
wget https://desktop.docker.com/linux/main/amd64/docker-desktop-<version>-amd64.deb
```

Run the following command to install Docker Desktop:

```
sudo apt install ./docker-desktop-<version>-amd64.deb
```

At the end of the installation, you will receive an error message like below.

```
[...]
N: Download is performed unsandboxed as root as file '/home/user/docker-desktop-4.10.1-amd64.deb' couldn't be accessed by user '_apt'. - pkgAcquire::Run (13: Permission denied)
```

You can safely ignore this error and continue the subsequent steps.

#### 6: Start Docker Desktop Service

Run the following commands to allow Docker Desktop service to start automatically at every system reboot. **Do not change** 'user' for your username.

```
systemctl --user enable docker-desktop
```

```
systemctl --user start docker-desktop
```

The first command will enable the docker-desktop service to start automatically on system reboot. The second command will start the service if it is not started already.

#### 7: Getting Started With Docker Desktop

Launch Docker Desktop either from Dash or Menu.

When you launch Docker Desktop for the first time, you will be prompted to accept the terms of service. Accept the terms and conditions and **keep the docker desktop app opened when continuing with the next step.**