<?php

/**
	
 Simple Log Parser


Log file should have records in the following format:


[2018-04-11   03:13:57]  OK

[2018-04-11   03:14:04]  OK

[2018-04-11 03:14:04] NOK

[2018-04-11 03:14:09] OK

[2018-04-11 03:14:15] NOK

[2018-04-11 03:15:10] NOK

[2018-04-11    03:15:15] NOK

[2018-04-11 03:16:15] NOK

*/

class LogParser {

    var $begin_time = null;

    var $end_time = null;

	var $verbose = false;

	var $log_filename = null;

	var $logfile_content = null;

	var $nok_rows_per_minute = 0;

	var $total_nok_rows = 0;

	function __construct() {
	}

	static function main() {
		$parser = new LogParser();
		$parser->parse_command_line();

		$parser->start();
        try {
            $parser->run();
		} catch(Exception $e) {
			$parser->log(LOG_CRIT, get_class($e).": ".$e->getMessage());
			return;
		}
		finally {
            $parser->finish();
        }
	}

	static function _level_for_priority($priority) {
		if($priority == LOG_DEBUG)
			$level = "DEBUG";
		elseif($priority == LOG_INFO)
			$level = "INFO";
		elseif($priority == LOG_NOTICE)
			$level = "NOTICE";
		elseif($priority == LOG_WARNING)
			$level = "WARNING";
		elseif($priority == LOG_ERR)
			$level = "ERR";
		elseif($priority == LOG_CRIT)
			$level = "CRIT";
		elseif($priority == LOG_ALERT)
			$level = "ALERT";
		elseif($priority == LOG_EMERG)
			$level = "EMERG";
		else
			$level = $priority;
		return $level;
	}


	function usage($extra_options = "", $extra_options_verbose = "") {
		global $argv;

		print "Usage: ".$argv[0]." [-h] [-v] ".$extra_options."\n";
		print "  -h               Help message (this)\n";
		print "  -v               Verbose error logging (to stderr)\n";
		print "  -f <filename>    events log file\n";
		print $extra_options_verbose;
	}

	function parse_command_line($extra_options = "") {
		$opts = getopt("hvf:".$extra_options);
		if(isset($opts['h']) || !isset($opts['f']) || empty($opts['f'])) {
            $this->usage($extra_options);
            exit();
        }

        if(isset($opts['v']))
            $this->set_verbose();

		if(isset($opts['f']))
            $this->set_log_filename($opts['f']);

		return $opts;
	}

	function set_verbose($setting = true) {
        $this->verbose = $setting;
	}

	function set_log_filename($filename) {
		$this->log_filename = $filename;
        $this->log(LOG_NOTICE, "File {$filename} being processed to calculate number of NOK records per minute");
	}

	function start() {
		$this->begin_time = microtime(true);
	}

	function run() {

		$this->load_log_file();

		$this->log(LOG_NOTICE, "processing {$this->log_filename} to calculate number of NOK records per minute");

		preg_match_all('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}):\d{2}.*?NOK$/im', $this->logfile_content, $nok_rows);

		$this->total_nok_rows = count($nok_rows[0]);

		$this->nok_rows_per_minute = array_count_values(array_map(fn($row): string => preg_replace('/\s+/', ' ', $row), $nok_rows[1]));

		$this->report();
	}

	function load_log_file() {
		if (is_null($this->log_filename)
			|| empty($this->log_filename)) {
				throw new Exception("no filename specified");
		}

		if (!is_file($this->log_filename)) {
			throw new Exception("File {$this->log_filename} not found!");
		}

		// considering the logfile is pretty small, we load it to memory completely  
		$this->logfile_content = file_get_contents($this->log_filename);

		$this->log(LOG_NOTICE, "{$this->log_filename} has been loaded");
	}

	function finish() {
		$this->end_time = microtime(true);

		$this->log(LOG_NOTICE, "Script executed for " . ($this->end_time - $this->begin_time) . " seconds");
	}

	function report() {

		print "\nNOK rows per minute:\n";
		foreach ($this->nok_rows_per_minute as $timestamp => $nok_count) {
			print "{$timestamp} ${nok_count}\n";
		}
		
		print "------------------\n";
		print "Total NOK rows in file: {$this->total_nok_rows}\n\n";
	}

	function log($priority, $message) {
		$level = self::_level_for_priority($priority);

        if ($this->verbose && $priority <= LOG_INFO || $priority <= LOG_CRIT)
            fwrite(STDERR, ($this->verbose ? date("Y-m-d H:i:s") . " ({$level}) : " : "") . "{$message}\n");
	}
}


if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
		LogParser::main();
}
