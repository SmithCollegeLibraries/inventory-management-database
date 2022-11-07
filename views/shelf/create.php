<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Shelf $model */

$this->title = 'Create Shelf';
$this->params['breadcrumbs'][] = ['label' => 'Shelves', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="shelf-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
