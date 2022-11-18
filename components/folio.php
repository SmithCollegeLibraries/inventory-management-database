<?php

namespace app\components;

use yii\httpclient\Client;

class FOLIO
{

    public static function barcodeLookup($barcode)
    {
        $client = new Client(['baseUrl' => "http://libtools2.smith.edu/folio/web/search/search-inventory"]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl([
                'query' => sprintf("(items.barcode==%s)", $barcode),
            ])
            ->send();
        if ($response->isOk) {
            return $response->data;
        } else {
            return null;
        }
    }

}