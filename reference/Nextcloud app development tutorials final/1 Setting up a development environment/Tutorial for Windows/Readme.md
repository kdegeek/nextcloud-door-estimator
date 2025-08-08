# Tutorial: install a Nextcloud development environment for Windows

**This tutorial contains one step:**

1. **Install the development environment**

### ℹ️ Before you start

Before you start, make sure your WiFi is working.

⚠️ Also note that **the steps for Windows are more complicated than for Ubuntu or macOS**. If you succeed in installing this, you proved to have enough perseverance to also succeed in developing several awesome apps in the future. 😉

# 

::: success
**🙋 Frequently asked questions:**

**How to stop the docker container?**  
For Mac, In the Docker Desktop App, go to Containers, and then next to "nextcloud" you see on the right some icons. Click the ◼️ icon to stop the container, and then click the bin-icon to delete the container.

For Ubuntu and Windows, Press **ctrl+c** on your keyboard  
Then, type the command `docker-compose down` and press the enter key on your keyboard.

**How to start the docker container a next time?**  
In the Terminal, cd to the folder nextcloud-docker-dev and run:  
`docker-compose up nextcloud proxy`

**How to use** [**Nextcloud occ commands**](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html?highlight=occ)**?**

`docker-compose exec -it nextcloud php ./occ <your-command>`

**How to run another Nextcloud version, LDAP, or S3?**  
For different versions there is some documentation at <https://github.com/juliushaertl/nextcloud-docker-dev#running-stable-versions>

For LDAP at <https://github.com/juliushaertl/nextcloud-docker-dev#-ldap>

For S3 <https://github.com/juliushaertl/nextcloud-docker-dev#object-storage>

If you are looking to quickly test against different server versions you might be more interested in this docker image of szaimen: <https://github.com/szaimen/nextcloud-easy-test>

:::