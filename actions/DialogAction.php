<?php
/**
 * Created by PhpStorm.
 * User: Rugalev
 * Date: 31.10.2014
 * Time: 9:39
 */

class DialogAction extends CAction {

	public $viewFile;
	public $sleepTime = 500000;

	public function run($last) {
//		if(Yii::app()->request->isAjaxRequest) {
			$this->ajaxAction($last);
			Yii::app()->end();
//		}
	}

	public function ajaxAction($last) {
		$maxTimeout = ini_get('max_execution_time') - 5;
		$startTime = time();
		do {
			$messages = Dialog::model()->messages()->user(Yii::app()->user->id)->senderNot(Yii::app()->user->id);
			if($last)
				$messages->after($startTime);
			$result = $messages->findAll();
			if(!count($result))
				usleep($this->sleepTime);
		} while(count($result) == 0 && (time() - $startTime) <= $maxTimeout);
		$this->response($result);
	}

	public function response($messages) {
		$data = array('messages' => array());
		$dialogs = array();
		foreach($messages as $message) {
			if(!isset($dialogs[$message->dialog_id]))
				$dialogs[$message->dialog_id] = $message->dialog;
			$data['messages'][(int)$message->dialog_id][(int)$message->id] = $this->controller->renderPartial($this->viewFile, array('data' => $message, 'dialog' => $dialogs[$message->dialog_id], 'index' => 1), true);
		}
		echo json_encode($data);
	}

}