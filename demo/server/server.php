<?php
/**
 * Demo server for xmlrpc library.
 *
 * Implements a lot of webservices, including a suite of services used for
 * interoperability testing (validator1 methods), and some whose only purpose
 * is to be used for unit-testing the library.
 *
 * Please do not copy this file verbatim into your production server.
 **/

// give user a chance to see the source for this server instead of running the services
if ($_SERVER['REQUEST_METHOD'] != 'POST' && isset($_GET['showSource'])) {
    highlight_file(__FILE__);
    die();
}

include_once __DIR__ . "/../../vendor/autoload.php";

// out-of-band information: let the client manipulate the server operations.
// we do this to help the testsuite script: do not reproduce in production!
if (isset($_COOKIE['PHPUNIT_SELENIUM_TEST_ID']) && extension_loaded('xdebug')) {
    $GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'] = '/tmp/phpxmlrpc_coverage';
    if (!is_dir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'])) {
        mkdir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY']);
    }

    include_once __DIR__ . "/../../vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/prepend.php";
}

use PhpXmlRpc\Value;

/**
 * Used to test usage of object methods in dispatch maps and in wrapper code.
 */
class xmlrpcServerMethodsContainer
{
    /**
     * Method used to test logging of php warnings generated by user functions.
     */
    public function phpWarningGenerator($m)
    {
        $a = $undefinedVariable; // this triggers a warning in E_ALL mode, since $undefinedVariable is undefined
        return new PhpXmlRpc\Response(new Value(1, 'boolean'));
    }

    /**
     * Method used to test catching of exceptions in the server.
     */
    public function exceptionGenerator($m)
    {
        throw new Exception("it's just a test", 1);
    }

    /**
    * A PHP version of the state-number server. Send me an integer and i'll sell you a state
    * @param integer $s
    * @return string
    */
    public static function findState($s)
    {
        return inner_findstate($s);
    }
}

// a PHP version of the state-number server
// send me an integer and i'll sell you a state

$stateNames = array(
    "Alabama", "Alaska", "Arizona", "Arkansas", "California",
    "Colorado", "Columbia", "Connecticut", "Delaware", "Florida",
    "Georgia", "Hawaii", "Idaho", "Illinois", "Indiana", "Iowa", "Kansas",
    "Kentucky", "Louisiana", "Maine", "Maryland", "Massachusetts", "Michigan",
    "Minnesota", "Mississippi", "Missouri", "Montana", "Nebraska", "Nevada",
    "New Hampshire", "New Jersey", "New Mexico", "New York", "North Carolina",
    "North Dakota", "Ohio", "Oklahoma", "Oregon", "Pennsylvania", "Rhode Island",
    "South Carolina", "South Dakota", "Tennessee", "Texas", "Utah", "Vermont",
    "Virginia", "Washington", "West Virginia", "Wisconsin", "Wyoming",
);

$findstate_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcInt));
$findstate_doc = 'When passed an integer between 1 and 51 returns the
name of a US state, where the integer is the index of that state name
in an alphabetic order.';

function findState($m)
{
    global $stateNames;

    $err = "";
    // get the first param
    $sno = $m->getParam(0);

    // param must be there and of the correct type: server object does the validation for us

    // extract the value of the state number
    $snv = $sno->scalarval();
    // look it up in our array (zero-based)
    if (isset($stateNames[$snv - 1])) {
        $stateName = $stateNames[$snv - 1];
    } else {
        // not there, so complain
        $err = "I don't have a state for the index '" . $snv . "'";
    }

    // if we generated an error, create an error return response
    if ($err) {
        return new PhpXmlRpc\Response(0, PhpXmlRpc\PhpXmlRpc::$xmlrpcerruser, $err);
    } else {
        // otherwise, we create the right response with the state name
        return new PhpXmlRpc\Response(new Value($stateName));
    }
}

/**
 * Inner code of the state-number server.
 * Used to test auto-registration of PHP functions as xmlrpc methods.
 *
 * @param integer $stateNo the state number
 *
 * @return string the name of the state (or error description)
 */
