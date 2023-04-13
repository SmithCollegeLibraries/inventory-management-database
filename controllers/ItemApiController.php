<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Collection;
use app\models\Tray;
use app\models\User;

class ItemApiController extends ActiveController
{
    public $modelClass = 'app\models\Item';
    public $modelLogClass = 'app\models\ItemLog';

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

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index']);
        return $actions;
    }

    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => $this->modelClass::find(),
            'pagination' => false,
        ]);
        return $dataProvider;
    }

    public function actionSearch()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $results = $this->modelClass::find()->where(['barcode' => $data["barcodes"], 'active' => 1])->all();
        return $results;
    }

    public function actionBrowse()
    {
        $barcode = isset($_REQUEST["query"]) ? $_REQUEST["query"] : null;
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            // If a barcode has been provided, search by barcode and return
            // a liminted number of results
            $provider = new ActiveDataProvider([
                'query' => $this->modelClass::find()
                    ->where(['like', 'barcode', $barcode])
                    ->andWhere(['active' => 1]),
                'sort' => [
                    'defaultOrder' => [
                        'id' => SORT_DESC,
                    ]
                ],
                'pagination' => [
                    'pageSize' => 10,
                ],
            ]);
            return $provider->getModels();
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to browse items');
        }
    }

    // Expects a single barcode in data under "barcode". Returns a single
    // true/false value whether the item is in FOLIO.
    public function actionCheckFolio()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $results = \app\components\Folio::barcodeLookup($data["barcode"]);
        try {
            return $results["data"]["totalRecords"] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Expects a single barcode in data under "barcode". Returns the item's
    // barcode, title and call number.
    public function actionInfoFromFolio()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $results = \app\components\Folio::barcodeLookup($data["barcode"]);
        try {
            // If the item is in FOLIO, there should be exactly one result
            // for this barcode.
            $instance = reset($results["data"]["instances"]);
            // The title is located on the instance record
            $title = $instance["title"];
            // To get the call number, we will have to look at the items,
            // find the item that matches on the barcode, and then get the
            // call number from effectiveCallNumberComponents.
            $items = $instance["items"];
            $item = array_filter($items, function($item) use ($data) {
                return $item["barcode"] == $data["barcode"];
            });
            $firstItem = reset($item);
            $callNumber = $firstItem["effectiveCallNumberComponents"]["callNumber"];
            return [
                "barcode" => $data["barcode"],
                "title" => $title,
                "callNumber" => $callNumber,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function handleItemUpdate($data, $userId)
    {
        $itemLog = new $this->modelLogClass;
        $logDetails = [];
        $flag = false;
        $flagDetails = [];

        // Get the item and related info
        $itemBarcode = $data['barcode'];
        $trayBarcode = isset($data['tray']) ? $data['tray'] : null;
        $collection = isset($data['collection']) ? $data['collection'] : null;
        $status = isset($data['status']) ? $data['status'] : null;
        $item = $this->modelClass::find()->where(['barcode' => $itemBarcode])->one();
        $tray = Tray::find()->where(['barcode' => $trayBarcode])->one();

        // Here are the anomalies where we throw an error.

        // If the item doesn't exist
        if ($item == null) {
            throw new \yii\web\HttpException(500, sprintf('Item %s does not exist', $itemBarcode));
        }

        // If the tray doesn't exist
        if ($tray == null) {
            // You can clear the shelf field in the manual tray edit form,
            // so it's not an error if this is a null string
            if ($trayBarcode != "") {
                throw new \yii\web\HttpException(500, sprintf('Tray %s does not exist', $trayBarcode));
            }
        }

        // If a barcode was provided and it's not the same as the current
        // one, check that it's not already in use (this doesn't happen with
        // the rapid shelve form), and also check that it is in FOLIO --
        // if it isn't in FOLIO, we can add it, but flag it.
        if (isset($data['new_barcode']) && $data['new_barcode'] != $data['barcode']) {
            $itemCheck = $this->modelClass::find()->where(['barcode' => $data["new_barcode"]])->one();
            if ($itemCheck != null) {
                if ($itemCheck->active == false) {
                    throw new \yii\web\HttpException(500, sprintf('Item %s used to exist, and was deleted. Please re-add that item instead of changing this one.', $data['new_barcode']));
                }
                else {
                    throw new \yii\web\HttpException(500, sprintf('Item %s already exists', $data['new_barcode']));
                }
            }
            $item->barcode = $data['new_barcode'];
            $logDetails[] = sprintf("barcode %s", $data['new_barcode']);
        }
        // Tray
        if (isset($item->tray) && $trayBarcode != null && $trayBarcode != $item->tray->barcode) {
            $newTray = Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->one();
            if ($newTray == null) {
                if (Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => false])->one()) {
                    throw new \yii\web\HttpException(500, sprintf('Tray %s has been deleted.', $trayBarcode));
                }
                else {
                    throw new \yii\web\HttpException(500, sprintf('Tray %s does not exist.', $trayBarcode));
                }
            }
            $item->tray_id = $newTray->id;
            $logDetails[] = sprintf("tray %s", $trayBarcode == null ? "null" : $trayBarcode);
        }
        // Collection
        if ($collection) {
            $newCollection = Collection::find()->where(['name' => $collection])->andWhere(['active' => true])->one();
            if ($newCollection == null) {
                if (Collection::find()->where(['name' => $collection])->andWhere(['active' => false])->one()) {
                    throw new \yii\web\HttpException(500, sprintf('Collection %s has been deleted.', $data['collection']));
                }
                else {
                    throw new \yii\web\HttpException(500, sprintf('Collection %s does not exist.', $data['collection']));
                }
            }
            else if ($item->collection_id != $newCollection->id) {
                $item->collection_id = $newCollection->id;
                $logDetails[] = sprintf("collection %s", $collection);
            }
        }

        // Status
        if ($status && $status != $item->status) {
            $item->status = $status;
            $logDetails[] = sprintf("status %s", $data['status'] == "" ? "null" : $data['status']);
        }
        // Flag
        if ($flag == true) {
            $item->flag = 1;
        }
        $item->save();

        // Log the update
        $itemLog->item_id = $item->id;
        $itemLog->action = 'Updated';
        if (count($logDetails) > 0) {
            $itemLog->details = sprintf("Updated item %s: %s", $item->barcode, implode(', ', $logDetails));
        }
        else {
            $itemLog->details = sprintf("Updated item %s (unchanged)", $item->barcode);
        }
        $itemLog->user_id = $userId;
        $itemLog->save();

        // Log any flags that occurred
        foreach ($flagDetails as $flagDetail) {
            $flagLog = new $this->modelLogClass;
            $flagLog->item_id = $item->id;
            $flagLog->action = 'Flagged';
            $flagLog->details = $flagDetail;
            $flagLog->user_id = $userId;
            $flagLog->save();
        }

        return $item;
    }

    // Receive updated item info and update the database
    public function actionUpdateItem()
    {
        // We want the id, as well as the following optional fields:
        // new item barcode, tray barcode, collection, status.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $item = $this->handleItemUpdate($data, $tokenCheck['id'], false);
            return $item;
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to update items.');
        }
    }

    public function actionDeleteItem()
    {
        // All we should get is the ID of the item to delete / make inactive.
        // We'll also need the user's token to make sure they have permission.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $item = $this->modelClass::find()->where(['barcode' => $data['barcode']])->one();
            $previousStatus = $item->active;
            if ($item->tray_id == null) {
                $oldLocation = 'was untrayed';
            }
            else {
                $oldLocation = sprintf("was in tray %s", $item->tray->barcode);
            }
            $item->tray_id = null;
            $item->status = "Deleted";
            $item->active = 0;
            $item->save();

            $itemLog = new $this->modelLogClass;
            $itemLog->item_id = $item->id;
            $itemLog->action = 'Deleted';
            if ($previousStatus == 0) {
                $itemLog->details = sprintf("Deleted item %s (was already deleted)", $item->barcode);
            }
            else {
                $itemLog->details = sprintf("Deleted item %s (%s)", $item->barcode, $oldLocation);
            }
            $itemLog->user_id = $tokenCheck['id'];
            $itemLog->save();

            return $item;
        }

        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to delete items');
        }
    }


}

