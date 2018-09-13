# Roistat Developer Test

Analyzes access_log file to unique urls, crawlers queries, status codes.
Calculates traffic and views.

## Dependencies

* PHP >= 5.4
* Redis >= 4.0
* PHP PCNTL Extension


Do not forget to configure your Redis connection in `$config`.

You can change regex pattern `$config['access_log_regex']` for different access log file format.

## Run

Before use run `composer install` <br><br>
`php read_access_log.php access_log` 16 lines <br>
`php read_access_log.php access_log_huge` 2456997 lines <br>

You can download `access_log_huge` [here](http://nousband.ru/misc/access_log_huge)