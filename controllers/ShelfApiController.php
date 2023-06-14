<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

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
            $shelfRow = array_key_exists('row', $data) ? $data['row'] : '';
            $shelfSide = array_key_exists('side', $data) ? $data['side'] : '';
            $shelfLadder = array_key_exists('ladder', $data) ? $data['ladder'] : '';
            $shelfRung = array_key_exists('rung', $data) ? $data['rung'] : '';

            // If the row, side, ladder or rung are not provided, fill them
            // in from the barcode
            if ($shelfBarcode != '') {
                if ($shelfRow == '') {
                    $shelfRow = substr($shelfBarcode, 0, 2);
                }
                if ($shelfSide == '') {
                    $shelfSide = substr($shelfBarcode, 2, 1);
                }
                if ($shelfLadder == '') {
                    $shelfLadder = substr($shelfBarcode, 3, 2);
                }
                if ($shelfRung == '') {
                    $shelfRung = substr($shelfBarcode, 5, 2);
                }
            }

            // And vice versa
            else if ($data['row'] != '' and $data['side'] != '' and $data['ladder'] != '' and $data['rung'] != '') {
                $shelfBarcode = sprintf('%s%s%s%s', $data['row'], $data['side'], $data['ladder'], $data['rung']);
            }

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


}


