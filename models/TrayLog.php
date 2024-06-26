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
            'barcode' => function ($trayLog) {
                $tray = 'app\models\Tray'::find()->where(['id' => $trayLog["tray_id"]])->one();
                if ($tray) {
                    return $tray->barcode;
                }
                else {
                    return null;
                }
            },
            'action',
            'details',
            'user' => function ($trayLog) {
                $user = 'app\models\User'::find()->where(['id' => $trayLog["user_id"]])->one();
                if ($user) {
                    return $user->name;
                }
                else {
                    return null;
                }
            },
            'timestamp',
            'currentActive' => function ($trayLog) {
                $tray = 'app\models\Tray'::find()->where(['id' => $trayLog["tray_id"]])->one();
                if ($tray) {
                    return $tray->active;
                }
                else {
                    return null;
                }
            },
            'currentFlag' => function ($trayLog) {
                $tray = 'app\models\Tray'::find()->where(['id' => $trayLog["tray_id"]])->one();
                if ($tray) {
                    return $tray->flag;
                }
                else {
                    return null;
                }
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
