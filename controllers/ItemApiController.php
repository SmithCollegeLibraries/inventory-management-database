<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\User;

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
            // a liminted number of results
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
            throw new \yii\web\HttpException(500, 'You do not have permission to browse items');
        }
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

    // Expects a single barcode in data under "barcode". Returns the item's
    // barcode, title and call number.
    public function actionInfoFromFolio()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $results = \app\components\Folio::barcodeLookup($data["barcode"]);
        try {
            // If the item is in FOLIO, there should be exactly one result
            // for this barcode.
            $instance = reset($results["data"]["instances"]);
            // The title is located on the instance record
            $title = $instance["title"];
            // To get the call number, we will have to look at the items,
            // find the item that matches on the barcode, and then get the
            // call number from effectiveCallNumberComponents.
            $items = $instance["items"];
            $item = array_filter($items, function($item) use ($data) {
                return $item["barcode"] == $data["barcode"];
            });
            $firstItem = reset($item);
            $callNumber = $firstItem["effectiveCallNumberComponents"]["callNumber"];
            return [
                "barcode" => $data["barcode"],
                "title" => $title,
                "callNumber" => $callNumber,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

}

