<?php

namespace app\controllers;

use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Size;
use app\models\Collection;
use app\models\User;

const SHELF_FULL = 0;
const SHELF_EMPTY = -1;
const UNSHELVED = -2;

class ShelfApiController extends ActiveController
{
    public $modelClass = 'app\models\Shelf';
    public $modelLogClass = 'app\models\ShelfLog';

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
        $modelClass = 'app\models\Collection';
        $dataProvider = new ActiveDataProvider([
            'query' => $modelClass::find(),
            'pagination' => false,
        ]);
        return $dataProvider;
    }

    public function actionGetShelf()
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
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelves');
        }
    }

    public function actionNewShelf()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            $shelfBarcode = array_key_exists('barcode', $data) ? $data['barcode'] : '';

            // Make sure the barcode is exactly 7 characters long
            if (strlen($shelfBarcode) != 7) {
                throw new \yii\web\HttpException(400, sprintf('Barcode %s must be exactly 7 characters long', $shelfBarcode));
            }
            // Fill in row, side, ladder and rung from the barcode
            $shelfRow = substr($shelfBarcode, 0, 2);
            $shelfSide = substr($shelfBarcode, 2, 1);
            $shelfLadder = substr($shelfBarcode, 3, 2);
            $shelfRung = substr($shelfBarcode, 5, 2);

            // If shelf already exists, return error
            if (\app\models\Shelf::find()->where(['barcode' => $shelfBarcode])->andWhere(['active' => true])->all() != []) {
                throw new \yii\web\HttpException(400, sprintf('Shelf %s already exists', $shelfBarcode));
            }

            // Ensure that the barcode matches the row, side, ladder, and rung
            if ($shelfBarcode != sprintf('%s%s%s%s', $shelfRow, $shelfSide, $shelfLadder, $shelfRung)) {
                throw new \yii\web\HttpException(400, sprintf('Barcode %s does not match row, side, ladder and rung information', $shelfBarcode));
            }

            // If the shelf used to exist, reactivate it instead of
            // creating a new object
            // If it already exists but is inactive, reactivate it
            if (\app\models\Shelf::find()->where(['barcode' => $shelfBarcode])->all() != []) {
                $shelf = \app\models\Shelf::find()->where(['barcode' => $shelfBarcode])->one();
                $shelf->row = $shelfRow;
                $shelf->side = $shelfSide;
                $shelf->ladder = $shelfLadder;
                $shelf->rung = $shelfRung;
                $shelf->active = 1;
                $shelf->save();
                // Log the reactivation
                $shelfLog = new $this->modelLogClass;
                $shelfLog->shelf_id = $shelf->id;
                $shelfLog->action = 'Restored';
                $shelfLog->details = sprintf("Restored shelf %s", $shelf->barcode);
                $shelfLog->user_id = $tokenCheck['id'];
                $shelfLog->save();
            }
            else {
                $shelf = new $this->modelClass;
                $shelf->barcode = $shelfBarcode;
                $shelf->row = $shelfRow;
                $shelf->side = $shelfSide;
                $shelf->ladder = $shelfLadder;
                $shelf->rung = $shelfRung;
                $shelf->active = 1;
                // Log the new shelf
                $shelfLog = new $this->modelLogClass;
                $shelfLog->shelf_id = $shelf->id;
                $shelfLog->action = 'Added';
                $shelfLog->details = sprintf("Added shelf %s", $shelf->barcode);
                $shelfLog->user_id = $tokenCheck['id'];
                $shelfLog->save();
            }

            $shelf->save();
            return $shelf;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to create shelves');
        }
    }

    public static function handleShelfUpdate($data, $userId)
    {
        $shelfBarcode = array_key_exists('barcode', $data) ? $data['barcode'] : '';
        $newBarcode = array_key_exists('new_barcode', $data) ? $data['new_barcode'] : null;
        $row = array_key_exists('row', $data) ? $data['row'] : null;
        if ($row !== null && strlen($row) < 2) {
            $row = str_pad($row, 2, '0', STR_PAD_LEFT);
        }
        $side = array_key_exists('side', $data) ? $data['side'] : null;
        $ladder = array_key_exists('ladder', $data) ? $data['ladder'] : null;
        if ($ladder !== null && strlen($ladder) < 2) {
            $ladder = str_pad($ladder, 2, '0', STR_PAD_LEFT);
        }
        $rung = array_key_exists('rung', $data) ? $data['rung'] : null;
        if ($rung !== null && strlen($rung) < 2) {
            $rung = str_pad($rung, 2, '0', STR_PAD_LEFT);
        }
        $height = array_key_exists('height', $data) ? $data['height'] : null;
        $width = array_key_exists('width', $data) ? $data['width'] : null;
        $size = array_key_exists('size', $data) ? $data['size'] : null;
        $collection = array_key_exists('collection', $data) ? $data['collection'] : null;
        $depths = array_key_exists('depths', $data) ? $data['depths'] : null;
        $positions = array_key_exists('positions', $data) ? $data['positions'] : null;
        $notes = array_key_exists('notes', $data) ? $data['notes'] : null;
        $flag = array_key_exists('flag', $data) ? $data['flag'] : null;

        $shelf = \app\models\Shelf::find()->where(['barcode' => $shelfBarcode, 'active' => true])->one();
        if (!$shelf) {
            throw new \yii\web\HttpException(400, sprintf('Shelf %s does not exist', $shelfBarcode));
        }
        if (!$shelf->active) {
            throw new \yii\web\HttpException(400, sprintf('Shelf %s has been deleted', $shelfBarcode));
        }
        $shelfLog = new \app\models\ShelfLog;
        $logDetails = [];
        $flagDetails = [];

        // If a barcode was provided and it's not the same as the current
        // one, check that it's not already in use
        if ($newBarcode !== null && $newBarcode != $shelfBarcode) {
            $shelfCheck = \app\models\Shelf::find()->where(['barcode' => $newBarcode])->one();
            if ($shelfCheck != null) {
                throw new \yii\web\HttpException(400, sprintf('Shelf %s already exists', $shelfBarcode));
            }
            $logDetails[] = sprintf("barcode %s", $newBarcode);
            $shelf->barcode = $newBarcode;
        }

        // Row
        if ($row !== null && $row != $shelf->row) {
            $logDetails[] = sprintf('row %s', $row === "" ? "null" : $row);
            $shelf->row = $row === "" ? null : $row;
        }
        // Side
        if ($side !== null && $side != $shelf->side) {
            $logDetails[] = sprintf('side %s', $side === "" ? "null" : $side);
            $shelf->side = $side === "" ? null : $side;
        }
        // Ladder
        if ($ladder !== null && $ladder != $shelf->ladder) {
            $ladder = strlen($ladder) == 1 ? '0' . $ladder : $ladder;
            $logDetails[] = sprintf('ladder %s', $ladder === "" ? "null" : $ladder);
            $shelf->ladder = $ladder === "" ? null : $ladder;
        }
        // Rung
        if ($rung !== null && $rung != $shelf->rung) {
            $logDetails[] = sprintf('rung %s', $rung === "" ? "null" : $rung);
            $shelf->rung = $rung === "" ? null : $rung;
        }
        // Height
        if ($height !== null && $height != $shelf->height) {
            $logDetails[] = sprintf('height %s', $height === "" ? "null" : $height);
            $shelf->height = $height === "" ? null : $height;
        }
        // Width
        if ($width !== null && $width != $shelf->width) {
            $logDetails[] = sprintf('width %s', $width === "" ? "null" : $width);
            $shelf->width = $width === "" ? null : $width;
        }
        // Size
        if ($size !== null && $size !== "") {
            $sizeObject = Size::find()->where(['code' => $size])->one();
        }
        if ($size !== null) {
            if ($size === "") {
                if ($shelf->size_id) {
                    $logDetails[] = sprintf('size null');
                }
                $shelf->size_id = null;
            }
            else {
                if (!$sizeObject) {
                    throw new \yii\web\HttpException(400, sprintf('Size %s does not exist', $size));
                }
                else if ($sizeObject->id != $shelf->size_id) {
                    $logDetails[] = sprintf('size %s', $size);
                    $shelf->size_id = $sizeObject->id;

                    // Calculate new positions and depths if not manually given
                    if ($sizeObject && $positions === null) {
                        $positions = $sizeObject->width && $shelf->width ? floor($shelf->width / $sizeObject->width) : null;
                    }
                    if ($sizeObject && $depths === null) {
                        $depths = $sizeObject->depths ? $sizeObject->depths : null;
                    }
                }
            }
        }
        // Collection
        if ($collection !== null) {
            if ($collection === "") {
                if ($shelf->collection_id) {
                    $logDetails[] = sprintf('size null');
                }
                $shelf->collection_id = null;
            }
            else {
                $collectionObject = Collection::find()->where(['name' => $collection])->one();
                if (!$collectionObject) {
                    throw new \yii\web\HttpException(400, sprintf('Collection %s does not exist', $collection));
                }
                else if ($collectionObject->id != $shelf->collection_id) {
                    $logDetails[] = sprintf('collection %s', $collection);
                    $shelf->collection_id = $collectionObject->id;
                }
            }
        }
        // Positions
        if ($positions !== null && $positions != $shelf->positions) {
            $logDetails[] = sprintf('positions %s', $positions === "" ? "null" : $positions);
            $shelf->positions = $positions === "" ? null : $positions;
        }
        // Depths
        if ($depths !== null && $depths != $shelf->depths) {
            $logDetails[] = sprintf('depths %s', $depths === "" ? "null" : $depths);
            $shelf->depths = $depths === "" ? null : $depths;
        }
        // Capacity
        $shelf->capacity = $shelf->depths * $shelf->positions;
        // Notes
        if ($notes !== null && $notes != $shelf->notes) {
            $logDetails[] = sprintf('notes');
            $shelf->notes = $notes;
        }

        // Unflag if specifically set to false or empty string (not null).
        // If there is a condition that would set the flag, it will get
        // reflagged right away.
        if ($shelf->flag && ($flag !== null && !$flag)) {
            $shelf->flag = 0;
            // Add separate log entry for unflagging
            $unflagLog = new \app\models\ShelfLog;
            $unflagLog->shelf_id = $shelf->id;
            $unflagLog->action = 'Unflagged';
            $unflagLog->details = sprintf("Unflagged shelf %s", $shelf->barcode);
            $unflagLog->user_id = $userId;
            $unflagLog->save();
        }

        // Flag shelf if size doesn't fit height
        if (isset($sizeObject->height) && $sizeObject->height && isset($shelf->height) && $shelf->height && $sizeObject->height > $shelf->height) {
            $flagDetails[] = sprintf('Size %s does not fit height %s', $size, $shelf->height);
            $shelf->flag = 1;
        }
        // Flag
        else if ($flag) {
            if (!$shelf->flag) {
                $flagDetails[] = sprintf("Flagged shelf %s (manual)", $shelf->barcode);
            }
            $shelf->flag = 1;
        }

        $shelf->save();

        $shelfLog->shelf_id = $shelf->id;
        $shelfLog->action = 'Updated';
        if ($logDetails) {
            $shelfLog->details = sprintf("Updated shelf %s: %s", $shelf->barcode, implode(', ', $logDetails));
        }
        else {
            $shelfLog->details = sprintf("Updated shelf %s (no changes)", $shelf->barcode);
        }
        $shelfLog->user_id = $userId;
        $shelfLog->save();

        // Log any flags that occurred
        foreach ($flagDetails as $flagDetail) {
            $flagLog = new \app\models\shelfLog;
            $flagLog->shelf_id = $shelf->id;
            $flagLog->action = 'Flagged';
            $flagLog->details = $flagDetail;
            $flagLog->user_id = $userId;
            $flagLog->save();
        }

        return $shelf;
    }

    public function actionUpdateShelf()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            return $this->handleShelfUpdate($data, $tokenCheck['id']);
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to update shelves');
        }
    }

    public function actionDeleteShelf()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 80) {
            $shelfBarcode = array_key_exists('barcode', $data) ? $data['barcode'] : '';
            $shelf = \app\models\Shelf::find()->where(['barcode' => $shelfBarcode])->one();
            if (!$shelf) {
                throw new \yii\web\HttpException(400, sprintf('Shelf %s does not exist', $shelfBarcode));
            }
            if (!$shelf->active) {
                throw new \yii\web\HttpException(400, sprintf('Shelf %s has already been deleted', $shelfBarcode));
            }
            // Log the deletion
            $shelfLog = new $this->modelLogClass;
            $shelfLog->shelf_id = $shelf->id;
            $shelfLog->action = 'Deleted';
            $shelfLog->details = sprintf("Deleted shelf %s", $shelf->barcode);
            $shelfLog->user_id = $tokenCheck['id'];
            $shelfLog->save();
            // Set the shelf to inactive
            $shelf->active = 0;
            $shelf->save();
            return true;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to delete shelves');
        }
    }

    public function actionSearch()
    {
        $shelfBarcode = isset($_REQUEST["shelf"]) && $_REQUEST["shelf"] !== "" ? str_replace('-', '_', $_REQUEST["shelf"]) : "_______";
        $trayBarcode = isset($_REQUEST["tray"]) && $_REQUEST["tray"] !== "" ? $_REQUEST["tray"] : '';
        $size = isset($_REQUEST["size"]) && $_REQUEST["size"] !== "" ? $_REQUEST["size"] : null;
        $collection = isset($_REQUEST["collection"]) && $_REQUEST["collection"] !== "" ? $_REQUEST["collection"] : null;
        $positionsFree = isset($_REQUEST["positions_free"]) && $_REQUEST["positions_free"] !== "" ? $_REQUEST["positions_free"] : null;
        $flaggedOnly = isset($_REQUEST["flagged_only"]) && $_REQUEST["flagged_only"] !== "" ? $_REQUEST["flagged_only"] == 'true' || $_REQUEST["flagged_only"] == 1 : false;
        if (isset($_REQUEST["height"]) && $_REQUEST["height"] !== "") {
            if ($_REQUEST["height"] === "-") {
                $height = null;
                $heightNotSet = true;
            } else {
                $height = $_REQUEST["height"];
                $heightNotSet = false;
            }
        } else {
            $height = null;
            $heightNotSet = false;
        }
        if (isset($_REQUEST["width"]) && $_REQUEST["width"] !== "") {
            if ($_REQUEST["width"] === "-") {
                $width = null;
                $widthNotSet = true;
            } else {
                $width = $_REQUEST["width"];
                $widthNotSet = false;
            }
        } else {
            $width = null;
            $widthNotSet = false;
        }
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        // Be defensive: Size::find()->one() or Collection::find()->one() may return null on the server
        $sizeId = null;
        if ($size) {
            $sizeObj = Size::find()->where(['code' => $size])->one();
            $sizeId = $sizeObj ? $sizeObj->id : null;
        }
        $collectionId = null;
        if ($collection) {
            $collectionObj = Collection::find()->where(['name' => $collection])->andWhere(['active' => true])->one();
            $collectionId = $collectionObj ? $collectionObj->id : null;
        }

        if ($tokenCheck['level'] >= 20) {
            // If the user is searching for unshelved trays
            if ($positionsFree == UNSHELVED) {
                // Query unshelved trays directly, but format results as shelf API structure
                $query = \app\models\Tray::find()
                    ->where(['tray.active' => 1, 'tray.shelf_id' => null])
                    ->andFilterWhere(['tray.size_id' => $sizeId])
                    ->andFilterWhere(['tray.collection_id' => $collectionId]);
                if ($trayBarcode != '') {
                    $query->andWhere(['like', 'tray.barcode', $trayBarcode]);
                }
                else {
                    $query->andWhere(['or', ['tray.active' => 1], ['tray.id' => null]]);
                }
                if ($flaggedOnly) {
                    $query->andWhere(['tray.flag' => 1]);
                }
                $trays = $query->all();

                $results = [];
                foreach ($trays as $tray) {
                    $results[] = [
                        "id" => null,
                        "barcode" => "[Unshelved]",
                        "row" => null,
                        "side" => null,
                        "ladder" => null,
                        "rung" => null,
                        "active" => true,
                        "flag" => true,
                        "size" => null,
                        "collection" => null,
                        "trays" => [$tray],
                        "capacity" => null,
                        "depths" => null,
                        "positions" => null,
                    ];
                }
                return [
                    'unshelved' => true,
                    'resultCount' => count($results),
                    'results' => $results,
                ];
            }
            // If the user is searching for empty shelves
            else if ($positionsFree == SHELF_EMPTY) {
                $query = $this->modelClass::find()
                    ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                    ->where(['like', 'shelf.barcode', $shelfBarcode, false]) // false parameter because we are providing wildcards manually
                    ->andFilterWhere(['shelf.size_id' => $sizeId])
                    ->andFilterWhere(['shelf.collection_id' => $collectionId])
                    ->andFilterWhere(['shelf.height' => $height])
                    ->andFilterWhere(['shelf.width' => $width])
                    ->andWhere(['shelf.active' => true])
                    ->andWhere(['tray.id' => null]);
                if ($flaggedOnly) {
                    $query->andWhere(['shelf.flag' => 1]);
                }
                if ($heightNotSet) {
                    $query->andWhere(['or', ['shelf.height' => null], ['shelf.height' => '']]);
                }
                if ($widthNotSet) {
                    $query->andWhere(['or', ['shelf.width' => null], ['shelf.width' => '']]);
                }
                $provider = new ActiveDataProvider([
                    'query' => $query->groupBy(['shelf.id']),
                    'sort' => [
                        'defaultOrder' => [
                            'barcode' => SORT_ASC,
                        ]
                    ],
                    'pagination' => [
                        'pageSize' => 60,
                    ],
                ]);
            }
            else if ($positionsFree == SHELF_FULL && $positionsFree !== null) {
                $query = $this->modelClass::find()
                    ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                    ->where(['like', 'shelf.barcode', $shelfBarcode, false]) // false parameter because we are providing wildcards manually
                    ->andFilterWhere(['shelf.size_id' => $sizeId])
                    ->andFilterWhere(['shelf.collection_id' => $collectionId])
                    ->andFilterWhere(['shelf.height' => $height])
                    ->andFilterWhere(['shelf.width' => $width])
                    ->andWhere(['shelf.active' => true])
                    ->andWhere(['or', ['tray.active' => true], ['tray.id' => null]]);
                if ($trayBarcode != '') {
                    $query->andWhere(['like', 'tray.barcode', $trayBarcode]);
                    $query->andWhere(['tray.active' => 1]);
                }
                else {
                    $query->andWhere(['or', ['tray.active' => 1], ['tray.id' => null]]);
                }
                if ($flaggedOnly) {
                    // Use array-form condition so Yii builds a valid SQL expression
                    $query->andWhere(['or', ['shelf.flag' => 1], ['tray.flag' => 1]]);
                }
                if ($heightNotSet) {
                    $query->andWhere(['or', ['shelf.height' => null], ['shelf.height' => '']]);
                }
                if ($widthNotSet) {
                    $query->andWhere(['or', ['shelf.width' => null], ['shelf.width' => '']]);
                }
                $provider = new ActiveDataProvider([
                    'query' => $query
                        ->groupBy(['capacity', 'shelf.id'])
                        ->having('cast(shelf.capacity as signed) - count(tray.id) <= 0'),
                    'sort' => [
                        'defaultOrder' => [
                            'barcode' => SORT_ASC,
                        ]
                    ],
                    'pagination' => [
                        'pageSize' => 60,
                    ],
                ]);
            }
            else if ($positionsFree > 0) {
                $query = $this->modelClass::find()
                    ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                    ->where(['like', 'shelf.barcode', $shelfBarcode, false]) // false parameter because we are providing wildcards manually
                    ->andFilterWhere(['shelf.size_id' => $sizeId])
                    ->andFilterWhere(['shelf.collection_id' => $collectionId])
                    ->andFilterWhere(['shelf.height' => $height])
                    ->andFilterWhere(['shelf.width' => $width])
                    ->andWhere(['shelf.active' => true])
                    ->andWhere(['or', ['tray.active' => true]]); // need at least one tray
                if ($trayBarcode != '') {
                    $query->andWhere(['like', 'tray.barcode', $trayBarcode]);
                    $query->andWhere(['tray.active' => 1]);
                }
                else {
                    $query->andWhere(['or', ['tray.active' => 1], ['tray.id' => null]]);
                }
                if ($flaggedOnly) {
                    $query->andWhere(['or', ['shelf.flag' => 1], ['tray.flag' => 1]]);
                }
                if ($heightNotSet) {
                    $query->andWhere(['or', ['shelf.height' => null], ['shelf.height' => '']]);
                }
                if ($widthNotSet) {
                    $query->andWhere(['or', ['shelf.width' => null], ['shelf.width' => '']]);
                }
                $provider = new ActiveDataProvider([
                    'query' => $query
                        ->groupBy(['capacity', 'shelf.id'])
                        ->having('cast(shelf.capacity as signed) - count(tray.id) >= :positionsFree', [':positionsFree' => $positionsFree]),
                    'sort' => [
                        'defaultOrder' => [
                            'barcode' => SORT_ASC,
                        ]
                    ],
                    'pagination' => [
                        'pageSize' => 60,
                    ],
                ]);
            }
            else {
                $query = $this->modelClass::find();
                if ($trayBarcode != '') {
                    $query->rightJoin('tray', 'tray.shelf_id = shelf.id')
                    ->filterWhere(['like', 'shelf.barcode', $shelfBarcode, false]) // false parameter because we are providing wildcards manually
                    ->andFilterWhere(['tray.size_id' => $sizeId])
                    ->andFilterWhere(['tray.collection_id' => $collectionId])
                    ->andFilterWhere(['tray.height' => $height])
                    ->andFilterWhere(['tray.width' => $width])
                    ->andWhere(['like', 'tray.barcode', $trayBarcode])
                    ->andWhere(['tray.active' => 1]);
                }
                else {
                    $query->leftJoin('tray', 'tray.shelf_id = shelf.id')
                    ->filterWhere(['like', 'shelf.barcode', $shelfBarcode, false]) // false parameter because we are providing wildcards manually
                    ->andFilterWhere(['shelf.size_id' => $sizeId])
                    ->andFilterWhere(['shelf.collection_id' => $collectionId])
                    ->andFilterWhere(['shelf.height' => $height])
                    ->andFilterWhere(['shelf.width' => $width])
                    ->andWhere(['or', ['shelf.active' => 1], ['shelf.active' => null]])
                    ->andWhere(['or', ['tray.active' => 1], ['tray.id' => null]]);
                }
                if ($flaggedOnly) {
                    $query->andWhere(['or', ['shelf.flag' => 1], ['tray.flag' => 1]]);
                }
                if ($heightNotSet) {
                    $query->andWhere(['or', ['height' => null], ['height' => '']]);
                }
                if ($widthNotSet) {
                    $query->andWhere(['or', ['width' => null], ['width' => '']]);
                }
                $provider = new ActiveDataProvider([
                    'query' => $query,
                    'sort' => [
                        'defaultOrder' => [
                            'barcode' => SORT_ASC,
                        ]
                    ],
                    'pagination' => [
                        'pageSize' => 60,
                    ],
                ]);
            }
            return [
                'resultCount' => $provider->getTotalCount(),
                'results' => $provider->getModels()
            ];
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelves');
        }
    }

    public function actionTotalCount()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $totalCount = $this->modelClass::find()
                ->where(['active' => 1])
                ->andWhere(['or', ['capacity' => null], ['>', 'capacity', 0]])
                ->count();
            return $totalCount;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view the total count.');
        }
    }

    public function actionLadderCount()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $ladderCount = $this->modelClass::find()
                ->select('row, side, ladder')
                ->where(['active' => 1])
                ->andWhere(['not', ['row' => null]])
                ->distinct()
                ->count();
            return $ladderCount;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view the ladder count.');
        }
    }

    public function actionCountsCollectionSize()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $collectionSizeCounts = $this->modelClass::find()
                ->select('collection_id, size_id, collection.code as collection_code, collection.name as collection_name, size.code as size, count(*) as count')
                ->leftJoin('collection', 'shelf.collection_id = collection.id')
                ->leftJoin('size', 'shelf.size_id = size.id')
                ->where(['shelf.active' => 1])
                ->andWhere(['or', ['shelf.capacity' => null], ['>', 'shelf.capacity', 0]])
                ->groupBy(['collection_id', 'size_id'])
                ->asArray()
                ->all();
            return $collectionSizeCounts;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelf counts by collection and size.');
        }
    }

    public function actionSpaceUsage()
    {
        $LABEL_SHELVES = "Shelves";
        $LABEL_TRAYS = "Total trays";
        $LABEL_CAPACITY = "Capacity";
        $LABEL_EMPTY = "Empty";
        $LABEL_PARTIAL = "Partial/full";
        $LABEL_FULL = "Full";

        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 40) {
            $query = $this->modelClass::find()
                ->select([
                    'collection.code AS collection_code',
                    'size.code AS size_code',
                    'shelf.barcode AS barcode',
                    'IF(tray.id, count(*), 0) AS shelf_count',
                    'shelf.capacity AS capacity'
                ])
                ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                ->leftJoin('size', 'size.id = shelf.size_id')
                ->leftJoin('collection', 'collection.id = shelf.collection_id')
                ->where(['or', ['tray.active' => 1], ['tray.id' => null]])
                ->andWhere(['shelf.active' => 1])
                ->andWhere(['or', ['shelf.capacity' => null], ['>', 'shelf.capacity', 0]])
                ->groupBy('shelf.id')
                ->orderBy(['collection.id' => SORT_ASC, 'size.id' => SORT_ASC])
                ->asArray()
                ->all();

            $results = [];
            foreach ($query as $row) {
                // PHP stores null array keys as empty strings anyway
                $collectionCode = $row['collection_code'] ?: "";
                $sizeCode = $row['size_code'] ?: "";
                if (!isset($results[$collectionCode])) {
                    $results[$collectionCode] = [];
                }
                if (!isset($results[$collectionCode][$sizeCode])) {
                    $results[$collectionCode][$sizeCode] = [
                        $LABEL_SHELVES => 0,
                        $LABEL_CAPACITY => 0,
                        $LABEL_TRAYS => 0,
                        $LABEL_FULL => 0,
                        $LABEL_PARTIAL => 0,
                        $LABEL_EMPTY => 0,
                    ];
                }
                $results[$collectionCode][$sizeCode][$LABEL_SHELVES]++;
                $results[$collectionCode][$sizeCode][$LABEL_TRAYS] += $row['shelf_count'];
                if ($row['capacity']) {
                    $results[$collectionCode][$sizeCode][$LABEL_CAPACITY] += $row['capacity'];
                }
                if ($row['shelf_count'] == 0 || $row['shelf_count'] === null) {
                    $results[$collectionCode][$sizeCode][$LABEL_EMPTY]++;
                }
                elseif ($row['shelf_count'] && $row['capacity'] && $row['shelf_count'] >= $row['capacity']) {
                    $results[$collectionCode][$sizeCode][$LABEL_FULL]++;
                }
                else {
                    $results[$collectionCode][$sizeCode][$LABEL_PARTIAL]++;
                }
            }

            return $results;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelf space usage.');
        }
    }

    public function actionGetAllHeights()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $heights = $this->modelClass::find()
                ->select('height')
                ->where(['active' => true])
                ->andWhere(['not', ['height' => null]])
                ->distinct()
                ->orderBy('height')
                ->asArray()
                ->all();
            return array_column($heights, 'height');
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelf heights.');
        }
    }

    public function actionGetAllWidths()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        if ($tokenCheck['level'] >= 20) {
            $widths = $this->modelClass::find()
                ->select('width')
                ->where(['active' => true])
                ->andWhere(['not', ['width' => null]])
                ->distinct()
                ->orderBy('width')
                ->asArray()
                ->all();
            return array_column($widths, 'width');
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelf widths.');
        }
    }

}
