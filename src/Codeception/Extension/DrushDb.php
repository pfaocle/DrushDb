<?php
namespace Codeception\Extension;

use Codeception\Exception\Configuration as ConfigurationException;
use Codeception\Exception\Extension as ExtensionException;

require_once 'DrushCommand.php';

/**
 * @file
 * Drush alias database population and cleaning.
 */

/**
 * Base definition of command to run drush.
 */
define('DRUSH_DB_CMD', 'drush %config');

/**
 * The command to run drush status.
 */
define('DRUSH_DB_CMD_STATUS', '@%alias st');

/**
 * The command to run drush sql-sync.
 */
define('DRUSH_DB_CMD_SQLSYNC', '-y sql-sync @%source @%destination');

/**
 * The command to run drush cc,
 */
define('DRUSH_DB_CMD_CC', '@%alias cc');


/**
 * Class DrushDb
 *
 * @package Codeception\Extension
 */
class DrushDb extends \Codeception\Platform\Extension {

  /**
   * Stores the source Drush alias, without a leading @,
   *
   * @var
   */
  protected $sourceDbAlias;

  /**
   * Stores the destination Drush alias, without a leading @,
   *
   * @var
   */
  protected $destinationDbAlias;

  /**
   * Whether to use the included drushdb.drushrc.php file or not.
   *
   * @var bool
   */
  private $useDrushRC = FALSE;

  /**
   * Internal Codeception events to listen to.
   *
   * @var array
   */
  static $events = array(
    'suite.before'  => 'populate',
    'test.end'      => 'cleanup',
  );

  /**
   * Check aliases in config are set and valid, set member variables if so.
   *
   * @param $config
   * @param $options
   * @throws \Codeception\Exception\Configuration
   */
  public function __construct($config, $options) {
    parent::__construct($config, $options);

    if (isset($this->config['source']) && isset($this->config['destination'])) {
      // Store the option to use accompanying drushdb.drushrc.php file or not.
      if (isset($this->config['drushrc']) && $this->config['drushrc']) {
        $this->useDrushRC = TRUE;
      }

      // Test aliases with drush st.
      $this->drushStatus($this->config['source']);
      $this->drushStatus($this->config['destination']);

      // If no exception is thrown, we can set the member variables.
      $this->sourceDbAlias = $this->config['source'];
      $this->destinationDbAlias = $this->config['destination'];

      // Check the named cache configured to clear is valid and set member variable.
      // @todo Ensure -s works as intended.
      if (isset($this->config['clear_cache']) && 'none' != $this->config['clear_cache']) {
        $output = array();
        $output_errors = array();

        // Run a simulated cache clear on the configured cache to validate cache name.
        $cmd = new \DrushCommand($this->useDrushRC, $this->config['verbose']);
        $cmd->addCommand('-s ' . DRUSH_DB_CMD_CC . ' ' . $this->config['clear_cache'], array('%alias' => $this->destinationDbAlias))
            ->execute($this, $output, $output_errors);

        // Parse the STDERR output a little and throw an Exception if the cache type is invalid.
        foreach (array_filter($output_errors, function($haystack) { return strpos($haystack, '[error]'); }) as $error) {
          // Strip out Drush coloured status.
          list($error, ) = explode("\033", $error);
          throw new ConfigurationException(trim($error));
        }
      }
    }
    else {
      throw new ConfigurationException('Drush aliases for source and destination are not configured.');
    }
  }

  /**
   * Listener method for suite.before event.
   *
   * @param $event
   *   A Codeception\Event\Suite object.
   */
  function populate($event) { $this->act(__FUNCTION__); }

  /**
   * Listener method for test.end event.
   *
   * @todo Act on PASS/FAIL here - what to do in each situation?
   *
   * @param $event
   *   A Codeception\Event\Test object.
   */
  function cleanup($event) { $this->act(__FUNCTION__); }

  /**
   * Somewhat abstracted listener method.
   *
   * @param $mode
   *   Can be 'cleanup' or 'populate'
   * @throws \Codeception\Exception\Extension
   */
  protected function act($mode) {
    // Get the internal Codeception event fired, for messaging.
    if ($event_name = array_search($mode, static::$events)) {
      $event_name = ucfirst(str_replace('.', ' ', $event_name));
    }

    switch ($mode) {
      case 'populate':
      case 'cleanup':
        if (isset($this->config[$mode]) && $this->config[$mode]) {
          $this->createMessage(
            "%event: will %mode target database (@%destination) with data from source (@%source)",
            array(
              '%event' => $event_name,
              '%mode' => $mode,
              '%destination' => $this->destinationDbAlias,
              '%source' => $this->sourceDbAlias,
            )
          );
          $this->drushSqlSync();
        }
        break;
      default:
        throw new ExtensionException(__CLASS__, 'Invalid mode.');
    }
  }

  /**
   * Construct the Drush sql-sync command and then actually perform the sync.
   */
  private function drushSqlSync() {
    $cmd = new \DrushCommand($this->useDrushRC, $this->config['verbose']);
    $cmd->addCommand(DRUSH_DB_CMD_SQLSYNC, array(
            '%source' => $this->sourceDbAlias,
            '%destination' => $this->destinationDbAlias))
        ->execute($this);

    // Clear destination cache, if configured.
    if ('none' != $this->config['clear_cache']) {
      $cmd->addCommand(DRUSH_DB_CMD_CC . ' ' . $this->config['clear_cache'], array('%alias' => $this->destinationDbAlias))
          ->execute($this);
    }
  }

  /**
   * Test a given Drush alias with drush status.
   *
   * @param $alias
   * @throws \Codeception\Exception\Configuration
   */
  private function drushStatus($alias) {
    $output = array();

    $cmd = new \DrushCommand($this->useDrushRC, $this->config['verbose']);
    $cmd->addCommand(DRUSH_DB_CMD_STATUS, array('%alias' => $alias))
        ->execute($this, $output);

    // Handle the case where $output is empty - there may be a problem with the alias.
    if (count($output) == 0) {
      throw new ConfigurationException("Drush error: a Drupal installation directory could not be found for @$alias");
    }
  }

  /**
   * Simple helper to create messages with variable replacements.
   *
   * @param $message
   * @param $replacements
   * @return mixed
   */
  private function createMessage($message, $replacements) {
    if (is_array($replacements)) {
      $message = strtr($message, $replacements);
    }
    $this->writeln($message);
  }

  // Allow writeln to be accessed as a public function.
  // @todo Check this.
  public function writeln($message) { parent::writeln($message); }
}
