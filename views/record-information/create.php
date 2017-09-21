<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model app\models\RecordInformation */

$this->title = 'Create Record Information';
$this->params['breadcrumbs'][] = ['label' => 'Record Informations', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="record-information-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
