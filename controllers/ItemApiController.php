<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Collection;
use app\models\Tray;
use app\models\User;
use app\models\OldBarcodeTray;


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

    // This function takes a list of barcodes and returns a list with
    // locations -- it also searches the old tables as well
    public function actionSearchLocations()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] < 10) {
            throw new \yii\web\HttpException(403, 'You do not have permission to search items');
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $query = $data["barcodes"] ? $data["barcodes"] : [];
        $newTableResults = $this->modelClass::find()->where(['barcode' => $query, 'active' => 1])->all();
        $oldTableResults = OldBarcodeTray::find()
                ->joinWith([
                    'oldTrayShelf' => function ($q) {
                        $q->select('boxbarcode, shelf, shelf_depth, shelf_position');
                    }
                ])
                ->where(['barcode' => $query])
                ->orderBy([
                    'shelf' => SORT_ASC,
                    'shelf_depth'=>SORT_ASC,
                    'shelf_position'=>SORT_ASC,
                  ])
                ->asArray()
                ->all();
        $results = [];
        // Default to a null for that barcode
        for ($i = 0; $i < count($query); $i++) {
            $results[$query[$i]] = [
                'barcode' => $query[$i],
                'tray' => null,
                'shelf' => null,
                'depth' => null,
                'position' => null,
                // 'collection' => null,
                'status' => 'Not found',
                'system' => null,
            ];
        }
        // Add the old table results. These will get overwritten by
        // new table results if they exist
        for ($i = 0; $i < count($oldTableResults); $i++) {
            $results[$oldTableResults[$i]['barcode']] = [
                'barcode' => $oldTableResults[$i]['barcode'],
                'tray' => $oldTableResults[$i]['boxbarcode'],
                'shelf' => $oldTableResults[$i]['oldTrayShelf'] ? $oldTableResults[$i]['oldTrayShelf']['shelf'] : null,
                'depth' => $oldTableResults[$i]['oldTrayShelf'] ? $oldTableResults[$i]['oldTrayShelf']['shelf_depth'] : null,
                'position' => $oldTableResults[$i]['oldTrayShelf'] ? $oldTableResults[$i]['oldTrayShelf']['shelf_position'] : null,
                // 'collection' => $newTableResults[$i]['stream'],
                'status' => $oldTableResults[$i]['status'],
                'system' => 'Old',
            ];
        }
        // Add the new table results
        for ($i = 0; $i < count($newTableResults); $i++) {
            $results[$newTableResults[$i]['barcode']] = [
                'barcode' => $newTableResults[$i]['barcode'],
                'tray' => $newTableResults[$i]['tray'] ? $newTableResults[$i]['tray']['barcode'] : null,
                'shelf' => $newTableResults[$i]['tray'] ? (isset($newTableResults[$i]['tray']['shelf']['barcode']) ? $newTableResults[$i]['tray']['shelf']['barcode'] : $newTableResults[$i]['tray']['shelf']) : null,
                'depth' => $newTableResults[$i]['tray'] ? $newTableResults[$i]['tray']['depth'] : null,
                'position' => $newTableResults[$i]['tray'] ? $newTableResults[$i]['tray']['position'] : null,
                // 'collection' => $newTableResults[$i]['collection'],
                'status' => $newTableResults[$i]['status'],
                'system' => 'New',
            ];
        }
        return $results;
    }

    public function actionBrowse()
    {
        $barcode = isset($_REQUEST["query"]) ? $_REQUEST["query"] : null;
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            // If a barcode has been provided, search by barcode and return
            // a limited number of results
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
            throw new \yii\web\HttpException(403, 'You do not have permission to browse items');
        }
    }

    // Expects a single barcode in data under "barcode". Returns a single
    // true/false value whether the item is in FOLIO.
    public function actionCheckFolio()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $results = \app\components\Folio::fullLookup($data["barcode"]);
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
        return \app\components\Folio::partialLookup($data["barcode"]);
    }

    public function actionMarkFolioAnomalies()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] < 80) {
            throw new \yii\web\HttpException(403, 'You do not have permission to do this operation');
        }

        $allItems = $this->modelClass::find()->where(['active' => 1])->all();
        $anomalousItems = [];
        // Go through each item and check it against FOLIO
        foreach ($allItems as $item) {
            $anomalous = \app\components\Folio::handleMarkFolioAnomaly($item, $tokenCheck['id']);
            if ($anomalous) {
                $anomalousItems[] = $item["barcode"];
            }
        }
        return $anomalousItems;
    }

    private function handleItemUpdate($data, $userId, $actionIndicator)
    {
        $itemLog = new $this->modelLogClass;
        $logDetails = [];
        $flag = false;
        $flagDetails = [];

        // Get the item and related info
        $itemBarcode = $data['barcode'];
        $trayBarcode = isset($data['tray']) ? $data['tray'] : null;
        $newTrayBarcode = isset($data['new_tray']) ? $data['new_tray'] : null;
        $collection = isset($data['collection']) ? $data['collection'] : null;
        $status = isset($data['status']) ? $data['status'] : null;
        $item = $this->modelClass::find()->where(['barcode' => $itemBarcode])->one();
        $currentCollection = \app\models\Collection::find()->where(['id' => $item->collection_id])->one();
        $currentTray = \app\models\Tray::find()->where(['id' => $item->tray_id])->one();

        // Here are the anomalies where we throw an error.

        // If the item doesn't exist
        if ($item === null) {
            throw new \yii\web\HttpException(400, sprintf('Item %s does not exist', $itemBarcode));
        }

        // If a barcode was provided and it's not the same as the current
        // one, check that it's not already in use (this doesn't happen with
        // the rapid shelve form), and also check that it is in FOLIO --
        // if it isn't in FOLIO, we can change it, but flag it.
        if ($newTrayBarcode && $newTrayBarcode != $data['barcode']) {
            $itemCheck = $this->modelClass::find()->where(['barcode' => $data["new_barcode"]])->one();
            if ($itemCheck != null) {
                if ($itemCheck->active == false) {
                    throw new \yii\web\HttpException(400, sprintf('Item %s used to exist, and was deleted. Please re-add that item instead of changing this one.', $data['new_barcode']));
                }
                else {
                    throw new \yii\web\HttpException(400, sprintf('Item %s already exists', $data['new_barcode']));
                }
            }
            $item->barcode = $data['new_barcode'];
            $item->save();
            \app\components\Folio::handleMarkFolioAnomaly($item, $userId);
            $logDetails[] = sprintf("barcode %s", $data['new_barcode']);
        }

        if ($actionIndicator === "Returned") {
            // Add flags if information is changing on return that we expect
            // to be the same
            if ($currentCollection && $collection && $currentCollection->name != $collection) {
                $flagDetails[] = sprintf("Item returned with collection %s but was previously in %s", $collection, $currentCollection->name);
            }
            if ($currentTray && $currentTray->barcode != $trayBarcode) {
                $flagDetails[] = sprintf("Item returned to tray %s but was previously in tray %s", $trayBarcode, $currentTray->barcode);
            }
            if ($item->status != "Circulating") {
                $flagDetails[] = sprintf("Item did not have status Circulating at the time of return");
            }
        }

        // Tray
        if ($trayBarcode === "") {
            $item->tray_id = null;
            // If the item was previously in a tray, log that it was made null
            if ($item->tray != null) {
                $logDetails[] = sprintf("tray null");
            }
        }
        else if ($trayBarcode === null) {
            // Do nothing if no tray barcode was provided
        }
        // If a tray barcode was provided (we've already confirmed it's not
        // empty) and either there was no tray previously, or it's different
        // than what was there previously, update it and log it
        else if ($item->tray === null || $trayBarcode != $item->tray->barcode) {
            $newTray = Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->one();
            if ($newTray === null) {
                if (Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => false])->one()) {
                    throw new \yii\web\HttpException(400, sprintf('Tray %s has been deleted.', $trayBarcode));
                }
                else {
                    throw new \yii\web\HttpException(400, sprintf('Tray %s does not exist.', $trayBarcode));
                }
            }
            $item->tray_id = $newTray->id;
            $logDetails[] = sprintf("tray %s", $trayBarcode);
        }
        // Collection
        if ($collection) {
            $newCollection = Collection::find()->where(['name' => $collection])->andWhere(['active' => true])->one();
            if ($newCollection === null) {
                if (Collection::find()->where(['name' => $collection])->andWhere(['active' => false])->one()) {
                    throw new \yii\web\HttpException(400, sprintf('Collection %s has been deleted.', $data['collection']));
                }
                else {
                    throw new \yii\web\HttpException(400, sprintf('Collection %s does not exist.', $data['collection']));
                }
            }
            else if ($item->collection_id != $newCollection->id) {
                $item->collection_id = $newCollection->id;
                $logDetails[] = sprintf("collection %s", $collection);
            }
        }

        // Status
        if ($status && $status != $item->status) {
            // Mark "To return to campus" items differently
            if ($status === "Picked" && ($item->status === "To return to campus" || $item->status === "Returned to campus")) {
                $item->status = "Returned to campus";
                $logDetails[] = sprintf("status Returned to campus");
            }
            else {
                $item->status = $status;
                $logDetails[] = sprintf("status %s", $data['status'] === "" ? "null" : $data['status']);
            }
        }
        // Active/inactive
        if (!$item->active) {
            $item->active = 1;
            // Make separate log entry for reactivating the item
            $reactivatedItemLog = new $this->modelLogClass;
            $reactivatedItemLog->item_id = $item->id;
            $reactivatedItemLog->action = 'Restored';
            $reactivatedItemLog->details = sprintf("Restored item %s", $item->barcode);
            $reactivatedItemLog->user_id = $userId;
            $reactivatedItemLog->save();
        }
        // Flag
        if ($flag == true || count($flagDetails) > 0) {
            $item->flag = 1;
        }
        $item->save();

        // Log the update
        $itemLog->item_id = $item->id;
        $itemLog->action = $actionIndicator;
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

        // Check whether the tray is overfull, and if so, flag it.
        \app\controllers\TrayApiController::flagTrayIfOverfull($newTrayBarcode ? $newTrayBarcode : $trayBarcode, $userId);

        return $item;
    }

    private function handleItemAdd($data, $userId)
    {
        // If the item is in the database but inactive
        $existingItem = $this->modelClass::find()
            ->where(['barcode' => $data['barcode']])
            ->andWhere(['active' => false])
            ->one();
        if ($existingItem) {
            $item = $this->handleItemUpdate($data, $userId, "Reactivated");
        }
        else {
            $item = new $this->modelClass;
            $itemLog = new $this->modelLogClass;
            $logDetails = [];
            $flagDetails = [];

            // Item barcode
            if (isset($data['new_barcode'])) {
                $itemBarcode = $data['new_barcode'];
            }
            else if (isset($data['barcode'])) {
                $itemBarcode = $data['barcode'];
            }
            else {
                throw new \yii\web\HttpException(400, 'No barcode provided.');
            }
            // Check that the barcode is not already in use
            $itemCheck = $this->modelClass::find()->where(['barcode' => $itemBarcode])->one();
            if ($itemCheck != null) {
                if ($itemCheck->active == false) {
                    throw new \yii\web\HttpException(400, sprintf('Item %s used to exist, and was deleted. Please re-add that item instead of changing this one.', $itemBarcode));
                }
                else {
                    throw new \yii\web\HttpException(400, sprintf('Item %s already exists', $itemBarcode));
                }
            }
            $item->barcode = $itemBarcode;
            $logDetails[] = sprintf("barcode %s", $itemBarcode);

            // Tray barcode
            $trayBarcode = isset($data['tray']) && $data['tray'] != '' ? $data['tray'] : null;
            if ($trayBarcode) {
                $tray = Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->one();
                if ($tray === null) {
                    if (Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => false])->one()) {
                        throw new \yii\web\HttpException(400, sprintf('Tray %s has been deleted', $trayBarcode));
                    }
                    else {
                        throw new \yii\web\HttpException(400, sprintf('Tray %s does not exist', $trayBarcode));
                    }
                }
                $item->tray_id = $tray->id;
                $logDetails[] = sprintf("tray %s", $trayBarcode);
            }
            else {
                $item->tray_id = null;
                $logDetails[] = "null tray";
            }

            // Collection
            if (isset($data['collection']) && $data['collection'] != '') {
                $collection = Collection::find()->where(['name' => $data['collection']])->andWhere(['active' => true])->one();
                if ($collection === null) {
                    if (Collection::find()->where(['name' => $collection])->andWhere(['active' => false])->one()) {
                        throw new \yii\web\HttpException(400, sprintf('Collection %s has been deleted.', $data['collection']));
                    }
                    else {
                        throw new \yii\web\HttpException(400, sprintf('Collection %s does not exist.', $data['collection']));
                    }
                }
                else {
                    $item->collection_id = $collection->id;
                    $logDetails[] = sprintf("collection %s", $data['collection']);
                }
            }
            else {
                $logDetails[] = "collection blank";
                $flagDetails[] = "Item added with no collection";
            }

            // Status
            if (isset($data['status']) && $data['status'] != '') {
                $item->status = $data['status'];
                $logDetails[] = sprintf("status %s", $data['status'] === "" ? "null" : $data['status']);
            }
            else {
                throw new \yii\web\HttpException(400, 'Status cannot be blank.');
            }

            // Flag
            if (isset($data['flag']) && $data['flag'] == true) {
                $flagDetails[] = "User specified flag";
            }

            if ($flagDetails) {
                $item->flag = 1;
            }
            $item->active = 1;
            $item->save();

            // Log the update
            $itemLog->item_id = $item->id;
            $itemLog->action = 'Added';
            $itemLog->details = sprintf("Added single item: %s", $item->barcode, implode(', ', $logDetails));
            $itemLog->user_id = $userId;
            $itemLog->save();

            // Mark the item in the old database as retrayed
            $oldBarcodeTray = OldBarcodeTray::find()->where(['barcode' => $item->barcode])->one();
            if ($oldBarcodeTray) {
                $oldBarcodeTray->status = "Retrayed";
                $oldBarcodeTray->save();
            }

            // Log any flags that occurred
            foreach ($flagDetails as $flagDetail) {
                $flagLog = new $this->modelLogClass;
                $flagLog->item_id = $item->id;
                $flagLog->action = 'Flagged';
                $flagLog->details = $flagDetail;
                $flagLog->user_id = $userId;
                $flagLog->save();
            }
        }

        // Look up the item in FOLIO to see if it is somewhere other
        // than the Annex, or marked as something other than Available.
        // If so, flag it.
        \app\components\Folio::handleMarkFolioAnomaly($item, $userId);

        // Check whether the tray is overfull, and if so, flag it.
        \app\controllers\TrayApiController::flagTrayIfOverfull($trayBarcode, $userId);

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
            $item = $this->handleItemUpdate($data, $tokenCheck['id'], "Updated");
            return $item;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to update items.');
        }
    }

    public function actionBulkUpdate()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        // Data should be in the form:
        // {
        //     "barcodes": ["3101...", ...]
        //     "status": "Circulating",
        // }

        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 50) {
            try {
                $itemList = $this->modelClass::find()->where(['barcode' => $data["barcodes"]])->all();

                if ($data["status"] === "Missing") {
                    $logAction = "Marked missing";
                    $logDetails = "Marked item as missing: ";
                }
                else if ($data["status"] === "Circulating") {
                    $logAction = "Circulated";
                    $logDetails = "Marked item as circulating: ";
                }
                else {
                    $logAction = "Updated";
                    $logDetails = sprintf("Updated item with status %s: ", $data["status"]);
                }

                $this->modelClass::updateAll(
                    ['status' => $data["status"]],
                    ['id' => array_map(function($i) { return $i['id']; }, $itemList)]
                );

                // Add logs for all the new items
                Yii::$app->db->createCommand()->batchInsert(
                    'item_log',
                    ['item_id', 'user_id', 'action', 'details'],
                    array_map(
                        function($i) use ($tokenCheck, $logAction, $logDetails) {
                            return [$i['id'], $tokenCheck['id'], $logAction, $logDetails . $i['barcode']];
                        },
                        $itemList
                    )
                )->execute();

                return $this->modelClass::find()->where(['barcode' => $data["barcodes"]])->all();
            }
            catch (Exception $e) {
                throw new \yii\web\HttpException(400, 'Error updating items.');
            }
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to page items.');
        }
    }

    public function actionMarkPicked()
    {
        // We need the item id in the data
        $json = file_get_contents('php://input');
        $data = ["status" => "Circulated"];
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 50) {
            $item = $this->handleItemUpdate($data, $tokenCheck['id'], "Picked");
            return $item;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to page items.');
        }
    }

    public function actionMarkMissing()
    {
        // We need the item id in the data
        $json = file_get_contents('php://input');
        $data = ["status" => "Missing"];
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 50) {
            $item = $this->handleItemUpdate($data, $tokenCheck['id'], "Marked missing");
            return $item;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to page items.');
        }
    }


    // Receive new item info and update the database
    public function actionNewItem()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $item = $this->handleItemAdd($data, $tokenCheck['id']);
            return $item;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to add items.');
        }
    }

    public function actionAddReturn()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $existingItem = $this->modelClass::find()->where(['barcode' => $data['barcode']])->andWhere(['active' => true])->one();
            if ($existingItem) {
                $item = $this->handleItemUpdate($data, $tokenCheck['id'], "Returned");
            }
            else {
                // If the item wasn't validated in FOLIO, add to the table
                // so that we can check it later
                if (!$data['folioVerified']) {
                    $folioValidation = new \app\models\FolioValidation;
                    $folioValidation->barcode = $data['barcode'];
                    $folioValidation->item_in_folio = null;
                    $folioValidation->save();
                }
                $item = $this->handleItemAdd($data, $tokenCheck['id']);
            }
            return $item;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to add or update items.');
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
            if ($item->tray_id === null) {
                $oldLocation = 'was untrayed';
            }
            else {
                $oldLocation = sprintf("was in tray %s", $item->tray->barcode);
            }
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
            throw new \yii\web\HttpException(403, 'You do not have permission to delete items');
        }
    }

    public function actionTotalCount()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $totalCount = $this->modelClass::find()->where(['active' => 1])->count();
            return $totalCount;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view the total count.');
        }
    }

}

