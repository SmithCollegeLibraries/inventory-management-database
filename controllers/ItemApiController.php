<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

class ItemApiController extends ActiveController
{
    public $modelClass = 'app\models\Item';

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
        $results = $this->modelClass::find()->where(['barcode' => $data["barcodes"]])->all();
        return $results;
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

}

