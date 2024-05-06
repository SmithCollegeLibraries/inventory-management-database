<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "FolioValidation".
 *
 * @property int $id
 * @property string $barcode
 * @property string $item_in_folio
 * @property string $timestamp
 */
class FolioValidation extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'folio_validation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['barcode'], 'string', 'max' => 20],
            [['barcode'], 'unique'],
            [['timestamp'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'barcode' => 'Item barcode',
            'item_in_folio' => 'Item in FOLIO?',
            'timestamp' => 'Timestamp',
        ];
    }

    /**
     * Gets query for [[FolioValidation]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getFolioValidation()
    {
        return $this->hasOne(OldTrayShelf::class, ['barcode' => 'barcode']);
    }
}
