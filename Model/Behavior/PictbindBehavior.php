<?php
/**
 * pictbind.php
 * @author kohei hieda
 *
 */
class PictbindBehavior extends ModelBehavior {

	var $picturebleModel = null;
	var $finished = false;

	/**
	 * setup
	 * @param $model
	 * @param $config
	 */
	function setup(&$model, $config) {
		$default = array(
			'strageHost'=>'localhost',
			'storePath'=>'pict',
			'ringApi'=>'/api/pictureble.php',
			'modelName'=>'Picture',
			'pictFields'=>array());

		$this->settings[$model->alias] = Set::merge($default, $config);
		$this->picturebleModel =& ClassRegistry::init($this->settings[$model->alias]['modelName']);
	}

	/**
	 * beforeFind
	 * @param $model
	 * @param $queryData
	 * @return array
	 */
	function beforeFind(&$model, $queryData = null) {
		if (empty($this->settings[$model->alias]['pictFields']) || empty($queryData['fields'])) {
			return $queryData;
		}

		$modelName = $model->alias;
		$fields = (array) $queryData['fields'];
		$flip = array_flip($fields);
		foreach ($this->settings[$model->alias]['pictFields'] as $pictField) {
			unset($flip[$modelName.'.'.$pictField]);
			unset($flip[$pictField]);
			if (!in_array($modelName.'.'.$pictField, $fields) && !in_array($pictField, $fields)) {
				unset($this->settings[$model->alias]['pictFields'][$pictField]);
			}
		}
		$queryData['fields'] = array_flip($flip);

		return $queryData;
	}

	/**
	 * afterFind
	 * @param $model
	 * @param $queryData
	 * @param $primary
	 * @return array
	 */
	function afterFind(&$model, $results, $primary) {
		if (empty($this->settings[$model->alias]['pictFields']) || empty($results)) {
			return $results;
		}

		$modelIds = Set::extract('/'.$model->alias.'/'.$this->picturebleModel->primaryKey, $results);

		$conditions = array(
			'model_name'=>$model->alias,
			'model_id'=>$modelIds);
		$params = array(
			'conditions'=>$conditions,
			'recursive'=>-1);
		$pictures = $this->picturebleModel->find('all', $params);

		$pictures = Set::combine($pictures, array('%1$s.%2$s', '/'.$this->settings[$model->alias]['modelName'].'/model_id', '/'.$this->settings[$model->alias]['modelName'].'/field_name'), '/'.$this->settings[$model->alias]['modelName']);

		foreach ($results as $key=>$value) {
			if (empty($results[$key][$model->alias])) {
				continue;
			}
			$modelId = $value[$model->alias][$model->primaryKey];
			foreach ($this->settings[$model->alias]['pictFields'] as $pictField) {
				if (array_key_exists($modelId.'.'.$pictField, $pictures)) {
					$results[$key][$model->alias][$pictField] = $pictures[$modelId.'.'.$pictField][$this->settings[$model->alias]['modelName']];
					$results[$key][$model->alias][$pictField.'_present_file'] = base64_encode(serialize($pictures[$modelId.'.'.$pictField][$this->settings[$model->alias]['modelName']]));
				} else {
					$results[$key][$model->alias][$pictField] = null;
				}
			}
		}

		return $results;
	}

	/**
	 * beforeSave
	 * @param $model
	 */
	function beforeSave(&$model) {
		return true;
	}

