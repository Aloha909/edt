<?php
require_once 'ics-parser/src/ICal/ICal.php';
require_once 'ics-parser/src/ICal/Event.php';

use ICal\ICal;

// This code was borrowed to Clément Grennerat at https://github.com/ClementGre/insa-utils
// This version changes how the locations are treated. Even if the room argument is false, the location will be added to the location property of the iCal event.
// The argument only controls if the location should be displayed in the name of the event (summary property)
// The description is also modified to show clearer information

// update -> subjects and subject tags for the second year


function convertCalendar($url, $mode, $room)
{
    try {
        $ical = new ICal('ICal.ics', array(
            'defaultSpan' => 2,     // Default value
            'defaultTimeZone' => 'UTC',
            'defaultWeekStart' => 'MO',  // Default value
            'disableCharacterReplacement' => false, // Default value
            'filterDaysAfter' => null,  // Default value
            'filterDaysBefore' => null,  // Default value
            'httpUserAgent' => null,  // Default value
            'skipRecurrence' => false, // Default value
        ));


        $ical->initUrl($url, $username = null, $password = null, $userAgent = null);

        header("Content-type:text/calendar");
        header("Content-Disposition:attachment;filename=edt_insa_alois.ics");

        echo "BEGIN:VCALENDAR\r\n";
        echo "METHOD:REQUEST\r\n";
        echo "PRODID:-//themsVPS/version 1.0\r\n";
        echo "VERSION:2.0\r\n";
        echo "CALSCALE:GREGORIAN\r\n";

        foreach ($ical->events() as $i => $event) {
            editEventAndPrint($event, $mode, $room);
        }

        echo "END:VCALENDAR\r\n";

    } catch (\Exception $e) {
        die($e);
    }
}