function inner_findstate($stateNo)
{
    global $stateNames;

    if (isset($stateNames[$stateNo - 1])) {
        return $stateNames[$stateNo - 1];
    } else {
        // not, there so complain
        return "I don't have a state for the index '" . $stateNo . "'";
    }
}

$wrapper = new PhpXmlRpc\Wrapper();

$findstate2_sig = $wrapper->wrap_php_function('inner_findstate');

$findstate3_sig = $wrapper->wrap_php_function(array('xmlrpcServerMethodsContainer', 'findState'));

$findstate5_sig = $wrapper->wrap_php_function('xmlrpcServerMethodsContainer::findState');

$obj = new xmlrpcServerMethodsContainer();
$findstate4_sig = $wrapper->wrap_php_function(array($obj, 'findstate'));

$addtwo_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcInt, Value::$xmlrpcInt));
$addtwo_doc = 'Add two integers together and return the result';
function addTwo($m)
{
    $s = $m->getParam(0);
    $t = $m->getParam(1);

    return new PhpXmlRpc\Response(new Value($s->scalarval() + $t->scalarval(), "int"));
}

$addtwodouble_sig = array(array(Value::$xmlrpcDouble, Value::$xmlrpcDouble, Value::$xmlrpcDouble));
$addtwodouble_doc = 'Add two doubles together and return the result';
function addTwoDouble($m)
{
    $s = $m->getParam(0);
    $t = $m->getParam(1);

    return new PhpXmlRpc\Response(new Value($s->scalarval() + $t->scalarval(), "double"));
}

$stringecho_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcString));
$stringecho_doc = 'Accepts a string parameter, returns the string.';
function stringEcho($m)
{
    // just sends back a string
    return new PhpXmlRpc\Response(new Value($m->getParam(0)->scalarval()));
}

$echoback_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcString));
$echoback_doc = 'Accepts a string parameter, returns the entire incoming payload';
function echoBack($m)
{
    // just sends back a string with what i got sent to me, just escaped, that's all
    $s = "I got the following message:\n" . $m->serialize();

    return new PhpXmlRpc\Response(new Value($s));
}

$echosixtyfour_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcBase64));
$echosixtyfour_doc = 'Accepts a base64 parameter and returns it decoded as a string';
function echoSixtyFour($m)
{
    // Accepts an encoded value, but sends it back as a normal string.
    // This is to test that base64 encoding is working as expected
    $incoming = $m->getParam(0);

    return new PhpXmlRpc\Response(new Value($incoming->scalarval(), "string"));
}

$bitflipper_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$bitflipper_doc = 'Accepts an array of booleans, and returns them inverted';
function bitFlipper($m)
{
    $v = $m->getParam(0);
    $sz = $v->arraysize();
    $rv = new Value(array(), Value::$xmlrpcArray);

    for ($j = 0; $j < $sz; $j++) {
        $b = $v->arraymem($j);
        if ($b->scalarval()) {
            $rv->addScalar(false, "boolean");
        } else {
            $rv->addScalar(true, "boolean");
        }
    }

    return new PhpXmlRpc\Response($rv);
}

// Sorting demo
//
// send me an array of structs thus:
//
// Dave 35
// Edd  45
// Fred 23
// Barney 37
//
// and I'll return it to you in sorted order

function agesorter_compare($a, $b)
{
    global $agesorter_arr;

    // don't even ask me _why_ these come padded with hyphens, I couldn't tell you :p
    $a = str_replace("-", "", $a);
    $b = str_replace("-", "", $b);

    if ($agesorter_arr[$a] == $agesorter_arr[$b]) {
        return 0;
    }

    return ($agesorter_arr[$a] > $agesorter_arr[$b]) ? -1 : 1;
}

$agesorter_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$agesorter_doc = 'Send this method an array of [string, int] structs, eg:
<pre>
 Dave   35
 Edd    45
 Fred   23
 Barney 37
