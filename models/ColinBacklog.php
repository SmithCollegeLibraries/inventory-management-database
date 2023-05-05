<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "colin_backlog".
 *
 * @property string $barcode
 */
class ColinBacklog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'colin_backlog';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['barcode'], 'required'],
            [['barcode'], 'unique'],
            [['barcode'], 'string', 'max' => 15],
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
        ];
    }
}
