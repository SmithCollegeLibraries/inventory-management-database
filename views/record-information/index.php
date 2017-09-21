<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\RecordInformationSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Record Informations';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="record-information-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Create Record Information', ['create'], ['class' => 'btn btn-success']) ?>
    </p>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'barcode',
            'title',
            'callnumber',
            'call_number_normalized',
            // 'isbn',
            // 'issn',
            // 'img:ntext',
            // 'status',
            // 'check_out_time',
            // 'return_time',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
</div>