</pre>
And the array will be returned with the entries sorted by their numbers.
';
function ageSorter($m)
{
    global $agesorter_arr, $s;

    PhpXmlRpc\Server::xmlrpc_debugmsg("Entering 'agesorter'");
    // get the parameter
    $sno = $m->getParam(0);
    // error string for [if|when] things go wrong
    $err = "";
    // create the output value
    $v = new Value();
    $agar = array();

    $max = $sno->arraysize();
    PhpXmlRpc\Server::xmlrpc_debugmsg("Found $max array elements");
    for ($i = 0; $i < $max; $i++) {
        $rec = $sno->arraymem($i);
        if ($rec->kindOf() != "struct") {
            $err = "Found non-struct in array at element $i";
            break;
        }
        // extract name and age from struct
        $n = $rec->structmem("name");
        $a = $rec->structmem("age");
        // $n and $a are xmlrpcvals,
        // so get the scalarval from them
        $agar[$n->scalarval()] = $a->scalarval();
    }

    $agesorter_arr = $agar;
    // hack, must make global as uksort() won't
    // allow us to pass any other auxiliary information
    uksort($agesorter_arr, 'agesorter_compare');
    $outAr = array();
    while (list($key, $val) = each($agesorter_arr)) {
        // recreate each struct element
        $outAr[] = new Value(array("name" => new Value($key),
            "age" => new Value($val, "int"),), "struct");
    }
    // add this array to the output value
    $v->addArray($outAr);

    if ($err) {
        return new PhpXmlRpc\Response(0, PhpXmlRpc\PhpXmlRpc::$xmlrpcerruser, $err);
    } else {
        return new PhpXmlRpc\Response($v);
    }
}

// signature and instructions, place these in the dispatch
// map
$mailsend_sig = array(array(
    Value::$xmlrpcBoolean, Value::$xmlrpcString, Value::$xmlrpcString,
    Value::$xmlrpcString, Value::$xmlrpcString, Value::$xmlrpcString,
    Value::$xmlrpcString, Value::$xmlrpcString,
));
$mailsend_doc = 'mail.send(recipient, subject, text, sender, cc, bcc, mimetype)<br/>
recipient, cc, and bcc are strings, comma-separated lists of email addresses, as described above.<br/>
subject is a string, the subject of the message.<br/>
sender is a string, it\'s the email address of the person sending the message. This string can not be
a comma-separated list, it must contain a single email address only.<br/>
text is a string, it contains the body of the message.<br/>
mimetype, a string, is a standard MIME type, for example, text/plain.
';
// WARNING; this functionality depends on the sendmail -t option
// it may not work with Windows machines properly; particularly
// the Bcc option. Sneak on your friends at your own risk!
function mailSend($m)
{
    $err = "";

    $mTo = $m->getParam(0);
    $mSub = $m->getParam(1);
    $mBody = $m->getParam(2);
    $mFrom = $m->getParam(3);
    $mCc = $m->getParam(4);
    $mBcc = $m->getParam(5);
    $mMime = $m->getParam(6);

    if ($mTo->scalarval() == "") {
        $err = "Error, no 'To' field specified";
    }

    if ($mFrom->scalarval() == "") {
        $err = "Error, no 'From' field specified";
    }

    $msghdr = "From: " . $mFrom->scalarval() . "\n";
    $msghdr .= "To: " . $mTo->scalarval() . "\n";

    if ($mCc->scalarval() != "") {
        $msghdr .= "Cc: " . $mCc->scalarval() . "\n";
    }
    if ($mBcc->scalarval() != "") {
        $msghdr .= "Bcc: " . $mBcc->scalarval() . "\n";
    }
    if ($mMime->scalarval() != "") {
        $msghdr .= "Content-type: " . $mMime->scalarval() . "\n";
    }
    $msghdr .= "X-Mailer: XML-RPC for PHP mailer 1.0";

    if ($err == "") {
        if (!mail("",
            $mSub->scalarval(),
            $mBody->scalarval(),
            $msghdr)
        ) {
            $err = "Error, could not send the mail.";
        }
    }

    if ($err) {
        return new PhpXmlRpc\Response(0, PhpXmlRpc\PhpXmlRpc::$xmlrpcerruser, $err);
    } else {
        return new PhpXmlRpc\Response(new Value("true", Value::$xmlrpcBoolean));
    }
}

