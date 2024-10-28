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
            [['average_count'], 'integer'],
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
            'average_count' => 'Average count',
        ];
    }
}
