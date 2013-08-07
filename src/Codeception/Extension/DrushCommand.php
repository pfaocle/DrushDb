<?php

use Codeception\Exception\Extension as ExtensionException;

/**
 * @file
 * DrushDB - Drush commands wrapper.
 *
 * A wrapper class allowing Drush commands to be run via exec() from the DrushDb
 * Codeception extension.
 *
 */

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
      $path = __DIR__ . '/../../../drushdb.drushrc.php';
      $replacements['%config'] = "-c $path";
    }
    $this->command = strtr(DRUSH_DB_CMD, $replacements);

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
   * @param $output
   */
  public function execute(Codeception\Platform\Extension $extension, &$output = array()) {
    $command = $this->command . ' ' . $this->drushCommand;
    if ($this->validate()) {
      if ($this->verbose) {
        $extension->writeln('Executing: ' . $command);
      }
      exec($command, $output);
      if ($this->verbose) {
        foreach (array_filter($output, 'strlen') as $msg) {
          $extension->writeln('Drush: ' . $msg);
        }
      }
    }
    else {
      throw new ExtensionException(__CLASS__, 'Drush command is invalid: ' . $command);
    }
  }
}
