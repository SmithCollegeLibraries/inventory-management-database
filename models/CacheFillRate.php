<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "cache_fill_rate".
 *
 * @property int $id
 * @property int $year
 * @property int $month
 * @property int $collection_id
 * @property int $size_id
 * @property int $item_count
 * @property int $tray_count
 * @property int $shelf_count
 * @property string $timestamp
 *
 */
class CacheFillRate extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'cache_fill_rate';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['year', 'month'], 'required'],
            [['year', 'month', 'collection_id', 'size_id', 'item_count', 'tray_count', 'shelf_count'], 'integer'],
            [['timestamp'], 'safe'],
            [['year', 'month', 'collection_id', 'size_id'], 'unique', 'targetAttribute' => ['year', 'month', 'collection_id', 'size_id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'year',
            'month',
            'collection_id',
            'size_id',
            'collection_code' => function ($cacheFillRate) {
            $collection = 'app\models\Collection'::find()->where(['id' => $cacheFillRate["collection_id"]])->one();
            if ($collection) {
                return $collection->code;
            } else {
                return null;
            }
            },
            'size_code' => function ($cacheFillRate) {
            $size = 'app\models\Size'::find()->where(['id' => $cacheFillRate["size_id"]])->one();
            if ($size) {
                return $size->code;
            } else {
                return null;
            }
            },
            'item_count',
            'tray_count',
            'shelf_count',
            'timestamp',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'year' => 'Year',
            'month' => 'Month',
            'collection_id' => 'Collection ID',
            'size_id' => 'Size ID',
            'item_count' => 'Item count',
            'tray_count' => 'Tray count',
            'shelf_count' => 'Shelf count',
            'timestamp' => 'Counts last updated',
        ];
    }
}
