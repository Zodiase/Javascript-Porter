<?php
/**
 * Javascript Porter ver 2.0
 * ver 2.0 change log
 * Now supports file inclusion, which implements
 * "file dependency" functionality.
 *     Add single-line inclusion commands to include
 *     necessary files.
 *     Syntax:
 *         //#include <filename>
 *         //#include_once <filename>
 *     filename is the name of the Javascript to be
 *     included, without extension ".js".
 *     When using "#include_once", the file will only
 *     be included when that file has not yet been
 *     included before.
 *         If loading file A, B and C, while A and B
 *         both need to include D, when processing
 *         the "#include_once" command in file B, D
 *         will not be included again.
 *     The inclusion command is find recuisively; if
 *     the file being included needs to include other
 *     files, those file will be loaded first.
 *     If any file that is need by one file fails to
 *     be loaded, either not existing or unreadable,
 *     the whole file is considered failing to be loaded.
 * Change the header to console commands.
 * Added a switch to disable header.
 *
 * ver 1.1 change log
 * Fixed the issue when removing in-line comments. Now only
 * single-line comments will be removed, pending in-line
 * comments will remain.
 *
 * ver 1.0 change log
 * This program gathers and sends multiple Javascript files
 * on demand which benefits webpage loading speed in the
 * way of avoiding unnecessary requests and reduces the
 * amount of Script tags on pages.
 *
 * Instruction:
 * This program search under the preset folder (JSFILEFOLDER)
 * for requested files and send them in order
 * This program accepts 4 arguments: l(link list), duplicate
 * , noheader and compress
 * Arguments:
 *     link list
 *         A list of names of Javascript files needed to be gathered
 *         Do not include file extension (.js), separate with commas
 *     duplicate
 *         Allow duplication or not (i.e. when the request is "A,B,A", if duplication is not allowed, the last duplicated A will be ignored)
 *         Acceptable Value: true | yes | false | no
 *         Default: no
 *     noheader
 *         disable header
 *         Acceptable Value: true | yes | false | no
 *         Default: no
 *     compress
 *         Level of compression
 *         Add 1 to remove comment blocks
 *         Add 2 to remove comment lines
 *         Add 4 to remove indents
 *         Add 8 to remove line breakers
 *         i.e. if need to remove all comments, use value 3 (Add 1 and 2)
 *         Default: 0 (no compression)
 * Request Sample: js.php?l=jquery-latest,yui-latest&duplicate=true&compress=15&noheader=true
 * jquery-latest.js and yui-latest.js are requested, allowing duplication, at compression level of 15 and disable header
 */
define('APPNAME', 'Javascript Porter ver 2.0');
define('JSFILEFOLDER', './js');
// output buffer start
ob_start();
// set output header
header('Content-Type: application/javascript');
header('Content-Disposition: inline; filename="container.js"');
header('Cache-Control: no-cache');
header('Pragma: no-cache');
// print title
printf("console.group('%s');n", APPNAME);
// fetch raw request
// if no js file list request detected, halt
if (!array_key_exists('l', $_GET))
    die("console.warn('no request');");
$rawRequestString = $_GET['l'];
$noHeader  = array_key_exists('noheader', $_GET)
    ? (($_GET['noheader'] == 'yes' || $_GET['noheader'] == 'true')
    ? true
    : false)
    : false;
$allowDuplicate  = array_key_exists('duplicate', $_GET)
    ? (($_GET['duplicate'] == 'yes' || $_GET['duplicate'] == 'true')
    ? true
    : false)
    : false;
$compression      = array_key_exists('compress', $_GET)
    ? intval($_GET['compress'])
    : 0;
// force direct to directory containing this file
chdir(dirname(__FILE__));
// javascript file folder check
if (!file_exists(JSFILEFOLDER) || !is_dir(JSFILEFOLDER))
    die("console.error('js directory not found');");
