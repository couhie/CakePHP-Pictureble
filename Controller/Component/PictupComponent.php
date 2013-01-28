<?php
/**
 * pictup.php
 * @author kohei hieda
 *
 */
class PictupComponent extends Object {

	var $_controller = null;

	/**
	 * initialize
	 * @param $controller
	 * @param $settings
	 */
	function initialize(&$controller, $settings = array()) {
		$this->_controller = $controller;
	}

	/**
	 * startup
	 * @param $controller
	 */
	function startup(&$controller) {
	}

	/**
	 * bindUp
	 * @param $modelName
	 * @param $data
	 */
	function bindUp($modelName = null, $data = null){
		if (empty($modelName)) {
			$modelName = $this->_controller->modelClass;
		}

		if (empty($this->_controller->request->data[$modelName])) {
			return;
		}

		$pictFields = $this->_controller->{$modelName}->pictFields();

		foreach ($pictFields as $pictField) {
			if (!isset($this->_controller->request->data[$modelName][$pictField]) &&
				(isset($this->_controller->request->data[$modelName][$pictField.'_upload_file']) ||
				 isset($this->_controller->request->data[$modelName][$pictField.'_present_file']) ||
				 isset($this->_controller->request->data[$modelName][$pictField.'_delete']))) {
				$this->_controller->request->data[$modelName][$pictField] = '';
			}
		}

		foreach ($this->_controller->request->data[$modelName] as $fieldName=>$value) {
			if (!in_array($fieldName, $pictFields)) {
				continue;
			}

			if (isset($data[$modelName][$fieldName])) {
				$this->_controller->request->data[$modelName][$fieldName] = $data[$modelName][$fieldName];
			}

			if (!empty($this->_controller->request->data[$modelName][$fieldName.'_delete'])) {
				$this->_controller->request->data[$modelName][$fieldName] = null;
				$this->_controller->request->data[$modelName][$fieldName]['is_delete'] = true;
				continue;
			}

			if (!is_array($value) || !isset($value['error']) || $value['error'] === '\\' || $value['error'] == 4) {
				if (!empty($this->_controller->request->data[$modelName][$fieldName.'_upload_file'])) {
					$this->_controller->request->data[$modelName][$fieldName] = unserialize(base64_decode($this->_controller->request->data[$modelName][$fieldName.'_upload_file']));
				} else if (!empty($this->_controller->request->data[$modelName][$fieldName.'_present_file'])) {
					$this->_controller->request->data[$modelName][$fieldName] = unserialize(base64_decode($this->_controller->request->data[$modelName][$fieldName.'_present_file']));
					$this->_controller->request->data[$modelName][$fieldName]['is_present'] = true;
				} else {
					$this->_controller->request->data[$modelName][$fieldName] = null;
				}
				continue;
			}

			$meta = getimagesize($value['tmp_name']);
			if ($meta === false) {
				$this->_controller->request->data[$modelName][$fieldName] = null;
				$this->_controller->request->data[$modelName][$fieldName.'_error'] = true;
				continue;
			}
			$fileWidth = $meta[0];
			$fileHeight = $meta[1];

			$fileSize = filesize($value['tmp_name']);
			if ($fileSize === false) {
				$this->_controller->request->data[$modelName][$fieldName] = null;
				$this->_controller->request->data[$modelName][$fieldName.'_error'] = true;
				continue;
			}

			$tmpFileName = Security::hash($modelName.$fieldName.$value['name'].uniqid($_SERVER['SERVER_ADDR'])).$value['name'];

			$fp = fopen($value['tmp_name'], 'r');
			$ofile = fread($fp, $fileSize);
			fclose($fp);

			$fileName = $value['name'];
			$fileContentType = $value['type'];

			$url = 'http://'.$this->_controller->{$modelName}->pictSettings('strageHost').$this->_controller->{$modelName}->pictSettings('ringApi').'?mode=up';
			$content = array(
				'storePath'=>$this->_controller->{$modelName}->pictSettings('storePath').DS.'tmp',
				'fileName'=>$tmpFileName,
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
				$this->_controller->request->data[$modelName][$fieldName] = null;
				continue;
			}

			unlink($value['tmp_name']);

			$picture = array(
				'model_name'=>$modelName,
				'field_name'=>$fieldName,
				'file_name'=>$fileName,
				'file_content_type'=>$fileContentType,
				'file_size'=>$fileSize,
				'file_width'=>$fileWidth,
				'file_height'=>$fileHeight,
				'tmp_file_name'=>$tmpFileName);

			$this->_controller->request->data[$modelName][$fieldName] = $picture;
		}
	}

	function beforeRender(&$controller) {
	}

	function shutdown(&$controller) {
	}

	function beforeRedirect(&$controller) {
	}

}