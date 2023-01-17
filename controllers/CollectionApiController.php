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
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            // If there hasn't been a collection with that name before,
            // we add a new row to the database
            $collection = Collection::find()->where(['name' => $data["name"]])->one();
            if ($collection == null) {
                // Add collection to database
                $model = new $this->modelClass;
                $model->name = $data["name"];
                $model->save();

                // Add log to database
                $modelLog = new $this->modelLogClass;
                $modelLog->collection_id = $model->id;
                $modelLog->user_id = $tokenCheck['id'];
                $modelLog->action = "Created";
                $modelLog->details = sprintf('Created %s', $data['name']);
                $modelLog->save();
                return $model;
            }
            // Otherwise, if the collection has existed before but is
            // currently inactive, restore it
            else if (!$collection['active']) {
                $collection->active = 1;
                $collection->save();

                // Add log to database
                $modelLog = new $this->modelLogClass;
                $modelLog->collection_id = $collection->id;
                $modelLog->user_id = $tokenCheck['id'];
                $modelLog->action = "Restored";
                $modelLog->details = sprintf('Restored %s', $data['name']);
                $modelLog->save();
                return $collection;
            }
            // Finally, if the collection already exists and is active,
            // do nothing
            else {
                throw new \yii\web\HttpException(500, sprintf('Collection %s already exists', $data['name']));
            }
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
            try {
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
                $modelLog->save();

                return true;
            }
            catch (\Exception $e) {
                throw new \yii\web\HttpException(500, sprintf('Collection %s does not exist', $data['name']));
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
