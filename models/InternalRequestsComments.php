<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "internal_requests_comments".
 *
 * @property integer $id
 * @property integer $request_id
 * @property string $name
 * @property string $comment
 */
class InternalRequestsComments extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'internal_requests_comments';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['request_id', 'name', 'comment'], 'required'],
            [['request_id'], 'integer'],
            [['comment'], 'string'],
            [['name'], 'string', 'max' => 100],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'request_id' => 'Request ID',
            'name' => 'Name',
            'comment' => 'Comment',
        ];
    }
    
     public function getInternalRequests(){
	    return $this->hasOne(InternalRequests::className(), ['id' => 'request_id']);
    }
   
}
