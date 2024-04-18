<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;

use app\models\Shelf;
use app\models\User;

class ShelfLogApiController extends ActiveController
{
    public $modelClass = 'app\models\ShelfLog';

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
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        $actionQ = isset($data['action']) ? $data['action'] : null;
        $barcodeQ = isset($data['barcode']) ? $data['barcode'] : null;
        $detailsQ = isset($data['details']) ? $data['details'] : null;
        $userQ = isset($data['user']) ? $data['user'] : null;
        $timestampPost = isset($data['timestampPost']) ? $data['timestampPost'] : null;
        $timestampAnte = isset($data['timestampAnte']) ? $data['timestampAnte'] : null;

        if ($tokenCheck['level'] >= 60) {
            $query = $this->modelClass::find()
                ->joinWith('shelf', 'shelf_log.shelf_id = shelf.id')
                ->joinWith('user', 'shelf_log.user_id = user.id')
                ->andFilterWhere(['shelf_log.action' => $actionQ])
                ->andFilterWhere(['like', 'shelf.barcode', $barcodeQ])
                ->andFilterWhere(['like', 'shelf_log.details', $detailsQ])
                ->andFilterWhere(['like', 'user.name', $userQ])
                ->andFilterWhere(['>=', 'shelf_log.timestamp', $timestampPost])
                ->andFilterWhere(['<', 'shelf_log.timestamp', $timestampAnte])
                ->orderBy(['shelf_log.id' => SORT_DESC])
                ->limit(100)->all();
            return $query;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view logs');
        }
    }

}
