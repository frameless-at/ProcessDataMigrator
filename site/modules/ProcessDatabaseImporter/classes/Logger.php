<?php

namespace ProcessWire;

/**
 * Configurable Logger for Database Importer
 *
 * Provides different log levels to control verbosity:
 * - ERROR: Only critical errors
 * - WARNING: Errors and warnings
 * - INFO: Errors, warnings, and important info
 * - DEBUG: Everything including debug details
 */
class Logger extends WireData {

    const ERROR = 1;
    const WARNING = 2;
    const INFO = 3;
    const DEBUG = 4;

    protected $level = self::INFO; // Default: INFO level
    protected $logName = 'db-importer';

    /**
     * Set log level
     *
     * @param int $level One of Logger::ERROR, WARNING, INFO, DEBUG
     */
    public function setLevel($level) {
        $this->level = (int) $level;
    }

    /**
     * Get current log level
     *
     * @return int
     */
    public function getLevel() {
        return $this->level;
    }

    /**
     * Set log file name
     *
     * @param string $name Log file name (without .txt extension)
     */
    public function setLogName($name) {
        $this->logName = $name;
    }

    /**
     * Log error message (always logged)
     *
     * @param string $message
     */
    public function error($message) {
        if ($this->level >= self::ERROR) {
            $this->write('ERROR', $message);
        }
    }

    /**
     * Log warning message
     *
     * @param string $message
     */
    public function warning($message) {
        if ($this->level >= self::WARNING) {
            $this->write('WARNING', $message);
        }
    }

    /**
     * Log info message
     *
     * @param string $message
     */
    public function info($message) {
        if ($this->level >= self::INFO) {
            $this->write('INFO', $message);
        }
    }

    /**
     * Log debug message (only in DEBUG mode)
     *
     * @param string $message
     */
    public function debug($message) {
        if ($this->level >= self::DEBUG) {
            $this->write('DEBUG', $message);
        }
    }

    /**
     * Write message to log file
     *
     * @param string $level Level name (ERROR, WARNING, INFO, DEBUG)
     * @param string $message Message to log
     */
    protected function write($level, $message) {
        $this->wire()->log->save(
            $this->logName,
            sprintf('[%s] %s', $level, $message)
        );
    }

    /**
     * Get level name from constant
     *
     * @param int $level
     * @return string
     */
    public static function getLevelName($level) {
        switch ($level) {
            case self::ERROR: return 'ERROR';
            case self::WARNING: return 'WARNING';
            case self::INFO: return 'INFO';
            case self::DEBUG: return 'DEBUG';
            default: return 'UNKNOWN';
        }
    }

    /**
     * Parse level name to constant
     *
     * @param string $name
     * @return int
     */
    public static function parseLevelName($name) {
        $name = strtoupper(trim($name));
        switch ($name) {
            case 'ERROR': return self::ERROR;
            case 'WARNING': return self::WARNING;
            case 'INFO': return self::INFO;
            case 'DEBUG': return self::DEBUG;
            default: return self::INFO;
        }
    }
}
