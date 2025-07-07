<?php

namespace app\controllers;

use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Size;
use app\models\Collection;
use app\models\TrayLog;
use app\models\User;

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
                // Log the new tray
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
        $side = array_key_exists('side', $data) ? $data['side'] : null;
        $ladder = array_key_exists('ladder', $data) ? $data['ladder'] : null;
        $rung = array_key_exists('rung', $data) ? $data['rung'] : null;
        $width = array_key_exists('width', $data) ? $data['width'] : null;
        $height = array_key_exists('height', $data) ? $data['height'] : null;
        $size = array_key_exists('size', $data) ? $data['size'] : null;
        $collection = array_key_exists('collection', $data) ? $data['collection'] : null;
        $capacity = array_key_exists('capacity', $data) ? $data['capacity'] : null;
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
        $trayLog = new \app\models\ShelfLog;
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
        // Width
        if ($width !== null && $width != $shelf->width) {
            $logDetails[] = sprintf('width %s', $width === "" ? "null" : $width);
            $shelf->width = $width === "" ? null : $width;
        }
        // Height
        if ($height !== null && $height != $shelf->height) {
            $logDetails[] = sprintf('height %s', $height === "" ? "null" : $height);
            $shelf->height = $height === "" ? null : $height;
        }
        // Size
        if ($size !== null) {
            if ($size === "") {
                $logDetails[] = sprintf('size null');
                $shelf->size_id = null;
            }
            else {
                $sizeObject = Size::find()->where(['code' => $size])->one();
                if (!$sizeObject) {
                    throw new \yii\web\HttpException(400, sprintf('Size %s does not exist', $size));
                }
                else if ($sizeObject->id != $shelf->size_id) {
                    $logDetails[] = sprintf('size %s', $size);
                    $shelf->size_id = $sizeObject->id;

                    // Flag shelf if size doesn't fit height
                    if ($sizeObject->height && $shelf->height && $sizeObject->height > $shelf->height) {
                        $flagDetails[] = sprintf('Size %s does not fit height %s', $size, $shelf->height);
                    }

                    // Calculate new capacity, positions, and depths if
                    // they weren't manually given
                    if ($sizeObject && $capacity === null) {
                        $capacity = $sizeObject->width && $shelf->width ? $sizeObject->depths * floor($shelf->width / $sizeObject->width) : null;
                    }
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
                $logDetails[] = sprintf('collection null');
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
        // Capacity
        if ($capacity !== null && $capacity != $shelf->capacity) {
            $logDetails[] = sprintf('capacity %s', $capacity === "" ? "null" : $capacity);
            $shelf->capacity = $capacity === "" ? null : $capacity;
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
        // Notes
        if ($notes !== null && $notes != $shelf->notes) {
            $logDetails[] = sprintf('notes');
            $shelf->notes = $notes;
        }

        if ($flagDetails || $flag) {
            $shelf->flag = 1;
            $flagLog = new \app\models\ShelfLog;
            $flagLog->shelf_id = $shelf->id;
            $flagLog->action = 'Flagged';
            $flagLog->details = sprintf("Flagged shelf %s: %s", $shelf->barcode, implode('; ', $flagDetails));
            $flagLog->user_id = $userId;
            $flagLog->save();
        }

        $shelf->save();

        $trayLog->shelf_id = $shelf->id;
        $trayLog->action = 'Updated';
        if ($logDetails) {
            $trayLog->details = sprintf("Updated shelf %s: %s", $shelf->barcode, implode(', ', $logDetails));
        }
        else {
            $trayLog->details = sprintf("Updated shelf %s (no changes)", $shelf->barcode);
        }
        $trayLog->user_id = $userId;
        $trayLog->save();
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
        $shelfBarcode = isset($_REQUEST["shelf"]) ? str_replace('-', '_', $_REQUEST["shelf"]) : "_______";
        $trayBarcode = isset($_REQUEST["tray"]) ? $_REQUEST["tray"] : '';
        $size = isset($_REQUEST["size"]) ? $_REQUEST["size"] : null;
        $collection = isset($_REQUEST["collection"]) ? $_REQUEST["collection"] : null;
        $positionsFree = isset($_REQUEST["positionsfree"]) ? $_REQUEST["positionsfree"] : null;
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        $sizeId = $size ? Size::find()->where(['code' => $size])->one()->id : null;
        $collectionId = $collection ? Collection::find()->where(['name' => $collection])->andWhere(['active' => true])->one()->id : null;

        if ($tokenCheck['level'] >= 20) {
            // If a tray barcode is provided, search just by the tray barcode
            // (the shelf query will be cleared); otherwise, 60 shelves
            // will be returned
            if ($trayBarcode != '') {
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->rightJoin('tray', 'tray.shelf_id = shelf.id')
                        ->where(['tray.barcode' => $trayBarcode])
                        ->andWhere(['tray.active' => true]),
                ]);
            }
            // If the user is looking for empty shelves specifically
            else if ($positionsFree == -1) {
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                        ->where(['like', 'shelf.barcode', $shelfBarcode, false])
                        ->andFilterWhere(['shelf.size_id' => $sizeId])
                        ->andFilterWhere(['shelf.collection_id' => $collectionId])
                        ->andWhere(['shelf.active' => true])
                        ->andWhere(['tray.id' => null])
                        ->groupBy(['shelf.id']),
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
            else if ($positionsFree === 0 || $positionsFree === "0") {
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                        ->where(['like', 'shelf.barcode', $shelfBarcode, false])
                        ->andFilterWhere(['shelf.size_id' => $sizeId])
                        ->andFilterWhere(['shelf.collection_id' => $collectionId])
                        ->andWhere(['shelf.active' => true])
                        ->andWhere(['or', ['tray.active' => true], ['tray.id' => null]])
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
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->leftJoin('tray', 'tray.shelf_id = shelf.id')
                        ->where(['like', 'shelf.barcode', $shelfBarcode, false])
                        ->andFilterWhere(['shelf.size_id' => $sizeId])
                        ->andFilterWhere(['shelf.collection_id' => $collectionId])
                        ->andWhere(['shelf.active' => true])
                        ->andWhere(['or', ['tray.active' => true], ['tray.id' => null]])
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
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->where(['like', 'barcode', $shelfBarcode, false])
                        ->andFilterWhere(['size_id' => $sizeId])
                        ->andFilterWhere(['collection_id' => $collectionId])
                        ->andWhere(['active' => true]),
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
            // If there was a tray result for an unshelved tray, return
            // just that tray, with placeholder null/unshelved information.
            // TODO: replace this exception with reworking shelf search
            // to allow searching for "NONE" as its own shelf, and including
            // all unshelved trays in that virtual shelf
            if ($provider->getModels() && $provider->getModels()[0]['id'] === null) {
                $tray = \app\models\Tray::find()
                    ->where(['barcode' => $trayBarcode, 'active' => true, 'shelf_id' => null])
                    ->one();
                if ($tray) {
                    return [
                        'resultCount' => 1,
                        'results' => [[
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
                        ]],
                    ];
                }
                else {
                    return [
                        'resultCount' => 0,
                        'results' => [],
                    ];
                }
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
            $totalCount = $this->modelClass::find()->where(['active' => 1])->count();
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
                        'Total trays' => 0,
                        'Capacity' => null,
                        'Shelves' => 0,
                        'Empty' => 0,
                        'Partial/full' => 0,
                        'Full' => 0,
                    ];
                }
                $results[$collectionCode][$sizeCode]['Shelves']++;
                $results[$collectionCode][$sizeCode]['Total trays'] += $row['shelf_count'];
                if ($row['capacity']) {
                    $results[$collectionCode][$sizeCode]['Capacity'] += $row['capacity'];
                }
                if ($row['shelf_count'] == 0 || $row['shelf_count'] === null) {
                    $results[$collectionCode][$sizeCode]['Empty']++;
                }
                elseif ($row['shelf_count'] && $row['capacity'] && $row['shelf_count'] >= $row['capacity']) {
                    $results[$collectionCode][$sizeCode]['Full']++;
                }
                else {
                    $results[$collectionCode][$sizeCode]['Partial/full']++;
                }
            }

            return $results;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view shelf space usage.');
        }
    }

}
