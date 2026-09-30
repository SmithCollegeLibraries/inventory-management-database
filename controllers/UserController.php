<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\User;
use app\models\Collection;

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
                "level" => $user->level,
                "default_collection" => $user->default_collection ? Collection::find()->where(['id' => $user->default_collection])->one()->code : null
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
            return array('id'=>$user_id, 'name'=>$result ? $result['email'] : null);
        } else {
            throw new \yii\web\ForbiddenHttpException('You are not authorized to see users');
        }
    }

    public function actionDeleteAccount()
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
            return $user->level == 0;  // Only return true if successful
        }
        else {
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
            if (isset($data["password"]) && $data["password"] !== "") {
                $user->passwordhash = Yii::$app->getSecurity()->generatePasswordHash($data["password"]);
            }
            if (isset($data["name"]) && $data["name"] !== "") {
                $user->name = $data["name"];
            }
            if (isset($data["level"]) && $data["level"] !== "") {
                $user->level = $data["level"];
            }

            if ($user->save()) {
                return true;
            }
            else {
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

        $objectType = $_GET['objectType'] ?? null;

        if ($tokenCheck['level'] >= 40) {
            // If objectType is provided, filter by the logs of the
            // object specified: item, tray, shelf, collection, setting.
            // This is so that when we are getting the name list in
            // a log filter, we're only showing names that actually
            // have log entries.

            // If objectType = 'item', then the list of users returned should
            // only include those for whom there are itemLog rows where
            // user.id = item_log.user_id.
            // Same for 'tray' (tray_log), 'shelf' (shelf_log),
            // 'collection' (collection_log), and 'setting' (setting_log).
            if ($objectType === 'item') {
                            return User::find()
                                ->select('name')
                                ->where([
                                    'id' => (new \yii\db\Query())
                                        ->select('user_id')
                                        ->distinct()
                                        ->from('item_log')
                                ])
                                ->orderBy('name')
                                ->column();
            }
            if ($objectType === 'tray') {
                return User::find()
                    ->select('name')
                    ->where([
                        'id' => (new \yii\db\Query())
                            ->select('user_id')
                            ->distinct()
                            ->from('tray_log')
                    ])
                    ->orderBy('name')
                    ->column();
            }
            if ($objectType === 'shelf') {
                return User::find()
                    ->select('name')
                    ->where([
                        'id' => (new \yii\db\Query())
                            ->select('user_id')
                            ->distinct()
                            ->from('shelf_log')
                    ])
                    ->orderBy('name')
                    ->column();
            }
            if ($objectType === 'collection') {
                return User::find()
                    ->select('name')
                    ->where([
                        'id' => (new \yii\db\Query())
                            ->select('user_id')
                            ->distinct()
                            ->from('collection_log')
                    ])
                    ->orderBy('name')
                    ->column();
            }
            if ($objectType === 'setting') {
                return User::find()
                    ->select('name')
                    ->where([
                        'id' => (new \yii\db\Query())
                            ->select('user_id')
                            ->distinct()
                            ->from('setting_log')
                    ])
                    ->orderBy('name')
                    ->column();
            }

            // If no specific object type is provided, return all users,
            // but only those that are currently active (level > 0).
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
