<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model app\models\Collections */

$this->title = 'Create Collections';
$this->params['breadcrumbs'][] = ['label' => 'Collections', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="collections-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
