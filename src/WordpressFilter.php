<?php

class WordpressFilter {
	public function __construct() {
		include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'thirdparty'.DIRECTORY_SEPARATOR.'wordpress'.DIRECTORY_SEPARATOR.'wp-includes'.DIRECTORY_SEPARATOR.'formatting.php');
	}

	public function post_title($value) {
		// Fix funky UTF-8 encoding
		$value = \ForceUTF8\Encoding::toUTF8($value);
		$value = htmlspecialchars_decode($value);
		return $value;
	}

	public function post_content($value) {
		$value = \ForceUTF8\Encoding::toUTF8($value);
		$value = wpautop($value);
		return $value;
	}
}