<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Shelf;
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

    private function alreadyOccupyingTray($shelf_id, $depth, $position)
    {
        return $this->modelClass::find()
            ->where(['shelf_id' => $shelf_id])
            ->andWhere(['depth' => $depth])
            ->andWhere(['position' => $position])
            ->andWhere(['active' => 1])
            ->one();
    }

    private function handleCreateTray($trayBarcode, $userId)
    {
        // If the tray used to exist but has been deactivated, reactivate
        // it instead of creating a new object
        if (\app\models\Tray::find()->where(['barcode' => $trayBarcode])->all() != []) {
            $tray = \app\models\Tray::find()->where(['barcode' => $trayBarcode])->one();
            $tray->active = 1;
            $tray->save();
            // Log the reactivation
            $trayLog = new $this->modelLogClass;
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Restored';
            $trayLog->details = sprintf("Restored tray %s", $tray->barcode);
            $trayLog->user_id = $userId;
            $trayLog->save();
        }
        else {
            $tray = new $this->modelClass;
            $tray->barcode = $trayBarcode;
            $tray->save();
            // Log the new tray
            $trayLog = new $this->modelLogClass;
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Added';
            $trayLog->details = sprintf("Added tray %s", $tray->barcode);
            $trayLog->user_id = $userId;
            $trayLog->save();
        }

        return $tray;
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
            if (\app\models\Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->all() != []) {
                throw new \yii\web\HttpException(500, sprintf('Tray %s already exists', $trayBarcode));
            }

            // If any of the items already exist, return error
            foreach ($barcodes as $barcode) {
                if (\app\models\Item::find()->where(['barcode' => $barcode])->andWhere(['active' => true])->all() != []) {
                    throw new \yii\web\HttpException(500, sprintf('Item %s already exists', $barcode));
                }
            }

            // If the tray location is already occupied, return error
            if (isset($data['shelf'])) {
                $shelf = \app\models\Shelf::find()->where(['barcode' => $data['shelf']])->one();
                if ($shelf) {
                    $shelfId = \app\models\Shelf::find()->where(['barcode' => $data['shelf']])->one()->id;
                    if (count($this->alreadyOccupyingTray($shelfId, $data['depth'], $data['position'])) != null) {
                        throw new \yii\web\HttpException(500, sprintf('Shelf %s, %s, position %s is already occupied by tray', $data['shelf'], $data['depth'], $data['position']));
                    }
                }
            }

            // Create new tray
            $tray = $this->handleCreateTray($trayBarcode, $tokenCheck['id']);

            // Create new items and add them to tray; add logs as well
            foreach ($barcodes as $barcode) {
                // If item already exists but is inactive, reactivate it
                if (\app\models\Item::find()->where(['barcode' => $barcode])->all() != []) {
                    $item = \app\models\Item::find()->where(['barcode' => $barcode])->one();
                    $oldItemStatus = $item->status;

                    $item->active = 1;
                    $item->tray_id = $tray->id;
                    $item->status = "Trayed";
                    $item->save();
                    // Log the reactivation
                    $itemLog = new $this->itemLogClass;
                    $itemLog->item_id = $item->id;
                    $itemLog->action = 'Restored';
                    $itemLog->details = sprintf("Restored item %s and added to tray %s", $item->barcode, $tray->barcode);
                    $itemLog->user_id = $tokenCheck['id'];
                    $itemLog->save();
                }
                else {
                    $item = new $this->itemClass;
                    $item->tray_id = $tray->id;
                    $item->status = "Trayed";
                    $item->barcode = $barcode;
                    $item->collection_id = $collectionId;
                    $item->save();

                    $itemLog = new $this->itemLogClass;
                    $itemLog->item_id = $item->id;
                    $itemLog->action = 'Added';
                    $itemLog->details = sprintf("Added item %s in tray %s", $item->barcode, $tray->barcode);
                    $itemLog->user_id = $tokenCheck['id'];
                    $itemLog->save();
                }
            }

            return $barcodes;
        }

        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to add new trays');
        }
    }

    // If $flagsAllowed is set, certain anomalies will be allowed but
    // flagged and logged. This may happen when vendors are shelving using
    // the rapid load form, but aren't connected to the internet.
    private function handleTrayUpdate($data, $userId, $flagsAllowed)
    {
        $trayLog = new $this->modelLogClass;
        $logDetails = [];
        $flag = false;
        $flagDetails = [];

        // Get the tray and shelf
        $trayBarcode = $data['barcode'];
        $tray = $this->modelClass::find()->where(['barcode' => $trayBarcode])->one();
        $shelf = \app\models\Shelf::find()->where(['barcode' => $data['shelf']])->one();

        // Here are the five anomalies where we either throw an error
        // or shelve and flag, depending on the situation.

        // 1. If the tray doesn't exist
        if ($tray == null) {
            if ($flagsAllowed == true) {
                $tray = $this->handleCreateTray($trayBarcode, $userId);
                $flag = true;
                $flagDetails[] = sprintf('Tray %s did not exist before being shelved', $trayBarcode);
            }
            else {
                throw new \yii\web\HttpException(500, sprintf('Tray %s does not exist', $trayBarcode));
            }
        }

        // 2. If the tray is empty
        if ($tray->items == []) {
            if ($flagsAllowed == true) {
                $flag = true;
                $flagDetails[] = sprintf('Tray %s was shelved when empty', $trayBarcode);
            }
            else {
                // Do nothing: This isn't actually a problem when updating
                // a tray manually, although it should be flagged if this
                // situation happens using the rapid load form.
            }
        }

        // 3. If the tray is already shelved:
        $oldShelf = \app\models\Shelf::find()->where(['id' => $tray->shelf_id])->one();
        if ($tray && $tray->shelf_id != null) {
            if ($flagsAllowed == true) {
                $flag = true;
                $oldShelfBarcode = $oldShelf->barcode;
                $oldPosition = $tray->position == null ? 'null' : $tray->position;
                $oldDepth = $tray->depth == null ? 'null' : $tray->depth;
                $flagDetails[] = sprintf('Tray %s was already on shelf %s, %s, position %s', $trayBarcode, $oldShelfBarcode, $oldDepth, $oldPosition);
            }
            else {
                throw new \yii\web\HttpException(500, sprintf('Tray %s is already shelved', $trayBarcode));
            }
        }

        // 4. If the shelf doesn't exist:
        if ($shelf == null) {
            if ($flagsAllowed == true) {
                $shelfBarcode = $data['shelf'];
                $shelf = new \app\models\Shelf;
                $shelf->barcode = $shelfBarcode;
                $shelf->row = substr($shelfBarcode, 0, 2);
                $shelf->side = substr($shelfBarcode, 2, 1);
                $shelf->ladder = substr($shelfBarcode, 3, 2);
                $shelf->rung = substr($shelfBarcode, 5, 2);
                $shelf->active = 1;
                $shelf->save();
                $flag = true;
                $flagDetails[] = sprintf('Tray %s was assigned to shelf %s, which didn\'t exist yet', $trayBarcode, $data['shelf']);
            }
            else {
                throw new \yii\web\HttpException(500, sprintf('Shelf %s does not exist', $data['shelf']));
            }
        }

        // 5. If the location of the tray is already taken:
        $shelfId = \app\models\Shelf::find()->where(['barcode' => $data['shelf']])->one()->id;
        $existingTray = $this->alreadyOccupyingTray($shelfId, $data['depth'], $data['position']);
        if ($existingTray != null) {
            if ($flagsAllowed == true) {
                $flag = true;
                $flagDetails[] = sprintf('Tray %s was assigned to shelf %s, %s, position %s, which was already occupied by tray %s', $trayBarcode, $data['shelf'], $data['depth'], $data['position'], $existingTray->barcode);
            }
            else {
                throw new \yii\web\HttpException(500, sprintf('Shelf %s, %s, position %s is already occupied by tray %s', $data['shelf'], $data['depth'], $data['position'], $existingTray->barcode));
            }
        }

        // If a barcode was provided and it's not the same as the current
        // one, check that it's not already in use (this doesn't happen with
        // the rapid load form)
        if (isset($data['new_barcode']) && $data['new_barcode'] != $data['barcode']) {
            $trayCheck = $this->modelClass::find()->where(['barcode' => $data["new_barcode"]])->one();
            if ($trayCheck != null) {
                throw new \yii\web\HttpException(500, sprintf('Tray %s already exists', $data['new_barcode']));
            }
            $tray->barcode = $data['new_barcode'];
            $logDetails[] = sprintf("barcode %s", $data['new_barcode']);
        }
        // Shelf
        if (isset($data['shelf']) && (!isset($tray->shelf) || $data['shelf'] != $tray->shelf->barcode)) {
            $tray->shelf_id = $shelf->id;
            $logDetails[] = sprintf("shelf %s", $data['shelf']);
        }
        // Depth and position
        if (isset($data['depth']) && $data['depth'] != $tray->depth) {
            $tray->depth = $data['depth'];
            $logDetails[] = sprintf("depth %s", $data['depth']);
        }
        if (isset($data['position']) && $data['position'] != $tray->position) {
            if (gettype($data['position']) != 'integer') {
                $tray->position = str_pad($data['position'], 2, '0', STR_PAD_LEFT);
            }
            else {
                $tray->position = $data['position'];
            }
        }
        // Flag
        if ($flag == true) {
            $tray->flag = 1;
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
        $trayLog->user_id = $userId;
        $trayLog->save();

        // Log any flags that occurred
        foreach ($flagDetails as $flagDetail) {
            $flagLog = new $this->modelLogClass;
            $flagLog->tray_id = $tray->id;
            $flagLog->action = 'Flagged';
            $flagLog->details = $flagDetail;
            $flagLog->user_id = $userId;
            $flagLog->save();
        }

        return $tray;
    }

    public function actionUpdateTray()
    {
        // We want the id, as well as the following optional fields:
        // (new tray) barcode, (new) shelf (barcode), depth, and position.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $tray = $this->handleTrayUpdate($data, $tokenCheck['id'], false);
            return $tray;
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to update trays');
        }
    }

    // Wrapper function that makes it easier to do the most common
    // type of tray update
    public function actionShelveTray()
    {
        // We will calculate the ID from the barcode given. A tray's
        // barcode will not be changed using this function. We will also
        // get the shelf barcode, depth, and position.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $newData = [
                'barcode' => $data['tray'],
                'shelf' => $data['shelf'],
                'depth' => $data['depth'],
                'position' => $data['position']
            ];
            $tray = $this->handleTrayUpdate($newData, $tokenCheck['id'], true);
            return $tray;
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to shelve trays');
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
            $tray = $this->modelClass::find()->where(['barcode' => $data['barcode']])->one();
            if ($tray->shelf_id == null) {
                $oldLocation = 'not shelved';
            }
            else {
                $oldLocation = sprintf("shelf %s, %s, position %s", $tray->shelf->barcode, $tray->depth, $tray->position);
            }
            $tray->shelf_id = null;
            $tray->depth = null;
            $tray->position = null;
            $tray->active = 0;
            $tray->save();

            $trayLog = new $this->modelLogClass;
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Deleted';
            $trayLog->details = sprintf("Deleted tray %s (%s)", $tray->barcode, $oldLocation);
            $trayLog->user_id = $tokenCheck['id'];
            $trayLog->save();

            $items = $this->itemClass::find()->where(['tray_id' => $tray->id])->all();
            foreach ($items as $item) {
                $item->status = 'Deleted';
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

    // public function actionViewAllTrays() {
    //     $token = $_REQUEST["access-token"];
    //     $tokenCheck = User::find()->where(['access_token' => $token])->one();
    //
    //     if ($tokenCheck['level'] >= 20) {
    //         $trays = $this->modelClass::find()->all();
    //         return $trays;
    //     }
    //     else {
    //         throw new \yii\web\HttpException(500, 'You do not have permission to view trays');
    //     }
    // }

    public function actionSearch()
    {
        $barcode = isset($_REQUEST["query"]) ? $_REQUEST["query"] : null;
        $shelf = isset($_REQUEST["shelf"]) ? $_REQUEST["shelf"] : null;
        $depth = isset($_REQUEST["depth"]) ? $_REQUEST["depth"] : null;
        $position = isset($_REQUEST["position"]) ? $_REQUEST["position"] : null;
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            // If a barcode has been provided, search by parcode and return
            // up to 20 results
            if ($barcode != null) {
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->where(['like', 'barcode', $barcode])
                        ->andWhere(['active' => true]),
                    'sort' => [
                        'defaultOrder' => [
                            'updated' => SORT_DESC,
                        ]
                    ],
                    'pagination' => [
                        'pageSize' => 20,
                    ],
                ]);
                return $provider->getModels();
            }
            else {
                // Use the shelf, depth, and position to search for trays
                $tray = $this->modelClass::find()
                    ->where(['shelf_id' => $shelf])
                    ->andWhere(['depth' => $depth])
                    ->andWhere(['position' => $position])
                    ->andWhere(['active' => true])
                    ->one();
                return $tray;
            }
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to view trays');
        }
    }

    public function actionGetTray()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $tray = $this->modelClass::find()
                ->where(['barcode' => $data['barcode']])
                ->andWhere(['active' => true])
                ->one();
            return $tray;
        }
        else {
            throw new \yii\web\HttpException(500, 'You do not have permission to view trays');
        }
    }

}
