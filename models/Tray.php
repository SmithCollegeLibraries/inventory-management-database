<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tray".
 *
 * @property int $id
 * @property string $barcode
 * @property int|null $shelf_id
 * @property string|null $depth
 * @property string|null $position
 * @property int $active
 * @property int $flag
 *
 * @property Item[] $items
 * @property Shelf $shelf
 * @property TrayLog[] $trayLogs
 */
class Tray extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tray';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['barcode'], 'required'],
            [['shelf_id', 'active', 'flag'], 'integer'],
            [['barcode'], 'string', 'max' => 20],
            [['depth'], 'string', 'max' => 6],
            [['position'], 'integer', 'max' => 20],
            [['barcode'], 'unique'],
            [['shelf_id'], 'exist', 'skipOnError' => true, 'targetClass' => Shelf::class, 'targetAttribute' => ['shelf_id' => 'id']],
        ];
    }

    public function fields()
    {
        return [
            'id',
            'barcode',
            'depth',
            'position',
            'active',
            'flag',
            'shelf' => function ($tray) {
                $shelf = 'app\models\Shelf'::find()->where(['id' => $tray["shelf_id"]])->one();
                if ($shelf) {
                    return $shelf->barcode;
                }
                else {
                    return null;
                }
            },
            'items' => function ($tray) { return $this->getItemBarcodes(); },
            'trayer' => function ($tray) { return $this->getTrayer(); },
            'created',
            'updated',
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
            'shelf_id' => 'Shelf ID',
            'depth' => 'Depth',
            'position' => 'Position from left',
            'active' => 'Active',
            'flag' => 'Flag',
        ];
    }

    /**
     * Gets query for [[Items]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItems()
    {
        return $this->hasMany(Item::class, ['tray_id' => 'id'])->select(['id', 'barcode', 'status', 'collection_id', 'active', 'flag']);
    }

    /**
     * Gets query for [[ItemBarcodes]].
     *
     * @return array
     */
    public function getItemBarcodes()
    {
        $items = $this->hasMany(Item::class, ['tray_id' => 'id'])->select(['id', 'barcode', 'status', 'flag'])->where(["active" => true])->all();
        $itemArray = [];
        foreach ($items as $item) {
            $itemArray[] = array("id" => $item->id, "barcode" => $item->barcode, "status" => $item->status, "flag" => $item->flag);
        }
        return $itemArray;
    }

    /**
     * Gets query for [[Shelf]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShelf()
    {
        return $this->hasOne(Shelf::class, ['id' => 'shelf_id']);
    }

    /**
     * Gets query for [[TrayLogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTrayLogs()
    {
        return $this->hasMany(TrayLog::class, ['tray_id' => 'id']);
    }

    /**
     * Gets query for [[Trayer]].
     * 
     * @return string|null
     */

     public function getTrayer()
     {
         $trayLog = TrayLog::find()->where(['tray_id' => $this->id, 'action' => ["Added", "Restored"]])->orderBy(['id' => SORT_DESC])->one();
         if ($trayLog) {
             return $trayLog->user->name;
         }
         else {
             return null;
         }
     }
}
