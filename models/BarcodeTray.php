<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "barcode_tray".
 *
 * @property integer $id
 * @property string $boxbarcode
 * @property string $barcode
 * @property string $stream
 * @property string $initials
 * @property string $added
 * @property string $timestamp
 */
class BarcodeTray extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'barcode_tray';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['boxbarcode', 'barcode', 'stream', 'initials', 'status', 'added', 'timestamp'], 'required'],
            [['boxbarcode'], 'string', 'max' => 255],
            [['barcode'], 'string', 'max' => 20],
            [['stream'], 'string', 'max' => 25],
            [['initials'], 'string', 'max' => 10],
            [['status'], 'string', 'max' => 25],
            [['added'], 'string', 'max' => 100],
            [['timestamp'], 'string', 'max' => 30],
            [['barcode'], 'unique'],
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
            'barcode' => 'Barcode',
            'stream' => 'Stream',
            'initials' => 'Initials',
            'status' => 'Status',
            'added' => 'Added',
            'timestamp' => 'Timestamp',
        ];
    }
}
