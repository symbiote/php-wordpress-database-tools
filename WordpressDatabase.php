<?php

class WordpressDatabase {
	const HINT = '____extraRelatedData';

	public static $default_config = array(
		'ipaddress' => '127.0.0.1',
		'username'  => '',
		'password'  => '',
		'database'  => '',
		'table_prefix' => 'wp',
	);

	public static $default_filter_class = 'WordpressFilter';

	public $filter_methods = array();

	/**
	 * @var mysqli
	 */
	public $_db = null;

	public $_db_ipaddress = '127.0.0.1';

	public $_db_username = '';

	public $_db_password = '';

	public $_db_database = '';

	public $_db_table_prefix = 'wp';

	/**
	 * @var WordpressFilter
	 */ 
	public $_filter = null;

	public function __construct($config = null, $filter_class = null) {
		if ($config === null || $config === false) {
			$config = static::$default_config;
		}
		if (!$config) {
			throw new Exception('Empty config is invalid.');
		}
		if (!is_array($config)) {
			throw new Exception('First parameter must be an array.');
		}
		if (isset($config['ipaddress'])) {
			$this->_db_ipaddress = $config['ipaddress'];
		}
		if (isset($config['username'])) {
			$this->_db_username = $config['username'];
		}
		if (isset($config['password'])) {
			$this->_db_password = $config['password'];
		}
		if (isset($config['database'])) {
			$this->_db_database = $config['database'];
		}
		if (isset($config['table_prefix'])) {
			$this->_db_table_prefix = $config['table_prefix'];
		}

		if ($filter_class === null || $filter_class === false) {
			$filter_class = static::$default_filter_class;
		}
		if ($filter_class) {
			$this->filter_methods = array();
			foreach (get_class_methods($filter_class) as $function) {
				$this->filter_methods[$function] = $function;
			}
			unset($this->filter_methods['__construct']);
			$this->_filter = new $filter_class;
		}
	}

	/** 
	 * @return string
	 */
	public function getTable($tablenameWithoutPrefix) {
		return $this->_db_table_prefix.'_'.$tablenameWithoutPrefix;
	}

	public function getPosts($post_type = null, $retrieveAllRelatedData = false) {
		$result = array();
		if ($post_type) {
			$result = $this->_queryPosts('SELECT * FROM {{table}} WHERE post_type = \''.$post_type.'\'', $retrieveAllRelatedData);
		} else {
			$result = $this->_queryPosts('SELECT * FROM {{table}}', $retrieveAllRelatedData);
		}
		return $result;
	}

	public function attachAndGetPostMeta(&$post_object) {
		if (isset($post_object[self::HINT]['meta'])) {
			// If meta is already attached, just get it from HINT.
			return $post_object[self::HINT]['meta'];
		}
		$post_id = (int)$post_object['ID'];
		$result = $this->_query('SELECT meta_key, meta_value FROM '.$this->getTable('postmeta').' WHERE post_id = '.$post_id, 'meta_key', 'meta_value');
		if ($result) {
			$post_object[self::HINT]['meta'] = $result;
		}
		return $result;
	}

	public function getPostTypes() {
		$result = $this->_query('SELECT DISTINCT post_type FROM '.$this->getTable('posts'), 'post_type', true);
		return $result;
	}

	public function getPostTypesString() {
		return "Wordpress Post Types: ".implode(', ', $this->getPostTypes());
	}

	public function getPageTemplates() {
		$result = $this->_query('SELECT DISTINCT meta_value FROM '.$this->getTable('postmeta').' WHERE meta_key = \'_wp_page_template\'', 'meta_value', true);
		return $result;
	}

	public function getPages($page_template = '', $retrieveAllRelatedData = false) {
		$result = array();
		if ($page_template)
		{
			// Get all page IDs with page template
			$result = $this->_query('SELECT post_id FROM '.$this->getTable('postmeta').' WHERE meta_key = \'_wp_page_template\' AND meta_value = \''.$page_template.'\'', 'post_id', true);

			// Query IDs directly
			$result = $this->_queryPosts('SELECT * FROM {{table}} WHERE ID IN ('.implode(',', $result).')', $retrieveAllRelatedData);
		}
		else
		{
			$result = $this->getPosts('page', $retrieveAllRelatedData);
		}
		return $result;
	}

	/**
	 * Return the front page.
	 *
	 * Throw an exception if this Wordpress DB isn't using a 'page' as the homepage
	 * and is showing a post listing instead.
	 *
	 * @return array|null
	 */
	public function getFrontPageID() {
		// Core WP only handles either 'page' or 'posts' as of 2016-07-22
		$type = $this->getOption('show_on_front');
		if ($type !== 'page')
		{
			throw new Exception('Cannot use "'.__FUNCTION__.'" if Wordpress database isn\'t using a page as it\'s home page. Type = '.$type);
		}
		$wordpressID = $this->getOption('page_on_front');
		if ($wordpressID) {
			return $wordpressID;
		} else {
			$wordpressID = ($wordpressID === false) ? 'false' : $wordpressID; // Fix printing of 'false'
			throw new Exception('Invalid ID #'.$wordpressID.' returned from "'.__FUNCTION__.'"');
		}
	}

	public function getOption($optionName) {
		$this->_init();
		$optionName = $this->_db->real_escape_string($optionName);
		$result = $this->_query('SELECT option_name, option_value FROM '.$this->getTable('options').' WHERE option_name = \''.$optionName.'\'', 'option_name', 'option_value');
		return reset($result);
	}

	public function getAttachments($retrieveAllRelatedData = false) {
		$result = $this->_queryPosts('SELECT * FROM {{table}} WHERE post_type = \'attachment\'', false);
		if ($retrieveAllRelatedData) {
			foreach ($result as &$item) {
				$this->attachAndattachAndattachAndGetAttachmentMeta($item);
				unset($item);
			}
		}
		return $result;
	}

