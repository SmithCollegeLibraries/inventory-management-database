<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\TrayShelfSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Tray Shelves';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tray-shelf-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Create Tray Shelf', ['create'], ['class' => 'btn btn-success']) ?>
    </p>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'boxbarcode',
            'shelf',
            'row',
            'side',
            // 'ladder',
            // 'shelf_number',
            // 'shelf_depth',
            // 'shelf_position',
            // 'initials',
            // 'added',
            // 'timestamp',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
</div>
