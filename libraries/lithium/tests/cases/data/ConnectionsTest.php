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

namespace lithium\tests\cases\data;

use \lithium\data\Connections;

class ConnectionsTest extends \lithium\test\Unit {

	public $config = array(
		'adapter' => 'MySql',
		'host' => 'localhost',
		'login' => '--user--',
		'password' => '--pass--',
		'database' => 'db'
	);

	protected $_preserved = array();

	public function setUp() {
		if (empty($this->_preserved)) {
			foreach (Connections::get() as $conn) {
				$this->_preserved[$conn] = Connections::get($conn, array('config' => true));
			}
		}
		Connections::clear();
	}

	public function tearDown() {
		foreach ($this->_preserved as $name => $config) {
			Connections::add($name, $config['type'], $config);
		}
	}

	public function testConnectionCreate() {
		$result = Connections::add('conn-test', 'Database', $this->config);
		$expected = $this->config + array('type' => 'Database');
		$this->assertEqual($expected, $result);

		$this->expectException('/mysql_get_server_info/');
		$this->expectException('/mysql_select_db/');
		$this->expectException('/mysql_connect/');
		$result = Connections::get('conn-test');
		$this->assertTrue($result instanceof \lithium\data\source\database\adapter\MySql);

		$result = Connections::add('conn-test-2', $this->config);
		$this->assertEqual($expected, $result);

		$this->expectException('/mysql_get_server_info/');
		$this->expectException('/mysql_select_db/');
		$this->expectException('/mysql_connect/');
		$result = Connections::get('conn-test-2');
		$this->assertTrue($result instanceof \lithium\data\source\database\adapter\MySql);
	}

	public function testConnectionGetAndReset() {
		Connections::add('conn-test', $this->config);
		Connections::add('conn-test-2', $this->config);
		$this->assertEqual(array('conn-test', 'conn-test-2'), Connections::get());

		$expected = $this->config + array('type' => 'Database');
		$this->assertEqual($expected, Connections::get('conn-test', array('config' => true)));

		$this->assertNull(Connections::clear());
		$this->assertFalse(Connections::get());

		Connections::__init();
		$this->assertTrue(Connections::get());
	}

	public function testConnectionAutoInstantiation() {
		Connections::add('conn-test', $this->config);
		Connections::add('conn-test-2', $this->config);

		$this->expectException('/mysql_get_server_info/');
		$this->expectException('/mysql_select_db/');
		$this->expectException('/mysql_connect/');
		$result = Connections::get('conn-test');
		$this->assertTrue($result instanceof \lithium\data\source\database\adapter\MySql);

		$result = Connections::get('conn-test');
		$this->assertTrue($result instanceof \lithium\data\source\database\adapter\MySql);

		$this->assertNull(Connections::get('conn-test-2', array('autoBuild' => false)));
	}

	public function testInvalidConnection() {
		$this->assertNull(Connections::get('conn-invalid'));
	}

	public function testStreamConnection() {
		$config = array(
			'adapter' => 'Stream',
			'host' => 'localhost',
			'login' => 'root',
			'password' => '',
			'port' => '80'
		);

		Connections::add('stream-test', 'Http', $config);
		$result = Connections::get('stream-test');
		$this->assertTrue($result instanceof \lithium\data\source\http\adapter\Stream);
	}
}

?>