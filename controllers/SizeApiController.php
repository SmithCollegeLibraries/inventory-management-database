<?php

namespace app\controllers;

use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;
use yii\filters\auth\QueryParamAuth;
use app\models\Size;
use app\models\User;

class SizeApiController extends ActiveController
{
    public $modelClass = 'app\models\Size';

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

    public function actionGetAllSizes()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 10) {
            return Size::find()->all();
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to view size');
        }
    }

    public function actionNewSize()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            // If there isn't a size with that code before, add a new row
            // to the database
            $size = Size::find()->where(['code' => $data["code"]])->one();
            if ($size === null) {
                // Add collection to database
                $model = new $this->modelClass;
                $model->code = $data["code"];
                $model->save();

                return $model;
            }
            // If the size already exists, do nothing
            else {
                throw new \yii\web\HttpException(400, sprintf('Size %s already exists', $data['code']));
            }
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to update sizes');
        }
    }

    public function actionUpdateSize()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 60) {
            $size = Size::find()->where(['code' => $data['code']])->one();

            if ($size === null) {
                throw new \yii\web\HttpException(400, sprintf('Size %s does not exist', $data['code']));
            }

            $dataNewCode = isset($data["new_code"]) ? $data["new_code"] : null;
            $dataHeight = isset($data["height"]) ? $data["height"] : null;
            $dataWidth = isset($data["width"]) ? $data["width"] : null;

            if ($dataNewCode) { $size->code = $dataNewCode; }
            if ($dataHeight !== null) { $size->height = $dataHeight === '' ? null : $dataHeight; }
            if ($dataWidth !== null) { $size->width = $dataWidth === '' ? null : $dataWidth; }

            $size->save();

            return $size;
        }
        else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to update size');
        }
    }

    public function actionSizeExists()
    {
        $code = isset($_REQUEST["query"]) ? $_REQUEST["query"] : null;
        return Size::find()->where(['code' => $code])->exists();
    }

}