$getallheaders_sig = array(array(Value::$xmlrpcStruct));
$getallheaders_doc = 'Returns a struct containing all the HTTP headers received with the request. Provides limited functionality with IIS';
function getallheaders_xmlrpc($m)
{
    $encoder = new PhpXmlRpc\Encoder();

    if (function_exists('getallheaders')) {
        return new PhpXmlRpc\Response($encoder->encode(getallheaders()));
    } else {
        $headers = array();
        // IIS: poor man's version of getallheaders
        foreach ($_SERVER as $key => $val) {
            if (strpos($key, 'HTTP_') === 0) {
                $key = ucfirst(str_replace('_', '-', strtolower(substr($key, 5))));
                $headers[$key] = $val;
            }
        }

        return new PhpXmlRpc\Response($encoder->encode($headers));
    }
}

$setcookies_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcStruct));
$setcookies_doc = 'Sends to client a response containing a single \'1\' digit, and sets to it http cookies as received in the request (array of structs describing a cookie)';
function setCookies($m)
{
    $encoder = new PhpXmlRpc\Encoder();
    $m = $m->getParam(0);
    while (list($name, $value) = $m->structeach()) {
        $cookieDesc = $encoder->decode($value);
        setcookie($name, @$cookieDesc['value'], @$cookieDesc['expires'], @$cookieDesc['path'], @$cookieDesc['domain'], @$cookieDesc['secure']);
    }

    return new PhpXmlRpc\Response(new Value(1, 'int'));
}

$getcookies_sig = array(array(Value::$xmlrpcStruct));
$getcookies_doc = 'Sends to client a response containing all http cookies as received in the request (as struct)';
function getCookies($m)
{
    $encoder = new PhpXmlRpc\Encoder();
    return new PhpXmlRpc\Response($encoder->encode($_COOKIE));
}

$v1_arrayOfStructs_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcArray));
$v1_arrayOfStructs_doc = 'This handler takes a single parameter, an array of structs, each of which contains at least three elements named moe, larry and curly, all <i4>s. Your handler must add all the struct elements named curly and return the result.';
function v1_arrayOfStructs($m)
{
    $sno = $m->getParam(0);
    $numCurly = 0;
    for ($i = 0; $i < $sno->arraysize(); $i++) {
        $str = $sno->arraymem($i);
        $str->structreset();
        while (list($key, $val) = $str->structeach()) {
            if ($key == "curly") {
                $numCurly += $val->scalarval();
            }
        }
    }

    return new PhpXmlRpc\Response(new Value($numCurly, "int"));
}

$v1_easyStruct_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcStruct));
$v1_easyStruct_doc = 'This handler takes a single parameter, a struct, containing at least three elements named moe, larry and curly, all &lt;i4&gt;s. Your handler must add the three numbers and return the result.';
function v1_easyStruct($m)
{
    $sno = $m->getParam(0);
    $moe = $sno->structmem("moe");
    $larry = $sno->structmem("larry");
    $curly = $sno->structmem("curly");
    $num = $moe->scalarval() + $larry->scalarval() + $curly->scalarval();

    return new PhpXmlRpc\Response(new Value($num, "int"));
}

$v1_echoStruct_sig = array(array(Value::$xmlrpcStruct, Value::$xmlrpcStruct));
$v1_echoStruct_doc = 'This handler takes a single parameter, a struct. Your handler must return the struct.';
function v1_echoStruct($m)
{
    $sno = $m->getParam(0);

    return new PhpXmlRpc\Response($sno);
}

