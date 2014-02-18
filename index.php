<?php
function price($S, $E, $inf) {
  $N = count($S);
  $I = array_count_values($inf);
  $s = $N * max($S) - array_sum($S);
  $e = $N * max($E) - array_sum($E);
  $synctime = (10*$s+$e);
  # return $synctime + @$I['completed'] + @$I['unlisted'] + @$I['archived'];
  $revoir = @$I['completed'] * 3;
  $archive = @$I['archived'] + @$I['unlisted'] / 3. + $N-@$I['doing'];
  $tropneuf = (1-tanh((10*max($S)+max($E))/10))*4;
  return $synctime*0.1 + $tropneuf + $revoir + $archive;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<head>
<link rel="icon" type="image/png" href="../favicon.png">
<style>
a { color:#008; text-decoration:none;}
a:hover { text-decoration:underline;}
h2>a { color:#000; text-decoration:none;}
div#content { text-align:left; }
body {font-family:monospace,verdana,arial; margin-left:2em; text-align:center;}
#log { display:none; position:absolute; right:0; width:40%; }
.done,     .done    >a {color:#070;}
.doing,    .doing   >a {color:#005;}
.unlisted, .unlisted>a {color:#888;}
.archived, .archived>a {color:#800;}
th {text-align:left;}
input {font-family:monospace;}
td {padding-right:10px;}
th {padding-right:10px;}
pre#errors{color:red;font-weight:bold;}
</style>
<script>document.onload = function() { document.getElementById("q").focus(); }</script>
<title>diffbs<?php if(isset($_GET['users'])&&$_GET['users']) {echo " for "; echo(implode(", ", explode(" ", $_GET['users']))); } ?></title>
</head>
<body>
<center><h2><a href=".">diffbs</a></h2>
<?php
if(!isset($_GET['users'])) {
?><div style="width:49em; margin-bottom:3em; text-align:left;"><p>
<strong>diffbs</strong> utilise <a href="http://www.betaseries.com">betaseries</a> pour récupérer votre progression sur vos séries tv. Il liste des séries à regarder avec vos amis par ordre de pertinence, tentant de minimiser le nombre d'épisode que vous aurez à revoir.
</p>
<p>
Pour l'utiliser, entrez deux usernames ou plus :
</p>
</div>
<?php
}
?>
<p>
<form action="." method="GET">
usernames:
<input size="30" type="text" name="users" value="<?php if(isset($_GET['users'])) {echo($_GET['users']);} ?>" />
force recache:<input type="checkbox" name="recache" id="q" />
<input type="submit" value="go" />
</form>
</p>
<?php

$bs="http://www.betaseries.com";

$log=array();
function htmllog($s) {echo("$s\n");}

$errors=array();
function errormsg($s) {global $errors; $errors[ ]=$s;}
#function htmllog($s) {$log[ ]="$s";}

# short grep
function G($text, $pattern) {
  $m=array(); preg_match($pattern, $text, $m);
  # if($m[1])  or die("Could not find ()-$pattern in $text");
  return $m?$m[1]:false;
}

# same as "file" but using another user agent
function robust_file($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
  $s = curl_exec($ch);
  if(!$s) return false;
  return explode("\n", $s);
}

function read_with_cache($user) {
  global $bs;
  $url="$bs/membre/$user/series";
  $cachepath="cache/CACHE-$user";
  $forcefetch = isset($_GET['recache']) && $_GET['recache'];
  $v = false;
  
  # trying to read from cache
  if(file_exists($cachepath) && !$forcefetch) {
    htmllog("cache $cachepath exists, reading file...");
    $v = file($cachepath);
    htmllog("cache $cachepath status:" . ((!$v)?"fail":"success"));
  }
  
  # read from website if cache unsuccessful
  if(!$v) {
    htmllog("fetching $url... (force=$forcefetch)");
    $v = robust_file($url);
    htmllog("fetching $url status:" . ((!$v)?"fais":"success"));
    if(!v) return false;
    
    # caching
    if($v) {
      htmllog("now caching to $cachepath... ");
      # caching (only grepped lines)
      $f=fopen($cachepath, 'w');
      if(!$f) htmllog("impossible to open file");
      else {
        foreach($v as $line) {
          if(preg_match("/class=.show/", $line)) {
            fprintf($f, "%s\n", $line); }}
        fclose($f);
        htmllog("caching to $cachepath done.");
      }
    }
  }
  
  return $v;
}


# fetch list of tv series of one user
function series_of($user) {
  # fetching
  global $bs;
  $v = read_with_cache($user);
  if(!$v) return false;
  
  /*$url="$bs/membre/$user/series";  if(0) {    $url="CACHE-$user";
  $v = file($url) or die("unable to reach $url\n");  } else {
  $v = robust_file($url) or die("unable to reach $url\n");  }*/
  
  # grepping
  foreach($v as $line) {
    if(preg_match("/class=.show/", $line)) {
      $series[ ] = $line;}}
  unset($v);
  
  # getting [name, season, episode]
  $list=array();
  $realname=array();
  foreach($series as $s) {
    $name = G($s, '/<div class="show[ a-z]*" id="([^"]+)">/');
    $ep   = G($s, '/(S[0-9][0-9][0-9]*E[0-9][0-9][0-9]*)/');
    $real = G($s, '/>([^<]+)<\/a>/');
    $iscomplete = !!G($s, '/completion.(green)/');
    $archived = !!G($s, '/show.(archive)/');
    #if(!$archived);htmllog("$real $archived");
    $list[ ] = array(
      $name,
      G($ep,'/S([0-9]+)/'),
      G($ep,'/E([0-9]+)/'),
      $real,
      $iscomplete,
      $archived);
    
  }
  
  return $list;
}

# list of series common to some users (>=1)
function in_common($series) {
  # smashing all names
  $all=array();
  foreach($series as $user=>$s) {
    foreach($s as $v) {
      $all[ ] = $v[0];}}
  
  # getting those appearing twice
  sort($all);
  if(0) {
    $last="_"; $c=1; $twice=array();
    foreach($all as $name) {
      if($name == $last) {$c++; if($c==2) {$twice[ ] = $name;}}
      else {$last=$name; $c=1;}}
    return $twice;
  } else {
    return array_unique($all);
  }
}

function main($users) {
  echo "<div id='log'><pre>log:\n";
  foreach($users as $u) {
    $s=series_of($u);
    if(!!$s) $series[$u] = $s;
    else errormsg("unable to fetch data for user '$u'");
  }
  $users = array_keys($series);
  if(count($users)<2) errormsg("better specify at least 2 valid usernames");
  
  # building arrays indexed by name
  $real=array(); $E=array(); $S=array(); $complete=array();
  foreach($series as $u=>$s) {
    foreach($s as $v) {
      $S[$v[0]][$u] = 0+$v[1];
      $E[$v[0]][$u] = 0+$v[2];
      $real[$v[0]] = $v[3];
      $complete[$v[0]][$u] = $v[4];
      $archived[$v[0]][$u] = $v[5];}}
  
  # completing arrays
  $common=in_common($series); #warning, twice defined
  foreach($common as $name) {
    foreach($users as $user) {
      if(!isset($S[$name][$user])) $S[$name][$user] = 0;
      if(!isset($E[$name][$user])) $E[$name][$user] = 0;
      $inf='unlisted';
      if(isset($complete[$name][$user])) {
        $inf = 'doing';
        if($archived[$name][$user]) $inf = 'archived';
        if($complete[$name][$user]) $inf = 'completed';
      }
      $info[$name][$user] = $inf;
    }
  }
  
  # find good candidates
  $common=in_common($series);
  $candidates=array();
  foreach($common as $name) {
    $candidates[ ]=array(price($S[$name], $E[$name], $info[$name]), $name); }
  sort($candidates);
  
  # print text only
  $symbol=array('completed'=>'.', 'doing'=>' ', 'unlisted'=>'?', 'archived'=>'!');
  foreach($candidates as $c) {
    $n=$c[1];
    foreach($users as $u) {
      printf("$u:S%02dE%02d%s ", $S[$n][$u], $E[$n][$u], $symbol[$info[$n][$u]]); }
    printf("(cost %.4f) %s", $c[0], $n);
    echo("\n");
  }
  echo "</pre></div>\n";
  
  global $errors; echo "<pre id='errors'>\n";
  echo implode("\n", $errors);
  echo "</pre>\n";
  
  # print HTML
  if(1) {
    $class=array('completed'=>'done', 'doing'=>'doing', 'unlisted'=>'unlisted', 'archived'=>'archived');
    $tip=array('completed'=>'terminée', 'doing'=>'en cours', 'unlisted'=>'non listée', 'archived'=>'archivée');
    global $bs; 
    echo "<table><tr><th>series</th>";
    foreach($users as $u) {
      $url="$bs/membre/$u/series";
      echo "<th><a href=\"$url\">$u</a></th>";
    }
    echo "<th>factor</th></tr>\n";
    foreach($candidates as $c) {
      $n=$c[1];
      $url="$bs/serie/$n";
      $cost=sprintf("%.4f", $c[0]);
      printf("<tr><td><a href=\"$url\">$real[$n]</a></td>");
      foreach($users as $u) {
        $num=sprintf("s%02de%02d", $S[$n][$u], $E[$n][$u]);
        $url="$bs/episode/$n/$num";
        $inf=$info[$n][$u];
        $title="dernier épisode regardé par $u (série $tip[$inf])";
        if($inf=='unlisted') $title="série non listée par $u";
        printf("<td class='%s' title='$title'><a href='$url'>$num</a>%s</td> ", $class[$inf], $symbol[$inf]);
      }
      printf("<td>$cost</td>");
      printf("</tr>\n");
    }
    echo "</table>\n";
    printf("<p>%s%d entries</p>\n",
      count($candidates)?"":"sorry, ",
      count($candidates),
      (2<=count($candidates))?"s":"");
  }
}


if(isset($_GET['users'])) {
  main(explode(' ',$_GET['users']));
} else {
  if(php_sapi_name() == "cli") {
    main(array('xjm', 'xgopi', 'igor-d'));
  }
}
?>
<p><a href="http://madiot.org">by jm</a></p>
</center>
</body>
</html>
