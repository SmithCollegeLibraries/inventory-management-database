<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;


class OldBarcodeTrayApiController extends ActiveController
{
    public $modelClass = 'app\models\OldBarcodeTray';

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
    public function actionBatchCheckFolio()
    {
        $modelClass = 'app\models\OldBarcodeTray';
        $number = 20;
        if (isset($_REQUEST["number"])) {
            if ($_REQUEST["number"] >= 120) {
                $number = 120;
            }
            else {
                $number = $_REQUEST["number"];
            }
        }
        $status = isset($_REQUEST["status"]) ? $_REQUEST["status"] : null;
        if (is_null($status)) {
            $rows = $modelClass::find()->where(['in_folio' => null])->orderBy("id")->limit($number)->all();
        }
        else {
            $rows = $modelClass::find()->where(['in_folio' => null, 'status' => $status])->orderBy("id")->limit($number)->all();
        }
        $rows = $modelClass::find()->where(['in_folio' => null])->orderBy("id")->limit($number)->all();
        foreach ($rows as $row) {
            $inFolio = $this->handleCheckFolio($row->barcode);
            $row->in_folio = $inFolio;
            $row->save();
        }
        return true;
    }

}
