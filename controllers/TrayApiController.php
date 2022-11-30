<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\User;

class TrayApiController extends ActiveController
{
    public $modelClass = 'app\models\Tray';
    public $itemClass = 'app\models\Item';
    public $modelLogClass = 'app\models\TrayLog';
    public $itemLogClass = 'app\models\ItemLog';

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
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {

            // We expect barcodes in the form of an array, but if it's not, we'll make it one
            if (!is_array($data["items"])) {
                $barcodes = explode(PHP_EOL, $data['items']);
            } else {
                $barcodes = $data["items"];
            }

            $trayBarcode = $data['barcode'];
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
            // Log the new tray
            $trayLog = new $this->modelLogClass;
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Created';
            $trayLog->details = sprintf("Created tray %s", $tray->barcode);
            $trayLog->user_id = $tokenCheck['id'];
            $trayLog->save();

            // Create new items and add them to tray; add logs as well
            foreach ($barcodes as $barcode) {
                $item = new $this->itemClass;
                $item->tray_id = $tray->id;
                $item->barcode = $barcode;
                $item->collection_id = $collectionId;
                $item->save();

                $itemLog = new $this->itemLogClass;
                $itemLog->item_id = $item->id;
                $itemLog->action = 'Created';
                $itemLog->details = sprintf("Created item %s", $item->barcode);
                $itemLog->user_id = $tokenCheck['id'];
                $itemLog->save();

                $itemLog = new $this->itemLogClass;
                $itemLog->item_id = $item->id;
                $itemLog->action = 'Trayed';
                $itemLog->details = sprintf("Added to tray %s", $tray->barcode);
                $itemLog->user_id = $tokenCheck['id'];
                $itemLog->save();
            }

            return $barcodes;
        }

        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to add new trays');
        }
    }

    public function actionSearch()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $results = $this->modelClass::find()->where(['barcode' => $data["barcode"]])->all();
        return $results;
    }

}
