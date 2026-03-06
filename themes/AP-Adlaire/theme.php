<!doctype html>
<html lang="ja">
<head>
<?php
	echo "	<meta charset='utf-8'>
	<title>".h($c['title'])." - ".h($c['page'])."</title>
	<base href='".h($host)."'>
	<meta name='viewport' content='width=device-width, initial-scale=1'>
	<link rel='stylesheet' href='themes/".h($c['themeSelect'])."/style.css'>
	<meta name='description' content='".h($c['description'])."'>
	<meta name='keywords' content='".h($c['keywords'])."'>
	<meta name='csrf-token' content='".csrf_token()."'>";
	editTags();
?>

</head>
<body>
	<nav id="nav">
		<h1><a href='./'><?php echo h($c['title']);?></a></h1>
		<?php menu(); ?>
		<div class="clear"></div>
	</nav>
	<?php if(is_loggedin()) settings();?>

	<div id="wrapper" class="border">
		<div class="pad">
			<?php content($c['page'],$c['content']);?>

		</div>
	</div>
	
	<div id="side" class="border">
		<div class="pad">
			<?php content('subside',$c['subside']);?>

		</div>
	</div>

	<div class="clear"></div>
	<footer>
		<p><?php echo $c['copyright'] ." | $lstatus | $apcredit";?></p>
	</footer>
</body>
</html>
