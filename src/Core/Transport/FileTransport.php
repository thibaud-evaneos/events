<?php

namespace Aztech\Events\Core\Transport;

use Aztech\Events\Event;
use Aztech\Events\Transport;

class FileTransport implements Transport
{

    private $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function read()
    {
        while (! isset($data)) {
            if ($handle = fopen($this->file, "c+")) { // open the file in reading and editing mode
                if (flock($handle, LOCK_EX)) { // lock the file, so no one can read or edit this file
                    $lines = array();
                    while (($line = fgets($handle)) !== false) {
                        if (isset($data)) { // move the line to previous position, except the first line
                            $lines[] = trim($line);
                        }
                        if (! isset($data) && trim($line) != '') {
                            $data = trim($line); // First line with data is event we're fetching
                        }
                    }


                    fseek($handle, 0, SEEK_SET);
                    fwrite($handle, join(PHP_EOL, $lines));
                    fflush($handle); // write any pending change to file
                    ftruncate($handle, strlen(join(PHP_EOL, $lines))); // drop the repeated last line
                    flock($handle, LOCK_UN); // unlock the file
                }
                fclose($handle);
            }

            if (! isset($data)) {
                usleep(250000);
            }
        }

        return isset($data) ? $data : false;
    }

    public function write(Event $event, $serializedEvent)
    {
        if ($handle = fopen($this->file, "c+")) { // open the file in reading and editing mode
            if (flock($handle, LOCK_EX)) { // lock the file, so no one can read or edit this file
                while (($line = fgets($handle) !== false)) {
                    continue;
                }

                fwrite($handle, $serializedEvent . PHP_EOL);
                fflush($handle); // write any pending change to file
                flock($handle, LOCK_UN); // unlock the file
            }
            fclose($handle);
        }

        return null;
    }
}