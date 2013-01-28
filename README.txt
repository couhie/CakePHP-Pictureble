* Dependent modules
  - imagemagick
  - jquery plugin jquery.upload

* Recommendation modules
  - pecl imagick!

* Table definition
CREATE TABLE `picture` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `model_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `field_name` varchar(255) collate utf8_unicode_ci NOT NULL,
  `file_name` text collate utf8_unicode_ci NOT NULL,
  `file_content_type` text collate utf8_unicode_ci NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `file_width` int(10) unsigned NOT NULL,
  `file_height` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  `deleted` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;

* Model setting
	var $validate = array(
		'hogehoge'=>array(
			'picturebleIsPicture'=>array(
				'rule'=>array('picturebleIsPicture'),
				'message'=>'Error Pictureble IsPicture'),
			'picturebleAllowExtensions'=>array(
				'rule'=>array('picturebleAllowExtensions', array('jpg', 'png', 'gif')),
				'message'=>'Error Pictureble AllowExtension')))
