<?
if (!list($Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements) = $Cache->get_value('geodistribution')) {
  include_once(SERVER_ROOT.'/classes/charts.class.php');
  $DB->query('
    SELECT Code, Users
    FROM users_geodistribution');
  $Data = $DB->to_array();
  $Count = $DB->record_count() - 1;
  $CountryMinThreshold = Min($Count, 30);
  $CountryMax = ceil(log(Max(1, $Data[0][1])) / log(2)) + 1;
  $CountryMin = floor(log(Max(1, $Data[$CountryMinThreshold][1])) / log(2));

  $CountryRegions = array('RS' => array('RS-KM')); // Count Kosovo as Serbia as it doesn't have a TLD
  foreach ($Data as $Key => $Item) {
    list($Country, $UserCount) = $Item;
    $Countries[] = $Country;
    $CountryUsers[] = number_format((((log($UserCount) / log(2)) - $CountryMin) / ($CountryMax - $CountryMin)) * 100, 2);
    $Rank[] = round((1 - ($Key / $Count)) * 100);

    if (isset($CountryRegions[$Country])) {
      foreach ($CountryRegions[$Country] as $Region) {
        $Countries[] = $Region;
        $Rank[] = end($Rank);
      }
    }
  }
  reset($Rank);

  for ($i = $CountryMin; $i <= $CountryMax; $i++) {
    $LogIncrements[] = Format::human_format(pow(2, $i));
  }
  $Cache->cache_value('geodistribution', array($Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements), 0);
}

if (!$ClassDistribution = $Cache->get_value('class_distribution')) {
  $DB->query("
    SELECT p.Name, COUNT(m.ID) AS Users
    FROM users_main AS m
      JOIN permissions AS p ON m.PermissionID = p.ID
    WHERE m.Enabled = '1'
    GROUP BY p.Name
    ORDER BY Users DESC");
  $ClassDistribution = $DB->to_array();
  $Cache->cache_value('class_distribution', $ClassDistribution, 3600 * 24 * 14);
}
if (!$PlatformDistribution = $Cache->get_value('platform_distribution')) {
  $DB->query("
    SELECT OperatingSystem, COUNT(UserID) AS Users
    FROM users_sessions
    GROUP BY OperatingSystem
    ORDER BY Users DESC");

  $PlatformDistribution = $DB->to_array();
  $Cache->cache_value('platform_distribution', $PlatformDistribution, 3600 * 24 * 14);
}

if (!$BrowserDistribution = $Cache->get_value('browser_distribution')) {
  $DB->query("
    SELECT Browser, COUNT(UserID) AS Users
    FROM users_sessions
    GROUP BY Browser
    ORDER BY Users DESC");

  $BrowserDistribution = $DB->to_array();
  $Cache->cache_value('browser_distribution', $BrowserDistribution, 3600 * 24 * 14);
}


//Timeline generation
if (!list($Labels, $InFlow, $OutFlow) = $Cache->get_value('users_timeline')) {
  $DB->query("
    SELECT DATE_FORMAT(JoinDate,\"%b %Y\") AS Month, COUNT(UserID)
    FROM users_info
    GROUP BY Month
    ORDER BY JoinDate DESC
    LIMIT 1, 11");
  $TimelineIn = array_reverse($DB->to_array());
  $DB->query("
    SELECT DATE_FORMAT(BanDate,\"%b %Y\") AS Month, COUNT(UserID)
    FROM users_info
    WHERE BanDate > 0
    GROUP BY Month
    ORDER BY BanDate DESC
    LIMIT 1, 11");
  $TimelineOut = array_reverse($DB->to_array());

  $Labels = array();
  foreach($TimelineIn as $Month) {
    list($Labels[], $InFlow[]) = $Month;
  }
  foreach($TimelineOut as $Month) {
    list(, $OutFlow[]) = $Month;
  }
  $Cache->cache_value('users_timeline', array($Labels, $InFlow, $OutFlow), mktime(0, 0, 0, date('n') + 1, 2)); //Tested: fine for Dec -> Jan
}
//End timeline generation

View::show_header('Detailed User Statistics', 'chart');
?>
<h3 id="User_Flow"><a href="#User_Flow">User Flow</a></h3>
<div class="box pad center">
  <canvas class="chart" id="chart_user_timeline"></canvas>
  <script>
    new Chart($('#chart_user_timeline').raw().getContext('2d'), {
      type: 'line',
      data: {
        labels: <? print '["'.implode('","',$Labels).'"]'; ?>,
        datasets: [ {
          label: "New Registrations",
          backgroundColor: "rgba(0,0,255,0.2)",
          borderColor: "rgba(0,0,255,0.8)",
          data: <? print "[".implode(",",$InFlow)."]"; ?>
        }, {
          label: "Disabled Users",
          backgroundColor: "rgba(255,0,0,0.2)",
          borderColor: "rgba(255,0,0,0.8)",
          data: <? print "[".implode(",",$OutFlow)."]"; ?>
        }]
      }
    })
  </script>
</div>
<br />
<h3 id="User_Classes"><a href="#User_Classes">User Classes</a></h3>
<div class="box pad center">
  <canvas class="chart" id="chart_user_classes"></canvas>
  <script>
    new Chart($('#chart_user_classes').raw().getContext('2d'), {
      type: 'pie',
      data: {
        labels: <? print '["'.implode('","', array_column($ClassDistribution, 'Name')).'"]'; ?>,
        datasets: [ {
          data: <? print "[".implode(",", array_column($ClassDistribution, 'Users'))."]"; ?>,
          backgroundColor: ['#8a00b8','#a944cb','#be71d8','#e8ccf1', '#f3e3f9', '#fbf6fd', '#ffffff']
        }]
      }
    })
  </script>
</div>
<br />
<h3 id="User_Platforms"><a href="#User_Platforms">User Platforms</a></h3>
<div class="box pad center">
  <canvas class="chart" id="chart_user_platforms"></canvas>
  <script>
    new Chart($('#chart_user_platforms').raw().getContext('2d'), {
      type: 'pie',
      data: {
        labels: <? print '["'.implode('","', array_column($PlatformDistribution, 'OperatingSystem')).'"]'; ?>,
        datasets: [ {
          data: <? print "[".implode(",", array_column($PlatformDistribution, 'Users'))."]"; ?>,
          backgroundColor: ['#8a00b8','#9416bf','#9f2dc5','#a944cb','#b45bd2','#be71d8','#c988de','#d39fe5','#deb6eb','#e8ccf1', '#ffffff', '#ffffff', '#ffffff', '#ffffff']
        }]
      }
    })
  </script>
</div>
<br />
<h3 id="User_Browsers"><a href="#User_Browsers">User Browsers</a></h3>
<div class="box pad center">
  <canvas class="chart" id="chart_user_browsers"></canvas>
  <script>
    new Chart($('#chart_user_browsers').raw().getContext('2d'), {
      type: 'pie',
      data: {
        labels: <? print '["'.implode('","', array_column($BrowserDistribution, 'Browser')).'"]'; ?>,
        datasets: [ {
          data: <? print "[".implode(",", array_column($BrowserDistribution, 'Users'))."]"; ?>,
          backgroundColor: ['#8a00b8','#9416bf','#9f2dc5','#a944cb','#b45bd2','#be71d8','#c988de','#d39fe5','#deb6eb','#e8ccf1', '#ffffff', '#ffffff', '#ffffff', '#ffffff']
        }]
      }
    })
  </script>
</div>
<br />
<h3 id="Geo_Dist_Map"><a href="#Geo_Dist_Map">Geographical Distribution Map</a></h3>
<div class="box center">
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?cht=map:fixed=-55,-180,73,180&amp;chs=440x220&amp;chd=t:'.implode(',', $Rank).'&amp;chco=FFFFFF,EDEDED,1F0066&amp;chld='.implode('|', $Countries).'&amp;chf=bg,s,CCD6FF&.png')?>" alt="Geographical Distribution Map - Worldwide" />
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?cht=map:fixed=37,-26,65,67&amp;chs=440x220&amp;chs=440x220&amp;chd=t:'.implode(',', $Rank).'&amp;chco=FFFFFF,EDEDED,1F0066&amp;chld='.implode('|', $Countries).'&amp;chf=bg,s,CCD6FF&.png')?>" alt="Geographical Distribution Map - Europe" />
  <br />
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?cht=map:fixed=-46,-132,24,21.5&amp;chs=440x220&amp;chd=t:'.implode(',', $Rank).'&amp;chco=FFFFFF,EDEDED,1F0066&amp;chld='.implode('|', $Countries).'&amp;chf=bg,s,CCD6FF&.png')?>" alt="Geographical Distribution Map - South America" />
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?cht=map:fixed=-11,22,50,160&amp;chs=440x220&amp;chd=t:'.implode(',', $Rank).'&amp;chco=FFFFFF,EDEDED,1F0066&amp;chld='.implode('|', $Countries).'&amp;chf=bg,s,CCD6FF&.png')?>" alt="Geographical Distribution Map - Asia" />
  <br />
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?cht=map:fixed=-36,-57,37,100&amp;chs=440x220&amp;chd=t:'.implode(',', $Rank).'&amp;chco=FFFFFF,EDEDED,1F0066&amp;chld='.implode('|', $Countries).'&amp;chf=bg,s,CCD6FF&.png')?>" alt="Geographical Distribution Map - Africa" />
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?cht=map:fixed=14.8,15,45,86&amp;chs=440x220&amp;chd=t:'.implode(',', $Rank).'&amp;chco=FFFFFF,EDEDED,1F0066&amp;chld='.implode('|', $Countries).'&amp;chf=bg,s,CCD6FF&.png')?>" alt="Geographical Distribution Map - Middle East" />
  <br />
  <img src="<?=ImageTools::process('https://chart.googleapis.com/chart?chxt=y,x&amp;chg=0,-1,1,1&amp;chxs=0,h&amp;cht=bvs&amp;chco=76A4FB&amp;chs=880x300&amp;chd=t:'.implode(',', array_slice($CountryUsers, 0, 31)).'&amp;chxl=1:|'.implode('|', array_slice($Countries, 0, 31)).'|0:|'.implode('|', $LogIncrements).'&amp;chf=bg,s,FFFFFF00&.png')?>" alt="Number of users by country" />
</div>
<?
View::show_footer();
