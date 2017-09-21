<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model app\models\TrayShelf */

$this->title = 'Create Tray Shelf';
$this->params['breadcrumbs'][] = ['label' => 'Tray Shelves', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tray-shelf-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
