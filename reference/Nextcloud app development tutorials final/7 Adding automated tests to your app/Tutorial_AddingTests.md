# Tutorial: Adding automated tests to your app

::: info
This tutorial has been updated and tested for Nextcloud version 31, as well as the upcoming version 32 as of June 2025. On future versions, there is a chance that things may break here and there. If you find any bugs, please report them in the [forums](https://help.nextcloud.com/c/dev/11) or in the [community developer talk room](https://cloud.nextcloud.com/call/xs25tz5y)!

:::

## Step 1: Prepare the notebook app

- Make sure you first followed our previous tutorial "Developing a complete app with a navigation bar and database". We will add the tests for this notebook app.
- In the notebook app, go to the `.github` directory and check if there are any files there except from the `workflows` directory. If there are any files, delete them, but do keep the `workflows` directory.
- Delete the files inside the  `.github/workflows` directory.

  ::: info
  If you do not see the `.github` directory, then your operating system is hiding 'hidden files'. You can find out how to display 'hidden files' by using a search engine (on Mac you can display these files by pressing Cmd + Shift + .).

  :::
- Also delete the `.git` directory.

## Step 2: Configure the tests

::: info
On GitHub, you can run actions (e.g. a test) on some events (e.g. a pull request) on your repository. First, we have to to define those actions and events in a .yml file in the repository.

:::

- Create the file `.github/workflows/phpunit.yml` and set its content to:

  ```yml
  name: PHPUnit
  
  on:
    pull_request:
      paths:
        - .github/workflows/phpunit.yml
        - appinfo/**
        - composer.*
        - lib/**
        - templates/**
        - tests/**
    push:
      branches:
        - main
        - stable*
        - test
      paths:
        - .github/workflows/phpunit.yml
        - appinfo/**
        - composer.*
        - lib/**
        - templates/**
        - tests/**
  
  env:
    APP_NAME: notebook
  
  jobs:
    php:
      runs-on: ubuntu-latest
  
      strategy:
        # do not stop on another job's failure
        fail-fast: false
        matrix:
          php-versions: ['8.1', '8.2', '8.3']
          databases: ['sqlite', 'mysql', 'pgsql']
          server-versions: ['stable30', 'stable31', 'master']
  
      name: php${{ matrix.php-versions }}-${{ matrix.databases }}-${{ matrix.server-versions }}
  
      services:
        postgres:
          image: postgres:16
          ports:
            - 4445:5432/tcp
          env:
            POSTGRES_USER: root
            POSTGRES_PASSWORD: rootpassword
            POSTGRES_DB: nextcloud
          options: --health-cmd pg_isready --health-interval 5s --health-timeout 2s --health-retries 5
        mysql:
          image: mariadb:10.11
          ports:
            - 4444:3306/tcp
          env:
            MYSQL_ROOT_PASSWORD: rootpassword
          options: --health-cmd="mysqladmin ping" --health-interval 5s --health-timeout 2s --health-retries 5
  
      steps:
        - name: Checkout server
          uses: actions/checkout@v4
          with:
            repository: nextcloud/server
            ref: ${{ matrix.server-versions }}
            submodules: true
  
        - name: Checkout app
          uses: actions/checkout@v4
          with:
            path: apps/${{ env.APP_NAME }}
  
        - name: Set up php ${{ matrix.php-versions }}
          uses: shivammathur/setup-php@v2
          with:
            php-version: ${{ matrix.php-versions }}
            tools: phpunit
            extensions: mbstring, iconv, fileinfo, intl, sqlite, pdo_sqlite, gd, zip
  
        - name: Set up PHPUnit
          working-directory: apps/${{ env.APP_NAME }}
          run: composer i
  
        - name: Set up Nextcloud
          run: |
            if [ "${{ matrix.databases }}" = "mysql" ]; then
              export DB_PORT=4444
            elif [ "${{ matrix.databases }}" = "pgsql" ]; then
              export DB_PORT=4445
            fi
            mkdir data
            ./occ maintenance:install --verbose --database=${{ matrix.databases }} --database-name=nextcloud --database-host=127.0.0.1 --database-port=$DB_PORT --database-user=root --database-pass=rootpassword --admin-user admin --admin-pass admin
            ./occ app:enable --force ${{ env.APP_NAME }}
  
        - name: PHPUnit
          working-directory: apps/${{ env.APP_NAME }}
          run: ./vendor/phpunit/phpunit/phpunit -c tests/phpunit.xml
  ```

::: info
In this .yml file, we state that the job needs to run in a specific docker image, that it needs to run a specific script, and on which event the job will be triggered.

So, in this file we say that on pull requests, a job has to run in the "ubuntu-latest" docker image, it gets the app, it installs PHP with the right packages as well as PHPUnit which is the test framework, and sets up Nextcloud.

For the tests, we also have to manage the PHP dependencies. For this, we create the composer.json file which is the next step.

In the test matrix you want to add the recent supported PHP versions, some different databases, and the recent supported Nextcloud branches. At the time of writing this tutorial, both Nextcloud 30 and 31 support PHP versions 8.1, 8.2, and 8.3, but you want to adjust this to your current situation. For example:

*matrix:*

*php-versions: \['8.1', '8.2', '8.3'\]*

*databases: \['sqlite', 'mysql', 'pgsql'\]*

*server-versions: \['stable30', 'stable31', 'master'\]*

For reference, the supported PHP versions for each Nextcloud version are listed here:  
<https://github.com/nextcloud/server/wiki/Releases-and-PHP-versions>

:::

- In the `notebook` directory, find the `composer.json` file. Set its content to the following and fill in your `name`.

```json
{
    "name": "nextcloud/notebook",
    "authors": [
        {
            "name": "YOUR NAME HERE"
        }
    ],
    "require": {
        "php": "^8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    }
}
```

::: info
Next, we have to define the PHPUnit configuration. The GitHub action will run the tests that are configured in the phpunit.xml file. Creating this file is the next step.

:::

- Create the file `tests/phpunit.xml` and set its content to:

```xml
<?xml version="1.0" encoding="utf-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" bootstrap="bootstrap.php" timeoutForSmallTests="900" timeoutForMediumTests="900" timeoutForLargeTests="900" xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd">
  <coverage>
    <report>
      <clover outputFile="./clover.xml"/>
    </report>
  </coverage>
  <testsuite name="NoteBook App Tests">
    <directory suffix="Test.php">.</directory>
  </testsuite>
  <!-- filters for code coverage -->
  <logging>
    <!-- and this is where your report will be written -->
  </logging>
  <source>
    <include>
      <directory suffix=".php">../appinfo</directory>
      <directory suffix=".php">../lib</directory>
    </include>
  </source>
</phpunit>
```

::: info
This file defines that it will run all tests that are in the "test" directory of your app where the file name ends with `Test.php` .

When you run the tests, in the `Logging` section, the results of the test will appear. You will see this later when we try to run the tests.

:::

- Also create the file `tests/bootstrap.php` and set its content to:

  ```php
  <?php
  
  require_once __DIR__ . '/../../../tests/bootstrap.php';
  
  \OC_App::loadApp(OCA\NoteBook\AppInfo\Application::APP_ID);
  OC_Hook::clear();
  ```

::: info
This bootstrap file will use the bootstrap from the Nextcloud server. This is needed to run the tests in a proper environment.

:::

## Step 3: Implement the tests

- Create the directories `tests/unit` and `tests/unit/Service`.

::: info
Both directories can be created in a single command: `mkdir -p tests/unit/Service`.

:::

- Create the file `tests/unit/Service/NoteServiceTest.php` and set its content to:

  ```php
  <?php
  
  namespace OCA\NoteBook\Tests;
  
  use OCA\NoteBook\AppInfo\Application;
  
  class NoteServiceTest extends \Test\TestCase {
  
  	public function testDummy() {
  		$app = new Application();
  		$this->assertEquals('notebook', $app::APP_ID);
  	}
  }
  ```

  ::: info
  This file is a dummy test to show you the structure of a test file. This test checks if the app ID is correct.

  As you can see, a test file is a class that extends `\Test\TestCase` and that contains test methods.

  PHPUnit will only run the methods which names start with `test`  (for example: `testDummy`).

  Note that we put this test file `NoteServiceTest.php` in the `Service` directory to make it easy to find back, but it does not matter in which directory you place the test file, as long as the file name ends with `Test.php`.

  Now we understand the basic structure of a test file, the next step is to create a more useful test.

  :::
- Create the directory `tests/unit/Mapper`. Create the file `tests/unit/Mapper/NoteMapperTest.php` and set its content to:

  ```php
  <?php
  
  declare(strict_types=1);
  
  namespace OCA\NoteBook\Tests;
  
  use OCA\NoteBook\Db\NoteMapper;
  use OCP\AppFramework\Db\DoesNotExistException;
  use OCP\IUserManager;
  
  /**
   * @group DB
   */
  class NoteMapperTest extends \Test\TestCase {
  
  	private NoteMapper $noteMapper;
  	private array $testNoteValues = [
  		['user_id' => 'user1', 'name' => 'supername', 'content' => 'supercontent'],
  		['user_id' => 'user1', 'name' => '', 'content' => 'supercontent'],
  		['user_id' => 'user1', 'name' => 'supername', 'content' => ''],
  		['user_id' => 'user1', 'name' => '', 'content' => ''],
  	];
  
  	public function setUp(): void {
  		parent::setUp();
  
  		\OC::$server->getAppManager()->enableApp('notebook');
  
  		$this->noteMapper = \OC::$server->get(NoteMapper::class);
  	}
  
  	public function tearDown(): void {
  		$this->cleanupUser('user1');
  	}
  
  	private function cleanupUser(string $userId): void {
  		/** @var IUserManager $userManager */
  		$userManager = \OC::$server->get(IUserManager::class);
  		if ($userManager->userExists($userId)) {
  			$this->noteMapper->deleteNotesOfUser($userId);
  			$user = $userManager->get($userId);
  			$user->delete();
  		}
  	}
  
  	public function testAddNote() {
  		foreach ($this->testNoteValues as $note) {
  			$addedNote = $this->noteMapper->createNote('user1', $note['name'], $note['content']);
  			self::assertEquals($note['name'], $addedNote->getName());
  			self::assertEquals($note['content'], $addedNote->getContent());
  			self::assertEquals($note['user_id'], $addedNote->getUserId());
  		}
  	}
  
  	public function testDeleteNote() {
  		foreach ($this->testNoteValues as $note) {
  			$addedNote = $this->noteMapper->createNote($note['user_id'], $note['name'], $note['content']);
  			$addedNoteId = $addedNote->getId();
  			$dbNote = $this->noteMapper->getNoteOfUser($addedNoteId, $note['user_id']);
  			$deletedNote = $this->noteMapper->deleteNote($addedNoteId, $note['user_id']);
  			$this->assertNotNull($deletedNote, 'error deleting note');
  			$exceptionThrowed = false;
  			try {
  				$dbNote = $this->noteMapper->getNoteOfUser($addedNoteId, $note['user_id']);
  			} catch (DoesNotExistException $e) {
  				$exceptionThrowed = true;
  			}
  			$this->assertTrue($exceptionThrowed, 'deleted note still exists');
  		}
  	}
  
  	public function testEditNote() {
  		foreach ($this->testNoteValues as $note) {
  			$addedNote = $this->noteMapper->createNote($note['user_id'], $note['name'], $note['content']);
  			$addedNoteId = $addedNote->getId();
  
  			$editedNote = $this->noteMapper->updateNote($addedNoteId, $note['user_id'], $note['name'] . 'AAA', $note['content'] . 'BBB');
  			$this->assertNotNull($editedNote, 'error deleting note');
  			self::assertEquals($note['name'] . 'AAA', $editedNote->getName());
  			self::assertEquals($note['content'] . 'BBB', $editedNote->getContent());
  
  			$dbNote = $this->noteMapper->getNoteOfUser($addedNoteId, $note['user_id']);
  			self::assertEquals($note['name'] . 'AAA', $dbNote->getName());
  			self::assertEquals($note['content'] . 'BBB', $dbNote->getContent());
  		}
  	}
  }
  ```

  ::: info
  This file contains all the test cases: the creation, deletion and editing of a note in the database.

  As you can see, a test file is a class that, just like the previous dummy test, extends `\Test\TestCase` and that contains test methods. The names of the methods all start with `test` so that PHPUnit will run them.

  Then, we define a list of test values which we want to use. This is done in `$testNoteValues`. This array contains a list of values we want to use when testing note creation. We create a note with each user_id, name, and content. It's just dummy data, you can change the values. What you can see is that we test empty values: we define test data with an empty name, with an empty content, etc., to cover multiple cases and make sure the app works in those cases.

  The `setup` and `tearDown` methods are called respectively at the beginning and the end of the test case:

  `setup` gets a NoteMapper instance`tearDown` deletes potential leftovers that were created during the tests

  In this test we use "user1" as a user ID but the user does not have to really exist in Nextcloud. It's just a dummy value. As the mapper does not check if the user exists, we can use whatever value we want as user ID when creating a note.

  `cleanupUser`: This makes sure the tests leave no trace after being run. In case a test fails, the database might still contain data that could not be deleted. This test method is here just to show you best practice; in reality this method is useless because the docker container's purpose is just to run tests and then the docker will be deleted. But, it is considered good practice to clean things up after tests. This way, the tests can be run locally by the developer and we know it will not pollute the development environment's database.

  `testAddNote`: For each test dataset, we add a note and check that the added note (the returned entity) is well formed.

  `testDeleteNote`: For each test dataset, we add a note, then delete it and check that it has really been deleted.

  `testEditNote`: For each test dataset, we add a note, edit it, get the note again and check that the edit worked.

  :::

## Step 4: Publish your app on GitHub to trigger the tests

- Publish your app to GitHub and then create a small pull request to trigger the tests.  
  (If you are new to GitHub and don't know how to do this, scroll to 'Appendix: Publish your app to GitHub' below.)
- In GitHub, you can access the test results in the `Actions` tab by clicking on any "workflow run" (see first screenshot below).  
  You can then see if all the "jobs" ran successfully and you can find the details of the test logged to the  `phpunit.xml` file (see second and third screenshot below).

![Screenshot 2025-06-24 at 14-06-20 Workflow runs · edward-ly_notebook.png](.attachments.7663389/Screenshot%202025-06-24%20at%2014-06-20%20Workflow%20runs%20%C2%B7%20edward-ly_notebook.png)

![Screenshot 2025-06-24 at 14-06-52 Initial commit · edward-ly_notebook@cbff9c6 (2).png](.attachments.7663389/Screenshot%202025-06-24%20at%2014-06-52%20Initial%20commit%20%C2%B7%20edward-ly_notebook%40cbff9c6%20%282%29.png)

![Screenshot 2025-06-24 at 14-10-50 Initial commit · edward-ly_notebook@cbff9c6.png](.attachments.7663389/Screenshot%202025-06-24%20at%2014-10-50%20Initial%20commit%20%C2%B7%20edward-ly_notebook%40cbff9c6.png)

::: info
Good to know: if you want, it is possible to create a GitHub action to automate publishing of your app to the App Store. App developers can write an action on their own that is triggered when pushing on a specific branch. This action would:

\- Build the app (install dependencies, compile scripts, build an archive with only the necessary files)

\- Create a GitHub release and add the archive as asset

\- Use the App Store API to publish a new release of the app

<https://nextcloudappstore.readthedocs.io/en/latest/api/restapi.html#api-create-release>

You can implement the automated app publishing in any way you wish. For an example, you can take a look at the workflow template provided by Nextcloud: <https://github.com/nextcloud/.github/blob/master/workflow-templates/appstore-build-publish.yml>

Or the release workflow of the Cospend app: <https://github.com/julien-nc/cospend-nc/blob/main/.github/workflows/release.yml>

[See the App Store documentation here](https://nextcloudappstore.readthedocs.io/en/latest/developer.html) to learn how to publish your app to the App Store in general.

:::

## Questions?

If something is wrong, check the Nextcloud server logs or [ask for help in the Nextcloud forum](https://help.nextcloud.com/t/new-tutorial-available-adding-automated-tests-to-your-app/166020).

## Appendix: Publish your app to GitHub

::: info
While these tutorials are not meant to cover an 'introduction to GitHub', for that we recommend [other great resources](https://skills.github.com), this section outlines the basic steps to upload your app to GitHub if you are very new to development in general.

If you have questions or find any issues, please open a topic in the [developer forum](https://help.nextcloud.com/c/dev/11) and ping @edward or @Daphne.

:::

- Make your app directory a Git repository. In a terminal inside your `notebook` directory, run:

  ```
  git init
  ```
- Then tell git you want to follow changes on all the files:

  ```
  git add .
  ```

  ::: info
  if you run `git status` you will see information about on which branch you are on, the changes since the last commit, and some information about the commit history.

  We assume you are using a recent version of git without any custom configurations, in which case the name of the default branch should be `main`. If that is not the case, you can change this by running `git branch -M main`.

  :::
- Create a commit. Add a message about what your commit includes:

  ```
  git commit -m "some message here"
  ```

  ::: info
  if you run `git log` you get the history of the commits. In case it paginates, you can quit by pressing 'q'.

  :::
- We now want to push to a GitHub project.
  - Create a GitHub account if you haven't already.
  - Create and configure an SSH key for your machine and GitHub account, see a good tutorial on that [here](https://www.freecodecamp.org/news/git-ssh-how-to/).
  - Create a repository named `notebook` (as a convention, we name repositories with the Nextcloud app ID).
- In the `notebook` directory, run this command (replace YOURUSERNAMEHERE with your GitHub username):

  ```
  git remote add origin git@github.com:YOURUSERNAMEHERE/notebook.git
  ```
- Then, push your local commits to the newly created GitHub repository:

  ```
  git push -u origin main
  ```
- If you make changes to your app (e.g. add new files or change existing files) you can add them to git, commit the changes, and push them to GitHub:

  ```
  git add ./path/to/file
  git commit -m "some message here"
  git push
  ```
- You can then find your test results in the `Actions` tab of the `notebook` repository on GitHub.