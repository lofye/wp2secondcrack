This should work just fine, assuming you used urls like "/yyyy/mm/dd/title-slug-here", and that you have Composer installed.
Put these files in a directory containing only your Wordpress xml export file.
On the commandline, run:
composer update
php convert.php

That's it - you should now have year/month/day directories full of markdown files.