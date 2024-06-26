<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\User;

class UserController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return [
            'corsFilter' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    'Access-Control-Max-Age' => 3600,
                    'Access-Control-Expose-Headers' => [
                        'X-Pagination-Total-Count',
                        'X-Pagination-Current-Page',
                        'X-Pagination-Page-Count',
                        'X-Pagination-Per-Page'
                    ]
                ],

            ],
        ];
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Collection the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Collection::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionCreateAccount()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $user = User::find()->where(['email' => $data["email"]])->one();
        if ($user) {
            throw new \yii\web\NotFoundHttpException('User already exists');
        }
        if ($data["password"] && $data["email"]) {
            $account = new User();
            $account->email = $data["email"];
            $account->name = $data["name"];
            $account->passwordhash = Yii::$app->getSecurity()->generatePasswordHash($data["password"]);
            $account->access_token = Yii::$app->getSecurity()->generateRandomString();
            $account->level = $data["level"];
            $account->save();
            if ($account->save()) {
                $loggedin = User::find()->where(['email' => $data["email"]])->one();
                return [
                    "id" => $loggedin->id,
                    "name" => $loggedin->name,
                    "access_token" => $loggedin->access_token
                ];
            } else {
                return false;
            }
        } else {
            return false;
        }

    }

    public function actionAccountExists()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $user = User::find()->where(['email' => $data["email"]])->one();
        if ($user) {
            return true;
        } else {
            return false;
        }
    }

    public function actionLogin()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $user = User::find()->where(['email' => $data["email"]])->one();
        if (empty($user)) {
            throw new \yii\web\NotFoundHttpException('User not found');
        }
        else if ($user->level == 0) {
            throw new \yii\web\ForbiddenHttpException('User account is disabled');
        }
        else if (Yii::$app->getSecurity()->validatePassword($data['password'], $user->passwordhash)) {
            return [
                "id" => $user->id,
                "name" => $user->name,
                "access_token" => $user->access_token,
                "level" => $user->level
            ];
        } else {
            throw new \yii\web\ForbiddenHttpException();
        }
    }

    public function actionGetUsers()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 100) {
            $users = User::find()->where(['>', 'level', 0])->all();
            return $users;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to see users');
        }
    }

    public function actionGetName()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $token = $_REQUEST["access-token"];
        $user_id = $_REQUEST["id"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 35) {
            $result = User::find()->where(['id' => $user_id])->andWhere(['>', 'level', 0])->one();
            return array('id'=>$user_id, 'name'=>$result ? $result['name'] : null);
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to see users');
        }
    }

    public function actionDeleteUsers()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck['level'] >= 100) {
            $user = User::findOne($data["id"]);
            // Set level to 0 instead of deleting from database
            $user->level = 0;
            $user->save();
            return true;
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to delete users');
        }
    }

    public function actionUpdateAccount()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();
        if ($tokenCheck and $tokenCheck['level'] >= 100) {
            $user = User::findOne($data["id"]);
            if (isset($data["password"])) {
                $user->passwordhash = Yii::$app->getSecurity()->generatePasswordHash($data["password"]);
            }
            $user->level = $data["level"];
            $user->save();
            if ($user->save()) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to delete users');
        }
    }

    public function actionNameList()
    {
        $token = $_REQUEST["access-token"];
        $tokenCheck = User::find()->where(['access_token' => $token])->one();

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if ($tokenCheck['level'] >= 60) {
            $query = User::find()
                ->andFilterWhere(['>', 'level', 0])
                ->orderBy('name')
                ->all();
            // Use map/reduce to create just a list of names, no IDs or other info
            $nameList = array_map(function($item) {
                return $item->name;
            }, $query);
            return $nameList;
        }
        else {
            throw new \yii\web\HttpException(403, 'You do not have permission to view users');
        }
    }
}
