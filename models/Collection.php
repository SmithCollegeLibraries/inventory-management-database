<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "collection".
 *
 * @property int $id
 * @property string $name
 * @property int $active
 *
 * @property CollectionLog[] $collectionLogs
 * @property Item[] $items
 */
class Collection extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'collection';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['active'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['name'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'active' => 'Active',
        ];
    }

    /**
     * Gets query for [[CollectionLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCollectionLogs()
    {
        return $this->hasMany(CollectionLog::class, ['collection_id' => 'id']);
    }

    /**
     * Gets query for [[Items]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItems()
    {
        return $this->hasMany(Item::class, ['collection_id' => 'id']);
    }
}
