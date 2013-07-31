<?php
namespace Codeception\Extension;

use Codeception\Exception\Configuration as ConfigurationException;
use Codeception\Exception\Extension as ExtensionException;

/**
 * @file
 * Drush alias database population and cleaning.
 *
 * Note: this class requires the use of exec() - PHP safe mode should be off
 * or preferably,the drush script placed in a directory in PHP's safe_mode_exec_dir
 *
 * @see http://php.net/manual/en/function.exec.php
 *
 */

/**
 * The command to run drush status.
 */
define('DRUSH_DB_CMD_STATUS', 'drush @%alias st');

/**
 * The command to run drush sql-sync.
 */
define('DRUSH_DB_CMD_SQLSYNC', 'drush -y %config sql-sync @%source @%destination');


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
   * Whether to output more, or more verbose, messages.
   *
   * @var bool
   */
  private $verbose = FALSE;

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
   */
  public function __construct($config, $options) {
    parent::__construct($config, $options);

    if (isset($this->config['source']) && isset($this->config['destination'])) {
      // Test aliases with drush st.
      $this->drushStatus($this->config['source']);
      $this->drushStatus($this->config['destination']);

      // If no exception is thrown, we can set the member variables.
      $this->sourceDbAlias = $this->config['source'];
      $this->destinationDbAlias = $this->config['destination'];
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
          $this->doSync();
        }
        break;
      default:
        throw new ExtensionException(__CLASS__, 'Invalid mode.');
    }
  }

  /**
   * Actually perform the sql-sync.
   */
  private function doSync() {
    $this->writeln('Executing: ' . $this->drushCommand());

    $output = array();
    exec($this->drushCommand(), $output);

    // If in verbose mode, clean the Drush output and re-send.
    if ($this->config['verbose']) {
      foreach (array_filter($output, 'strlen') as $msg) {
        $this->writeln('Drush: ' . $msg);
      }
    }
  }

  /**
   * Construct the Drush command(s) based on the string:
   *
   *  - drush -y sql-sync @%source @%destination
   *
   * @return string
   */
  private function drushCommand() {
    $replacements = array(
      '%source' => $this->sourceDbAlias,
      '%destination' => $this->destinationDbAlias,
    );

    // Optional configuration.
    $replacements['%config'] = '';
    if (isset($this->config['structure-tables-key'])) {
      $path = __DIR__ . '/../../../drushdb.drushrc.php';
      $replacements['%config'] = "-c $path";
    }

    return strtr(DRUSH_DB_CMD_SQLSYNC, $replacements);
  }

  /**
   * Test a given Drush alias with drush status.
   *
   * @param $alias
   * @throws \Codeception\Exception\Configuration
   */
  private function drushStatus($alias) {
    $output = array();

    $cmd = str_replace('%alias', $alias, DRUSH_DB_CMD_STATUS);
    exec($cmd, $output);
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
}
