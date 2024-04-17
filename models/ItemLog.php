<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "item_log".
 *
 * @property int $id
 * @property int $item_id
 * @property string $action
 * @property string|null $details
 * @property string $timestamp
 * @property int $user_id
 *
 * @property Item $item
 * @property User $user
 */
class ItemLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'item_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['item_id', 'action', 'user_id'], 'required'],
            [['item_id', 'user_id'], 'integer'],
            [['details'], 'string'],
            [['timestamp'], 'safe'],
            [['action'], 'string', 'max' => 63],
            [['item_id'], 'exist', 'skipOnError' => true, 'targetClass' => Item::class, 'targetAttribute' => ['item_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'barcode' => function ($itemLog) {
                $item = 'app\models\Item'::find()->where(['id' => $itemLog["item_id"]])->one();
                if ($item) {
                    return $item->barcode;
                }
                else {
                    return null;
                }
            },
            'action',
            'details',
            'user' => function ($itemLog) {
                $user = 'app\models\User'::find()->where(['id' => $itemLog["user_id"]])->one();
                if ($user) {
                    return $user->name;
                }
                else {
                    return null;
                }
            },
            'timestamp',
            'currentActive' => function ($itemLog) {
                $item = 'app\models\Item'::find()->where(['id' => $itemLog["item_id"]])->one();
                if ($item) {
                    return $item->active;
                }
                else {
                    return null;
                }
            },
            'currentFlag' => function ($itemLog) {
                $item = 'app\models\Item'::find()->where(['id' => $itemLog["item_id"]])->one();
                if ($item) {
                    return $item->flag;
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
            'item_id' => 'Item ID',
            'action' => 'Action',
            'details' => 'Details',
            'timestamp' => 'Timestamp',
            'user_id' => 'User ID',
        ];
    }

    /**
     * Gets query for [[Item]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItem()
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
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
