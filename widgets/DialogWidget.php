<?php
/**
 * Created by PhpStorm.
 * User: Rugalev
 * Date: 31.10.2014
 * Time: 10:15
 */

class DialogWidget extends CWidget {

	public $options = array();

	public function init() {
		$this->registerAssets();
		parent::init();
	}

	public function run() {
		echo CHtml::tag('div', array('id' => $this->id, 'style' => 'display: none'), '');
	}

	public function registerAssets() {
		/** @var CAssetManager $assetManager */
		$assetManager = Yii::app()->assetManager;
		$baseUrl = $assetManager->publish(dirname(__DIR__) . '/assets', false, -1, YII_DEBUG);
		/** @var CClientScript $clientScript */
		$clientScript = Yii::app()->clientScript;
		$clientScript->registerScriptFile($baseUrl . '/dialog.js');
		$jsOptions = CJavaScript::encode($this->options);
		$clientScript->registerScript('dialogWidget', "$('#{$this->id}').imDialog({$jsOptions});");
	}

}