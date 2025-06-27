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

    public function actionGetAllCollections()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 10) {
            return Collection::find()
                ->where(['active' => 1])
                ->orderBy(['name' => SORT_ASC])
                ->all();
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to view collections');
        }
    }

    public function actionGetUnverifiedCollections()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 10) {
            return Collection::find()->where(['active' => 1, 'folio_validated' => 0])->all();
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to view collections');
        }
    }

    public function actionGetDefault()
    {
        $token = $_REQUEST["access-token"];
        $currentUser = User::find()->where(['access_token' => $token])->one();
        return Collection::find()->where(['id' => $currentUser['default_collection'], 'active' => 1])->one();
    }

    public function actionNewCollection()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            // If that collection code is already in use, throw an error
            if (isset($data["code"]) && Collection::find()->where(['code' => $data["code"]])->exists()) {
                throw new \yii\web\HttpException(400, sprintf('Collection code %s already exists', $data['code']));
            }
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
                throw new \yii\web\HttpException(400, sprintf('Collection %s already exists', $data['name']));
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

        if ($tokenCheck['level'] >= 80) {
            $newName = isset($data["name"]) ? $data["name"] : null;
            $newCode = isset($data["code"]) ? $data["code"] : null;
            $newValidationStatus = isset($data["folio_validated"]) ? $data["folio_validated"] : null;
            $logDetails = [];

            if (!isset($data["id"])) {
                throw new \yii\web\HttpException(400, 'Collection ID is required for updating');
            }
            $collection = Collection::findOne($data["id"]);
            $oldName = $collection->name;
            if ($collection == null) {
                throw new \yii\web\HttpException(400, sprintf('Tried to edit a non-existing collection'));
            }
            if ($newName !== null && $newName !== $oldName) {
                $collection->name = $newName;
                $logDetails[] = sprintf('Renamed %s to %s', $oldName, $newName);
            }
            if ($newCode !== null && $newCode !== $collection->code) {
                $collection->code = $newCode;
                $logDetails[] = sprintf('Updated %s: collection code %s', $oldName, $newCode);
            }
            if ($newValidationStatus !== null && $newValidationStatus !== $collection->folio_validated) {
                $collection->folio_validated = $newValidationStatus;
                $validationMessage = $newValidationStatus ? 'added validation against FOLIO' : 'removed validation against FOLIO';
                $logDetails[] = sprintf('Updated %s: %s', $oldName, $validationMessage);
            }
            $collection->save();

            if (!$logDetails) {
                $logDetails[] = sprintf('Updated %s (no changes)', $oldName);
            }

            // Add log for each thing that was changed about the collection
            for ($i = 0; $i < count($logDetails); $i++) {
                $modelLog = new $this->modelLogClass;
                $modelLog->collection_id = $data["id"];
                $modelLog->user_id = $tokenCheck['id'];
                $modelLog->action = "Updated";
                $modelLog->details = $logDetails[$i];
                $modelLog->save();
            }

            return $collection;
        }
        else {
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
        if ($tokenCheck['level'] >= 80) {
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
                throw new \yii\web\HttpException(400, sprintf('Collection %s does not exist', $data['name']));
            }
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to delete collections');
        }
    }

    public function actionCollectionExists()
    {
        $name = isset($_REQUEST["query"]) ? $_REQUEST["query"] : null;
        return Collection::find()->where(['name' => $name])->exists();
    }

    public function actionCollectionHasItems()
    {
        $id = isset($_REQUEST["query"]) ? $_REQUEST["query"] : null;
        $collection = Collection::findOne($id);
        if ($collection == null) {
            return false;
        }
        else {
            return $collection->getItems()->exists();
        }
    }

}
