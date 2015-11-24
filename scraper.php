<?php
require 'scraperwiki.php'; 
require 'simple_html_dom.php';

date_default_timezone_set('Australia/Sydney');

$url_base = "http://eservice.ryde.nsw.gov.au/DATracking/Modules/ApplicationMaster/";
$da_page = $url_base . "default.aspx?page=found&1=thisweek&4a=DA&6=F";
$comment_base = "mailto:cityofryde@ryde.nsw.gov.au?subject=Development Application Enquiry: ";

$mainUrl = scraperWiki::scrape("$da_page");
$dom = new simple_html_dom();
$dom->load($mainUrl);
$dataset = $dom->find("tr[class=rgRow], tr[class=rgAltRow]");

foreach ($dataset as $record) {
    # slow way to transform the date
    $date_received = explode(' ', (trim($record->children(2)->plaintext)), 2);
    $date_received = explode('/', $date_received[0]);
    $date_received = "$date_received[2]-$date_received[1]-$date_received[0]";
    
    # Get the address from the actual DA detail page 
    $addressUrl = scraperWiki::scrape($url_base . trim($record->find('a',0)->href));
    $dom = new simple_html_dom();
    $dom->load($addressUrl);
    $address = $dom->find("div[id=lblProp]",0)->plaintext;

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

?>