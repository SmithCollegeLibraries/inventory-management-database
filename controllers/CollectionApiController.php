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
    public $modelLogClass = 'app\models\CollectionLog';

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
            'query' => $this->modelClass::find()->where(['active' => true]),
            'pagination' => false,
        ]);
        return $dataProvider;
    }

    public function actionNewCollection()
    {
        $model = new $this->modelClass;
        $modelLog = new $this->modelLogClass;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            // Add collection to database
            $model->name = Yii::$app->request->post('name');
            $model->active = 1;
            $model->save();

            // Add log to database
            $modelLog->collection_id = $model->id;
            $modelLog->user_id = $tokenCheck['id'];
            $modelLog->action = "Created";
            $modelLog->details = sprintf('Created %s', $data['name']);
            $modelLog->timestamp = date('Y-m-d H:i:s');
            $modelLog->save();

            return $model;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to update collections');
        }
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
            $oldName = $collection->name;
            $collection->name = $data["name"];
            $collection->save();

            // Add log to database
            $modelLog = new $this->modelLogClass;
            $modelLog->collection_id = $data["id"];
            $modelLog->user_id = $tokenCheck['id'];
            $modelLog->action = "Updated";
            $modelLog->details = sprintf('Renamed %s to %s', $oldName, $data['name']);
            $modelLog->timestamp = date('Y-m-d H:i:s');
            $modelLog->save();

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

            // Add log to database
            $modelLog = new $this->modelLogClass;
            $modelLog->collection_id = $data["id"];
            $modelLog->user_id = $tokenCheck['id'];
            $modelLog->action = "Deleted";
            $modelLog->details = sprintf('Deleted %s', $collection->name);
            $modelLog->timestamp = date('Y-m-d H:i:s');
            $modelLog->save();

            return true;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to delete collections');
        }
    }

    public function actionRestoreCollection()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 90) {
            $collection = Collection::findOne($data["id"]);
            // Mark collection as active again
            if ($collection->active == 0) {
                $collection->active = 1;
                $collection->save();

                // Add log to database
                $modelLog = new $this->modelLogClass;
                $modelLog->collection_id = $collection->id;
                $modelLog->user_id = $tokenCheck['id'];
                $modelLog->action = "Restored";
                $modelLog->details = sprintf('Restored %s', $collection->name);
                $modelLog->timestamp = date('Y-m-d H:i:s');
                $modelLog->save();
                return true;
            } else {
                // Collection is already active
                return false;
            }
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
