Wordpress Database Tools
====================================

A set of classes for connecting to a Wordpress database and querying it. The point of this library is to easily and effeciently iterate over Wordpress data and import into other CMS systems.

## Quick Start

- Set Wordpress Database config details
```php
<?php
$wpDB = new WordpressDatabase(array(
	'database' 	 	 => 'wordpress-database',
	'username'		 => 'root',
	'password'		 => '',
	'table_prefix'   => 'wp'
));
```
- Loop over Wordpress data and do what you want with it.
```php
<?php
foreach ($wpDB->getPages() as $wpData) {
	$wpMeta = $wpDB->attachAndGetPostMeta($wpData);

	$newRecord = array();
	$newRecord['Title'] = $wpDB->process('post_title', $wpData['post_title']);
	$newRecord['Permalink'] = $wpData['post_name'];
	$newRecord['Content'] = $wpDB->process('post_content', $wpData['post_content']);
	$newRecord['Created'] = $wpData['post_date'];
	$newRecord['LastEdited'] = $wpData['post_modified'];
	$newRecord['WordpressData'] = $wpData; // Since 'attachAndGetPostMeta' attaches the meta to $wpData, it'll store that too.
}
```