// force direct to directory containing javascript files
chdir(JSFILEFOLDER);
function jsCompress($jsContent, $compressLevel)
{
    // 1 remove comment blocks
    // 2 remove comment lines
    // 3 remove all comments
    // 4 remove indents
    // 5 remove comment blocks and indents
    // 6 remove comment lines and indents
    // 7 remove all comments and indents
    // 8 remove line breakers *
    // 9 remove comment blocks and line breakers *
    // 10 remove comment lines and line breakers
    // 11 remove all comments and line breakers
    // 12 remove indents and line breakers *
    // 13 remove comment blocks, indents and line breakers *
    // 14 remove comment lines, indents and line breakers
    // 15 remove all comments and line breakers
    // * not suggested using without removing comment lines
    // if invalid comression level, return raw content
    if ($compressLevel > 15)
        return $jsContent;
    // remove comment blocks
    if ($compressLevel % 2 >= 1)
        $jsContent = preg_replace('//*.**//uUs', '', $jsContent);
    // remove comment lines
    if ($compressLevel % 4 >= 2)
        $jsContent = preg_replace('/^s*//.*$/uUm', '', $jsContent);
    // remove indents
    if ($compressLevel % 8 >= 4)
        $jsContent = preg_replace('/^s+/um', '', $jsContent);
    // remove line breakers
    if ($compressLevel % 16 >= 8)
        $jsContent = preg_replace('/(n|r)/um', ' ', $jsContent);
    return $jsContent;
}
function parseItems($itemString)
{
    $rawItems = explode(',', $itemString);
    $resultItems = array();
    for ($i = 0; $i < count($rawItems); ++$i) {
        $temp = trimKey($rawItems[$i]);
        if ($temp !== '') array_push($resultItems, $temp);
        unset($temp);
    }
    unset($rawItems);
    return $resultItems;
}
function trimKey($filename)
{
    return urlencode(trim(basename($filename)));
}
// a dummy function for getContent()
function getContentOnlyIfNotInBank($key, &$contentBank)
{
    return getContent($key, &$contentBank, true);
}
function getContent($key, &$contentBank, $onlyIfNotInBank = false)
{
    $key = trimKey($key);
    if (array_key_exists($key, $contentBank)) {
        return (!$onlyIfNotInBank) ? $contentBank[$key] : '';
    } else {
        $returnValue = false;
        $filename = "{$key}.js";
        printf("console.group('getting content of %s');n", $filename);
        if (!file_exists($filename) || !is_file($filename) || !is_readable($filename)) {
            print("console.warn('file not found or not readable');n");
        } else {
            $rawFileContent = file_get_contents($filename);
            if ($rawFileContent === false) {
                print("console.warn('file not readable');n");
            } else {
                $fileComplete = true;
                // deal with include_once commands
                while ($fileComplete && preg_match('/^(//#include_once <(.{1,})>)$/um', $rawFileContent, $matches) > 0) {
                    $includingCommand = $matches[1];
                    $includingKey = trimKey($matches[2]);
                    printf("console.log('including %s');n", $includingKey);
                    $includingContent = getContentOnlyIfNotInBank($includingKey, $contentBank);
                    if ($includingContent === false) {
                        print("console.warn('including failed, file incomplete');n");
                        $fileComplete = false; // break;
                    } else {
                        $rawFileContent = str_replace($includingCommand, $includingContent, $rawFileContent);
                        print("console.log('including done');n");
                    }
                    unset($includingCommand, $includingKey, $includingContent);
                }
                // deal with include commands
                while ($fileComplete && preg_match('/^(//#include <(.{1,})>)$/um', $rawFileContent, $matches) > 0) {
                    $includingCommand = $matches[1];
                    $includingKey = trimKey($matches[2]);
                    printf("console.log('including %s');n", $includingKey);
                    $includingContent = getContent($includingKey, $contentBank);
                    if ($includingContent === false) {
                        print("console.warn('including failed, file incomplete');n");
                        $fileComplete = false; // break;
                    } else {
                        $rawFileContent = str_replace($includingCommand, $includingContent, $rawFileContent);
                        print("console.log('including done');n");
                    }
                    unset($includingCommand, $includingKey, $includingContent);
                }
                $returnValue = ($fileComplete) ? $rawFileContent : false;
                unset($fileComplete);
            }
            unset($rawFileContent);
        }
        print("console.groupEnd();n");
        $contentBank[$key] = $returnValue;
        return $returnValue;
    }
}
printf("console.info('raw request: %s');n", $rawRequestString);
printf("console.info('allow duplicates: %s');n", $allowDuplicate ? 'true' : 'false');
// turn requested file list (seperated by ',') into an array
$requestList = parseItems($rawRequestString);
// traversal all requested items and setup a hash table for files to be loaded
// printList is an array of js filenames in which order js file contents should be printed
$printList = array();
while (count($requestList) > 0) {
    $requestItem = array_shift($requestList);
    if ($allowDuplicate || !in_array($requestItem, $printList, true)) array_push($printList, $requestItem);
}
print("console.group('requested js files...');n");
// $contents is an array of js file contents with their filename as keys
$contents = array();
for ($i = 0; $i < count($printList); ++$i) {
    $key = $printList[$i];
    $printList[$i] = jsCompress(getContent($key, $contents), $compression);
    printf("console.log('%s %s');n", $key, (!$printList[$i]) ? 'false' : md5($printList[$i]));
}
print("console.groupEnd();n");
// clean header if requested
if ($noHeader) ob_clean();
for ($i = 0; $i < count($printList); ++$i) {
    $content = (!$printList[$i]) ? '' : "n{$printList[$i]}n";
    print($content);
}
ob_end_flush();
?>