<?php

namespace app\controllers;

use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Size;
use app\models\Collection;
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
            $shelfFromTray = \app\models\Tray::find()
                ->where(['barcode' => $trayBarcode])
                ->andWhere(['active' => true])
                ->one();
            $secondShelfBarcode = $shelfFromTray ? $shelfFromTray->shelf->barcode : '';
            // If a tray barcode is provided but no shelf barcode,
            // search just by the tray barcode; otherwise, 60 shelves
            // will be returned
            if ($trayBarcode != '') {
                $provider = new ActiveDataProvider([
                    'query' => $this->modelClass::find()
                        ->where(['barcode' => $secondShelfBarcode])
                        ->andWhere(['active' => true]),
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
            else if ($positionsFree !== null && $positionsFree !== "") {
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

}
