<?php
// Simple web interface to adjust Qt4 translation files.
// (c) 2010 Dominik Riebeling
//
error_reporting(E_ALL);

function parse_update_xml($tsfile, $mode, $update = 0)
{
    $row = 0;
    foreach($tsfile->children() as $child) {
        $classname = $child->name;
        if($classname == "")
            $classname = "(unknown)";
        if($update == 0)
            echo("<tr><td colspan='5' class='cppclass'>$classname</td></tr>\n");

        foreach($child->message as $msg) {
            $status = "(unknown)";
            $sourcestring = preg_replace("/\n/", "<span class='cr'>CR</span><br/>",
                htmlspecialchars($msg->source));
            $locations = $msg->location;
            $location = "";
            foreach($locations as $s) {
                $l = $s->attributes();
                if($location != "")
                    $location .= ", ";
                $location .= $l['filename'] . ":" . $l['line'];
            }
            $translation = htmlspecialchars($msg->translation);
            $transstatus = $msg->translation->attributes();
            $status = $transstatus['type'];
            $comment = $msg->comment;
            if($comment == "")
                $comment = "(no translation comment available)";
            $rowclass = "c" . $row%2;
            if($mode == "empty" && $translation != "")
                continue;
            // FIXME: try to check for empty strings as well, don't rely on the
            // ts file having unfinished set for those.
            if($mode == "unfinished" && $status != "unfinished")
                continue;

            if($update == 0) {
                echo("<tr class='$rowclass' >\n");
                echo("<td>$sourcestring</td>\n");
                echo("<td>$status</td>\n");
                echo("<td>$comment</td>\n");
                echo("</tr>\n");
                echo("<tr class='$rowclass'>\n");
                echo("<td><textarea rows='3' cols='100' name='translation-$row'>"
                    ."$translation</textarea></td>\n");
                echo("<td></td>\n");
                echo("<td class='location'>$location</td>\n");
                echo("</tr>\n");
            }
            else {
                if(array_key_exists("translation-$row", $_POST)) {
                    $msg->translation = $_POST["translation-$row"];
                    // unset the "unfinished" translation type if it contains
                    // text.
                    // FIXME: allow the user to control this.
                    if($_POST["translation-$row"] != "") {
                        // to remove an attribute unset() it.
                        unset($msg->translation['type']);
                    }
                }
            }
            $row++;
        }
    }
    return $row;
}

if(array_key_exists('translation', $_POST))
    $inputfile = $_POST['translation'];
if(array_key_exists('inputfile', $_GET)) {
    $inputfile = $_GET['inputfile'];
    if(preg_match("/\.\./", $inputfile))
        die("invalid request!");
}
else if(array_key_exists('inputfile', $_POST))
    $inputfile = $_POST['inputfile'];

// load input file
if(isset($inputfile))
    $tsfile = simplexml_load_file($inputfile);


// if we want the updated file send out xml
if(array_key_exists('update', $_POST)) {
    header("Content-type: text/xml");
    // update data that has been sent in the POST request

    // send out the xml
    parse_update_xml($tsfile, $_POST['show'], 1);
    echo($tsfile->asXML());
    exit(0);
}
    
if(isset($inputfile))
    $title = $inputfile;
else
    $title = "Overview";

$show = "all";
if(array_key_exists('show', $_GET))
    $show = $_GET['show'];
else if(array_key_exists('show', $_POST))
    $show = $_POST['show'];


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style class='text/css'>
.cppclass { background-color:#ccc; text-align:center; }
.c0 { background-color:#9abdde; }
.c1 { background-color:#aacdee; }
.location { text-size:small; }
.cr { font-size:x-small; font-color:#ccc; }
.header { background-color:#729fcf; }
table { margin:0px; padding:0px; border-spacing:0px; }
td { padding:2px; padding-left:1em; padding-right:1em; }
</style>
<title><?php echo("Weblinguist: $title"); ?></title>
</head>
<body>

<?php
date_default_timezone_set("UTC");
if(!isset($inputfile)) {
    $files = glob("lang/*.ts");
    $row = 0;
    echo("<table>\n");
    echo("<tr class='header'><td><b>Language</b></td><td>source file timestamp</td><td colspan='3'><b>Edit</b></td></tr>");
    foreach($files as $f) {
        echo("<tr class='c" . $row%2 . "'>");
        echo("<td>$f</td>");
        echo("<td>" . date(DateTime::ISO8601, filemtime($f)) . "</td>");
        echo("<td><a href='$_SERVER[PHP_SELF]?inputfile=$f&amp;show=all'>all strings</a></td>");
        echo("<td><a href='$_SERVER[PHP_SELF]?inputfile=$f&amp;show=unfinished'>unfinished strings</a></td>");
        echo("<td><a href='$_SERVER[PHP_SELF]?inputfile=$f&amp;show=empty'>empty strings</a></td>");
        echo("</tr>\n");
        $row++;
    }
    echo("</table>\n");
}
else {
    echo("Translation language: " . $tsfile['language'] . "<br/>\n");
    echo("TS version: " . $tsfile['version'] . "<br/>\n");
    if(isset($inputfile)) {
        echo("<p>");
        echo("<a href='?inputfile=$inputfile&amp;show=all'>show all</a> * ");
        echo("<a href='?inputfile=$inputfile&amp;show=unfinished'>show unfinished</a> * ");
        echo("<a href='?inputfile=$inputfile&amp;show=empty'>show empty</a>");
        echo("</p>\n");
        echo("<p>");
        echo("Translation language: " . $tsfile['language'] . "<br/>\n");
        echo("TS version: " . $tsfile['version'] . "<br/>\n");
        echo("</p>\n");
    }

    if($show == "all")
        echo("<b>showing all strings</b>");
    else if($show == "empty")
        echo("<b>showing only empty translations.</b>");
    else if($show == "unfinished")
        echo("<b>showing unfinished (including empty) translations.</b>");

    echo("<p><b>Translating file $inputfile</b></p>\n");
    echo("<form action='$_SERVER[PHP_SELF]' method='POST'>\n");
    echo("<input type='hidden' name='show' value='$show'/>\n");
    echo("<input type='hidden' name='update' value='true'/>\n");
    echo("<input type='hidden' name='inputfile' value='$inputfile' />\n");
    echo("<table>\n");
    $rows = parse_update_xml($tsfile, $show);
    echo("</table>");

    if($rows > 0)
        echo("<input type='submit'/>");
    echo("</form>");
    if($rows == 0)
        echo("<b>No matching strings found!</b>");
}
?>
</body>
</html>

