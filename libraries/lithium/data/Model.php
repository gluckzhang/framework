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

namespace lithium\data;

use \lithium\util\Set;
use \lithium\util\Inflector;

/**
 * Model class
 *
 * @package default
 * @todo Methods: bind(), and 'bind' option for find() et al., create(), save(), delete(),
 * validate()
 */
class Model extends \lithium\core\StaticObject {

	public $hasOne = array();

	public $hasMany = array();

	public $belongsTo = array();

	protected static $_instances = array();

	protected $_instanceFilters = array();

	/**
	 * Class dependencies.
	 *
	 * @var array
	 */
	protected $_classes = array(
		'query' => '\lithium\data\model\Query',
		'record' => '\lithium\data\model\Record',
		'validator' => '\lithium\util\Validator',
		'recordSet' => '\lithium\data\model\RecordSet',
		'connections' => '\lithium\data\Connections'
	);

	protected $_relations = array();

	protected $_relationTypes = array(
		'belongsTo' => array('class', 'key', 'conditions', 'fields'),
		'hasOne' => array('class', 'key', 'conditions', 'fields', 'dependent'),
		'hasMany' => array(
			'class', 'key', 'conditions', 'fields', 'order', 'limit',
			'dependent', 'exclusive', 'finder', 'counter'
		)
	);

	protected $_meta = array(
		'key' => 'id',
		'name' => null,
		'class' => null,
		'title' => null,
		'source' => null,
		'prefix' => null,
		'connection' => 'default'
	);

	protected $_schema = array();

	/**
	 * Default query parameters.
	 *
	 * @var array
	 */
	protected $_query = array(
		'conditions' => null,
		'fields' => null,
		'order' => null,
		'limit' => null,
		'page' => null
	);

	/**
	 * Custom find query properties, indexed by name.
	 */
	protected $_finders = array();

	/**
	 * Called when a model class is loaded.  Used to call the default initialization routine.
	 *
	 * @return void
	 */
	public static function __init() {
		if (get_called_class() == __CLASS__) {
			return;
		}
		static::init();
	}

	/**
	 * Sets default connection options and connect default finders.
	 *
	 * @return void
	 * @todo Merge in inherited config from AppModel and other parent classes.
	 */
	public static function init($options = array()) {
		$self = static::_instance();
		$vars = get_class_vars(__CLASS__);
		$base = $self->_meta + $vars['_meta'];
		$self->_meta = (
			$options + array('class' => get_called_class(), 'name' => static::_name()) + $base
		);

		if (empty($self->_meta['source']) && $self->_meta['source'] !== false) {
			$self->_meta['source'] = Inflector::tableize($self->_meta['name']);
		}

		if (empty($meta['title'])) {
			foreach (array('title', 'name', $self->_meta['key']) as $field) {
				if (static::schema($field)) {
					$self->_meta['title'] = $field;
					break;
				}
			}
		}
		static::_instance()->_relations = static::_relations();
	}

	public static function __callStatic($method, $params) {
		if (preg_match('/^find(?P<type>\w+)By(?P<fields>\w+)/', $method, $match)) {
			$match['type'][0] = strtolower($match['type'][0]);
			$type = $match['type'];
			$fields = Inflector::underscore($match['fields']);
		}
	}

	/**
	 * undocumented function
	 *
	 * @param string $type
	 * @param string $options
	 * @return void
	 * @filter
	 */
	public static function find($type, $options = array()) {
		$self = static::_instance();
		$classes = $self->_classes;

		$defaults = array(
			'conditions' => null, 'fields' => null, 'order' => null, 'limit' => null, 'page' => 1
		);

		if (is_numeric($type) || $classes['validator']::isUuid($type)) {
			$options['conditions'] = array(
				"{$self->_meta['name']}.{$self->_meta['key']}" => $type
			);
			$type = 'first';
		}

		$options += ($self->_query + $defaults + compact('classes'));
		$meta = array('meta' => $self->_meta, 'name' => get_called_class());
		$params = compact('type', 'options');

		return static::_filter(__METHOD__, $params, function($self, $params, $chain) use ($meta) {
			$options = $params['options'] + array('model' => $meta['name']);
			$connections = $options['classes']['connections'];
			$name = $meta['meta']['connection'];

			$query = new $options['classes']['query']($options);
			$connection = $connections::get($name);

			return new $options['classes']['recordSet'](array(
				'query'    => $query,
				'model'    => $options['model'],
				'handle'   => &$connection,
				'classes'  => $options['classes'],
				'resource' => $connection->read($query, array('return' => 'resource') + $options)
			));
		});
	}

	/**
	 * Gets or sets a finder by name.  This can be an array of default query options,
	 * or a closure that accepts an array of query options, and a closure to execute.
	 *
	 * @param string $name
	 * @param string $options
	 * @return void
	 */
	public static function finder($name, $options = null) {
		$self = static::_instance();

		if (empty($options)) {
			return isset($self->_finders[$name]) ? $self->_finders[$name] : null;
		}
		$self->_finders[$name] = $options;
	}

	public static function meta($key = null, $value = null) {
		$self = static::_instance();

		if (!empty($value)) {
			$self->_meta[$key] = $value;
			return $self->_meta;
		}
		if (is_array($key)) {
			$self->_meta = $key + $self->_meta;
		}
		if (is_array($key) || empty($key)) {
			return $self->_meta;
		}
		return isset($self->_meta[$key]) ? $self->_meta[$key] : null;
	}