	/**
	 * afterSave
	 * @param $model
	 * @param $created
	 */
	function afterSave(&$model, $created) {
		if ($this->finished) {
			return true;
		}

		$modelName = $model->alias;
		$pictFields = $this->settings[$model->alias]['pictFields'];

		if ($created) {
			$modelId = $model->getLastInsertId();
		} else {
			$modelId = $model->id;
		}

		$downContents = array();
		$shiftContents = array();

		foreach ($model->data[$modelName] as $fieldName=>$value) {
			if (!in_array($fieldName, $pictFields)) {
				continue;
			}

			if (empty($value) || !empty($value['is_present'])) {
				continue;
			}

			if (!empty($model->data[$modelName][$fieldName.'_present_file']) && !empty($model->data[$modelName][$fieldName.'_delete'])) {
				$presentPicture = unserialize(base64_decode($model->data[$modelName][$fieldName.'_present_file']));
				if ($presentPicture['model_id'] == $modelId) {
					$this->picturebleModel->delete($presentPicture[$this->picturebleModel->primaryKey], false);
				}
				continue;
			}

			if (empty($value['tmp_file_name'])) {
				continue;
			}

			$data = $value;
			$data['model_name'] = $modelName;
			$data['model_id'] = $modelId;

			$this->picturebleModel->create();
			if (!empty($model->data[$modelName][$fieldName.'_present_file'])) {
				$presentPicture = unserialize(base64_decode($model->data[$modelName][$fieldName.'_present_file']));
				if ($presentPicture['model_id'] == $modelId) {
					$this->picturebleModel->id = $presentPicture[$this->picturebleModel->primaryKey];
				}
			}
			if (!$this->picturebleModel->save($data)) {
				continue;
			}

			Cache::delete('pictureble|'.$this->settings[$model->alias]['storePath'].DS.$modelName.DS.$modelId.DS.$fieldName);

			$downContents[] = array(
				'storePath'=>$this->settings[$model->alias]['storePath'].DS.$modelName.DS.$modelId.DS.$fieldName);
			$shiftContents[] = array(
				'sourceFile'=>$this->settings[$model->alias]['storePath'].DS.'tmp'.DS.$value['tmp_file_name'],
				'storePath'=>$this->settings[$model->alias]['storePath'].DS.$modelName.DS.$modelId.DS.$fieldName);
		}

		if (!empty($downContents)) {
			$url = 'http://'.$this->settings[$model->alias]['strageHost'].$this->settings[$model->alias]['ringApi'].'?mode=down';
			$query = http_build_query(array('contents'=>$downContents));
			$header = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($query));
			$context = array(
				'http'=>array(
					'method'=>'POST',
					'content'=>$query,
					'header'=>implode("\r\n", $header)));

			if (@file_get_contents($url, false, stream_context_create($context)) === false) {
				return false;
			}
		}
		if (!empty($shiftContents)) {
			$url = 'http://'.$this->settings[$model->alias]['strageHost'].$this->settings[$model->alias]['ringApi'].'?mode=shift';
			$query = http_build_query(array('contents'=>$shiftContents));
			$header = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($query));
			$context = array(
				'http'=>array(
					'method'=>'POST',
					'content'=>$query,
					'header'=>implode("\r\n", $header)));

