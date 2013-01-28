<?php
/**
 * pictureble_controller.php
 * @author kohei hieda
 *
 */
class PicturebleController extends PicturebleAppController {

	var $name = 'Pictureble';
	var $uses = array();
	var $components = array('Pictureble.Pictup');

	/**
	 * loading
	 */
	function loading() {
		$this->layout = false;
		$this->autoRender = false;
		Configure::write('debug', 0);

		$fileName = 'ajax-loader.gif';

		$filePath = dirname(dirname(__FILE__)).DS.'img'.DS.$fileName;

		if (!file_exists($filePath)) {
			return;
		}

		if (strstr(env('HTTP_USER_AGENT'), 'MSIE')) {
			$fileName = mb_convert_encoding($fileName,  "SJIS", "UTF-8");
			header('Content-Disposition: inline; filename="'. $fileName .'"');
		} else {
			header('Content-Disposition: attachment; filename="'. $fileName .'"');
		}

		header('Content-Length: '.filesize($filePath));
		//header('Content-Type: '.mime_content_type($filePath));
		header('Content-Type: image/gif');

		readfile($filePath);
	}

	/**
	 * upload
	 * @param $validate
	 */
	function upload($validate = null) {
		$this->layout = false;
		$this->autoRender = false;
		Configure::write('debug', 0);

		$modelName = array_shift(array_keys($this->request->data));
		$data = array_shift(array_values($this->request->data));

		$this->loadModel($modelName);

		$fieldName = array_shift(array_keys($data));
		$value = array_shift(array_values($data));

		$this->{$modelName}->addPictField($fieldName);
		if (empty($validate)) {
			$this->{$modelName}->validate = array($fieldName=>array(
				'picturebleIsPicture'=>array(
					'rule'=>array('picturebleIsPicture'),
					'message'=>__('Please Select A Picture File.', true))));
		} else {
			$this->{$modelName}->validate = array($fieldName=>$validate);
		}

		$this->Pictup->bindup($modelName);

		$this->{$modelName}->set($this->request->data);
		$this->{$modelName}->validates();

		$errMsg = '';
		if (!empty($this->{$modelName}->validationErrors[$fieldName])) {
			$errMsg = $this->{$modelName}->validationErrors[$fieldName];
		}

		$ret = array(
			'error'=>'',
			'upload'=>'');

		if (!empty($errMsg)) {
			$ret['error'] = $errMsg;
		} else if (!empty($this->request->data[$modelName][$fieldName])) {
			$ret['upload']['data'] = base64_encode(serialize($this->request->data[$modelName][$fieldName]));
			$ret['upload']['modelName'] = $modelName;
			$ret['upload']['fileName'] = $this->request->data[$modelName][$fieldName]['file_name'];
			$ret['upload']['fileWidth'] = $this->request->data[$modelName][$fieldName]['file_width'];
			$ret['upload']['fileHeight'] = $this->request->data[$modelName][$fieldName]['file_height'];
			$ret['upload']['tmpFileName'] = $this->request->data[$modelName][$fieldName]['tmp_file_name'];
		}

		echo(json_encode($ret));
	}

}