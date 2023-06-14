<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "Picklist".
 *
 * @property int $id
 * @property int $item_id
 * @property int $user_id
 * @property string $timestamp
 *
 * @property Item[] $item
 * @property User $user
 */
class Picklist extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'picklist';
    }

    public function fields()
    {
        return [
            'id',
            'item_id',
            'barcode' => function () {
                $item = $this->item;
                return $item ? $item->barcode : null;
            },
            'title',
            'volume',
            'user_id',
            'assignee' => function () {
                $user = $this->user;
                return $user ? $user->name : null;
            },
            'shelf' => function () {
                $item = $this->item;
                return $item && $item->tray && $item->tray->shelf ? $item->tray->shelf->barcode : null;
            },
            'row' => function () {
                $item = $this->item;
                return $item && $item->tray && $item->tray->shelf ? $item->tray->shelf->row . $item->tray->shelf->side : null;
            },
            'ladder' => function () {
                $item = $this->item;
                return $item && $item->tray && $item->tray->shelf ? $item->tray->shelf->ladder : null;
            },
            'rung' => function () {
                $item = $this->item;
                return $item && $item->tray && $item->tray->shelf ? $item->tray->shelf->rung : null;
            },
            'tray' => function () {
                $item = $this->item;
                return $item && $item->tray ? $item->tray->barcode : null;
            },
            'depth' => function () {
                $item = $this->item;
                return $item && $item->tray ? $item->tray->depth : null;
            },
            'position' => function () {
                $item = $this->item;
                return $item && $item->tray ? $item->tray->position : null;
            },
            'timestamp',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'Picklist ID',
            'item_id' => 'Item ID',
            'barcode' => 'Barcode',
            'title' => 'Title',
            'volume' => 'Volume',
            'user_id' => 'User ID',
            'timestamp' => 'Timestamp',
        ];
    }

    /**
     * Gets query for [[Item]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItem()
    {
        return $this->hasOne(Item::class, ['id' => 'item_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