			if (@file_get_contents($url, false, stream_context_create($context)) === false) {
				return false;
			}
		}

		$this->finished = true;

		return true;
	}

	/**
	 * beforeDelete
	 * @param $model
	 * @param $cascade
	 * @return boolean
	 */
	function beforeDelete(&$model, $cascade = true) {
		return true;
	}

	/**
	 * afterDelete
	 * @param $model
	 * @return boolean
	 */
	function afterDelete(&$model) {
		$conditions = array(
			'model_name'=>$model->alias,
			'model_id'=>$model->id);
		$this->picturebleModel->deleteAll($conditions);

		$url = 'http://'.$this->settings[$model->alias]['strageHost'].$this->settings[$model->alias]['ringApi'].'?mode=down';
		$content = array(
			'storePath'=>$this->settings[$model->alias]['storePath'].DS.$model->alias.DS.$model->id);
		$query = http_build_query(array('contents'=>array($content)));
		$header = array(
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($query));
		$context = array(
			'http'=>array(
				'method'=>'POST',
				'content'=>$query,
				'header'=>implode("\r\n", $header)));

		if (@file_get_contents($url, false, stream_context_create($context)) === false) {
			return false;
		}
	}

	/**
	 * pictUpdate
	 * @param $model
	 * @param $modelName
	 * @param $modelId
	 * @param $fieldName
	 * @param $images
	 * @return boolean
	 */
	function pictUpdate(&$model, $modelName, $modelId, $fieldName, $images) {
		$conditions = array(
			'Picture.model_name'=>$modelName,
			'Picture.model_id'=>$modelId,
			'Picture.field_name'=>$fieldName);
		$params = compact('conditions');
		$data = $this->picturebleModel->find('first', $params);

		$this->picturebleModel->create();

		if (!empty($data)) {
			Cache::delete('pictureble|'.$this->settings[$model->alias]['storePath'].DS.$modelName.DS.$modelId.DS.$fieldName);

			$url = 'http://'.$this->settings[$model->alias]['strageHost'].$this->settings[$model->alias]['ringApi'].'?mode=down';
			$content = array(
				'storePath'=>$this->settings[$model->alias]['storePath'].DS.$modelName.DS.$modelId.DS.$fieldName);
			$query = http_build_query(array('contents'=>array($content)));
			$header = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($query));
			$context = array(
				'http'=>array(
					'method'=>'POST',
					'content'=>$query,
					'header'=>implode("\r\n", $header)));

			if (@file_get_contents($url, false, stream_context_create($context)) === false) {
				return false;
			}

			if (empty($images)) {
				$this->picturebleModel->delete($data[$this->settings[$model->alias]['modelName']]['id'], false);
			}

			$this->picturebleModel->id = $data[$this->settings[$model->alias]['modelName']]['id'];
		}

		if (empty($images)) {
			return true;
		}

		$value = $images[0];

		if (!isset($value['error']) || $value['error'] == 4) {
			return false;
		}

		$meta = getimagesize($value['tmp_name']);
		if ($meta === false) {
			return false;
		}
		$fileWidth = $meta[0];
		$fileHeight = $meta[1];

		$fileSize = filesize($value['tmp_name']);
		if ($fileSize === false) {
			return false;
		}

		$fp = fopen($value['tmp_name'], 'r');
		$ofile = fread($fp, $fileSize);
		fclose($fp);

		$fileName = $value['name'];
		$fileContentType = $value['type'];

		$data = array();
		$data['model_name'] = $modelName;
		$data['model_id'] = $modelId;
		$data['field_name'] = $fieldName;
		$data['file_name'] = $fileName;
		$data['file_content_type'] = $fileContentType;
		$data['file_size'] = $fileSize;
		$data['file_width'] = $fileWidth;
		$data['file_height'] = $fileHeight;
		if (!$this->picturebleModel->save($data)) {
			return false;
		}

		$url = 'http://'.$this->settings[$model->alias]['strageHost'].$this->settings[$model->alias]['ringApi'].'?mode=up';
		$content = array(
			'storePath'=>$this->settings[$model->alias]['storePath'].DS.$modelName.DS.$modelId.DS.$fieldName,
			'fileName'=>preg_replace('/^.*[.]/', 'org.', $value['name']),
			'image'=>$ofile);
		$query = http_build_query(array('contents'=>array($content)));
		$header = array(
			'Content-Type: application/x-www-form-urlencoded',
			'Content-Length: '.strlen($query));
		$context = array(
			'http'=>array(
				'method'=>'POST',
				'content'=>$query,
				'header'=>implode("\r\n", $header)));

		if (@file_get_contents($url, false, stream_context_create($context)) === false) {
			return false;
		}

		unlink($value['tmp_name']);

		return true;
	}

	/**
	 * pictCopy
	 * @param $model
	 * @param $data
	 * @param $sourceModelId
	 * @param $distModelId
	 * @return boolean
	 */
	function pictCopy(&$model, $data, $sourceModelId, $distModelId) {
		$modelName = $model->alias;
		$pictFields = $this->settings[$modelName]['pictFields'];
		$deleteContents = array();
		$copyContents = array();

		foreach ($pictFields as $fieldName) {
			if (isset($data[$modelName][$fieldName]) && empty($data[$modelName][$fieldName]['is_present'])) {
				continue;
			}

			$conditions = array(
				'model_name'=>$modelName,
				'model_id'=>$distModelId,
				'field_name'=>$fieldName);
			$this->picturebleModel->deleteAll($conditions);

			$deleteContents[] = array(
				'storePath'=>$this->settings[$modelName]['storePath'].DS.$modelName.DS.$distModelId.DS.$fieldName);

			$conditions = array(
				'model_name'=>$modelName,
				'model_id'=>$sourceModelId,
				'field_name'=>$fieldName);
			$params = compact('conditions');
			$pictureble = $this->picturebleModel->find('first', $params);

			if (empty($pictureble)) {
				continue;
			}

			$copyContents[] = array(
				'sourcePath'=>$this->settings[$modelName]['storePath'].DS.$modelName.DS.$pictureble[$this->settings[$modelName]['modelName']]['model_id'].DS.$fieldName,
				'storePath'=>$this->settings[$modelName]['storePath'].DS.$modelName.DS.$distModelId.DS.$fieldName);

			unset($pictureble[$this->settings[$modelName]['modelName']]['id']);
			unset($pictureble[$this->settings[$modelName]['modelName']]['created']);
			unset($pictureble[$this->settings[$modelName]['modelName']]['updated']);
			unset($pictureble[$this->settings[$modelName]['modelName']]['is_present']);
			$pictureble[$this->settings[$modelName]['modelName']]['model_id'] = $distModelId;

			$this->picturebleModel->create();
			if (!$this->picturebleModel->save($pictureble)) {
				array_pop($copyContents);
				continue;
			}
		}

		if (!empty($deleteContents)) {
			$url = 'http://'.$this->settings[$modelName]['strageHost'].$this->settings[$modelName]['ringApi'].'?mode=down';
			$query = http_build_query(array('contents'=>$deleteContents));
			$header = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($query));
			$context = array(
				'http'=>array(
					'method'=>'POST',
					'content'=>$query,
					'header'=>implode("\r\n", $header)));

			if (@file_get_contents($url, false, stream_context_create($context)) === false) {
				return false;
			}
		}

		if (!empty($copyContents)) {
			$url = 'http://'.$this->settings[$modelName]['strageHost'].$this->settings[$modelName]['ringApi'].'?mode=copy';
			$query = http_build_query(array('contents'=>$copyContents));
			$header = array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: '.strlen($query));
			$context = array(
				'http'=>array(
					'method'=>'POST',
					'content'=>$query,
					'header'=>implode("\r\n", $header)));

			if (@file_get_contents($url, false, stream_context_create($context)) === false) {
				return false;
			}
		}

		return true;
	}

	/**
	 * pictSettings
	 * @param $model
	 * @param $key
	 */
	function pictSettings(&$model, $key = null) {
		if (empty($key)) {
			return $this->settings[$model->alias];
		} else {
			return $this->settings[$model->alias][$key];
		}
	}

	/**
	 * picturebleModel
	 * @return object
	 */
	function picturebleModel() {
		return $this->picturebleModel;
	}

	/**
	 * setPictStrageHost
	 * @param $model
	 * @param $strageHost
	 */
	function setPictStrageHost(&$model, $strageHost) {
		$this->settings[$model->alias]['strageHost'] = $strageHost;
	}

	/**
	 * pictStrageHost
	 * @param $model
	 * @return string
	 */
	function pictStrageHost(&$model) {
		return $this->settings[$model->alias]['strageHost'];
	}

	/**
	 * addPictField
	 * @param $model
	 * @param $field
	 */
	function addPictField(&$model, $field) {
		$this->settings[$model->alias]['pictFields'][] = $field;
	}

	/**
	 * clearPictField
	 * @param $model
	 * @param $field
	 */
	function clearPictField(&$model) {
		$this->settings[$model->alias]['pictFields'] = array();
	}

	/**
	 * pictFields
	 * @param $model
	 * @return array
	 */
	function pictFields(&$model) {
		return $this->settings[$model->alias]['pictFields'];
	}

	/**
	 * pictPrimaryKey
	 * @param $model
	 * @return string
	 */
	function pictPrimaryKey(&$model) {
		return $this->picturebleModel->primaryKey;
	}

	/**
	 * pictReset
	 */
	function pictReset(&$model) {
		$this->finished = false;
	}

	/**
	 * picturebleRequire
	 * @param $model
	 * @param $wordvalue
	 * @return boolean
	 */
	function picturebleRequire(&$model, $wordvalue) {
		$keys = array_keys($wordvalue);
		$key = array_shift($keys);
		$values = array_shift($wordvalue);
		if (empty($values) || !empty($values['is_delete'])) {
			return false;
		}
		return true;
	}

	/**
	 * picturebleIsPicture
	 * @param $model
	 * @param $wordvalue
	 * @return boolean
	 */
	function picturebleIsPicture(&$model, $wordvalue) {
		$keys = array_keys($wordvalue);
		$key = array_shift($keys);
		$values = array_shift($wordvalue);
		if (empty($values) || !empty($values[$key.'_error'])) {
			return false;
		}
		return true;
	}

	/**
	 * picturebleAllowExtensions
	 * @param $model
	 * @param $wordvalue
	 * @param $extentions
	 */
	function picturebleAllowExtensions(&$model, $wordvalue, $extensions) {
		$word = array_shift($wordvalue);

		if (empty($word)) {
			return true;
		}

		if (empty($word['file_name'])) {
			return true;
		}

		$extension = preg_replace('/^.*\./', '', $word['name']);

		if (!in_array($extension, $extensions)) {
			return false;
		}

		return true;
	}

}