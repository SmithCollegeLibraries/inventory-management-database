<?php

namespace app\controllers;
use Yii;
use yii\rest\ActiveController;
use yii\data\ActiveDataProvider;

class InternalRequestsCommentsController extends ActiveController
{
	public $modelClass = 'app\models\InternalRequestsComments';
	
	
	public function actionComment() 
	{	 
       	$model = new $this->modelClass;
		try {
            $provider = new ActiveDataProvider([
                'query' => $model->find()
                	->select('*')
                	->andFilterWhere(['request_id' => $_GET['id']])
                	->asArray(),
					'pagination' => false
				]);
        } catch (Exception $ex) {
            throw new \yii\web\HttpException(500, 'Internal server error');
        }

		if ($provider->getCount() <= 0) {
            return true;
        } else {
            return $provider;
        }
   	}
}
