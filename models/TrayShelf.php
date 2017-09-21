<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tray_shelf".
 *
 * @property integer $id
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
 */
class TrayShelf extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tray_shelf';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['boxbarcode', 'shelf', 'added', 'timestamp'], 'required'],
            [['boxbarcode', 'shelf'], 'string', 'max' => 255],
            [['row', 'ladder', 'shelf_number'], 'string', 'max' => 2],
            [['side'], 'string', 'max' => 1],
            [['shelf_depth'], 'string', 'max' => 10],
            [['shelf_position'], 'string', 'max' => 3],
            [['initials'], 'string', 'max' => 10],
            [['added'], 'string', 'max' => 100],
            [['timestamp'], 'string', 'max' => 30],
            [['boxbarcode'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'boxbarcode' => 'Boxbarcode',
            'shelf' => 'Shelf',
            'row' => 'Row',
            'side' => 'Side',
            'ladder' => 'Ladder',
            'shelf_number' => 'Shelf Number',
            'shelf_depth' => 'Shelf Depth',
            'shelf_position' => 'Shelf Position',
            'initials' => 'Initials',
            'added' => 'Added',
            'timestamp' => 'Timestamp',
        ];
    }
}
