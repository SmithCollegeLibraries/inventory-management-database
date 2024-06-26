SIS Inventory Management
============================

This project is built with Yii 2 [Yii 2](http://www.yiiframework.com/) an application best for
rapidly creating small projects.


REQUIREMENTS
------------

The minimum requirement by this project template that your Web server supports PHP 5.6.0.


INSTALLATION
------------

### Download
Download the project zip from git hub and extract to folder named sis.

### Install via Composer

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions
at [getcomposer.org](http://getcomposer.org/doc/00-intro.md#installation-nix).  Download composer directly to the sis folder.

cd to the sis director and install using:

~~~
php composer.phar install
~~~

Now you should be able to access the application through the following URL, assuming `sis` is the directory
directly under the Web root.

~~~
http://localhost/sis/web/
~~~

CONFIGURATION
-------------

### Database

Use the sis4.sql file found in the package to create your database structure.

Edit the file `config/db.php` with real data, for example:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=sis4',
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
vendor/bin/codecept run --coverage --coverage-html --coverage-xml

#collect coverage only for unit tests
vendor/bin/codecept run unit --coverage --coverage-html --coverage-xml

#collect coverage for unit and functional tests
vendor/bin/codecept run functional,unit --coverage --coverage-html --coverage-xml
```

You can see code coverage output under the `tests/_output` directory.
