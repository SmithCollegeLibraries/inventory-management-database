<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tray_log".
 *
 * @property int $id
 * @property int $tray_id
 * @property string $action
 * @property string|null $details
 * @property string $timestamp
 * @property int $user_id
 *
 * @property Tray $tray
 * @property User $user
 */
class TrayLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tray_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tray_id', 'action', 'user_id'], 'required'],
            [['tray_id', 'user_id'], 'integer'],
            [['details'], 'string'],
            [['timestamp'], 'safe'],
            [['action'], 'string', 'max' => 63],
            [['tray_id'], 'exist', 'skipOnError' => true, 'targetClass' => Tray::class, 'targetAttribute' => ['tray_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'tray_id',
            'action',
            'details',
            'timestamp',
            'user' => function ($tray_log) {
                return 'app\models\User'::find()->where(['id' => $tray_log["user_id"]])->one();
            },
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tray_id' => 'Tray ID',
            'action' => 'Action',
            'details' => 'Details',
            'timestamp' => 'Timestamp',
            'user_id' => 'User ID',
        ];
    }

    /**
     * Gets query for [[Tray]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTray()
    {
        return $this->hasOne(Tray::class, ['id' => 'tray_id']);
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
