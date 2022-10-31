<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "shelf_log".
 *
 * @property int $id
 * @property int $shelf_id
 * @property string $action
 * @property string|null $details
 * @property string $timestamp
 * @property int $user_id
 *
 * @property Shelf $shelf
 * @property User $user
 */
class ShelfLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shelf_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shelf_id', 'action', 'user_id'], 'required'],
            [['shelf_id', 'user_id'], 'integer'],
            [['details'], 'string'],
            [['timestamp'], 'safe'],
            [['action'], 'string', 'max' => 63],
            [['shelf_id'], 'exist', 'skipOnError' => true, 'targetClass' => Shelf::class, 'targetAttribute' => ['shelf_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shelf_id' => 'Shelf ID',
            'action' => 'Action',
            'details' => 'Details',
            'timestamp' => 'Timestamp',
            'user_id' => 'User ID',
        ];
    }

    /**
     * Gets query for [[Shelf]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShelf()
    {
        return $this->hasOne(Shelf::class, ['id' => 'shelf_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
