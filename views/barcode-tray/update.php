<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\BarcodeTray */

$this->title = 'Update Barcode Tray: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Barcode Trays', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="barcode-tray-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
