<?php
/**
 * Created by PhpStorm.
 * User: Rugalev
 * Date: 31.10.2014
 * Time: 11:02
 */

class DialogReadAction extends CAction {

	public function run() {
		if(isset($_POST['messageIds'])) {
			$ids = json_decode($_POST['messageIds']);
			foreach($ids as &$id)
				$id = (int)$id;
			Dialog::invalidateNewMessages($ids, Yii::app()->user->id);
		}
	}

}