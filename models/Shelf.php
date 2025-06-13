<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "shelf".
 *
 * @property int $id
 * @property string $barcode
 * @property string|null $row
 * @property string|null $side
 * @property string|null $ladder
 * @property string|null $rung
 * @property int $active
 * @property int $flag
 * @property int|null $size_id
 * @property int|null $collection_id
 * @property int|null $capacity
 *
 * @property ShelfLog[] $shelfLogs
 * @property Tray[] $trays
 */
class Shelf extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shelf';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['barcode'], 'required'],
            [['active', 'flag', 'capacity', 'depths', 'positions'], 'integer'],
            [['barcode'], 'string', 'max' => 64],
            [['row', 'ladder', 'rung'], 'string', 'max' => 2],
            [['side'], 'string', 'max' => 1],
            [['barcode'], 'unique'],
            [['row', 'side', 'ladder', 'rung'], 'unique', 'targetAttribute' => ['row', 'side', 'ladder', 'rung']],
            [['size_id'], 'exist', 'skipOnError' => true, 'targetClass' => Size::class, 'targetAttribute' => ['size_id' => 'id']],
            [['collection_id'], 'exist', 'skipOnError' => true, 'targetClass' => Collection::class, 'targetAttribute' => ['collection_id' => 'id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'barcode',
            'row',
            'side',
            'ladder',
            'rung',
            'active',
            'flag',
            'size' => function ($shelf) {
                $size = 'app\models\Size'::find()->where(['id' => $shelf["size_id"]])->one();
                return $size ? $size->code : null;
            },
            'collection' => function ($shelf) {
                $collection = 'app\models\Collection'::find()->where(['id' => $shelf["collection_id"]])->one();
                return $collection ? $collection->name : null;
            },
            'trays' => function () {
                $trays = $this->getTrays()->where(["active" => true])->all();
                $trayArray = [];
                foreach ($trays as $tray) {
                    $trayArray[] = array(
                        "depth" => $tray->depth,
                        "position" => $tray->position,
                        "size" => 'app\models\Size'::find()->where(['id' => $tray->size_id])->one(),
                        "barcode" => $tray->barcode,
                        "trayer" => $tray->getTrayer(),
                        "items" => $tray->getItemBarcodes(),
                        "freeSpace" => $tray->getFreeSpace($tray),
                        "flag" => $tray->flag,
                    );
                }
                return $trayArray;
            },
            'capacity',
            'depths',
            'positions',
            // 'created',
            // 'updated',
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
            'row' => 'Row',
            'side' => 'Side',
            'ladder' => 'Ladder',
            'rung' => 'Rung',
            'active' => 'Active',
            'flag' => 'Flag',
            'size' => 'Size',
            'collection' => 'Collection',
            'capacity' => 'Tray capacity',
            'depths' => 'Max depths',
            'positions' => 'Max positions',
        ];
    }

    /**
     * Gets query for [[ShelfLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShelfLogs()
    {
        return $this->hasMany(ShelfLog::class, ['shelf_id' => 'id']);
    }

    /**
     * Gets query for [[Trays]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTrays()
    {
        return $this->hasMany(Tray::class, ['shelf_id' => 'id']);
    }
}
