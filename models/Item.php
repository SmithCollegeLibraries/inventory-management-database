<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "item".
 *
 * @property int $id
 * @property string $barcode
 * @property string $status
 * @property int|null $tray_id
 * @property int $collection_id
 * @property int $active
 * @property int $flag
 *
 * @property Collection $collection
 * @property ItemLog[] $itemLogs
 * @property Picklist[] $itemLogs
 * @property Tray $tray
 */
class Item extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'item';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['barcode', 'collection_id'], 'required'],
            [['tray_id', 'collection_id', 'active', 'flag'], 'integer'],
            [['barcode'], 'string', 'max' => 20],
            [['status'], 'string', 'max' => 25],
            [['barcode'], 'unique'],
            [['collection_id'], 'exist', 'skipOnError' => true, 'targetClass' => Collection::class, 'targetAttribute' => ['collection_id' => 'id']],
            [['tray_id'], 'exist', 'skipOnError' => true, 'targetClass' => Tray::class, 'targetAttribute' => ['tray_id' => 'id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'barcode',
            'status',
            'tray' => function ($item) {
                $tray = 'app\models\Tray'::find()->where(['id' => $item["tray_id"]])->one();
                if ($tray) {
                    return [
                        "barcode" => $tray->barcode,
                        "shelf" => $tray->shelf ? $tray->shelf->barcode : null,
                        "depth" => $tray->depth,
                        "position" => $tray->position,
                    ];
                }
                return $tray;
            },
            'collection' => function ($item) {
                $collection = 'app\models\Collection'::find()->where(['id' => $item["collection_id"]])->one();
                if ($collection) {
                    return $collection->name;
                }
                return $collection;
            },
            'active',
            'flag',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'barcode' => 'Barcode',
            'status' => 'Status',
            'tray_id' => 'Tray ID',
            'collection_id' => 'Collection ID',
            'active' => 'Active',
            'flag' => 'Flag',
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
     * Gets query for [[ItemLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItemLogs()
    {
        return $this->hasMany(ItemLog::class, ['item_id' => 'id']);
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
     * Gets query for [[Picklist]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPicklist()
    {
        return $this->hasOne(Picklist::class, ['item_id' => 'id']);
    }
}
