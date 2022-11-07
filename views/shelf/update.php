<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Shelf $model */

$this->title = 'Update Shelf: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Shelves', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="shelf-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
