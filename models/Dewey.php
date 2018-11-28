<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "dewey".
 *
 * @property integer $id
 * @property string $shelf
 * @property string $call_number_begin
 * @property string $call_number_end
 * @property string $collection
 */
class Dewey extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'dewey';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['shelf', 'call_number_begin', 'call_number_end', 'collection'], 'required'],
            [['shelf', 'collection'], 'string', 'max' => 25],
            [['call_number_begin', 'call_number_end'], 'string', 'max' => 100],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shelf' => 'Shelf',
            'call_number_begin' => 'Call Number Begin',
            'call_number_end' => 'Call Number End',
            'collection' => 'Collection',
        ];
    }
}
