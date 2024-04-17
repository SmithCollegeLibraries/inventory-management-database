<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "collection_log".
 *
 * @property int $id
 * @property int $collection_id
 * @property string $action
 * @property string $timestamp
 * @property int $user_id
 *
 * @property Collection $collection
 * @property User $user
 */
class CollectionLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'collection_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['collection_id', 'action', 'user_id'], 'required'],
            [['collection_id', 'user_id'], 'integer'],
            [['timestamp'], 'safe'],
            [['action'], 'string', 'max' => 63],
            [['collection_id'], 'exist', 'skipOnError' => true, 'targetClass' => Collection::class, 'targetAttribute' => ['collection_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'name' => function ($collectionLog) {
                $collection = 'app\models\Collection'::find()->where(['id' => $collectionLog["collection_id"]])->one();
                if ($collection) {
                    return $collection->name;
                }
                else {
                    return null;
                }
            },
            'action',
            'details',
            'user' => function ($collectionLog) {
                $user = 'app\models\User'::find()->where(['id' => $collectionLog["user_id"]])->one();
                if ($user) {
                    return $user->name;
                }
                else {
                    return null;
                }
            },
            'timestamp',
            'currentActive' => function ($collectionLog) {
                $collection = 'app\models\Collection'::find()->where(['id' => $collectionLog["collection_id"]])->one();
                if ($collection) {
                    return $collection->active;
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
            'collection_id' => 'Collection ID',
            'action' => 'Action',
            'timestamp' => 'Timestamp',
            'user_id' => 'User ID',
        ];
    }

    /**
     * Gets query for [[Collection]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCollection()
    {
        return $this->hasOne(Collection::class, ['id' => 'collection_id']);
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
