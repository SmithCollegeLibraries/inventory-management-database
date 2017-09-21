<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model app\models\BarcodeTray */

$this->title = 'Create Barcode Tray';
$this->params['breadcrumbs'][] = ['label' => 'Barcode Trays', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="barcode-tray-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
