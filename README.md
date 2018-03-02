Push the code to the Pantheon git repo.

```
git push pantheon master
```

Upload the MySQL database to Pantheon

```
cd /drupal
vagrant ssh
cd /var/www/drupalvm/drupal/sql
mysqldump -uroot -proot drupal > drupal.sql;
exit
cd sql
mysql -u pantheon -p{random_password} 7 -h {pantheon_hostname} -P 27595 pantheon < drupal.sql
```