$v1_manyTypes_sig = array(array(
    Value::$xmlrpcArray, Value::$xmlrpcInt, Value::$xmlrpcBoolean,
    Value::$xmlrpcString, Value::$xmlrpcDouble, Value::$xmlrpcDateTime,
    Value::$xmlrpcBase64,
));
$v1_manyTypes_doc = 'This handler takes six parameters, and returns an array containing all the parameters.';
function v1_manyTypes($m)
{
    return new PhpXmlRpc\Response(new Value(array(
        $m->getParam(0),
        $m->getParam(1),
        $m->getParam(2),
        $m->getParam(3),
        $m->getParam(4),
        $m->getParam(5),),
        "array"
    ));
}

$v1_moderateSizeArrayCheck_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcArray));
$v1_moderateSizeArrayCheck_doc = 'This handler takes a single parameter, which is an array containing between 100 and 200 elements. Each of the items is a string, your handler must return a string containing the concatenated text of the first and last elements.';
function v1_moderateSizeArrayCheck($m)
{
    $ar = $m->getParam(0);
    $sz = $ar->arraysize();
    $first = $ar->arraymem(0);
    $last = $ar->arraymem($sz - 1);

    return new PhpXmlRpc\Response(new Value($first->scalarval() .
        $last->scalarval(), "string"));
}

$v1_simpleStructReturn_sig = array(array(Value::$xmlrpcStruct, Value::$xmlrpcInt));
$v1_simpleStructReturn_doc = 'This handler takes one parameter, and returns a struct containing three elements, times10, times100 and times1000, the result of multiplying the number by 10, 100 and 1000.';
function v1_simpleStructReturn($m)
{
    $sno = $m->getParam(0);
    $v = $sno->scalarval();

    return new PhpXmlRpc\Response(new Value(array(
        "times10" => new Value($v * 10, "int"),
        "times100" => new Value($v * 100, "int"),
        "times1000" => new Value($v * 1000, "int"),),
        "struct"
    ));
}

$v1_nestedStruct_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcStruct));
$v1_nestedStruct_doc = 'This handler takes a single parameter, a struct, that models a daily calendar. At the top level, there is one struct for each year. Each year is broken down into months, and months into days. Most of the days are empty in the struct you receive, but the entry for April 1, 2000 contains a least three elements named moe, larry and curly, all &lt;i4&gt;s. Your handler must add the three numbers and return the result.';
function v1_nestedStruct($m)
{
    $sno = $m->getParam(0);

    $twoK = $sno->structmem("2000");
    $april = $twoK->structmem("04");
    $fools = $april->structmem("01");
    $curly = $fools->structmem("curly");
    $larry = $fools->structmem("larry");
    $moe = $fools->structmem("moe");

    return new PhpXmlRpc\Response(new Value($curly->scalarval() + $larry->scalarval() + $moe->scalarval(), "int"));
}

$v1_countTheEntities_sig = array(array(Value::$xmlrpcStruct, Value::$xmlrpcString));
$v1_countTheEntities_doc = 'This handler takes a single parameter, a string, that contains any number of predefined entities, namely &lt;, &gt;, &amp; \' and ".<BR>Your handler must return a struct that contains five fields, all numbers: ctLeftAngleBrackets, ctRightAngleBrackets, ctAmpersands, ctApostrophes, ctQuotes.';
function v1_countTheEntities($m)
{
    $sno = $m->getParam(0);
    $str = $sno->scalarval();
    $gt = 0;
    $lt = 0;
    $ap = 0;
    $qu = 0;
    $amp = 0;
    for ($i = 0; $i < strlen($str); $i++) {
        $c = substr($str, $i, 1);
        switch ($c) {
            case ">":
                $gt++;
                break;
            case "<":
                $lt++;
                break;
            case "\"":
                $qu++;
                break;
            case "'":
                $ap++;
                break;
            case "&":
                $amp++;
                break;
            default:
                break;
        }
    }

    return new PhpXmlRpc\Response(new Value(array(
        "ctLeftAngleBrackets" => new Value($lt, "int"),
        "ctRightAngleBrackets" => new Value($gt, "int"),
        "ctAmpersands" => new Value($amp, "int"),
        "ctApostrophes" => new Value($ap, "int"),
        "ctQuotes" => new Value($qu, "int"),),
        "struct"
    ));
}

