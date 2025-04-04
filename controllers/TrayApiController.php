<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Shelf;
use app\models\User;
use app\models\OldBarcodeTray;

use app\controllers\ShelfApiController;

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

    private function alreadyOccupyingTray($shelfId, $depth, $position, $trayBarcode)
    {
        if ($shelfId == null || $depth == null || $position == null) {
            return null;
        }
        else {
            $foundTray = $this->modelClass::find()
                ->where(['shelf_id' => $shelfId])
                ->andWhere(['depth' => $depth])
                ->andWhere(['position' => $position])
                ->andWhere(['active' => true])
                ->one();
            if ($foundTray != null && $foundTray->barcode != $trayBarcode) {
                return $foundTray;
            }
            else {
                return null;
            }
        }
    }

    private function handleCreateTray($trayBarcode, $userId)
    {
        // If the tray already exists and is active, throw an error
        if (\app\models\Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->all() != []) {
            throw new \yii\web\HttpException(400, sprintf('Tray %s already exists', $trayBarcode));
        }
        // If the tray used to exist but has been deactivated, reactivate
        // it instead of creating a new object
        else if (\app\models\Tray::find()->where(['barcode' => $trayBarcode])->all() != []) {
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
        if ($tokenCheck['level'] >= 30) {
            $trayBarcode = $data['barcode'];

            // We expect barcodes in the form of an array, but if it's not, we'll make it one
            if (!isset($data['items'])) {
                $barcodes = [];
            }
            else if (!is_array($data['items'])) {
                $barcodes = explode(PHP_EOL, $data['items']);
            }
            else {
                $barcodes = $data['items'];
            }

            $collectionName = isset($data['collection']) ? $data['collection'] : null;
            // Get collection ID, while making sure that a collection of that name exists
            try {
                $collection = \app\models\Collection::find()->where(['name' => $collectionName])->one();
                $collectionId = $collection ? $collection->id : null;
            }
            catch (\Exception $e) {
                throw new \yii\web\HttpException(400, sprintf('Collection %s does not exist', $collectionName));
            }

            // If tray already exists, return error
            if (\app\models\Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->all() != []) {
                throw new \yii\web\HttpException(400, sprintf('Tray %s already exists', $trayBarcode));
            }

            // If any of the items already exist, return error
            foreach ($barcodes as $barcode) {
                if (\app\models\Item::find()->where(['barcode' => $barcode])->andWhere(['active' => true])->all() != []) {
                    throw new \yii\web\HttpException(400, sprintf('Item %s already exists', $barcode));
                }
            }

            // If the tray location is already occupied, return error
            $shelfId = null;
            if (isset($data['shelf'])) {
                $shelf = \app\models\Shelf::find()->where(['barcode' => $data['shelf']])->one();
                if ($shelf) {
                    $shelfId = $shelf->id;
                    // If a shelf is provided, depth and position must also be provided
                    if (isset($data['depth']) && isset($data['position'])) {
                        $trayInTheWay = $this->alreadyOccupyingTray($shelfId, $data['depth'], $data['position'], null);
                        if ($trayInTheWay != null) {
                            throw new \yii\web\HttpException(400, sprintf('Shelf %s, depth %s, position %s is already occupied by tray %s', $data['shelf'], $data['depth'], $data['position'], $trayInTheWay->barcode));
                        }
                    }
                }
            }
            $sizeId = null;
            if (isset($data['size'])) {
                $size = \app\models\Size::find()->where(['code' => $data['size']])->one();
                if ($data['size'] && !$size) {
                    throw new \yii\web\HttpException(400, sprintf('Size %s does not exist', $data['size']));
                }
                $sizeId = $size ? $size->id : null;
            }
            $depth = isset($data['depth']) && $data['depth'] ? $data['depth'] : null;
            $position = isset($data['position']) && $data['position'] ? $data['position'] : null;

            // Create new tray (or reactivate existing one)
            $tray = $this->handleCreateTray($trayBarcode, $tokenCheck['id']);

            // If necessary, update the tray
            $shelf = isset($data['shelf']) ? $data['shelf'] : null;
            $depth = isset($data['depth']) && $data['depth'] ? $data['depth'] : null;
            $position = isset($data['position']) && $data['position'] ? $data['position'] : null;
            $fullCount = isset($data['full_count']) && $data['full_count'] ? $data['full_count'] : null;

            if ($tray->shelf_id != $shelfId || $tray->depth != $depth || $tray->position != $position || $tray->full_count != $fullCount || $tray->size_id != $sizeId) {
                $this->handleTrayUpdate([
                    'barcode' => $trayBarcode,
                    'size' => isset($data['size']) ? $data['size'] : null,
                    'collection' => $collectionName,
                    'shelf' => $shelf,
                    'depth' => $depth,
                    'position' => $position,
                    'full_count' => $fullCount
                ], $tokenCheck['id'], false);
            }

            // Create new items and add them to tray; add logs as well
            foreach ($barcodes as $barcode) {
                // If item already exists but is inactive, reactivate it
                if (\app\models\Item::find()->where(['barcode' => $barcode])->all() != []) {
                    $item = \app\models\Item::find()->where(['barcode' => $barcode])->one();

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

                    // Mark the item in the old tables as retrayed
                    $oldBarcodeTray = OldBarcodeTray::find()->where(['barcode' => $barcode])->one();
                    if ($oldBarcodeTray) {
                        $oldBarcodeTray->status = "Retrayed";
                        $oldBarcodeTray->save();
                    }

                    $itemLog = new $this->itemLogClass;
                    $itemLog->item_id = $item->id;
                    $itemLog->action = 'Added';
                    $itemLog->details = sprintf("Added item %s in tray %s", $item->barcode, $tray->barcode);
                    $itemLog->user_id = $tokenCheck['id'];
                    $itemLog->save();
                }
                // Regardless of whether the item already existed, check
                // it in FOLIO to make sure it was Available and in the Annex.
                // If not, flag it.
                \app\components\Folio::handleMarkFolioAnomaly($item, $tokenCheck['id']);
            }

            // Return the new tray as confirmation, after double-checking
            // that it was actually added to the database
            $trays = \app\models\Tray::find()->where(['barcode' => $trayBarcode])->andWhere(['active' => true])->all();
            if (count($trays) == 1) {
                return $trays[0];
            }
            else {
                if (count($trays) > 1) {
                    throw new \yii\web\HttpException(500, sprintf('Cannot have more than one tray with barcode %s', $trayBarcode));
                }
                else {
                    throw new \yii\web\HttpException(500, sprintf('Tray %s was not added to the database', $trayBarcode));
                }
            }
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to add new trays');
        }
    }

    private function findOrCreateShelf($shelfBarcode, $userId)
    {
        // If the shelf already exists, return it
        $possibleShelf = \app\models\Shelf::find()->where(['barcode' => $shelfBarcode])->one();
        if ($possibleShelf) {
            if ($possibleShelf->active == 0) {
                $possibleShelf->active = 1;
                $possibleShelf->flag = 1;
                $possibleShelf->save();

                // Log the reactivation
                $shelfLog = new \app\models\ShelfLog;
                $shelfLog->shelf_id = $possibleShelf->id;
                $shelfLog->action = 'Restored';
                $shelfLog->details = sprintf('Restored shelf %s', $possibleShelf->barcode);
                $shelfLog->user_id = $userId;
                $shelfLog->save();

                $flagShelfLog = new \app\models\ShelfLog;
                $flagShelfLog->shelf_id = $possibleShelf->id;
                $flagShelfLog->action = 'Flagged';
                $flagShelfLog->details = sprintf('Flagged shelf %s: shelf was inactive when a tray was assigned to it', $possibleShelf->barcode);
                $flagShelfLog->user_id = $userId;
                $flagShelfLog->save();
            }
            return $possibleShelf;
        }
        else {
            $shelf = new \app\models\Shelf;
            $shelf->barcode = $shelfBarcode;
            $shelf->row = substr($shelfBarcode, 0, 2);
            $shelf->side = substr($shelfBarcode, 2, 1);
            $shelf->ladder = substr($shelfBarcode, 3, 2);
            $shelf->rung = substr($shelfBarcode, 5, 2);
            $shelf->active = 1;
            $shelf->flag = 1;
            $shelf->save();

            $shelfLog = new \app\models\ShelfLog;
            $shelfLog->shelf_id = $shelf->id;
            $shelfLog->action = 'Created';
            $shelfLog->details = sprintf('Created shelf %s automatically', $shelf->barcode);
            $shelfLog->user_id = $userId;
            $shelfLog->save();

            $flagShelfLog = new \app\models\ShelfLog;
            $flagShelfLog->shelf_id = $shelf->id;
            $flagShelfLog->action = 'Flagged';
            $flagShelfLog->details = sprintf('Flagged shelf %s: shelf did not exist when a tray was assigned to it', $shelf->barcode);
            $flagShelfLog->user_id = $userId;
            $flagShelfLog->save();

            return $shelf;
        }
    }

    // If $flagsAllowed is set, certain anomalies will be allowed but
    // flagged and logged. This may happen when vendors are shelving using
    // the rapid shelve form, but aren't connected to the internet.
    private function handleTrayUpdate($data, $userId, $flagsAllowed)
    {
        $trayLog = new $this->modelLogClass;
        $logDetails = [];
        $flag = false;
        $flagDetails = [];

        // Get the tray and shelf
        $trayBarcode = $data['barcode'];
        $dataSize = isset($data['size']) ? $data['size'] : null;
        $dataCollection = isset($data['collection']) ? $data['collection'] : null;
        $dataShelf = isset($data['shelf']) ? $data['shelf'] : null;
        $dataDepth = isset($data['depth']) ? $data['depth'] : null;
        $dataPosition = isset($data['position']) ? intval($data['position']) : null;
        $dataFullCount = isset($data['full_count']) ? $data['full_count'] : null;
        $tray = $this->modelClass::find()->where(['barcode' => $trayBarcode, 'active' => 1])->one();
        $shelf = \app\models\Shelf::find()->where(['barcode' => $dataShelf, 'active' => 1])->one();

        // Here are the five anomalies where we either throw an error
        // or shelve and flag, depending on the situation.

        // 1. If the shelf doesn't exist: with rapid shelve, this isn't a
        // problem, we should have created the shelf on the fly already.
        // When editing a tray manually, we should create the shelf first
        // and confirm it exists, so we throw an error in that case.
        if ($shelf == null) {
            // You can clear the shelf field in the manual tray edit form,
            // so it's not an error if this is a null string
            if ($dataShelf != "") {
                $shelf = $this->findOrCreateShelf($dataShelf, $userId);
            }
        }

        // 2. If the tray doesn't exist
        if ($tray == null) {
            if ($flagsAllowed == true) {
                $tray = $this->handleCreateTray($trayBarcode, $userId);
                $flagDetails[] = sprintf('Tray %s did not exist before being shelved', $trayBarcode);
            }
            else {
                throw new \yii\web\HttpException(400, sprintf('Tray %s does not exist', $trayBarcode));
            }
        }

        // 3. If the tray is empty
        if ($tray->items == []) {
            if ($flagsAllowed == true) {
                $flagDetails[] = sprintf('Tray %s was shelved when empty', $trayBarcode);
            }
            else {
                // Do nothing: This isn't actually a problem when updating
                // a tray manually, although it should be flagged if this
                // situation happens using the rapid shelve form.
            }
        }

        // 4. If the tray is already shelved
        $oldShelf = \app\models\Shelf::find()->where(['id' => $tray->shelf_id])->one();
        if ($tray && $tray->shelf_id != null) {
            if ($flagsAllowed == true) {
                $oldShelfBarcode = $oldShelf->barcode;
                $oldPosition = $tray->position == null ? 'null' : $tray->position;
                $oldDepth = $tray->depth == null ? 'null' : $tray->depth;
                // Don't worry about it unless it's actually a different location
                if ($oldShelfBarcode != $dataShelf || $oldPosition != $dataPosition || $oldDepth != $dataDepth) {
                    $flagDetails[] = sprintf('Tray %s was already on shelf %s, depth %s, position %s', $trayBarcode, $oldShelfBarcode, $oldDepth, $oldPosition);
                }
            }
            else {
                // This isn't an issue when using the non-rapid shelve form:
                // it's actually normal to be editing already-shelved trays
            }
        }

        // 5. If the location of the tray is already taken
        if ($shelf != null) {
            $shelfId = $shelf->id;
            $existingTray = $this->alreadyOccupyingTray($shelfId, $dataDepth, $dataPosition, $trayBarcode);
            if ($existingTray != null) {
                if ($flagsAllowed == true) {
                    $flagDetails[] = sprintf('Tray %s was assigned to shelf %s, depth %s, position %s, which was already occupied by tray %s', $trayBarcode, $dataShelf, $dataDepth, $dataPosition, $existingTray->barcode);
                }
                else {
                    throw new \yii\web\HttpException(400, sprintf('Shelf %s, depth %s, position %s is already occupied by tray %s', $dataShelf, $dataDepth, $dataPosition, $existingTray->barcode));
                }
            }
        }

        // If a barcode was provided and it's not the same as the current
        // one, check that it's not already in use (this doesn't happen with
        // the rapid shelve form)
        if (isset($data['new_barcode']) && $data['new_barcode'] != $trayBarcode) {
            $trayCheck = $this->modelClass::find()->where(['barcode' => $data['new_barcode']])->one();
            if ($trayCheck != null) {
                throw new \yii\web\HttpException(400, sprintf('Tray %s already exists', $data['new_barcode']));
            }
            $tray->barcode = $data['new_barcode'];
            $logDetails[] = sprintf("barcode %s", $data['new_barcode']);
        }

        // In each case, don't make any changes if a given parameter wasn't
        // provided -- but do clear to null if an empty string or 0 was provided

        // Size
        $sizeChanged = false;
        if (!is_null($dataSize)) {
            $size = \app\models\Size::find()->where(['code' => $data['size']])->one();
            if ($data['size'] && !$size) {
                throw new \yii\web\HttpException(400, sprintf('Size %s does not exist', $data['size']));
            }
            else if ($size == "") {
                $tray->size_id = null;
                $logDetails[] = sprintf("size null");
            }
            else if ($size->id != $tray->size_id) {
                $sizeChanged = true;
                $tray->size_id = $size->id;
                $logDetails[] = sprintf("size %s", $data['size']);
            }
        }
        // Collection
        $collectionChanged = false;
        if (!is_null($dataCollection)) {
            $collection = \app\models\Collection::find()->where(['name' => $data['collection']])->one();
            if ($data['collection'] && !$collection) {
                throw new \yii\web\HttpException(400, sprintf('Collection %s does not exist', $data['collection']));
            }
            else if ($collection == "") {
                $tray->collection_id = null;
                $logDetails[] = sprintf("collection null");
            }
            else if ($collection->id != $tray->collection_id) {
                $collectionChanged = true;
                $tray->collection_id = $collection->id;
                $logDetails[] = sprintf("collection %s", $data['collection']);
            }
        }

        // Shelf
        $shelfChanged = false;
        if (!is_null($dataShelf)) {
            // If there is a current shelf and the new shelf is different/null
            // or there is no current shelf and the new shelf is not null
            if ((isset($tray->shelf) && $dataShelf != $tray->shelf->barcode)
                || (!isset($tray->shelf) && $shelf)
            ) {
                $shelfChanged = true;
                $tray->shelf_id = $shelf == null ? null : $shelf->id;
                $logDetails[] = sprintf("shelf %s", $shelf == null ? "null" : $dataShelf);

                // Assign the shelf size and collection if not assigned yet
                // (the shelf will be flagged if the size is too big)
                if ($shelf != null) {
                    if (!$shelf->size_id && $tray->size_id) {
                        $sizeObject = \app\models\Size::find()->where(['id' => $tray->size_id])->one();
                        ShelfApiController::handleShelfUpdate(["barcode" => $shelf->barcode, "size" => $sizeObject->code], $userId);
                    }
                    if (!$shelf->collection_id && $tray->collection_id) {
                        $collectionObject = \app\models\Collection::find()->where(['id' => $tray->collection_id])->one();
                        ShelfApiController::handleShelfUpdate(["barcode" => $shelf->barcode, "collection" => $collectionObject->name], $userId);
                    }
                }
            }
        }
        // Depth
        if (!is_null($dataDepth)) {
            if ($dataDepth != $tray->depth) {
                $tray->depth = $dataDepth ? $dataDepth : null;
                $logDetails[] = sprintf("depth %s", $dataDepth ? $dataDepth : "null");
            }
        }
        // Position
        if (!is_null($dataPosition)) {
            if ($dataPosition != $tray->position) {
                $tray->position = $dataPosition ? $dataPosition : null;
                $logDetails[] = sprintf("position %s", $dataPosition ? $dataPosition : "null");
            }
        }
        // Full count
        if (!is_null($dataFullCount)) {
            if ($dataFullCount != $tray->full_count) {
                $tray->full_count = $dataFullCount ? $dataFullCount : null;
                $logDetails[] = sprintf("full count %s", $dataFullCount ? $dataFullCount : "null");
            }
        }

        // If the tray update results in partial location information, flag it
        if (($tray->shelf_id == null || $tray->depth == null || $tray->position == null) &&
                !($tray->shelf_id == null && $tray->depth == null && $tray->position == null)) {
            $flagDetails[] = sprintf('Tray %s was shelved with incomplete location information', $tray->barcode);
        }

        // If the tray size or collection doesn't match with the shelf, flag it
        if ($tray->shelf_id != null) {
            $shelf = \app\models\Shelf::find()->where(['id' => $tray->shelf_id])->one();
            // Log the flag if either the tray size or tray shelf changed
            if (($tray->size_id != null && $shelf->size_id != $tray->size_id)
                && ($shelfChanged || $sizeChanged)
            ) {
                $traySizeCode = \app\models\Size::find()->where(['id' => $tray->size_id])->one()->code;
                $shelfSizeCode = \app\models\Size::find()->where(['id' => $shelf->size_id])->one()->code;
                $flagDetails[] = sprintf('Size mismatch between tray %s (%s) and shelf %s (%s)', $tray->barcode, $traySizeCode, $shelf->barcode, $shelfSizeCode);
            }
            if (($tray->collection_id != null && $shelf->collection_id != $tray->collection_id)
                && ($shelfChanged || $collectionChanged)
            ) {
                $trayCollectionName = \app\models\Collection::find()->where(['id' => $tray->collection_id])->one()->name;
                $shelfCollectionName = \app\models\Collection::find()->where(['id' => $shelf->collection_id])->one()->name;
                $flagDetails[] = sprintf('Collection mismatch between tray %s (%s) and shelf %s (%s)', $tray->barcode, $trayCollectionName, $shelf->barcode, $shelfCollectionName);
            }
        }

        if ($flagDetails || $flag) {
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

        // If the tray is overfull, flag it
        $tray->flagTrayIfOverfull($userId);

        return $tray;
    }

    public function actionUpdateTray()
    {
        // We want the id, as well as the following optional fields:
        // new tray barcode, shelf barcode, depth, position.
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $tray = $this->handleTrayUpdate($data, $tokenCheck['id'], false);
            return $tray;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to update trays.');
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

        if ($tokenCheck['level'] >= 30) {
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
            throw new \yii\web\HttpException(403, 'You do not have permission to shelve trays');
        }
    }

    // Wrapper function for adding and shelving an archival box (which is
    // a tray with a single item, added via a combined accession/shelve
    // form) to the system
    public function actionNewBox()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 30) {
            // Create the shelf on the fly if it doesn't exist
            if (!isset($data['shelf']) || $data['shelf'] == "") {
                throw new \yii\web\HttpException(400, 'No shelf provided');
            }

            // For archival boxes, the item is provided along with the other
            // data, instead of being a pre-existing item. Create the item
            // first along with the tray before shelving.
            if ($data['items']) {
                $itemBarcode = $data['items'][0];
                $item = $this->itemClass::find()->where(['barcode' => $itemBarcode])->one();
                if ($item) {
                    throw new \yii\web\HttpException(400, sprintf('Item %s already exists', $itemBarcode));
                }
                $this->actionNewTray();
            }
            else {
                throw new \yii\web\HttpException(400, 'No item provided');
            }

            $newData = [
                'barcode' => $data['barcode'],
                'size' => isset($data['size']) ? $data['size'] : null,
                'collection' => isset($data['collection']) ? $data['collection'] : null,
                'shelf' => $data['shelf'],
                'depth' => $data['depth'],
                'position' => $data['position'],
                'full_count' => 1
            ];
            $tray = $this->handleTrayUpdate($newData, $tokenCheck['id'], true);
            return $tray;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to add or shelve trays');
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
            $previousStatus = $tray->active;

            if ($tray->shelf_id == null) {
                $oldLocation = 'not shelved';
            }
            else {
                $oldLocation = sprintf("shelf %s, depth %s, position %s", $tray->shelf->barcode, $tray->depth, $tray->position);
            }
            // Clear shelf, otherwise it gets counted as occupying a space
            // when we look for shelves without space
            $tray->shelf_id = null;
            $tray->depth = null;
            $tray->position = null;
            $tray->active = 0;
            $tray->save();

            $trayLog = new $this->modelLogClass;
            $trayLog->tray_id = $tray->id;
            $trayLog->action = 'Deleted';
            if ($previousStatus == 0) {
                $trayLog->details = sprintf("Deleted tray %s (was already deleted)", $tray->barcode);
            }
            else {
                $trayLog->details = sprintf("Deleted tray %s (%s)", $tray->barcode, $oldLocation);
            }
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
            throw new \yii\web\HttpException(403, 'You do not have permission to delete trays');
        }
    }

    public function actionSearch()
    {
        $barcode = isset($_REQUEST["barcode"]) ? $_REQUEST["barcode"] : null;
        $freeSpace = isset($_REQUEST["free_space"]) ? $_REQUEST["free_space"] : null;
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        // TODO: Get this from settings, or from the average size
        // defined on each tray/shelf size
        $averageCount = 16;

        if ($tokenCheck['level'] >= 20) {
            // If a barcode has been provided, search by barcode and return
            // up to 20 results
            $query = $this->modelClass::find()
                ->select(['tray.*', 'size.code AS size', 'collection.name AS collection', 'COUNT(item.id) AS total_items', 'full_count - COUNT(item.id) AS free_space'])
                ->leftJoin('item', 'tray.id = item.tray_id')
                ->leftJoin('size', 'tray.size_id = size.id')
                ->leftJoin('collection', 'tray.collection_id = collection.id')
                ->groupBy('tray.id')
                ->andWhere(['tray.active' => true]);
            if ($barcode) {
                $query->andWhere(['tray.barcode' => $barcode]);
            }
            // If we're searching for free space at all
            if ($freeSpace) {
                // With finding trays with ANY space, include all trays
                // with a null full count, as well as any that definitely
                // have room
                if ($freeSpace <= 1) {
                    $query->having(['or',
                        ['>=', 'free_space', $freeSpace],
                        ['full_count' => null]
                    ]);
                }
                // If we are searching for a more specific amount of space,
                // only include trays that have that amount of space, or are
                // likely to do so given tray averages
                else {
                    $query->having(['or',
                        ['>=', 'free_space', $freeSpace],
                        ['and', ['full_count' => null], ['<=', 'total_items', $averageCount - $freeSpace]]
                    ]);
                }
            }
            $provider = new ActiveDataProvider([
                'query' => $query,
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
            throw new \yii\web\HttpException(403, 'You do not have permission to view trays');
        }
    }

    public function actionSearchByLocation()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $shelfBarcode = isset($data['shelf']) ? $data['shelf'] : null;
        $depth = isset($data['depth']) ? $data['depth'] : null;
        $position = isset($data['position']) ? $data['position'] : null;

        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $shelf = Shelf::find()->where(['barcode' => $shelfBarcode])->one();
            if ($shelf == null) {
                return [];
            }
            else {
                // Use the shelf ID, depth, and position to search for trays
                $trays = $this->modelClass::find()
                    ->where(['shelf_id' =>  $shelf->id])
                    ->andWhere(['depth' => $depth])
                    ->andWhere(['position' => $position])
                    ->andWhere(['active' => true])
                    ->all();
                return $trays;
            }
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view trays');
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
            throw new \yii\web\HttpException(403, 'You do not have permission to view trays');
        }
    }

    public function actionFindGaps()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        $problems = [];

        if ($tokenCheck['level'] >= 40) {
            $allTrays = $this->modelClass::find()
                ->where(['active' => 1])
                ->andWhere(['not', ['depth' => null]])
                ->andWhere(['not', ['position' => null]])
                ->orderBy([
                    'shelf_id' => SORT_ASC,
                    'depth' => SORT_DESC,
                    'position' => SORT_ASC,
                ])
                ->asArray()
                ->all();
            for ($i = 1; $i < count($allTrays); $i++) {
                if ($allTrays[$i]['position'] == 1) {
                    continue;
                }
                else {
                    $previousTray = $allTrays[$i-1];
                    $currentTray = $allTrays[$i];
                    if ($previousTray['shelf_id'] != $currentTray['shelf_id'] || $previousTray['depth'] != $currentTray['depth'] || $previousTray['position'] != $currentTray['position'] - 1) {
                        // Don't add to problem list if the shelf is empty.
                        // That represents an unshelved tray -- or rather a
                        // tray that is not marked as shelved in the system --
                        // and that is a separate problem to worry about.
                        if ($currentTray['shelf_id']) {
                            $shelfId = $currentTray['shelf_id'];
                            $shelfBarcode = Shelf::find()->where(['id' => $shelfId])->one()->barcode;
                            $thisProblem = [
                                'shelf' => $shelfBarcode,
                                'depth' => $currentTray['depth'],
                                'position' => $currentTray['position'] - 1,
                            ];
                            $problems[] = $thisProblem;
                        }
                    }
                }
            }
            return $problems;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to do this operation');
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

    public function actionCountsCollectionSize()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 60) {
            $collectionSizeCounts = $this->modelClass::find()
                ->select('tray.collection_id, tray.size_id, collection.code as collection_code, collection.name as collection_name, size.code as size, count(*) as count')
                ->leftJoin('collection', 'tray.collection_id = collection.id')
                ->leftJoin('size', 'tray.size_id = size.id')
                ->where(['tray.active' => 1])
                ->groupBy(['tray.collection_id', 'tray.size_id'])
                ->asArray()
                ->all();
            return $collectionSizeCounts;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view tray counts by collection and size.');
        }
    }

}
