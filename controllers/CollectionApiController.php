<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;
use app\models\Collection;
use app\models\User;

class CollectionApiController extends ActiveController
{
    public $modelClass = 'app\models\Collection';

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
            'query' => $modelClass::find()->where(['active' => true]),
            'pagination' => false,
        ]);
        return $dataProvider;
    }

    public function actionNewCollection()
    {
        $modelClass = 'app\models\Collection';
        $model = new $this->modelClass;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            Yii::$app->db->createCommand()->insert($modelClass::tableName(), [
                'name' => $data['name'],
                'active' => 1,
            ])->execute();
            return true;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to update collections');
        }


        $model->name = Yii::$app->request->post('name');
        $model->active = 1;
        $model->save();
        return $model;
    }

    public function actionUpdateCollection()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            $collection = Collection::findOne($data["id"]);
            $collection->name = $data["name"];
            $collection->save();
            return true;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to update collections');
        }
    }

    public function actionDeleteCollection()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            $collection = Collection::findOne($data["id"]);
            // Mark collection as inactive instead of deleting from database
            $collection->active = 0;
            $collection->save();
            return true;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to delete collections');
        }
    }

    public function collectionExists($name): bool
    {
        return Collection::find()->where(['name' => $name])->exists();
    }

    public function collectionHasItems($id): bool
    {
        return Collection::findOne($id)->getItems()->exists();
    }

}