// trivial interop tests
// http://www.xmlrpc.com/stories/storyReader$1636

$i_echoString_sig = array(array(Value::$xmlrpcString, Value::$xmlrpcString));
$i_echoString_doc = "Echoes string.";

$i_echoStringArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoStringArray_doc = "Echoes string array.";

$i_echoInteger_sig = array(array(Value::$xmlrpcInt, Value::$xmlrpcInt));
$i_echoInteger_doc = "Echoes integer.";

$i_echoIntegerArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoIntegerArray_doc = "Echoes integer array.";

$i_echoFloat_sig = array(array(Value::$xmlrpcDouble, Value::$xmlrpcDouble));
$i_echoFloat_doc = "Echoes float.";

$i_echoFloatArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoFloatArray_doc = "Echoes float array.";

$i_echoStruct_sig = array(array(Value::$xmlrpcStruct, Value::$xmlrpcStruct));
$i_echoStruct_doc = "Echoes struct.";

$i_echoStructArray_sig = array(array(Value::$xmlrpcArray, Value::$xmlrpcArray));
$i_echoStructArray_doc = "Echoes struct array.";

$i_echoValue_doc = "Echoes any value back.";
$i_echoValue_sig = array(array(Value::$xmlrpcValue, Value::$xmlrpcValue));

$i_echoBase64_sig = array(array(Value::$xmlrpcBase64, Value::$xmlrpcBase64));
$i_echoBase64_doc = "Echoes base64.";

$i_echoDate_sig = array(array(Value::$xmlrpcDateTime, Value::$xmlrpcDateTime));
$i_echoDate_doc = "Echoes dateTime.";

function i_echoParam($m)
{
    $s = $m->getParam(0);

    return new PhpXmlRpc\Response($s);
}

function i_echoString($m)
{
    return i_echoParam($m);
}

function i_echoInteger($m)
{
    return i_echoParam($m);
}

function i_echoFloat($m)
{
    return i_echoParam($m);
}

function i_echoStruct($m)
{
    return i_echoParam($m);
}

function i_echoStringArray($m)
{
    return i_echoParam($m);
}

function i_echoIntegerArray($m)
{
    return i_echoParam($m);
}

function i_echoFloatArray($m)
{
    return i_echoParam($m);
}

function i_echoStructArray($m)
{
    return i_echoParam($m);
}

function i_echoValue($m)
{
    return i_echoParam($m);
}

function i_echoBase64($m)
{
    return i_echoParam($m);
}

function i_echoDate($m)
{
    return i_echoParam($m);
}

$i_whichToolkit_sig = array(array(Value::$xmlrpcStruct));
$i_whichToolkit_doc = "Returns a struct containing the following strings: toolkitDocsUrl, toolkitName, toolkitVersion, toolkitOperatingSystem.";

function i_whichToolkit($m)
{
    global $SERVER_SOFTWARE;
    $ret = array(
        "toolkitDocsUrl" => "http://phpxmlrpc.sourceforge.net/",
        "toolkitName" => PhpXmlRpc\PhpXmlRpc::$xmlrpcName,
        "toolkitVersion" => PhpXmlRpc\PhpXmlRpc::$xmlrpcVersion,
        "toolkitOperatingSystem" => isset($SERVER_SOFTWARE) ? $SERVER_SOFTWARE : $_SERVER['SERVER_SOFTWARE'],
    );

    $encoder = new PhpXmlRpc\Encoder();
    return new PhpXmlRpc\Response($encoder->encode($ret));
}

