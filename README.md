wp2secondcrack
==============

This should work just fine, assuming you used urls like "/yyyy/mm/dd/title-slug-here", and that you have Composer installed.
Put these files in a directory containing only your Wordpress xml export file.
Open "convert.php" and change line 3 to match your domain name.
Then run these commands on the commandline:
```
composer update
php convert.php
```

That's it - you should now have year/month/day directories full of markdown files.