<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "OldTrayShelf".
 *
 * @property int $id
 * @property string $boxbarcode
 * @property string $shelf
 * @property string $row
 * @property string $side
 * @property string $ladder
 * @property string $shelf_number
 * @property string $shelf_depth
 * @property string $shelf_position
 * @property string $initials
 * @property string $added
 * @property string $timestamp
 *
 * @property OldBarcodeTrays[] $oldBarcodeTrays
 */
class OldTrayShelf extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'old_tray_shelf';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['boxbarcode'], 'string', 'max' => 8],
            [['shelf'], 'string', 'max' => 7],
            [['row'], 'string', 'max' => 2],
            [['side'], 'string', 'max' => 1],
            [['ladder'], 'string', 'max' => 2],
            [['shelf_number'], 'string', 'max' => 2],
            [['shelf_depth'], 'string', 'max' => 10],
            [['shelf_position'], 'string', 'max' => 3],
            [['initials'], 'string', 'max' => 5],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'boxbarcode',
            'shelf',
            'row',
            'side',
            'ladder',
            'shelf_number',
            'shelf_depth',
            'shelf_position',
            'initials',
            'added',
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
            'boxbarcode' => 'Tray barcode',
            'shelf' => 'Shelf barcode',
            'row' => 'Row',
            'side' => 'Side',
            'ladder' => 'Ladder',
            'shelf_number' => 'Rung',
            'shelf_depth' => 'Depth',
            'shelf_position' => 'Position',
            'initials' => 'Initials',
            'added' => 'Created',
            'timestamp' => 'Updated',
        ];
    }

    /**
     * Gets query for [[OldBarcodeTray]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOldBarcodeTrays()
    {
        return $this->hasMany(OldBarcodeTray::class, ['boxbarcode' => 'boxbarcode']);
    }
}
