<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "history".
 *
 * @property int $id
 * @property string $action
 * @property string $item
 * @property string $status_change
 * @property string $timestamp
 */
class History extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'history';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['action', 'item', 'status_change', 'timestamp'], 'required'],
            [['action'], 'string', 'max' => 200],
            [['item'], 'string', 'max' => 150],
            [['status_change'], 'string', 'max' => 100],
            [['timestamp'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'action' => 'Action',
            'item' => 'Item',
            'status_change' => 'Status Change',
            'timestamp' => 'Timestamp',
        ];
    }
}
