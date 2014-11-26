<?php

/**
 * Class Dialog
 *
 * @property int $id
 * @property int $user_id
 * @property int $dialog_id
 * @property string $message
 * @property bool $new
 * @property int $lastmod
 * @property int $created
 *
 * @property User[] $members
 * @property User $sender
 * @property Dialog $lastMessage
 */
class Dialog extends RActiveRecord
{

	/**
	 * @param string $className
	 * @return Dialog
	 */
	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'dialog';
	}

	public function relations()
	{
		return array(
			'dialog' => array(self::BELONGS_TO, 'Dialog', 'dialog_id'),
			'sender' => array(self::BELONGS_TO, 'User', 'user_id'),
			'members' => array(self::MANY_MANY, 'User', 'dialog_user(dialog_id, user_id)', 'together' => false),
			'lastMessage' => array(self::HAS_ONE, 'Dialog', 'dialog_id', 'scopes' => 'last', 'together' => false),
			'files' => array(self::MANY_MANY, 'UserFiles', 'dialog_user_files(dialog_id, user_files_id)', 'together' => false),
		);
	}

	public function last()
	{
		$criteria = $this->dbCriteria;
		$criteria->join .= " LEFT OUTER JOIN dialog lastMessage2 ON (t.id = lastMessage2.dialog_id AND lastMessage.id < lastMessage2.id)";
		return $this;
	}

	public function lastCondition()
	{
		$this->dbCriteria->addCondition('lastMessage2.id IS NULL');
		return $this;
	}

	public function messageList($dialogId)
	{
		$criteria = $this->dbCriteria;
		$criteria->compare("t.dialog_id", $dialogId);
		$criteria->with[] = 'sender';
		$criteria->order = "t.id ASC";
		return $this;
	}

	/**
	 * @param $userId
	 * @return Dialog
	 */
	public function userList($userId)
	{
		$t = $this->getTableAlias(false, false);
		$criteria = $this->lastCondition()->dbCriteria;
		$criteria->with['lastMessage'] = array('together' => false);
		$criteria->with['members'] = array('together' => false);
		$criteria->join .= " INNER JOIN `dialog_user` `u` ON (`u`.`dialog_id` = `{$t}`.`id` AND `u`.`user_id` = :userId)";
		$criteria->params[':userId'] = $userId;
		$criteria->order = "{$t}.lastmod DESC";
		return $this;
	}

	public function messages() {
		$t = $this->getTableAlias(false, false);
		$criteria = $this->dbCriteria;
		$criteria->addCondition("{$t}.dialog_id != 0");
		return $this;
	}

	public function after($timestamp) {
		$t = $this->getTableAlias(false, false);
		$criteria = $this->dbCriteria;
		$criteria->addCondition("{$t}.created >= FROM_UNIXTIME(:timestamp)");
		$criteria->params[':timestamp'] = $timestamp;
		$criteria->order = "{$t}.created ASC";
		return $this;
	}

	public function user($userId) {
		$t = $this->getTableAlias(false, false);
		$criteria = $this->dbCriteria;
		$criteria->join .= " JOIN dialog_user du ON (du.dialog_id = {$t}.dialog_id AND du.user_id = :userId)";
		$criteria->params[':userId'] = $userId;
		return $this;
	}

	public function senderNot($userId) {
		$t = $this->getTableAlias(false, false);
		$criteria = $this->dbCriteria;
		$criteria->addCondition("{$t}.user_id != :senderId");
		$criteria->params[':senderId'] = $userId;
		return $this;
	}

	public function hasMember($userId)
	{
		return $this->dbConnection->createCommand("SELECT user_id FROM dialog_user WHERE user_id = :userId AND dialog_id = :dialogId")->queryScalar(array(':userId' => $userId, ':dialogId' => $this->id)) !== false;
	}

	public function getIsDialog()
	{
		return $this->dialog_id == 0;
	}

	public function getIsMessage()
	{
		return !$this->getIsDialog();
	}

	public function getIsNew()
	{
		return $this->new == 1;
	}

	public static function newDialog($subject, $memberIds = array())
	{
		$dialog = new Dialog('dialog');
		$dialog->dialog_id = 0;
		$dialog->user_id = 0;
		$dialog->new = 0;
		$dialog->message = $subject;
		$dialog->save();
		$dialog->addMembers($memberIds);
		return $dialog;
	}

	public static function invalidateNewMessages($messageIds, $userId) {
		// Инвалидируем сообщения только если отправитель не является текущим пользователем
		$ids = implode(',', $messageIds);
		return Dialog::model()->updateAll(array('new' => 0, 'created' => new CDbExpression('created'), 'lastmod' => new CDbExpression('NOW()')), array('condition' => "id IN ({$ids}) AND user_id != :userId", 'params' => array(':userId' => $userId)));
	}

	public static function invalidateNewDialog($dialogId, $userId) {
		// Инвалидируем сообщения только если отправитель не является текущим пользователем
		return Dialog::model()->updateAll(array('new' => 0, 'created' => new CDbExpression('created'), 'lastmod' => new CDbExpression('NOW()')), array('condition' => 'dialog_id = :dialogId AND user_id != :userId', 'params' => array(':dialogId' => $dialogId, ':userId' => $userId)));
	}

	/**
	 * @param int|array $memberIds
	 */
	public function addMembers($memberIds)
	{
		if (!is_array($memberIds))
			$memberIds = (array)$memberIds;
		if (count($memberIds)) {
			$insertData = array();
			foreach ($memberIds as $memberId) {
				$insertData[] = array('dialog_id' => $this->id, 'user_id' => $memberId);
			}
			$this->commandBuilder->createMultipleInsertCommand('dialog_user', $insertData)->execute();
		}
	}

	/**
	 * Вызывается у диалога
	 * @param int $senderId
	 * @param string $messageText
	 * @return Dialog
	 * @throws CException
	 */
	public function newMessage($senderId, $messageText)
	{
		if (!$this->getIsDialog()) {
			throw new CException("Нельзя создавать сообщения (Dialog::newMessage()) когда модель не является диалогом");
		}
		$message = new Dialog('message');
		$message->new = 1;
		$message->message = $messageText;
		$message->user_id = $senderId;
		$message->dialog_id = $this->id;
		$message->save();
		$this->lastmod = new CDbExpression('NOW()');
		$this->save(false, array('lastmod'));
		return $message;
	}

	public static function getMembersSql()
	{
		return "SELECT members.dialog_id, GROUP_CONCAT(members.user_id ORDER BY members.user_id ASC) members FROM dialog_user members GROUP BY members.dialog_id";
	}

	public static function getDialogsByMembers($members)
	{
		sort($members);
		$members = array_unique($members);
		$criteria = new CDbCriteria();
		$criteria->join = "JOIN (" . self::getMembersSql() . ") members ON (members.dialog_id = t.id)";
		$criteria->addCondition('members.members = :members');
		$criteria->params[':members'] = implode(',', $members);
		return Dialog::model()->resetScope()->findAll($criteria);
	}

	public function getFormattedDate() {
		return Text::relativeTime(is_object($this->created) ? time() : (int)$this->created, 1);
	}

	public static function getUnreadDialogsCount($userId) {
		$db = Yii::app()->db;
		$result = $db->createCommand("SELECT COUNT(DISTINCT d.id) FROM dialog d JOIN dialog m ON m.dialog_id = d.id AND m.user_id != :userId WHERE d.dialog_id = 0 AND m.new = 1")
			->queryScalar(array(
				':userId' => $userId,
			));
		return (int)$result;
	}

}