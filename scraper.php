<?php
### City of Ryde scraper

// require 'scraperwiki.php';
// require 'simple_html_dom.php';

require_once 'vendor/autoload.php';
require_once 'vendor/openaustralia/scraperwiki/scraperwiki.php';

use PGuardiario\PGBrowser;
use Sunra\PhpSimple\HtmlDomParser;

date_default_timezone_set('Australia/Sydney');

# Default to 'thisweek', use MORPH_PERIOD to change to 'thismonth' or 'lastmonth' for data recovery
switch(getenv('MORPH_PERIOD')) {
    case 'thismonth' :
        $period = 'TM';
        break;
    case 'lastmonth' :
        $period = 'LM';
        break;
    case 'thisweek' :
    default         :
        $period = 'TW';
        break;
}
print "Getting data for '" .$period. "', changable via MORPH_PERIOD environment\n";

$url_base     = "https://eservices.ryde.nsw.gov.au/T1PRProd/WebApps/eProperty/P1/eTrack/";
$da_page      = $url_base . "eTrackApplicationSearchResults.aspx?Field=S&Period=" .$period. '&r=COR.P1.WEBGUEST&f=$P1.ETR.SEARCH.STM';
$comment_base = "mailto:cityofryde@ryde.nsw.gov.au?subject=Development Application Enquiry: ";

# Agreed Terms
$browser = new PGBrowser();
$page = $browser->get($da_page);
$dom = HtmlDomParser::str_get_html($page->html);

# By default, assume it is single page
$dataset  = $dom->find("tr[class=normalRow], tr[class=alternateRow]");
$NumPages = count($dom->find('tr[class=pagerRow] td table tr td'));

if ($NumPages === 0) {
    $NumPages = 1;
}

for ($i = 1; $i <= $NumPages; $i++) {
    # If more than a single page, fetch the page
    if ($i > 1) {
        $form = $page->form();
        $page = $form->doPostBack($dom->find('tr[class=pagerRow] a', $i-2)->href);
        $dom  = HtmlDomParser::str_get_html($page->html);
        $dataset = $dom->find("tr[class=normalRow], tr[class=alternateRow]");

        echo "Scraping page $i of $NumPages\r\n";
    }

    # The usual, look for the data set and if needed, save it
    foreach ($dataset as $record) {
        # Slow way to transform the date but it works
        $date_received = explode(' ', (trim($record->find('td', 2)->plaintext)), 2);
        $date_received = explode('/', $date_received[0]);
        $date_received = "$date_received[2]-$date_received[1]-$date_received[0]";

        # Put all information in an array
        $application = array (
            'council_reference' => trim($record->find('a', 0)->plaintext),
            'address' => trim($record->find('td', 1)->plaintext),
            'description' => trim($record->find('td', 3)->plaintext),
            'info_url' => $url_base . 'eTrackApplicationDetails.aspx?r=COR.P1.WEBGUEST&f=$P1.ETR.APPDET.VIW&ApplicationId=' . trim($record->find('a', 0)->plaintext),
            'comment_url' => $comment_base . trim($record->find('a', 0)->plaintext),
            'date_scraped' => date('Y-m-d'),
            'date_received' => date('Y-m-d', strtotime($date_received))
        );

        # Check if record exist, if not, INSERT, else do nothing
        $existingRecords = scraperwiki::select("* from data where `council_reference`='" . $application['council_reference'] . "'");
        if (count($existingRecords) == 0) {
            print ("Saving record " . $application['council_reference'] . ' - ' . $application['address'] . "\n");
//             print_r ($application);
            scraperwiki::save(array('council_reference'), $application);
        } else {
            print ("Skipping already saved record " . $application['council_reference'] . "\n");
        }
    }
}

?>
