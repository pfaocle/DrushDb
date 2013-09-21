# DrushDb

DrushDb is a [Codeception][1] extension to populate and cleanup test Drupal sites during test runs using Drush aliases and the sql-sync command.

[![Build Status](https://secure.travis-ci.org/pfaocle/DrushDb.png?branch=master)](http://travis-ci.org/pfaocle/DrushDb)

**Note this is very much a work-in-progress proof-of-concept and other disclaimery type things.**


## Minimum requirements

* Codeception 1.6.4
* PHP 5.3


## Installation

### Manual

1. `cd /path/to/test/suite`
2. `mkdir extensions`
3. `git clone https://github.com/pfaocle/DrushDb.git extensions/DrushDb`
4. Edit your **tests/_bootstrap.php** file and add the line:

`include __DIR__ . '/../extensions/DrushDb/src/Codeception/Extension/DrushDb.php';`

### Installation with Codeception source and Composer

Coming soon...?


## Configuration

In your **codeception.yml** file:

    extensions:
        enabled: [Codeception\Extension\DrushDb]
        config:
            Codeception\Extension\DrushDb:
                source: mysite.uat
                destination: mysite.local
                cleanup: true
                populate: false
                clear_cache: all
                drushrc: true
                verbose: false

Configured entries for `source` and `destination` are required if this extension is enabled. These should be working [Drush aliases][2], _without_ a leading @ character, pointing to two Drupal sites:

* `source`: the Drupal site from which to grab the database
* `destination`: the Drupal site to which the database will be copied. This is usually the site being tested. **Warning:** currently the destination database is not backed up, simply overwritten.

Some sites will require a cache clear after the database has been overwritten. The `clear_cache` option should be set to either `none` (for no cache clears at all) or a valid Drupal cache name as returned by `drush cc`, eg `all`, `menu` or `block`.  The common settings for this are `none` or `all`.

Other configuration is optional: if any of `cleanup`, `populate`, `drushrc` or `verbose` are omitted they are assumed to be `false`.

* `cleanup` - Re-populate the destination database at the end of each test.
* `populate` - Populate the destination database when the suite run starts.
* `drushrc` - Use the included **drushdb.drushrc.php** file. Note that this is merged with the configuration from any other **drushrc.php** file active on the system. [Read more about drushrc.php][3] files and their order of precedence.
* `verbose` - Be verbose if true.

[1]: http://codeception.com/
[2]: http://drush.ws/examples/example.aliases.drushrc.php
[3]: http://drush.ws/examples/example.drushrc.php
