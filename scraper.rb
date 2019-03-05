require 'scraperwiki'
require 'mechanize'

class Mechanize::Form
  def postback target, argument
    self['__EVENTTARGET'], self['__EVENTARGUMENT'] = target, argument
    submit
  end
end

ENV['MORPH_PERIOD'] = 'lastmonth'

case ENV['MORPH_PERIOD']
  when 'lastmonth'
  	period = "LM"
  when 'thismonth'
  	period = "TM"
  else
    period = "TW"
  	ENV['MORPH_PERIOD'] = 'thisweek'
end
puts "Getting data in `" + ENV['MORPH_PERIOD'] + "`, changable via MORPH_PERIOD environment"

url         = 'https://eservices.ryde.nsw.gov.au/T1PRProd/WebApps/eProperty/P1/eTrack/eTrackApplicationSearchResults.aspx?Field=S&Period=' + period +'&r=COR.P1.WEBGUEST&f=$P1.ETR.SEARCH.S' + period
info_url    = 'https://eservices.ryde.nsw.gov.au/T1PRProd/WebApps/eProperty/P1/eTrack/eTrackApplicationDetails.aspx?r=COR.P1.WEBGUEST&f=$P1.ETR.APPDET.VIW&ApplicationId='
comment_url = 'mailto:cityofryde@ryde.nsw.gov.au'

agent = Mechanize.new
page = agent.get(url)

if page.search("tr.pagerRow").empty?
  totalPages = 1
else
  target, argument = page.search("tr.pagerRow").search("td")[-1].at('a')['href'].scan(/'([^']*)'/).flatten
  totalPages = page.search("tr.pagerRow").search("td")[-1].inner_text.to_i
end


(1..totalPages).each do |i|
  puts "Scraping page " + i.to_s + " of " + totalPages.to_s

  if i == 1
    page = agent.get(url)
  else
    page = page.form.postback target, 'Page$' + i.to_s
  end

  results = page.search("tr.normalRow, tr.alternateRow")
  results.each do |result|
    record = {
      'council_reference' => result.search("td")[0].inner_text.to_s,
      'address'           => result.search("td")[1].inner_text.to_s,
      'description'       => result.search("td")[3].inner_text.to_s,
      'info_url'          => info_url + result.search("td")[0].inner_text.sub!("/", "%2f"),
      'comment_url'       => comment_url,
      'date_scraped'      => Date.today.to_s,
      'date_received'     => Date.parse(result.search("td")[2]).to_s
    }

    if (ScraperWiki.select("* from data where `council_reference`='#{record['council_reference']}'").empty? rescue true)
      puts "Saving record " + record['council_reference'] + ", " + record['address']
      puts record
#       ScraperWiki.save_sqlite(['council_reference'], record)
    else
      puts 'Skipping already saved record ' + record['council_reference']
    end
  end
end
