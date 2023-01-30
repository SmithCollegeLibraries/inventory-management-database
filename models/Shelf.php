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
            [['active', 'flag'], 'integer'],
            [['barcode'], 'string', 'max' => 20],
            [['row', 'ladder', 'rung'], 'string', 'max' => 2],
            [['side'], 'string', 'max' => 1],
            [['barcode'], 'unique'],
            [['row', 'side', 'ladder', 'rung'], 'unique', 'targetAttribute' => ['row', 'side', 'ladder', 'rung']],
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
            'trays' => function ($shelf) {
                $trays = $this->getTrays()->where(["active" => true])->all();
                $trayArray = [];
                foreach ($trays as $tray) {
                    $trayArray[] = $tray->barcode;
                }
                return $trayArray;
            },
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
