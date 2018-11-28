SIS Inventory Management
============================

This project is built with Yii 2 [Yii 2](http://www.yiiframework.com/) an application best for
rapidly creating small projects.


REQUIREMENTS
------------

The minimum requirement by this project template that your Web server supports PHP 5.4.0.


INSTALLATION
------------

### Download
Download the project zip from git hub and extract to folder named inventory-management.

### Install via Composer

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](http://getcomposer.org/doc/00-intro.md#installation-nix).  Download composer directly to the inventory-management folder.

cd to the inventory-management directory and install using:

~~~
php composer.phar install
~~~

Change the User-sample.php file found in models/User-sample.php to User.php and add an access token.  You can create new tokens in the following format:

        'TOKENNAME' => [
	        'id' => 'TOKENID',
	        'accessToken' => 'XXXXXXXXXXXX',
        ]

Now you should be able to access the application through the following URL, assuming `inventory-management` is the directory
directly under the Web root.

~~~
http://localhost/inventory-management/web/
~~~

CONFIGURATION
-------------

### Database

Use the sis.sql file found in the package to create your database structure.

Edit the file `config/db.php` with real data, for example:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=sis',
    'username' => 'xxxxxxx',
    'password' => 'xxxxxxx',
    'charset' => 'utf8',
];
```


### Code coverage support

By default, code coverage is disabled in `codeception.yml` configuration file, you should uncomment needed rows to be able
to collect code coverage. You can run your tests and collect coverage with the following command:

```
#collect coverage for all tests
vendor/bin/codecept run -- --coverage-html --coverage-xml

#collect coverage only for unit tests
vendor/bin/codecept run unit -- --coverage-html --coverage-xml

#collect coverage for unit and functional tests
vendor/bin/codecept run functional,unit -- --coverage-html --coverage-xml
```

You can see code coverage output under the `tests/_output` directory.
