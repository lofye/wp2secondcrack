wp2secondcrack
==============

This should work just fine, assuming you used urls like "/yyyy/mm/dd/title-slug-here", and that you have [Composer](https://getcomposer.org) installed.
Put these files in a directory containing your Wordpress xml export file.
Then run these commands on the commandline:
```
composer update
php convert.php http://www.yourdomain.xyz your-xml-export-filename.xml
```
You should see a message like:
Exported 6 pages and 792 posts from derekmartinca.wordpress.2017-03-16.xml

That's it - you should now have year/month/day directories full of markdown files.