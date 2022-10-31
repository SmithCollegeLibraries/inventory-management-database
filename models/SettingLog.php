<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "setting_log".
 *
 * @property int $id
 * @property int $setting_id
 * @property string $value
 * @property string $timestamp
 * @property int $user_id
 *
 * @property Setting $setting
 * @property User $user
 */
class SettingLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'setting_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['setting_id', 'value', 'user_id'], 'required'],
            [['setting_id', 'user_id'], 'integer'],
            [['timestamp'], 'safe'],
            [['value'], 'string', 'max' => 255],
            [['setting_id'], 'exist', 'skipOnError' => true, 'targetClass' => Setting::class, 'targetAttribute' => ['setting_id' => 'id']],
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
            'setting_id' => 'Setting ID',
            'value' => 'Value',
            'timestamp' => 'Timestamp',
            'user_id' => 'User ID',
        ];
    }

    /**
     * Gets query for [[Setting]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSetting()
    {
        return $this->hasOne(Setting::class, ['id' => 'setting_id']);
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
