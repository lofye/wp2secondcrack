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

That's it - you should now have "wordpress-content/year/month/day/" directories full of markdown files.

-----
Wordpress Comments Import / Export plugin
select all articles, leave everything else as default

Helpful Articles:
http://www.dropboxwiki.com/tips-and-tricks/install-dropbox-in-an-entirely-text-based-linux-environment
https://www.dropbox.com/install-linux
https://daringfireball.net/projects/markdown/
https://github.com/marcoarment/secondcrack
http://www.dropboxwiki.com/tips-and-tricks/using-the-official-dropbox-command-line-interface-cli#Installation
when you invite your linux dropbox account, login to browser as that user, and go to notifications, and ACCEPT THE FOLDER SHARE!
crontab -e
* * * * * /var/www/www.derekmartin.ca/html/secondcrack/engine/update.sh /root/Dropbox/Derekmartin.ca /var/www/www.derekmartin.ca/html/secondcrack
apt-get install inotify-tools
/etc/apache2/sites-enabled
/var/www/secondcrack/