function editEventAndPrint($event, $mode, $room) {

    // $line1 = explode("\n()", $event->description)[0];
    // $subject = explode("] ", $line1)[1]; // Full name

    $description = "";
    $line1 = explode("\n\n", $event->description)[0];
    $subject = str_replace("\n()", "", explode("] ", $line1)[1]); // Full name
    $description .= $subject;
    $description .= "\n\n";

    $paragraph = explode("\n\n", $event->description)[1];
    $lines = explode("\n", $paragraph);
    foreach ($lines as $line) {
        if ($line === $lines[0]) {
            $description .= $line;
            if (count($lines) > 1 && count($lines) < 3) {
                $description .= "\n\nProf : ";
            } elseif (count($lines) > 2) {
                $description .= "\n\nProfs :";
            }
        } else {
            if (count($lines) > 1 && count($lines) < 3) {
                $description .= $line;
            } elseif (count($lines) > 2) {
                $description .= "\n - ";
                $description .= $line;
            }
        }
    }

    $explodedSummary = explode("::", $event->summary);
    $subSummary = count($explodedSummary) >= 2 ? $explodedSummary[1] : ""; // PC-S1-PH-AMP
    $explodedSubSummary = explode(":", $subSummary); // MS-TF-SH1:CM

    $tag = count($explodedSubSummary) >= 1 ? $explodedSubSummary[0] : ""; // MS-TF-SH1
    $type = count($explodedSubSummary) >= 2 ? $explodedSubSummary[1] : null; // CM, TD, TP, EV => IE
    if ($type == "EV") $type = "IE";
    if ($type == "EDT") $type = null;

    if (substr($tags, 0, strlen("ANG-1FC-")) === "ANG-1FC-") { // Anglais
        $tag = "PC-S1-ANG";
    }

    if (substr($tags, 0, strlen("EPS-1-MA14-")) === "EPS-1-MA14-") { // Sport
        $tag = "PC-S1-EPS";
        $type = null;
    }

    $subjectTag = str_replace("PC-S2-", "", str_replace("PC-S1-", "", $tag)); // PH-AMP, MA-AP, SOL-TF

    $num_salle_pattern = '/\d{7}/';
    if (preg_match($num_salle_pattern, $event->location, $matches)) {
        $num_salle = $matches; 
    }

    // with $num_salle, get lat/long, generate google maps url and add to $description


    // $location = $event->location == null ? null : str_replace("Amphithéâtre", "Amphi", explode(" - ", $event->location)[1]); // Room letter & number only
    $location_pattern = '/\d{7} - /';
    $location = $event->location == null ? null : str_replace("\\,", ", ", str_replace("Amphithéâtre", "Amphi", preg_replace($location_pattern, "", $event->location)));

    if (!$room || $room == 'false') $name_location = null;
    else $name_location = $event->location == null ? null :
        // str_replace("Amphithéâtre", "Amphi", explode(" - ", $event->location)[1]); // Room letter & number only
        str_replace("\\", "", str_replace("Amphithéâtre", "Amphi", preg_replace($location_pattern, "", $event->location)));

    if ($tag === "PC-S1-SOU-EDT" // Soutien
        || $tag === "PC-S13-LV-EDT" // Langues *2
        || $tag === "PC-S13-EPS-EDT") { // Sport *2
        return;
    }

    // Modes : 0 = full name, 1 = short, 2 = default
    if ($mode != 2) {
        $subjectTag = explode("-", $subjectTag)[0];
        if ($mode != 1) { // Default, Human full readable names
            if ($subjectTag === "PH") {
                $subjectTag = "d'Electromagnetisme";
            } elseif ($subjectTag === "MA") {
                $subjectTag = "de Maths";
            } elseif ($subjectTag === "CP") {
                $subjectTag = "de Conception-Prototypage";
            } elseif ($subjectTag === "CH") {
                $subjectTag = "de Chimie";
            } elseif ($subjectTag === "TH") {
                $subjectTag = "de Thermo";
            } elseif ($subjectTag === "ANG") {
                $subjectTag = "d'Anglais";
            } elseif ($subjectTag === "EPS") {
                $subjectTag = "Sport";
            } elseif ($subjectTag === "CSS") {
                $subjectTag = "de CSS";
            } elseif ($subjectTag === "ISN") {
                $subjectTag = "d'ISN";
            } elseif ($subjectTag === "MS") {
                $subjectTag = "Mécanique des systèmes";
            } elseif ($subjectTag === "ETRE") {
                $subjectTag = "d'ETRE";
            } elseif ($subjectTag === "SOU") {
                $subjectTag = "Soutien";
            } elseif ($subjectTag === "LV") {
                $subjectTag = "Anglais";
            } else {
                // Aucune correspondance, conserver la valeur d'origine
            };
        }
    }

    $event->summary = ($type == null ? "" : $type . " ") . $subjectTag . ($name_location == null ? "" : " - " . $name_location);
    $event->location = $location;
    $event->description=$description;

    printEvent($event);
}

function printEvent($event)
{
    echo "BEGIN:VEVENT\r\n";
    echo getEventDataString($event);
    echo "END:VEVENT\r\n";
}

function getEventDataString($event)
{
    $data = array(
        'SUMMARY' => $event->summary,
        'DTSTART' => $event->dtstart,
        'DTEND' => $event->dtend,
        /*'DTSTART_TZ' => $event->dtstart_tz,
        'DTEND_TZ' => $event->dtend_tz,*/
        'DURATION' => $event->duration,
        'DTSTAMP' => $event->dtstamp,
        'UID' => $event->uid,
        'CREATED' => $event->created,
        'LAST-MODIFIED' => $event->last_modified,
        'DESCRIPTION' => $event->description,
        'LOCATION' => $event->location,
        'SEQUENCE' => $event->sequence,
        'STATUS' => $event->status,
        'TRANSP' => $event->transp,
        'ORGANISER' => $event->organizer,
        'ATTENDEE(S)' => $event->attendee,
    );

    // Remove any blank values
    $data = array_filter($data);

    $output = '';
    foreach ($data as $key => $value) {
        $output .= sprintf("%s:%s\r\n", $key, str_replace("\n", "\\n", $value));
    }
    return $output;
}


if (isset($_GET['url'])) {
    convertCalendar(urldecode($_GET['url']), $_GET['mode'], $_GET['room']);
} else {
    header("Location: ./");
}

?>