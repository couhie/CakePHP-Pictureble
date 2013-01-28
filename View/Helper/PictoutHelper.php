<?php
/**
 * pictout.php
 * @author kohei hieda
 *
 */
class PictoutHelper extends AppHelper {

	var $helpers = array('Html', 'Form');

	var $picturebleModels = array();

	var $view = null;

	function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);
		$this->view = $View;
	}

	/**
	 * input
	 * @param $fieldsName
	 * @param $options
	 */
	function input($fieldName, $options = array()) {
		$ret = '';

		$this->setEntity($fieldName);
		$entity = $this->entity();
		$modelName = array_shift($entity);
		$dataFieldName = array_shift($entity);
		$domId = $this->domId();

		// モデルのインスタンス生成
		if (empty($this->picturebleModels[$modelName])) {
			$this->picturebleModels[$modelName] =& ClassRegistry::init($modelName);
		}

		$image = array();
		if (isset($options['image'])) {
			$image = $options['image'];
			unset($options['image']);
		}

		$deleteLabel = __('Picture Delete', true);
		if (!empty($options['delete_label'])) {
			$deleteLabel = $options['delete_label'];
			unset($options['delete_label']);
		}

		$after = '';
		if (empty($options['ajax'])) {
			if (empty($options['label'])) {
				$options['label'] = __('Picture', true);
			}

			$options['label'] .= '('.$this->Form->input($fieldName.'_delete', array('type'=>'checkbox', 'multiple'=>'checkbox', 'label'=>false, 'div'=>false, 'style'=>'float:none;clear:both;', 'hiddenField'=>false, 'after'=>$deleteLabel)).')';
		} else {
			$validate = '';
			if (!empty($options['validate'])) {
				$validate = $options['validate'];
				unset($options['validate']);
			}

			$uploadUrl = $this->Html->url("/pictureble/pictureble/upload/{$validate}");
			$thumbnailUrl = 'http://'.$this->picturebleModels[$modelName]->pictSettings('strageHost').'/'.$this->picturebleModels[$modelName]->pictSettings('storePath').'/tmp/';
			$loadingUrl = $this->Html->url("/pictureble/pictureble/loading");

			$width = empty($image['width']) ? 0 : $image['width'];
			$height = empty($image['height']) ? 0 : $image['height'];

			$resizeProc = '';
			$resizeProc .=<<< EOD
width = {$width};
height = {$height};
EOD;

			if (empty($image['expanse'])) {
				$resizeProc .=<<< EOD
if (json.upload.fileWidth < width) {
	width = 0;
}
if (json.upload.fileHeight < height) {
	height = 0;
}
EOD;
			}

			$resizeProc .=<<< EOD
if (width > 0 && height > 0) {
	if ((width / json.upload.fileWidth) <= (height / json.upload.fileHeight)) {
		resize = "width=\"" + width + "\"";
	} else {
		resize = "height=\"" + height + "\"";
	}
} else if (width > 0) {
	resize = "width=\"" + width + "\"";
} else if (height > 0) {
	resize = "height=\"" + height + "\"";
}
EOD;

			$scripts =<<< EOD
$(function() {
	$('#{$domId}').change(function() {
		$('#{$domId}Thumbnail').html('<img src="{$loadingUrl}" />');
		$('#{$domId}').upload('{$uploadUrl}', function(res) {
			json = eval("(" + res + ")");
			if (json.error != "") {
				alert(json.error);
				$('#{$domId}').val('');
				$('#{$domId}Thumbnail').empty();
				$('#{$domId}Delete').val(1);
			} else if (typeof json.upload == "object") {
				resize = "";
				{$resizeProc}
				$('#{$domId}').hide();
				$('#{$domId}').val('');
				$('#{$domId}Erase').before('<label id="{$domId}Label" style="float:left;">' + json.upload.fileName + '&nbsp;</label>');
				$('#{$domId}Erase').show();
				$('#{$domId}Thumbnail').html('<img src="{$thumbnailUrl}' + json.upload.tmpFileName + '" ' + resize + ' />');
				$('#{$domId}UploadFile').val(json.upload.data);
				$('#{$domId}Delete').val(0);
			}
		}, 'html');
	});
	$('#{$domId}Erase').click(function() {
		$('#{$domId}').val('');
		$('#{$domId}Label').remove();
		$('#{$domId}Erase').hide();
		$('#{$domId}').show();
		$('#{$domId}Thumbnail').empty();
		$('#{$domId}Delete').val(1);
		$('#{$domId}UploadFile').val('');
		return false;
	});
});
EOD;

			$presentFile = null;
			$uploadFile = null;
			$eraserLabel = null;
			$eraserStyle = 'float:none;clear:both;';
			if ((empty($this->data[$modelName][$dataFieldName.'_upload_file']) &&
				 empty($this->data[$modelName][$dataFieldName.'_present_file'])) ||
				!empty($this->data[$modelName][$dataFieldName.'_delete'])) {
				$eraserStyle .= 'display:none;';
			} else {
				if (!empty($this->data[$modelName][$dataFieldName.'_upload_file'])) {
					$uploadFile = unserialize(base64_decode($this->data[$modelName][$dataFieldName.'_upload_file']));
					$eraserLabel = $this->Form->label($fieldName.'_label', $uploadFile['file_name'].'&nbsp;', array('id'=>$domId.'Label', 'style'=>'float:left;'));
				} else if (!empty($this->data[$modelName][$dataFieldName.'_present_file'])) {
					$presentFile = unserialize(base64_decode($this->data[$modelName][$dataFieldName.'_present_file']));
					$eraserLabel = $this->Form->label($fieldName.'_label', $presentFile['file_name'].'&nbsp;', array('id'=>$domId.'Label', 'style'=>'float:left;'));
				}

				if (empty($options['style'])) {
					$options['style'] = '';
				} else if (preg_match('/; +$/', $options['style']) === 0) {
					$options['style'] .= ';';
				}
				$options['style'] .= 'display:none;';
			}

			$after .= $this->Html->scriptBlock($scripts);
			$after .= $this->Form->input($fieldName.'_delete', array('type'=>'hidden'));
			$after .= $this->Form->input($fieldName.'_upload_file', array('type'=>'hidden'));
			if (!empty($eraserLabel)) {
				$after .= $eraserLabel;
			}
			$after .= $this->Form->button($deleteLabel, array('type'=>'button', 'id'=>$domId.'Erase', 'style'=>$eraserStyle));
		}

		$options['div'] = false;

		$ret .= $this->Form->input($fieldName, $options);
		$ret .= $this->Form->input($fieldName.'_present_file', array('type'=>'hidden'));
		$ret .= $after;

		$thumbnail = '';
		if (empty($this->data[$modelName][$dataFieldName.'_delete'])) {
			if (!empty($presentFile)) {
				$thumbnail = $this->image($presentFile, $image);
			} else if (!empty($uploadFile)) {
				$thumbnail = $this->image($uploadFile, $image);
			}
		}
		$thumbnail = $this->Html->div(null, $thumbnail, array('id'=>$domId.'Thumbnail', 'style'=>'margin:0;padding:0;'));

		$ret .= $thumbnail;

		$ret = $this->Html->div('input file', $ret);

		return $ret;
	}

	/**
	 * image
	 * @param $file
	 * @param $params
	 */
	function image($file, $params = array()) {
		// URL取得
		$url = $this->url($file, $params, $options);
		if (empty($url)) {
			return;
		}

		// 画像出力
		$image = $this->Html->image($url, $options);
		return $image;
	}

	/**
	 * url
	 * @param $file
	 * @param $params
	 * @param $options
	 */
	function url($file, $params = array(), &$options = null) {
		if (empty($file)) {
			return;
		}

		$params = Set::merge(
			array(
				'width'=>0,
				'height'=>0,
				'expanse'=>false),
			$params);

		// 削除設定の場合は表示しない
		if (!empty($file['is_delete'])) {
			return;
		}

		// モデルのインスタンス生成
		if (empty($this->picturebleModels[$file['model_name']])) {
			$this->picturebleModels[$file['model_name']] =& ClassRegistry::init($file['model_name']);
		}

		// 縦横幅の設定
		if ($params['expanse'] === false) {
			if ($file['file_width'] < $params['width']) {
				$params['width'] = 0;
			}
			if ($file['file_height'] < $params['height']) {
				$params['height'] = 0;
			}
		}

		// 縦横幅の再設定
		$options = array();
		if (!empty($params['width']) && !empty($params['height'])) {
			if (($params['width'] / $file['file_width']) <= ($params['height'] / $file['file_height'])) {
				$options['width'] = $params['width'];
			} else {
				$options['height'] = $params['height'];
			}
		} else if (!empty($params['width'])) {
			$options['width'] = $params['width'];
		} else if (!empty($params['height'])) {
			$options['height'] = $params['height'];
		}

		if (isset($file['tmp_file_name'])) {
			// 登録前ファイル出力時
			// URL出力
			$url = 'http://'.$this->picturebleModels[$file['model_name']]->pictSettings('strageHost').'/'.$this->picturebleModels[$file['model_name']]->pictSettings('storePath').'/tmp/'.$file['tmp_file_name'];
			return $url;
		} else {
			// 登録後ファイル出力時
			$storePath = $this->picturebleModels[$file['model_name']]->pictSettings('storePath');
			$fileStorePath = $storePath.DS.$file['model_name'].DS.$file['model_id'].DS.$file['field_name'];

			$fileName = '';
			$ext = preg_replace('/^.*\./', '', $file['file_name']);

			if (empty($options['width']) && empty($options['height'])) {
				$fileName = 'org.'.$ext;
			} else {
				$width = empty($options['width']) ? 0 : $options['width'];
				$height = empty($options['height']) ? 0 : $options['height'];

				$cacheKey = 'pictureble|'.$fileStorePath;
				$cacheValue = '|'.$width.'-'.$height.'|';
				$cacheValues = Cache::read($cacheKey);
				if (strpos($cacheValues, $cacheValue) === false) {
					$url = 'http://'.$this->picturebleModels[$file['model_name']]->pictSettings('strageHost').$this->picturebleModels[$file['model_name']]->pictSettings('ringApi').'?mode=touch';
					$content = array(
						'storePath'=>$fileStorePath,
						'ext'=>$ext,
						'width'=>$width,
						'height'=>$height);
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
						return;
					}

					$cacheValues .= $cacheValue;
					Cache::write($cacheKey, $cacheValues);
				}

				$fileName = $width.'_'.$height.'.'.$ext;
			}

			// URL出力
			$url = 'http://'.$this->picturebleModels[$file['model_name']]->pictSettings('strageHost').'/'.$fileStorePath.'/'.$fileName;
			return $url;
		}
	}

}