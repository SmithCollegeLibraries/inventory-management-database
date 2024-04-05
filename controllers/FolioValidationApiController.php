<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;


class FolioValidationApiController extends ActiveController
{
    public $modelClass = 'app\models\FolioValidation';

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

    // Expects a single barcode in data under "barcode". Returns a single
    // true/false value whether the item is in FOLIO.
    public function handleCheckFolio($barcode)
    {
        $results = \app\components\Folio::fullLookup($barcode);
        try {
            return $results["data"]["totalRecords"] > 0;
        }
        catch (\Exception $e) {
            return null;
        }
    }

    // Check multiple items in FOLIO and add the results to the `in_folio`
    // column in the database.
    public function actionBatchCheck()
    {
        $modelClass = 'app\models\FolioValidation';
        $number = 20;
        if (isset($_REQUEST["number"])) {
            if ($_REQUEST["number"] >= 500) {
                $number = 500;
            }
            else {
                $number = $_REQUEST["number"];
            }
        }
        $rows = $modelClass::find()->where(['item_in_folio' => null])->orderBy("id")->limit($number)->all();
        foreach ($rows as $row) {
            $inFolio = $this->handleCheckFolio($row->barcode);
            $row->item_in_folio = $inFolio;
            $row->save();
        }
        return true;
    }
}