	public function attachAndGetAttachmentMeta(&$post_object) {
		$meta = $this->attachAndGetPostMeta($post_object);
		if (isset($meta['_wp_attachment_metadata']) && $meta['_wp_attachment_metadata']) {
			// todo(Jake): add set_error_handler() to detect Unserialize bugs.
			$meta['_wp_attachment_metadata'] = @unserialize($meta['_wp_attachment_metadata']);
		}
		if (isset($meta['amazonS3_info']) && $meta['amazonS3_info']) {
			// Support AmazonS3 plugin data
			$meta['amazonS3_info'] = @unserialize($meta['amazonS3_info']);
		}
		$post_object[self::HINT]['meta'] = $meta;
		return $meta;
	}

	public function getNavMenuTypeBySlug($slug) {
		// Query terms with the slug
		$terms = $this->_query('SELECT * FROM '.$this->getTable('terms').' WHERE slug = \''.$slug.'\'', 'term_id');

		// Create hashmap of IDs
		$ids = array();
		foreach ($terms as $term) {
			$ids[$term['term_id']] = $term['term_id'];
		}

		// Put any terms into $result that are of the taxonomy 'nav_menu'
		$result = array();
		$term_taxonomys = $this->_query('SELECT term_id FROM '.$this->getTable('term_taxonomy').' WHERE taxonomy = \'nav_menu\' AND term_id IN ('.implode(',', $ids).')', 'term_id', true);
		foreach ($term_taxonomys as $id)
		{
			if (isset($terms[$id])) {
				return $terms[$id];
			}
		}
		return null;
	}

	public function getNavMenuTypes() {
		$result = $this->_query('SELECT term_id FROM '.$this->getTable('term_taxonomy').' WHERE taxonomy = \'nav_menu\'', 'term_id', true);
		$result = $this->_query('SELECT * FROM '.$this->getTable('terms').' WHERE term_id IN ('.implode(',', $result).')', 'term_id');
		return $result;
	}

	public function getNavMenuItems($nav_menu_type, $retrieveAllRelatedData = false) {
		$navMenuID = null;
		if (is_string($nav_menu_type)) {
			$nav_menu_type = $this->getNavMenuTypeBySlug($nav_menu_type);
			$navMenuID = $nav_menu_type['term_id'];
		}
		if (is_array($nav_menu_type)) {
			if (!isset($nav_menu_type['term_id'])) {
				throw new Exception('Expected "term_id" in array passed in.');
			}
			$navMenuID = $nav_menu_type['term_id'];
		}
		if ($navMenuID === null) {
			throw new Exception('No ID from passed in variable.');
		}
		$navMenuID = (int)$navMenuID;
		$menuItemIDs = $this->_query('SELECT * FROM '.$this->getTable('term_relationships').' WHERE term_taxonomy_id = '.$navMenuID, 'object_id', 'object_id');
		$result = $this->_query('SELECT * FROM '.$this->getTable('posts').' WHERE ID IN ('.implode(',', $menuItemIDs).') ORDER BY menu_order ASC', 'ID');
		if ($retrieveAllRelatedData) {
			foreach ($result as &$item) {
				$this->attachAndGetPostMeta($item);
				unset($item);
			}
		}
		return $result;
	}

	public function process($columnName, $value) {
		if (!isset($this->filter_methods[$columnName])) {
			throw new Exception(get_class($this->_filter).' filter class does not have a function to process "'.$columnName.'"');
		}
		return $this->_filter->{$columnName}($value);
	}

	//
	// "Protected"
	//

	public function _init() {
		if (!$this->_db) {
			$this->_db = new mysqli($this->_db_ipaddress, $this->_db_username, $this->_db_password, $this->_db_database);
			if ($this->_db->connect_errno) {
				throw new Exception('Unable to connect: '.$this->_db->connect_error);
			}
		}
	}

	/**
	 * @return array
	 */
	public function _queryPosts($sql, $retrieveAllRelatedData = false) {
		// setTable
		static $table = 'posts';
		$tableWithPrefix = $this->_db_table_prefix.'_'.$table;

		$sql = str_replace(array('{{table}}'), $tableWithPrefix, $sql);
		$result = $this->_query($sql. ' ORDER BY menu_order ASC');
	
		foreach ($result as &$item) {
			$item[self::HINT]['table'] = $table;
			unset($item);
		}
		if ($retrieveAllRelatedData) {
			foreach ($result as &$item) {
				$this->attachAndGetPostMeta($item);
				unset($item);
			}
		}
		return $result;
	}

	/**
	 * @return array
	 */
	public function _query($sql, $keyName = null, $onlyGetKeyField = false) {
		$this->_init();

		$result = array();
		$query = $this->_db->query($sql);
		if (!$query)
		{
			 throw new Exception('Query error: '.$this->_db->error);
		}
		else
		{
			if ($keyName) {
				if ($onlyGetKeyField === true) {
					// ie. if $keyName = "ID", $result[4] = 4;
					while($record = $query->fetch_assoc()) { 
						$result[$record[$keyName]] = $record[$keyName];
					}
				} else if ($onlyGetKeyField) {
					while($record = $query->fetch_assoc()) { 
						$result[$record[$keyName]] = $record[$onlyGetKeyField];
					}
				} else {
					// ie. if $keyName = "ID", $result[4] = array('post_id' => 4, 'post_title' => 'Blah', ...)
					while($record = $query->fetch_assoc()) { 
						$result[$record[$keyName]] = $record;
					}
				}
			} else {
				while($record = $query->fetch_assoc()) { 
					$result[] = $record;
				}
			}
			$query->close();
		}
		return $result;
	}
}