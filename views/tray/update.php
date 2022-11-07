<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Tray $model */

$this->title = 'Update Tray: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Trays', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="tray-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
