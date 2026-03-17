<?php
/**
 * Simple file + stdout logger.
 */

class Logger
{
    private string $logFile;
    private string $level;
    private array  $levels = ['debug' => 0, 'info' => 1, 'error' => 2];

    public function __construct(string $logFile, string $level = 'info')
    {
        $this->logFile = $logFile;
        $this->level   = $level;

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function debug(string $msg): void { $this->write('DEBUG', $msg); }
    public function info(string $msg): void  { $this->write('INFO',  $msg); }
    public function error(string $msg): void { $this->write('ERROR', $msg); }

    private function write(string $severity, string $msg): void
    {
        if (($this->levels[strtolower($severity)] ?? 0) < ($this->levels[$this->level] ?? 1)) {
            return;
        }
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $severity, $msg);
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    }
}
