<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\RecordInformation;

/**
 * RecordInformationSearch represents the model behind the search form about `app\models\RecordInformation`.
 */
class RecordInformationSearch extends RecordInformation
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['barcode', 'title', 'callnumber', 'call_number_normalized', 'isbn', 'issn', 'img', 'status', 'check_out_time', 'return_time'], 'safe'],
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
        $query = RecordInformation::find();

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

        $query->andFilterWhere(['like', 'barcode', $this->barcode])
            ->andFilterWhere(['like', 'title', $this->title])
            ->andFilterWhere(['like', 'callnumber', $this->callnumber])
            ->andFilterWhere(['like', 'call_number_normalized', $this->call_number_normalized])
            ->andFilterWhere(['like', 'isbn', $this->isbn])
            ->andFilterWhere(['like', 'issn', $this->issn])
            ->andFilterWhere(['like', 'img', $this->img])
            ->andFilterWhere(['like', 'status', $this->status])
            ->andFilterWhere(['like', 'check_out_time', $this->check_out_time])
            ->andFilterWhere(['like', 'return_time', $this->return_time]);

        return $dataProvider;
    }
}
