<?php

use Codeception\Exception\Extension as ExtensionException;

/**
 * @file
 * DrushDB - Drush commands wrapper.
 *
 * A wrapper class around Drush commands allowing them to be run via proc_open()
 * from the DrushDb Codeception extension.
 *
 * Note: this class requires the use of proc_open().
 *
 * @see http://php.net/manual/en/function.proc-open.php
 *
 */

/**
 * The maximum length of a line returned in Drush output (stdout or stderr).
 */
define('DRUSHDB_DRUSH_OUTPUT_LINE_LENGTH_MAX', 4096);


/**
 * Class DrushCommand
 */
class DrushCommand {

  /**
   * Command to run Drush itself, with optional configuration.
   *
   * @var string
   */
  protected $command;

  /**
   * The Drush command to run.
   *
   * @var
   */
  protected $drushCommand;

  /**
   * Output more, or more verbose, messages.
   *
   * @var bool
   */
  protected $verbose;

  /**
   * Constructor - deal with using drushdb.drushrc.php and set verbose mode.
   *
   * @param bool $useDrushRC
   * @param bool $verbose
   */
  public function __construct($useDrushRC = FALSE, $verbose = FALSE) {
    $replacements = array('%config' => '');
    if ($useDrushRC) {
      $path = str_replace('src/Codeception/Extension', '', __DIR__) . 'drushdb.drushrc.php';
      $replacements['%config'] = "-c $path";
    }
    $this->command = trim(strtr(DRUSH_DB_CMD, $replacements));

    $this->verbose = $verbose;
  }

  /**
   * Return a string representation of the command.
   *
   * @return string
   */
  public function __tostring() {
    return $this->command;
  }

  /**
   * Add a Drush command such as status or sql-sync and deal with "parameters".
   *
   * @param $commandString
   * @param array $replacements
   */
  public function addCommand($commandString, $replacements = array()) {
    $this->drushCommand = strtr($commandString, $replacements);
    return $this;
  }

  /**
   * Ensures the complete command is valid.
   *
   * @return bool
   */
  protected function validate() {
    return isset($this->command) && isset($this->drushCommand);
  }

  /**
   * Execute the command.
   *
   * @param \Codeception\Platform\Extension $extension
   * @param array $output
   * @throws Codeception\Exception\Extension
   */
  public function execute(Codeception\Platform\Extension $extension, &$output = array()) {
    $command = $this->command . ' ' . $this->drushCommand;
    if ($this->validate()) {
      if ($this->verbose) {
        $extension->writeln('Executing: ' . $command);
      }

      // Descriptors and pipes.
      $descriptors = array(
        0 => array('pipe', 'r'),  // STDIN
        1 => array('pipe', 'w'),  // STDOUT
        2 => array('pipe', 'w'),  // STDERR
      );
      $pipes = array();

      // Execute the command using proc_open() and create the output array, as exec() would.
      $process = proc_open($command, $descriptors, $pipes, dirname(__FILE__));
      if (is_resource($process)) {
        // Drush outputs its verbose messages to STDERR - we simply re-output this
        // without checking $this->verbose, assuming that if $options['verbose'] is
        // set to 1 in drushrc.php, the end user wants this output.
        while (!feof($pipes[2])) {
          if ($stderr_line = stream_get_line($pipes[2], DRUSHDB_DRUSH_OUTPUT_LINE_LENGTH_MAX, "\n")) {
            $extension->writeln('Drush: ' . $stderr_line);
          }
        }

        // Store output on STDOUT to $output array and immediately output each line
        // if in verbose mode.
        while (!feof($pipes[1])) {
          if ($msg = stream_get_line($pipes[1], DRUSHDB_DRUSH_OUTPUT_LINE_LENGTH_MAX, "\n")) {
            if ($this->verbose) {
              $extension->writeln('Drush: ' . $msg);
            }
            $output[] = $msg;
          }
        }
        proc_close($process);
      }
      else {
        throw new ExtensionException(__CLASS__, "Could not open a process to run $command");
      }
    }
    else {
      throw new ExtensionException(__CLASS__, 'Drush command is invalid: ' . $command);
    }
  }
}
