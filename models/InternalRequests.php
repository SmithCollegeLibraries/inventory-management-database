<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "internal_requests".
 *
 * @property integer $id
 * @property string $name
 * @property string $material
 * @property string $barcode
 * @property string $title
 * @property string $call_number
 * @property string $volume_year
 * @property string $full_run
 * @property string $notes
 * @property string $collection
 * @property string $tray
 * @property string $shelf
 * @property string $completed
 * @property string $timestamp
 */
class InternalRequests extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'internal_requests';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['notes'], 'string'],
            [['name', 'material', 'call_number', 'collection', 'timestamp'], 'string', 'max' => 100],
            [['barcode'], 'string', 'max' => 50],
            [['title'], 'string', 'max' => 255],
            [['volume_year'], 'string', 'max' => 25],
            [['full_run', 'completed'], 'string', 'max' => 10],
            [['tray', 'shelf'], 'string', 'max' => 30],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'material' => 'Material',
            'barcode' => 'Barcode',
            'title' => 'Title',
            'call_number' => 'Call Number',
            'volume_year' => 'Volume Year',
            'full_run' => 'Full Run',
            'notes' => 'Notes',
            'collection' => 'Collection',
            'tray' => 'Tray',
            'shelf' => 'Shelf',
            'completed' => 'Completed',
            'timestamp' => 'Timestamp',
        ];
    }
    
   public function getInternalRequestsComments(){
	    return $this->hasMany(InternalRequestsComments::className(), ['request_id' => 'id']);
    }
    
}
