<?php
use App\Library\Application;
use App\Library\Arr;
use App\Library\Server;
use App\Library\Str;

define("PI", 3.14159265358979323846);
/**
* Lib Geocode
*
*/
function Lib_GeoCode($strAdres, $strPlaats, $strLand)
{
    $strTmp = "";
    $sGoogleUrl = null;
    if (substr($strPlaats, max(0, strlen($strPlaats) - 3)) == ' OV' || substr($strPlaats, max(0, strlen($strPlaats) - 3)) == ' FR' || substr($strPlaats, max(0, strlen($strPlaats) - 3)) == ' GN' || substr($strPlaats, max(0, strlen($strPlaats) - 3)) == ' UT') {
        $strPlaats = substr($strPlaats, 0, max(0, min((is_null($strPlaats) ? 0 : strlen($strPlaats)) - 3, strlen($strPlaats))));
    }
    $strAdres = Str::replaceDiacritics(Str::trim($strAdres));
    $strPlaats = Str::replaceDiacritics(Str::trim($strPlaats));
    if ($strLand == '') {
        $strLand = 'Nederland';
    }
    $sGoogleUrl = 'https://maps.googleapis.com/maps/api/geocode/xml?address=' . Server::urlEncode($strAdres) . ',' . Server::urlEncode($strPlaats) . ',' . Server::urlEncode($strLand) . '&sensor=false' . (Application::get('Google_api_key', '') != '' ? '&key=' . Application::get('Google_api_key', '') : '');
    // response.write vbCrlf & "<!---" & sGoogleURL & "--->" & vbCrlf
    $strTmp = lib_HttpGet($sGoogleUrl);
    // response.write vbCrlf & "<!--- resultaat: " & strTmp & "--->" & vbCrlf
    if ((($pos = stripos($strTmp, '<status>OK</status>', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
        $strTmp = substr($strTmp, max(0, (($pos = stripos($strTmp, '<location>')) !== false ? $pos + 1 : 0) + 10 - 1));
        $strTmp = substr($strTmp, 0, max(0, min((($pos = stripos($strTmp, '</location>')) !== false ? $pos + 1 : 0) - 1, strlen($strTmp))));
        $strTmp = str_replace('<lat>', '', $strTmp);
        $strTmp = str_replace('<lng>', '', $strTmp);
        $strTmp = str_replace('</lng>', '', $strTmp);
        $strTmp = str_replace('</lat>', ',', $strTmp);
        $strTmp = str_replace(PHP_EOL, '', $strTmp);
        $strTmp = str_replace(chr(13), '', $strTmp);
        $strTmp = str_replace(chr(10), '', $strTmp);
        $strTmp = str_replace(' ', '', $strTmp);
    } else {
        $strTmp = '';
    }
    return $strTmp;
}
// Ophalen provincie op basis van een adres
/**
* Lib Provincie
*
*/
function Lib_Provincie($strAdres, $strPlaats)
{
    $strTmp = '';
    $objxml = null;
    $Node = null;
    $NodeList = null;
    $Elt = null;
    $Elt2 = null;
    $bGevonden = null;
    $strAdres = str_replace('  ', ' ', $strAdres);
    $strTmp = lib_HttpGet('https://maps.googleapis.com/maps/api/geocode/xml?address=' . ($strAdres != '' ? Server::urlEncode($strAdres) . ',' : '') . Server::urlEncode($strPlaats) . ',Nederland&sensor=false' . (Application::get('Google_api_key', '') != '' ? '&key=' . Application::get('Google_api_key', '') : ''));
    if ((($pos = stripos($strTmp, '<status>OK</status>', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
        $objxml = null /* COM Object: Microsoft.XMLDOM - not supported in PHP */;
        $objxml->LoadXml($strTmp);
        $NodeList = $objxml->documentElement.selectNodes('/GeocodeResponse/result/address_component');
        foreach ($NodeList as $Node) {
            $bGevonden = false;
            foreach ($Node->childnodes() as $Elt) {
                if ($Elt->text == 'administrative_area_level_1') {
                    $bGevonden = true;
                }
            }
            if ($bGevonden) {
                foreach ($Node->childnodes() as $Elt) {
                    foreach ($Elt->childnodes() as $Elt2) {
                        if ($Elt->NodeName == 'long_name') {
                            $strTmp = $Elt2->text();
                        }
                    }
                }
            }
        }
        $NodeList = null;
        $objxml = null;
    }
    // even zonder straatnaam proberen, test op leeg adres is om recursie te voorkomen
    if ((($pos = stripos($strTmp, 'INVALID_REQUEST')) !== false ? $pos + 1 : 0) > 0 || (($pos = stripos($strTmp, 'ZERO_RESULTS')) !== false ? $pos + 1 : 0) > 0 && $strAdres != '') {
        $strTmp = Lib_Provincie('', $strPlaats);
    }
    $strTmp = str_replace('North Holland', 'Noord-Holland', $strTmp);
    $strTmp = str_replace('South Holland', 'Zuid-Holland', $strTmp);
    $strTmp = str_replace('North Brabant', 'Noord-Brabant', $strTmp);
    return $strTmp;
}
// Calculate the distance between two pairs of Latitude,Lontitude in kilometers
/**
* Lib Geocode Distance
*
*/
function Lib_Geocode_distance($strFrom, $strTo)
{
    // dim arrLoc1 : arrLoc1 = Arr::splitAlways(location1, ",")
    // dim arrLoc2 : arrLoc2 = Arr::splitAlways(location2, ",")
    // Lib_Geocode_distance = Lib_Travel_Distance( cdbl( replace(arrLoc1(0),".",",")) &","& cdbl( replace(arrLoc1(1),,",")), cdbl( replace(arrLoc2(0),,","))&","&cdbl( replace(arrLoc2(1),,",")))
    $strTmp = "";
    $strUrl = 'https://maps.googleapis.com/maps/api/directions/xml?origin=' . Server::urlEncode($strFrom) . '&destination=' . Server::urlEncode($strTo) . '&sensor=false' . (Application::get('Google_api_key', '') != '' ? '&key=' . Application::get('Google_api_key', '') : '');
    $strTmp = lib_HttpGet($strUrl);
    // response.write strUrl
    // response.write "<hr>" & Server::htmlEncode($strTmp) & "<hr><br>"
    if ((($pos = stripos($strTmp, '<status>OK</status>', max(0, 1 - 1))) !== false ? $pos + 1 : 0) > 0) {
        $strTmp = substr($strTmp, max(0, (($pos = strrpos($strTmp, '<distance>')) !== false ? $pos + 1 : 0) + 10 - 1));
        $strTmp = substr($strTmp, 0, max(0, min((($pos = stripos($strTmp, '</distance>')) !== false ? $pos + 1 : 0) - 1, strlen($strTmp))));
        $strTmp = substr($strTmp, max(0, (($pos = strrpos($strTmp, '<text>')) !== false ? $pos + 1 : 0) + 6 - 1));
        $strTmp = substr($strTmp, 0, max(0, min((($pos = stripos($strTmp, '</text>')) !== false ? $pos + 1 : 0) - 1, strlen($strTmp))));
        $strTmp = str_replace(PHP_EOL, '', $strTmp);
        $strTmp = str_replace(chr(13), '', $strTmp);
        $strTmp = str_replace(chr(10), '', $strTmp);
        $strTmp = str_replace(' ', '', $strTmp);
    } else {
        $strTmp = '';
    }
    return str_replace('km', '', $strTmp);
}
// tester : http://developer.mapquest.com/web/tools/distance-calculator
/**
* Lib Distance Vogelvlucht Km
*
*/
function Lib_distance_vogelvlucht_km($geocode1, $geocode2)
{
    $arrGeocode1 = Arr::splitAlways($geocode1, ',');
    $arrGeocode2 = Arr::splitAlways($geocode2, ',');
    $dist = Lib_Distance(floatval(str_replace('.', ',', $arrGeocode1["0"])), floatval(str_replace('.', ',', $arrGeocode1["1"])), floatval(str_replace('.', ',', $arrGeocode2["0"])), floatval(str_replace('.', ',', $arrGeocode2["1"])), 'K');
    // dist = Lib_distance( arrGeocode1(0), arrGeocode1(1), arrGeocode2(0), arrGeocode2(1), "K")
    // response.write "Afstandsberekening: " & geocode1 & " - " & geocode2 & " = " & dist & "KM<br>"
    return $dist;
}
// In vogelvlucht: vrij onnauwkeurig
/**
* Lib Distance
*
*/
function Lib_distance($lat1, $lon1, $lat2, $lon2, $unit)
{
    $theta = null;
    $dist = null;
    // response.write "Lib_distance: lat1: " & lat1 & ", lon1: " & lon1 & ", lat2: " & lat2 & ", lon2: " & lon2 & "<br>"
    $theta = $lon1 - $lon2;
    $dist = sin($deg2rad[$lat1]) * sin($deg2rad[$lat2]) + cos($deg2rad[$lat1]) * cos($deg2rad[$lat2]) * cos($deg2rad[$theta]);
    $dist = $acos[$dist];
    $dist = $rad2deg[$dist];
    return $dist * 60 * 1.1515;
    switch ((is_null($unit) ? "" : strtoupper($unit))) {
    case 'K':
        return Lib_distance * 1.609344;
        break;
    case 'N':
        return Lib_distance * 0.8684;
        break;
    }
}
// This function get the arccos function from arctan function
// Skipped: acos() - using PHP built-in function
// This function converts decimal degrees to radians
// Skipped: deg2rad() - using PHP built-in function
// This function converts radians to decimal degrees
// Skipped: rad2deg() - using PHP built-in function
?>
