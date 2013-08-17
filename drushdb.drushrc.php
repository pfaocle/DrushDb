<?php

/**
 * @file
 * Additional Drush configuration used when running commands via DrushDb/Codeception.
 */

// Uncomment this for increased Drush verbosity.
//$options['verbose'] = 1;

$options['structure_tables'] = array(
  'common' => 'cache',
);
