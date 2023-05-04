<?php

namespace app\components;

use yii\httpclient\Client;

class Folio
{

    public static function fullLookup($barcode)
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
        }
        else {
            return null;
        }
    }

    public static function partialLookup($barcode)
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
            $results = $response->data;
            try {
                // If the item is in FOLIO, there should be exactly one result
                // for this barcode.
                $instance = reset($results["data"]["instances"]);
                // The title is located on the instance record
                $title = $instance["title"];
                // To get the call number, we will have to look at the items,
                // find the item that matches on the barcode, and then get the
                // call number from effectiveCallNumberComponents.
                $items = array_filter($instance["items"], function($item) use ($barcode) {
                    return isset($item["barcode"]) && $item["barcode"] == $barcode;
                });
                $correctItem = reset($items);
                $callNumber = $correctItem["effectiveCallNumberComponents"]["callNumber"];
                $status = isset($correctItem["status"]) ? $correctItem["status"]["name"] : null;
                $ANNEX_LOCATION_ID = "5eb79fcc-af08-4ae1-9ab3-dde11e330a01";
                return [
                    "barcode" => $barcode,
                    "title" => $title,
                    "callNumber" => $callNumber,
                    "status" => $status,
                    "annex" => $correctItem["effectiveLocationId"] == $ANNEX_LOCATION_ID,
                ];
            } catch (\Exception $e) {
                return [];
            }
        }
        else {
            return null;
        }
    }

    public static function handleMarkFolioAnomaly($item, $userId)
    {
        $folioInfo = \app\components\Folio::partialLookup($item["barcode"]);
        $anomalous = false;
        if (isset($folioInfo["status"]) && $folioInfo["status"] != "Available") {
            $anomalous = true;
            // Flag the item and add a log
            if ($item->flag != 1) {
                $item->flag = 1;
                $item->save();
            }

            $itemLog = new \app\models\ItemLog;
            $itemLog->item_id = $item->id;
            $itemLog->action = 'Flagged';
            $itemLog->details = sprintf("Flagged item %s because it has a status other than Available in FOLIO", $item->barcode);
            $itemLog->user_id = $userId;
            $itemLog->save();
        }
        if (isset($folioInfo["annex"]) && !$folioInfo["annex"]) {
            $anomalous = true;
            // Flag the item and add a log
            if ($item->flag != 1) {
                $item->flag = 1;
                $item->save();
            }

            $itemLog = new \app\models\ItemLog;
            $itemLog->item_id = $item->id;
            $itemLog->action = 'Flagged';
            $itemLog->details = sprintf("Flagged item %s because it is not marked with location Annex in FOLIO", $item->barcode);
            $itemLog->user_id = $userId;
            $itemLog->save();
        }
        return $anomalous;
    }

}