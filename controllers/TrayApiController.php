<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

class TrayApiController extends ActiveController
{
    public $modelClass = 'app\models\Tray';
    public $itemClass = 'app\models\Item';

    public function init()
    {
        parent::init();
        \Yii::$app->user->enableSession = false;
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => QueryParamAuth::class,
        ];
        return $behaviors;
    }

    public function actionNewTray()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // We expect barcodes in the form of an array, but if it's not, we'll make it one
        if (!is_array($data["barcodes"])) {
            $barcodes = explode(PHP_EOL, $data['barcodes']);
        } else {
            $barcodes = $data["barcodes"];
        }

        $trayBarcode = $data['tray'];
        $collectionName = $data['collection'];

        // Get collection ID, while making sure that a collection of that name exists
        try {
            $collectionId = \app\models\Collection::find()->where(['name' => $collectionName])->one()->id;
        } catch (\Exception $e) {
            throw new \yii\web\HttpException(500, sprintf('Collection %s does not exist', $collectionName));
        }

        // If tray already exists, return error
        if (\app\models\Tray::find()->where(['barcode' => $trayBarcode])->all() != []) {
            throw new \yii\web\HttpException(500, sprintf('Tray %s already exists', $trayBarcode));
        }

        // If any of the items already exist, return error
        foreach ($barcodes as $barcode) {
            if (\app\models\Item::find()->where(['barcode' => $barcode])->all() != []) {
                throw new \yii\web\HttpException(500, sprintf('Item %s already exists', $barcode));
            }
        }

        // Create new tray
        $tray = new $this->modelClass;
        $tray->barcode = $trayBarcode;
        $tray->save();

        // Create new items and add them to tray
        foreach ($barcodes as $barcode) {
            $item = new $this->itemClass;
            $item->tray_id = $tray->id;
            $item->barcode = $barcode;
            $item->collection_id = $collectionId;
            $item->save();
        }

        return $barcodes;
    }

}
