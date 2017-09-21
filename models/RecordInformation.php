<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "record_information".
 *
 * @property integer $id
 * @property string $barcode
 * @property string $title
 * @property string $callnumber
 * @property string $call_number_normalized
 * @property string $isbn
 * @property string $issn
 * @property string $img
 * @property string $status
 * @property string $check_out_time
 * @property string $return_time
 */
class RecordInformation extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'record_information';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['barcode', 'title', 'callnumber', 'call_number_normalized', 'img', 'check_out_time', 'return_time'], 'required'],
            [['img'], 'string'],
            [['barcode', 'title', 'callnumber', 'call_number_normalized', 'check_out_time', 'return_time'], 'string', 'max' => 255],
            [['isbn', 'issn'], 'string', 'max' => 25],
            [['status'], 'string', 'max' => 100],
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
            'barcode' => 'Barcode',
            'title' => 'Title',
            'callnumber' => 'Callnumber',
            'call_number_normalized' => 'Call Number Normalized',
            'isbn' => 'Isbn',
            'issn' => 'Issn',
            'img' => 'Img',
            'status' => 'Status',
            'check_out_time' => 'Check Out Time',
            'return_time' => 'Return Time',
        ];
    }
}