	public static function key($values = array()) {
		$key = static::_instance()->_meta['key'];
		$values = is_object($values) ? $values->to('array') : $values;

		if (empty($values)) {
			return $key;
		}
		if (is_array($key)) {
			$scope = array_combine($key, array_fill(0, count($key), null));
			return array_intersect_key($values, $scope);
		}
		return isset($values[$key]) ? $values[$key] : null;
	}

	public static function relations($name = null) {
		$self = static::_instance();

		if (empty($name)) {
			return array_keys($self->_relations);
		}

		if (array_key_exists($name, $self->_relationTypes)) {
			return $self->$name;
		}

		foreach (array_keys($self->_relationTypes) as $type) {
			if (isset($self->{$type}[$name])) {
				return $self->{$type}[$name];
			}
		}
		return null;
	}

	/**
	 * Lazy-initialize the schema for this Model object, if it is not already manually set in the
	 * object. You can declare `protected static $_schema = array(...)` to define the schema
	 * manually.
	 *
	 * @param string $field Optional. You may pass a field name to get schema information for just
	 *        one field. Otherwise, an array with containing all fields is returned.
	 * @return array
	 */
	public static function schema($field = null) {
		$self = static::_instance();

		if (empty($self->_schema)) {
			$name = $self->_meta['connection'];
			$conn = $self->_classes['connections'];
			$self->_schema = $conn::get($name)->describe($self->_meta['source'], $self->_meta);
		}
		if (!empty($field)) {
			return isset($self->_schema[$field]) ? $self->_schema[$field] : null;
		}
		return $self->_schema;
	}

	public static function hasField($field) {
		if (is_array($field)) {
			foreach ($field as $f) {
				if (static::hasField($f)) {
					return $f;
				}
			}
			return false;
		}
		$schema = static::schema();
		return (!empty($schema) && isset($schema[$field]));
	}

	protected static function _name() {
		static $name;
		return $name ?: $name = join('', array_slice(explode("\\", get_called_class()), -1));
	}

	/**
	 * This is pretty much completely broken right now
	 *
	 * @param string $type
	 * @param string $data
	 * @param string $altType
	 * @param string $r
	 * @param string $root
	 * @return void
	 */
	protected static function _normalize($type, $data, $altType = null, $r = array(), $root = true) {

		foreach ((array)$data as $name => $children) {
			if (is_numeric($name)) {
				$name = $children;
				$children = array();
			}

			if (strpos($name, '.') !== false) {
				$chain = explode('.', $name);
				$name = array_shift($chain);
				$children = array(join('.', $chain) => $children);
			}

			if (!empty($children)) {
				if (get_called_class() == $name) {
					$r = array_merge($r, static::_normalize($type, $children, $altType, $r, false));
				} else {
					if (!$_this->getAssociated($name)) {
						$r[$altType][$name] = $children;
					} else {
						$r[$name] = static::_normalize($type, $children, $altType, @$r[$name], $_this->{$name});
					}
				}
			} else {
				if ($_this->getAssociated($name)) {
					$r[$name] = array($type => null);
				} else {
					if ($altType != null) {
						$r[$type][] = $name;
					} else {
						$r[$type] = $name;
					}
				}
			}
		}

		if ($root) {
			return array($this->name => $r);
		}
		return $r;
	}

	/**
	 * Re-implements `applyFilter()` from `StaticObject` to account for object instances.
	 *
	 * @see lithium\core\StaticObject::applyFilter()
	 */
	public static function applyFilter($method, $closure = null) {
		foreach ((array)$method as $m) {
			if (!isset(static::_instance()->_instanceFilters[$m])) {
				static::_instance()->_instanceFilters[$m] = array();
			}
			static::_instance()->_instanceFilters[$m][] = $closure;
		}
	}

	protected static function _filter($method, $params, $callback, $filters = array()) {
		$m = $method;
		if (strpos($method, '::') !== false) {
			list(, $m) = explode('::', $method, 2);
		}
		if (isset(static::_instance()->_instanceFilters[$m])) {
			$filters = array_merge(static::_instance()->_instanceFilters[$m], $filters);
		}
		return parent::_filter($method, $params, $callback, $filters);
	}

	protected static function &_instance() {
		$class = get_called_class();

		if (!isset(static::$_instances[$class])) {
			static::$_instances[$class] = new $class();
		}
		return static::$_instances[$class];
	}

	/**
	 * Iterates through relationship types to construct relation map.
	 *
	 * @return void
	 * @todo See if this can be rewritten to be lazy.
	 */
	protected static function _relations() {
		$relations = array();
		$self = static::_instance();

		foreach ($self->_relationTypes as $type => $keys) {
			foreach (Set::normalize($self->{$type}) as $name => $options) {
				$key = Inflector::underscore($type == 'belongsTo' ? $name : $self->_meta['name']);
				$defaults = array(
					'type' => $type,
					'class' => $name,
					'fields' => true,
					'key' => $key . '_id'
				);
				$relations[$name] = (array)$options + $defaults;
			}
		}
		return $relations;
	}
}

?>