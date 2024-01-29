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
                $ANNEX_LOCATION_IDS = [
                    "5eb79fcc-af08-4ae1-9ab3-dde11e330a01",
                    "ed12a1d9-33e7-4c62-8daf-485e7d2369c3",
                ];
                return [
                    "barcode" => $barcode,
                    "title" => $title,
                    "callNumber" => $callNumber,
                    "status" => $status,
                    "annex" => in_array($correctItem["effectiveLocationId"], $ANNEX_LOCATION_IDS),
                ];
            } catch (\Exception $e) {
                return [];
            }
        }
        else {
            return null;
        }
    }

    public static function getTitleAndVolume($barcode)
    {
        $client = new Client(['baseUrl' => "http://libtools2.smith.edu/folio/web/search/search-inventory"]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl([
                'type' => 'inventory',
                'query' => sprintf("items.barcode==%s", $barcode),
            ])
            ->send();
        if ($response->isOk) {
            $results = $response->data;
            try {
                // If the item is in FOLIO, there should be exactly one result
                // for this barcode.
                $itemsList = $results["data"]["items"];
                $matchingItems = array_filter($itemsList, function($item) use ($barcode) {
                    return isset($item["barcode"]) && $item["barcode"] == $barcode;
                });
                $correctItem = reset($matchingItems);
                $title = isset($correctItem["title"]) ? $correctItem["title"] : null;
                $volume = isset($correctItem["volume"]) ? $correctItem["volume"] : "";
                $copy = isset($correctItem["copyNumber"]) && $correctItem["copyNumber"] != 1 ? "c." . $correctItem["copyNumber"] : "";
                if ($volume && $copy) {
                    $volumeAndCopy = $volume . " " . $copy;
                }
                else {
                    $volumeAndCopy = $volume . $copy;
                }
                return [
                    "barcode" => $barcode,
                    "title" => $title,
                    "volume" => $volumeAndCopy ? $volumeAndCopy : null,
                ];
            } catch (\Exception $e) {
                return [];
            }
        }
        else {
            return null;
        }
    }

    // TODO: If performance is slow, modify this to get titles and volumes
    // in this report, instead of separately
    public static function getPicklist($location)
    {
        static $locationList = [
            "FC_ANNEX" => "c0250137-9c4a-4c4c-be9b-61f5bb8f645c",
            "SC_ANNEX" => "9e4b06c8-0cb0-4011-ab1d-a23af57d190c",
            "HILLYER" => "25d98f21-ef4d-4846-955e-17840062b1f0",
            "JOSTEN" => "602aba21-73ae-4e43-a92e-91f2dee34c8f",
            "NEILSON" => "2c0764b7-63b3-4254-9950-0c730b7e438b",
            "SELF_CHECK" => "6b6b9f00-aac7-4e20-a819-fb24386e48bb",
            "SPECIAL_COLLECTIONS" => "83f25b2e-49d8-44ae-956b-91060d819b07",
            "WEST_STREET" => "b13c7bb4-278e-4592-9a0d-3dcb600f8a1e",
        ];
        $locationId = $locationList[$location];
        $client = new Client(['baseUrl' => "https://libtools2.smith.edu/folio/web/search/search-circulation?id=" . $locationId]);
        $response = $client->createRequest()
            ->setMethod('get')
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        if ($response->isOk) {
            $results = $response->data;
            return $results["data"];
        }
        else {
            return null;
        }
    }

    public static function handleMarkFolioAnomaly($item, $userId)
    {
        $folioInfo = \app\components\Folio::partialLookup($item["barcode"]);
        $notAvailable = false;
        $notAnnex = false;
        if (isset($folioInfo["status"]) && $folioInfo["status"] != "Available") {
            $notAvailable = true;
        }
        if (isset($folioInfo["annex"]) && !$folioInfo["annex"]) {
            $notAnnex = true;
        }

        if ($notAvailable || $notAnnex) {
            // If the item isn't already on Colin's backlog list, flag it and log
            if (\app\models\ColinBacklog::find()->where(['barcode' => $item->barcode])->count() == 0) {
                if ($item->flag != 1) {
                    $item->flag = 1;
                    $item->save();
                }

                if ($notAvailable && $notAnnex) {
                    $flagReason = "it is not in the Annex in FOLIO and also has a status other than Available in FOLIO";
                }
                else if ($notAvailable) {
                    $flagReason = "it has a status other than Available in FOLIO";
                }
                else if ($notAnnex) {
                    $flagReason = "it is not in the Annex in FOLIO";
                }

                $itemLog = new \app\models\ItemLog;
                $itemLog->item_id = $item->id;
                $itemLog->action = 'Flagged';
                $itemLog->details = sprintf("Flagged item because %s: %s", $flagReason, $item->barcode);
                $itemLog->user_id = $userId;
                $itemLog->save();
            }

            return true;
        }
        else {
            // Unflag the item
            if ($item->flag != 0) {
                $item->flag = 0;
                $item->save();
            }

            return false;
        }
    }

}