<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "OldBarcodeTray".
 *
 * @property int $id
 * @property string $boxbarcode
 * @property string $barcode
 * @property string $stream
 * @property string $initials
 * @property string $status
 * @property string $added
 * @property string $timestamp
 *
 * @property OldTrayShelf[] $oldTrayShelf
 */
class OldBarcodeTray extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'old_barcode_tray';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['boxbarcode'], 'string', 'max' => 8],
            [['barcode'], 'string', 'max' => 15],
            [['stream'], 'string'],
            [['initials'], 'string', 'max' => 5],
            [['status'], 'string', 'max' => 25],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'boxbarcode',
            'barcode',
            'stream',
            'initials',
            'status',
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
            'id' => 'Tray ID',
            'boxbarcode' => 'Tray barcode',
            'barcode' => 'Item barcode',
            'stream' => 'Collection',
            'initials' => 'Initials',
            'status' => 'Status',
            'added' => 'Created',
            'timestamp' => 'Updated',
        ];
    }

    /**
     * Gets query for [[OldTrayShelf]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOldTrayShelf()
    {
        return $this->hasOne(OldTrayShelf::class, ['boxbarcode' => 'boxbarcode']);
    }
}
