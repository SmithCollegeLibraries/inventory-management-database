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

    public function actionUpdateTray()
    {
        // We want the id, as well as the following optional fields:
        // (new tray) barcode, (new) shelf (barcode), depth, and position.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if (tokenCheck['level'] >= 60) {
            // Get the tray
            $tray = $this->modelClass::find()->where(['id' => $data['id']])->one();
            $trayLog = new $this->modelLogClass;

            $logDetails = [];

            // If a barcode was provided and it's not the same as the current
            // one, check that it's not already in use
            if (isset($data['barcode']) and $data['barcode'] != $tray->barcode) {
                $trayCheck = $this->modelClass::find()->where(['barcode' => $data["barcode"]])->one();
                if ($trayCheck != null) {
                    throw new \yii\web\HttpException(500, sprintf('Tray %s already exists', $data['barcode']));
                }
                $tray->barcode = $data['barcode'];
                $logDetails[] = sprintf("barcode %s", $data['barcode']);
            }
            // Shelf
            if (isset($data['shelf']) and $data['shelf'] != $tray->shelf->barcode) {
                $shelf = \app\models\Shelf::find()->where(['barcode' => $data['shelf']])->one();
                if ($shelf == null) {
                    throw new \yii\web\HttpException(500, sprintf('Shelf %s does not exist', $data['shelf']));
                }
                $tray->shelf_id = $shelf->id;
                $logDetails[] = sprintf("shelf %s", $data['shelf']);
            }
            // Depth and position
            if (isset($data['depth']) and $data['depth'] != $tray->depth) {
                $tray->depth = $data['depth'];
                $logDetails[] = sprintf("depth %s", $data['depth']);
            }
            if (isset($data['position']) and $data['position'] != $tray->position) {
                $tray->position = $data['position'];
            }
            $tray->save();

            // Log the update
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Updated';
            if (count($logDetails) > 0) {
                $trayLog->details = sprintf("Updated tray %s: %s", $tray->barcode, implode(', ', $logDetails));
            }
            else {
                $trayLog->details = sprintf("Updated tray %s (unchanged)", $tray->barcode);
            }
            $trayLog->user_id = $tokenCheck['id'];
            $trayLog->save();
        }
    }

    // Deleting a tray deletes all its items as well. They can be restored
    // or added to the system again, but for the time being they are not
    // in the system.
    public function actionDeleteTray()
    {
        // All we should get is the ID of the tray to delete / make inactive.
        // We'll also need the user's token to make sure they have permission.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $tray = $this->modelClass::findOne($data['id']);
            $tray->active = 0;
            $tray->save();

            $trayLog = new $this->modelLogClass;
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Deleted';
            $trayLog->details = sprintf("Deleted tray %s", $tray->barcode);
            $trayLog->user_id = $tokenCheck['id'];
            $trayLog->save();

            $items = $this->itemClass::find()->where(['tray_id' => $tray->id])->all();
            foreach ($items as $item) {
                $item->active = 0;
                $item->save();

                $itemLog = new $this->itemLogClass;
                $itemLog->item_id = $item->id;
                $itemLog->action = 'Deleted';
                $itemLog->details = sprintf("Deleted item %s along with tray %s", $item->barcode, $tray->barcode);
                $itemLog->user_id = $tokenCheck['id'];
                $itemLog->save();
            }

            return $tray;
        }

        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to delete trays');
        }
    }

    public function actionGetTrayWithItems()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $tray = $this->modelClass::find()->where(['id' => $data['id']])->one();
            // $items = $this->itemClass::find()->where(['tray_id' => $tray->id])->all();
            // $tray->items = $items;
            return $tray;

        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to view trays');
        }
    }

    public function actionViewAllTrays() {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $trays = $this->modelClass::find()->all();
            return $trays;
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to view trays');
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