$object = new xmlrpcServerMethodsContainer();
$signatures = array(
    "examples.getStateName" => array(
        "function" => "findState",
        "signature" => $findstate_sig,
        "docstring" => $findstate_doc,
    ),
    "examples.sortByAge" => array(
        "function" => "ageSorter",
        "signature" => $agesorter_sig,
        "docstring" => $agesorter_doc,
    ),
    "examples.addtwo" => array(
        "function" => "addTwo",
        "signature" => $addtwo_sig,
        "docstring" => $addtwo_doc,
    ),
    "examples.addtwodouble" => array(
        "function" => "addTwoDouble",
        "signature" => $addtwodouble_sig,
        "docstring" => $addtwodouble_doc,
    ),
    "examples.stringecho" => array(
        "function" => "stringEcho",
        "signature" => $stringecho_sig,
        "docstring" => $stringecho_doc,
    ),
    "examples.echo" => array(
        "function" => "echoBack",
        "signature" => $echoback_sig,
        "docstring" => $echoback_doc,
    ),
    "examples.decode64" => array(
        "function" => "echoSixtyFour",
        "signature" => $echosixtyfour_sig,
        "docstring" => $echosixtyfour_doc,
    ),
    "examples.invertBooleans" => array(
        "function" => "bitFlipper",
        "signature" => $bitflipper_sig,
        "docstring" => $bitflipper_doc,
    ),
    // signature omitted on purpose
    "tests.generatePHPWarning" => array(
        "function" => array($object, "phpWarningGenerator"),
    ),
    // signature omitted on purpose
    "tests.raiseException" => array(
        "function" => array($object, "exceptionGenerator"),
    ),
    /*
    // Greek word 'kosme'. NB: NOT a valid ISO8859 string!
    // We can only register this when setting internal encoding to UTF-8, or it will break system.listMethods
    "tests.utf8methodname." . 'κόσμε' => array(
        "function" => "stringEcho",
        "signature" => $stringecho_sig,
        "docstring" => $stringecho_doc,
    ),*/
    "tests.iso88591methodname." . chr(224) . chr(252) . chr(232) => array(
        "function" => "stringEcho",
        "signature" => $stringecho_sig,
        "docstring" => $stringecho_doc,
    ),
    "examples.getallheaders" => array(
        "function" => 'getallheaders_xmlrpc',
        "signature" => $getallheaders_sig,
        "docstring" => $getallheaders_doc,
    ),
    "examples.setcookies" => array(
        "function" => 'setCookies',
        "signature" => $setcookies_sig,
        "docstring" => $setcookies_doc,
    ),
    "examples.getcookies" => array(
        "function" => 'getCookies',
        "signature" => $getcookies_sig,
        "docstring" => $getcookies_doc,
    ),
    "mail.send" => array(
        "function" => "mailSend",
        "signature" => $mailsend_sig,
        "docstring" => $mailsend_doc,
    ),
    "validator1.arrayOfStructsTest" => array(
        "function" => "v1_arrayOfStructs",
        "signature" => $v1_arrayOfStructs_sig,
        "docstring" => $v1_arrayOfStructs_doc,
    ),
    "validator1.easyStructTest" => array(
        "function" => "v1_easyStruct",
        "signature" => $v1_easyStruct_sig,
        "docstring" => $v1_easyStruct_doc,
    ),
    "validator1.echoStructTest" => array(
        "function" => "v1_echoStruct",
        "signature" => $v1_echoStruct_sig,
        "docstring" => $v1_echoStruct_doc,
    ),
    "validator1.manyTypesTest" => array(
        "function" => "v1_manyTypes",
        "signature" => $v1_manyTypes_sig,
        "docstring" => $v1_manyTypes_doc,
    ),
    "validator1.moderateSizeArrayCheck" => array(
        "function" => "v1_moderateSizeArrayCheck",
        "signature" => $v1_moderateSizeArrayCheck_sig,
        "docstring" => $v1_moderateSizeArrayCheck_doc,
    ),
    "validator1.simpleStructReturnTest" => array(
        "function" => "v1_simpleStructReturn",
        "signature" => $v1_simpleStructReturn_sig,
        "docstring" => $v1_simpleStructReturn_doc,
    ),
    "validator1.nestedStructTest" => array(
        "function" => "v1_nestedStruct",
        "signature" => $v1_nestedStruct_sig,
        "docstring" => $v1_nestedStruct_doc,
    ),
    "validator1.countTheEntities" => array(
        "function" => "v1_countTheEntities",
        "signature" => $v1_countTheEntities_sig,
        "docstring" => $v1_countTheEntities_doc,
    ),
    "interopEchoTests.echoString" => array(
        "function" => "i_echoString",
        "signature" => $i_echoString_sig,
        "docstring" => $i_echoString_doc,
    ),
    "interopEchoTests.echoStringArray" => array(
        "function" => "i_echoStringArray",
        "signature" => $i_echoStringArray_sig,
        "docstring" => $i_echoStringArray_doc,
    ),
    "interopEchoTests.echoInteger" => array(
        "function" => "i_echoInteger",
        "signature" => $i_echoInteger_sig,
        "docstring" => $i_echoInteger_doc,
    ),
    "interopEchoTests.echoIntegerArray" => array(
        "function" => "i_echoIntegerArray",
        "signature" => $i_echoIntegerArray_sig,
        "docstring" => $i_echoIntegerArray_doc,
    ),
    "interopEchoTests.echoFloat" => array(
        "function" => "i_echoFloat",
        "signature" => $i_echoFloat_sig,
        "docstring" => $i_echoFloat_doc,
    ),
    "interopEchoTests.echoFloatArray" => array(
        "function" => "i_echoFloatArray",
        "signature" => $i_echoFloatArray_sig,
        "docstring" => $i_echoFloatArray_doc,
    ),
    "interopEchoTests.echoStruct" => array(
        "function" => "i_echoStruct",
        "signature" => $i_echoStruct_sig,
        "docstring" => $i_echoStruct_doc,
    ),
    "interopEchoTests.echoStructArray" => array(
        "function" => "i_echoStructArray",
        "signature" => $i_echoStructArray_sig,
        "docstring" => $i_echoStructArray_doc,
    ),
    "interopEchoTests.echoValue" => array(
        "function" => "i_echoValue",
        "signature" => $i_echoValue_sig,
        "docstring" => $i_echoValue_doc,
    ),
    "interopEchoTests.echoBase64" => array(
        "function" => "i_echoBase64",
        "signature" => $i_echoBase64_sig,
        "docstring" => $i_echoBase64_doc,
    ),
    "interopEchoTests.echoDate" => array(
        "function" => "i_echoDate",
        "signature" => $i_echoDate_sig,
        "docstring" => $i_echoDate_doc,
    ),
    "interopEchoTests.whichToolkit" => array(
        "function" => "i_whichToolkit",
        "signature" => $i_whichToolkit_sig,
        "docstring" => $i_whichToolkit_doc,
    ),
);

