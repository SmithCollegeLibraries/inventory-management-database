<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "size".
 *
 * @property int $id
 * @property string $code
 * @property string $height
 * @property string $width
 * @property int $depths
 * @property string $average_count
 */
class Size extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'size';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code'], 'required'],
            [['height', 'width'], 'number'],
            [['average_count', 'depths'], 'integer'],
            [['code'], 'string', 'max' => 8],
            [['code'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'code' => 'Code',
            'height' => 'Height',
            'width' => 'Width',
            'depths' => 'Max depths',
            'average_count' => 'Average count',
        ];
    }
}
