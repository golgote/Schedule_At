<?php
/* vim: set ts=4 sw=4: */
// +----------------------------------------------------------------------+
// | PHP Version 5                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2012 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Colin Viebrock <colin@easydns.com>                           |
// +----------------------------------------------------------------------+
//
// Interface to the UNIX "at" program

/**
* Class to interface to the UNIX "at" program
*
* @author Colin Viebrock <colin@easydns.com>
* @author Bertrand Mansion <bmansion@mamasam.com>
*/

class Schedule_At
{
	/**
	* Path to at executable
	* @var string
	*/
	protected $exec = '/usr/bin/at';

	public $error      = false;
	public $runtime    = false;
	public $job        = false;

	public $lastcmd    = '';

	/**
	* Constructor: instantiates the class.
	* 
	* @param  string 	Path to at executable
	* @access public
	*/
	public function __construct($exec = null)
	{
		if (!empty($exec)) {
			$this->exec = $exec;
		}
		$this->reset();
	}


	/**
	* Adds an at command
	*
	* This makes an "at" job, where $cmd is the shell command to run
	* and $timespec describes when the function should run.  See the
	* at man page for a description of the spec.
	*
	* $queue is an optional 1 character string [a-zA-Z] that can define
	* which queue to put the job in.
	*
	* If $mail is set, then an email will be sent when the job runs,
	* even if the job doesn't output anything.  The mail gets sent to
	* the user running the script (probably the webserver, i.e.
	* nobody@localhost).
	*
	* The add() method returns false on error (in which case, check
	* $at->error for the message), or the job number on success.
	* On success, $at->runtime is also set with the timestamp corresponding
	* to when the job will run.
	*
	* @param string 	shell command to run
	* @param string 	timestamp when command should run
	* @param string 	at queue specifier
	* @param bool 		flag to specify whether to send email
	*
	* @return int|false  False on error or job number on success
	*/
	public function add($cmd, $timespec, $queue = 'a', $mail = false)
	{
		$this->reset();

		if (!strlen($cmd)) {
			throw new Schedule_AtException('Invalid command specification');
		}

		if ($queue && !preg_match('/^[a-zA-Z]$/', $queue)) {
			throw new Schedule_AtException('Invalid queue specification');
		}

		$time = strtotime($timespec);
		if ($time === false) {
			throw new Schedule_AtException('Invalid time specification');
		}

		$timespec = strftime('%y%m%d%H%M', $time); // Convert to Posix time format
		$cmd = escapeshellcmd($cmd);

		$exec = sprintf('echo "%s" | %s%s%s -t %s 2>&1',
			addslashes($cmd),
			$this->exec,
			($queue ? ' -q '.$queue : ''),
			($mail ? ' -m' : ''),
			$timespec
		);

		$result = $this->execute($exec);

		if (preg_match('/garbled time/i', $result) ) {
			throw new Schedule_AtException('Invalid time specification');
		}

		if (preg_match('/job (\d+) at (.*)/i', $result, $m) ) {
			$this->runtime = strtotime($m[2]);
			$this->job = (int)$m[1];
			return $this->job;
		} else {
			throw new Schedule_AtException('Exec error: '.$result);
		}

	}


	/**
	* Shows jobs in the at queue
	*
	* This returns an array listing all queued jobs.  The array's keys
	* are the job numbers, and each entry is itself an associative array
	* listing the runtime (timestamp) and queue (char).
	*
	* You can optionally provide a queue character to only list the jobs
	* in that queue.
	*
	* @param string        optional queue specifier
	*/
	public function queue($queue = null)
	{
		$this->reset();

		if ($queue && !preg_match('/^[a-zA-Z]$/', $queue) ) {
			throw new Schedule_AtException('Invalid queue specification');
		}

		$exec = sprintf("%s -l%s",
			$this->exec,
			($queue ? ' -q '.$queue : '')
		);

		$result = $this->execute($exec);
		$lines = explode("\n", $result);

		$jobs = array();
		foreach($lines as $line) {
			if (trim($line)) {
				list($job, $date, $time, $queue) = preg_split('/\s+/', trim($line));
				$jobs[$job] = array(
					'runtime' => strtotime($date.' '.$time),
					'queue'   => $queue
				);
			}
		}
		return $jobs;
	}


	/**
	* Remove job from the at queue
	*
	* This removes jobs from the queue.  Returns false if the job doesn't
	* exist or on failure, or true on success.
	*
	* @param int        job to remove
	*/
	public function remove($job)
	{
		$this->reset();

		$queue = $this->queue();

		if (!isset($queue[$job])) {
			return false;
		}

		$exec = sprintf("%s -d %d",
			$this->exec,
			$job
		);

		$this->execute($exec);

		/* this is required since the shell command doesn't return anything on success */

		$queue = $this->queue();
		return !isset($queue[$job]);
	}


	/**
	* Reset class
	*/
	protected function reset()
	{
		$this->error      = false;
		$this->runtime    = false;
		$this->job        = false;
		$this->lastexec   = '';
	}


	/**
	* Run a shell command
	*
	* @param string    command to run
	*/
	protected function execute($cmd)
	{
		$this->lastexec = $cmd;
		return shell_exec($cmd);
	}

}

class Schedule_AtException extends Exception
{

}