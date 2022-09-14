<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\BarcodeTray;

/**
 * BarcodeTraySearch represents the model behind the search form about `app\models\BarcodeTray`.
 */
class BarcodeTraySearch extends BarcodeTray
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['boxbarcode', 'barcode', 'stream', 'initials', 'added', 'timestamp'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = BarcodeTray::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
        ]);

        $query->andFilterWhere(['like', 'boxbarcode', $this->boxbarcode])
            ->andFilterWhere(['like', 'barcode', $this->barcode])
            ->andFilterWhere(['like', 'stream', $this->stream])
            ->andFilterWhere(['like', 'initials', $this->initials])
            ->andFilterWhere(['like', 'added', $this->added])
            ->andFilterWhere(['like', 'timestamp', $this->timestamp]);

        return $dataProvider;
    }
}
