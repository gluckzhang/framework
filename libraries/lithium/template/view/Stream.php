<?php
/**
 * Lithium: the most rad php framework
 * Copyright 2009, Union of Rad, Inc. (http://union-of-rad.org)
 *
 * Licensed under The BSD License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2009, Union of Rad, Inc. (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\template\view;

/**
 * Stream wrapper implementation based on the example provided at 
 * http://us3.php.net/manual/en/stream.streamwrapper.example-1.php, and inspired by the work of
 * Paul M. Jones (http://paul-m-jones.com/) and Mike Naberezny (http://mikenaberezny.com/).
 * 
 * Enables pure PHP template files to auto-escape output and implement custom content filtering.
 */
class Stream {

	protected $_position = 0;

	protected $_stats = array();

	protected $_data = null;

	protected $_path = null;

	function stream_open($path, $mode, $options, &$opened_path) {
		$path = str_replace('lithium.template://', '', $path);

		if (empty($path)) {
			return false;
		}

		$success = ($this->_data = file_get_contents($path));
		$this->_stats = stat($path);

		if ($success === false) {
			return false;
		}

		$echo = '/\<\?=\s*([^(?:;\s*\?>)(?:\s*\?>)]+)\s*;?\s*\?>/';
		$this->_data = preg_replace('/\<\?=@/', '<?php echo ', $this->_data);
		$this->_data = preg_replace($echo, '<?php echo $h($1); ?>', $this->_data);
		return true;
	}

	function stream_read($count) {
		$result = substr($this->_data, $this->_position, $count);
		$this->_position += strlen($result);
		return $result;
	}

	function stream_tell() {
		return $this->_position;
	}

	function stream_eof() {
		return ($this->_position >= strlen($this->_data));
	}

	function stream_seek($offset, $whence) {
		switch ($whence) {
			case SEEK_SET:
				if ($offset < strlen($this->_data) && $offset >= 0) {
					$this->_position = $offset;
					return true;
				}
				return false;
			case SEEK_CUR:
				if ($offset >= 0) {
					$this->_position += $offset;
					return true;
				}
				return false;
			case SEEK_END:
				if (strlen($this->_data) + $offset >= 0) {
					$this->_position = strlen($this->_data) + $offset;
					return true;
				}
				return false;
			default:
		}
		return false;
	}

	public function stream_stat() {
		return $this->_stats;
	}

	public function url_stat() {
		return $this->_stats;
	}
}

?>