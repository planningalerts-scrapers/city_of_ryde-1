<?php
require 'scraperwiki.php'; 
require 'simple_html_dom.php';

date_default_timezone_set('Australia/Sydney');

$url_base = "http://eservice.ryde.nsw.gov.au/DATracking/Modules/ApplicationMaster/";
$da_page = $url_base . "default.aspx?page=found&1=thisweek&4a=DA&6=F";
#$da_page = $url_base . "default.aspx?page=found&1=thismonth&4a=DA&6=F";        # Use this URL to get 'This Month' submitted DA, also to test pagination
$comment_base = "mailto:cityofryde@ryde.nsw.gov.au?subject=Development Application Enquiry: ";

$mainUrl = scraperWiki::scrape("$da_page");
$dom = new simple_html_dom();
$dom->load($mainUrl);

### Collect all 'hidden' inputs, plus add the current $eventtarget
### $eventtarget is coming from the pages section of the HTML
function buildformdata($dom, $eventtarget) {
    $a = array();
    foreach ($dom->find("input[type=hidden]") as $input) {
        if ($input->value === FALSE) {
            $a = array_merge($a, array($input->name => ""));
        } else {
            $a = array_merge($a, array($input->name => $input->value));
        }
    }
    $a = array_merge($a, array('__EVENTTARGET' => $eventtarget));
    $a = array_merge($a, array('__EVENTARGUMENT' => ''));

    return $a;
}

# By default, assume it is single page
$dataset  = $dom->find("tr[class=rgRow], tr[class=rgAltRow]");
$NumPages = count($dom->find('div[class=rgWrap rgNumPart] a'));
if ($NumPages === 0) { $NumPages = 1; }

for ($i = 1; $i <= $NumPages; $i++) {
    # If more than a single page, fetch the page
    if ($NumPages > 1) {
        $eventtarget = substr($dom->find('div[class=rgWrap rgNumPart] a',$i-1)->href, 25, 61);
        $request = array(
            'http'    => array(
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded\r\n',
            'content' => http_build_query(buildformdata($dom, $eventtarget))));
        $context = stream_context_create($request);
        $html = file_get_html($da_page, false, $context);
        $dataset = $html->find("tr[class=rgRow], tr[class=rgAltRow]");
        echo "Sorting out page $i of $NumPages\r\n";
    }

    # The usual, look for the data set and if needed, save it
    foreach ($dataset as $record) {
        # Slow way to transform the date but it works
        $date_received = explode(' ', (trim($record->children(2)->plaintext)), 2);
        $date_received = explode('/', $date_received[0]);
        $date_received = "$date_received[2]-$date_received[1]-$date_received[0]";
        
        # Get the address from the actual DA detail page 
        $addressUrl = scraperWiki::scrape($url_base . trim($record->find('a',0)->href));
        $dadom = new simple_html_dom();
        $dadom->load($addressUrl);
        $address = $dadom->find("div[id=lblProp]",0)->plaintext;

        # Put all information in an array
        $application = array (
            'council_reference' => trim($record->children(1)->plaintext),
            'address' => trim($address) . ", NSW  AUSTRALIA",
            'description' => trim($record->children(3)->plaintext),
            'info_url' => $url_base . trim($record->find('a',0)->href),
            'comment_url' => $comment_base . trim($record->children(1)->plaintext) . '&Body=',
            'date_scraped' => date('Y-m-d'),
            'date_received' => $date_received
        );

        # Check if record exist, if not, INSERT, else do nothing
        $existingRecords = scraperwiki::select("* from data where `council_reference`='" . $application['council_reference'] . "'");
        if (count($existingRecords) == 0) {
            print ("Saving record " . $application['council_reference'] . "\n");
            # print_r ($application);
            scraperwiki::save(array('council_reference'), $application);
        } else {
            print ("Skipping already saved record " . $application['council_reference'] . "\n");
        }
    }
}


?>