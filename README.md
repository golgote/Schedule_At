Schedule_At
===========

PHP Interface to the UNIX "at" program    
Original code from [Colin Viebrock](https://github.com/cviebrock) back in the old PHP 4.2 days.    
This repository serves as an archive, code might still be useful.

Example
-------

```php
require_once 'Schedule/At.php';

$at = new Schedule_At();
$queue = $at->queue();
foreach ($queue as $jid => $job) {
  $at->remove($jid);
}

// Time is converted to timestamp using strtotime()
$jid = $at->add('/path/to/command', '+1 hour');
var_dump($at->queue());
```

