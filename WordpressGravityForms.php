<?php

class WordpressGravityForms {
	const HINT = '____extraRelatedData';

	/**
	 * @var WordpressDatabase
	 */
	public $_db = null;

	public function __construct(WordpressDatabase $db) {
		$this->_db = $db;
	}

	public function getForms($retrieveAllRelatedData = false) {
		static $table = 'rg_form';
		$result = $this->_db->_query('SELECT * FROM '.$this->_db->getTable('rg_form').'');
		foreach ($result as &$item) {
			$item[self::HINT]['table'] = $table;
			unset($item);
		}
		if ($retrieveAllRelatedData)
		{
			foreach ($result as &$item) {
				$this->getFormMeta($item);
				unset($item);
			}
		}
		return $result;
	}

	public function attachAndGetFormMeta(&$rg_form) {
		static $table = 'rg_form_meta';
		$form_id = (int)$rg_form['id'];
		$result = $this->_db->_query('SELECT * FROM '.$this->_db->getTable($table).' WHERE form_id = '.$form_id);
		if ($result) {
			if (isset($result[1])) {
				throw new Exception('Expected 1 rg_form_meta record per rg_form::id, not '.count($result));
			}
			foreach ($result as &$item) 
			{
				foreach ($item as $k => $v) 
				{
					if ($v && isset($v[0]) && isset($v[1]))
					{
						$loopResult = $v;
						if ($v[0] === '{' || $v[0] === '[') {
							// If object or array in JSON
							$loopResult = json_decode($v, true);
						} 
						else if ($v[0] === 'a' && $v[1] === ':') 
						{
							// Gravity Forms seems to always serialize an array of data, so 'a:' is
							// always the first two characters.
							$loopResult = @unserialize($v);
							if ($loopResult === FALSE) {
								throw new Exception('Failed to unserialize in '.__CLASS__.'::'.__FUNCTION__);
							}
						}
						$item[$k] = $loopResult;
					}
				}
				unset($item);
			}
			$result = reset($result);
			$rg_form[self::HINT]['meta'] = $result;
		}
		return $result;
	}
}