if ($findstate2_sig) {
    $signatures['examples.php.getStateName'] = $findstate2_sig;
}

if ($findstate3_sig) {
    $signatures['examples.php2.getStateName'] = $findstate3_sig;
}

if ($findstate4_sig) {
    $signatures['examples.php3.getStateName'] = $findstate4_sig;
}

if ($findstate5_sig) {
    $signatures['examples.php4.getStateName'] = $findstate5_sig;
}

$s = new PhpXmlRpc\Server($signatures, false);
$s->setdebug(3);
$s->compress_response = true;

// out-of-band information: let the client manipulate the server operations.
// we do this to help the testsuite script: do not reproduce in production!
if (isset($_GET['RESPONSE_ENCODING'])) {
    $s->response_charset_encoding = $_GET['RESPONSE_ENCODING'];
}
if (isset($_GET['EXCEPTION_HANDLING'])) {
    $s->exception_handling = $_GET['EXCEPTION_HANDLING'];
}
$s->service();
// that should do all we need!

// out-of-band information: let the client manipulate the server operations.
// we do this to help the testsuite script: do not reproduce in production!
if (isset($_COOKIE['PHPUNIT_SELENIUM_TEST_ID']) && extension_loaded('xdebug')) {
    include_once __DIR__ . "/../../vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/append.php";
}
