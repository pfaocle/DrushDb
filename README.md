
# DrushDb

DrushDb is a [Codeception][1] extension to populate and cleanup test Drupal sites using Drush aliases.

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
                verbose: false

Configured entries for `source` and `destination` are required if this extension is enabled. These should be working [Drush aliases][2], _without_ a leading @ character, pointing to two Drupal sites:

* `source`: the Drupal site from which to grab the database
* `destination`: the Drupal site to which the database will be copied. This is usually the site being tested. **Warning:** currently the destination database is not backed up, simply overwritten.

Other configuration is optional: if any of `cleanup`, `populate` or `verbose` are omitted they are assumed to be `false`.

[1]: http://codeception.com/
[2]: http://drush.ws/examples/example.aliases.drushrc.php
